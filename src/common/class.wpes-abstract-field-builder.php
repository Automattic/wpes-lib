<?php

abstract class WPES_Abstract_Field_Builder {

	static $strip_bad_utf8_regex = <<<'END'
	/
	  (
		(?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
		|   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
		|   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
		|   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3 
		){1,100}                        # ...one or more times
	  )
	| .                                 # anything else
	/x
END;

	private $utf8from;

	////////////////////////////
	// Clean Input for ES indexing (prevent exceptions and failures)

	public function clean_object( $obj ) {
		switch ( gettype( $obj ) ) {
			case 'string':
				return $this->clean_string( $obj );
				break;
			case 'double':
			case 'boolean':
			case 'integer':
				return $obj;
				break;
		}

		//convert content to utf-8 because non-utf8 chars will cause json_encode() to return null
		$to_utf8 = null;
		if ( ! $to_utf8 )
			$to_utf8 = Jetpack__To_UTF8::init();
		$clean_obj = $to_utf8->convert( $obj );

		return $clean_obj;
	}

	public function clean_string( $content, $truncate_at = 100000 ) {
		//convert content to utf-8 because non-utf8 chars will cause json_encode() to return null!
		$clean_content = $this->convert_to_utf8( $content );

		$clean_content = strip_tags( $clean_content );

		$clean_content = html_entity_decode( $clean_content );

		if ( 0 < $truncate_at && mb_strlen( $clean_content ) > $truncate_at )
			$clean_content = mb_substr( $clean_content, 0, $truncate_at );

		// strip any remaining bad characters
		$clean_content = preg_replace( self::$strip_bad_utf8_regex, '$1', $clean_content );

		return $clean_content;
	}

	public function remove_shortcodes( $content ) {
		//strip shortcodes but keep any content enclosed in the shortcodes
		static $shortcode_pattern = null;
		if ( null === $shortcode_pattern ) {
			$shortcode_pattern = '/' . get_shortcode_regex() . '/s';
		}

		$clean_content = preg_replace( $shortcode_pattern, '$5', $content );

		return $clean_content;
	}

	//check for values out of bounds. For reduced memory consumption ES does not have the
	// full range that some of our MySQL fields have. eg site_id is 64 bits and we give it 16.
	//  This does not check the negative range.
	//  returns max number if out of bounds, so we can easily find incorrect data
	public function clean_long( $val, $field ) {
		$max = 9223372036854775807; //Java max, don't rely on PHP max
		return $this->clean_number( $val, $max, $field );
	}
	public function clean_int( $val, $field ) {
		$max = 2147483647; //Java max, don't rely on PHP max
		return $this->clean_number( $val, $max, $field );
	}
	public function clean_short( $val, $field ) {
		$max = 32767; //Java max, don't rely on PHP max
		return $this->clean_number( $val, $max, $field );
	}
	public function clean_byte( $val, $field ) {
		$max = 127; //Java max, don't rely on PHP max
		return $this->clean_number( $val, $max, $field );
	}
	private function clean_number( $val, $max, $field ) {
		$v = intval( $val );
		if ( $v > $max ) {
			error_log( 'Number out of range for "' . $field . '". Val: ' . $val . ' Max: ' . $max );
			return $max;
		}
		return $v;
	}

	public function clean_float( $val ) {
		$v = (float) $val;
		if ( is_finite( $v ) ) {
			return $v;
		}
		return 0;
	}

	public function clean_date( $date_str ) {
		$dd = (int) substr( $date_str, 8, 2 );
		$mm = (int) substr( $date_str, 5, 2 );
		$yyyy = (int) substr( $date_str, 0, 4 );
		$date_is_bad = ! checkdate( $mm, $dd, $yyyy );

		if ( !$date_is_bad )
			return $date_str;

		//bad date, just set to 1970-01-01 00:00:00
		return '1970-01-01 00:00:00';
	}

	public function remove_url_scheme( $url ) {
		return preg_replace( '/^[a-zA-z]+:\/\//', '', $this->convert_to_utf8( $url ), 1 );
	}

	public function is_assoc_array( $arr ) {
		for( reset( $arr ); is_int( key( $arr ) ); next( $arr) );
		$is_assoc = !is_null( key( $arr ) );
		reset( $arr );
		return $is_assoc;
	}

	public function is_multi_dim_array( $arr ) {
		foreach( $arr as $v ) {
			if ( is_array( $v ) )
				return true;
		}
		return false;
	}

	////////////////////////////
	// UTF8 conversion code

	public function convert_to_utf8( $data, $from = null ) {
		if ( ! function_exists( 'mb_convert_encoding' ) ) {
			return $data;
		}

		// Removes any invalid characters
		$old = ini_set( 'mbstring.substitute_character', 'none' );

		if ( ! $from ) {
			$from = get_option( 'blog_charset' );
		}

		// We still convert UTF-8 to UTF-8 to remove invalid characters
		if ( in_array( $from, array( 'utf8', 'utf-8', 'UTF8', 'UTF-8' ) ) ) {
			$from = 'UTF-8';
		}

		$this->utf8from = $from;

		$data = $this->recursively_modify_strings( array( $this, 'convert_string' ), $data );

		ini_set( 'mbstring.substitute_character', $old );

		return $data;
	}

	private function recursively_modify_strings( $callback, $input ) {
		if ( is_string( $input ) ) {
			return call_user_func( $callback, $input );
		} elseif ( is_array( $input ) ) {
			foreach ( $input as $key => $value ) {
				$input[$key] = $this->recursively_modify_strings( $callback, $value );
			}
		} elseif ( is_object( $input ) ) {
			foreach ( get_object_vars( $input ) as $key => $value ) {
				$input->{$key} = $this->recursively_modify_strings( $callback, $value );
			}
		}

		return $input;
	}

	private function convert_string( $string ) {
		if ( empty( $this->utf8from ) )
			return $string;

		return mb_convert_encoding( $string, 'UTF-8', $this->utf8from );
	}

	//probably want to call clean_string() on any content passed into this function first
	// handles word counts in multiple languages, but does not do a good job on mixed content (eg English and Japanese in one post)
	public function word_count( $text, $lang ) {
		$non_asian_char_pattern = '/([^\p{L}]|[a-zA-Z0-9])/u';
		//The word to character ratios are based on the rates translators charge
		//so, very approximate, especially for mixed text
		switch ( $lang ) {
			case 'zh':
			case 'zh-tw':
			case 'zh-hk':
			case 'zh-cn':
				//use a ratio of 1.5:1 chars per word
				$clean_text = preg_replace( $non_asian_char_pattern, '', $text );
				$wc = mb_strlen( $clean_text );
				$wc = ceil( $wc / 1.5 ); //round up so if we have 1 char, then we'll always have 1 word
				break;
			case 'ja':
				//use a ratio of 2.5:1 chars per word
				$clean_text = preg_replace( $non_asian_char_pattern, '', $text );
				$wc = mb_strlen( $clean_text );
				$wc = ceil( $wc / 2.5 );
				break;
			case 'ko':
				//use a ratio of 2:1 chars per word
				$clean_text = preg_replace( $non_asian_char_pattern, '', $text );
				$wc = mb_strlen( $clean_text );
				$wc = ceil( $wc / 2 );
				break;
			default:
				$wc = preg_match_all( '/\S+/u', $text );
		}

		return $wc;
	}

	public function date_object( $date ) {
		$time = strtotime( $date );
		$day_secs = $time - strtotime( date( 'Y-m-d 00:00:00', $time ) );
		$hour_secs = $time - strtotime( date( 'Y-m-d H:00:00', $time ) );
		$data = array(
			'year' => $this->clean_short( date( 'Y', $time ), '.year' ),
			'month' => $this->clean_byte( date( 'n', $time ), '.month' ),
			'day' => $this->clean_byte( date( 'j', $time ), '.day' ),
			'day_of_week' => $this->clean_byte( date( 'N', $time ), '.day_of_week' ),
			'day_of_year' => $this->clean_short( date( 'z', $time ), '.day_of_year' ),
			'week_of_year' => $this->clean_byte( date( 'W', $time ), '.week_of_year' ),
			'hour' => $this->clean_byte( date( 'G', $time ), '.hour' ),
			'minute' => $this->clean_byte( date( 'i', $time ), '.minute' ),
			'second' => $this->clean_byte( date( 's', $time ), '.second' ),
			'seconds_from_day' => $this->clean_int( $day_secs, '.seconds_from_day' ),
			'seconds_from_hour' => $this->clean_short( $hour_secs, '.seconds_from_hour' ),
		);
		return $data;
	}

	public function reverse_host( $host ) {
		return implode( '.', array_reverse( explode( '.', $host) ));
	}

	//Take the given content, and apply it to the 'default' field and any fields that have
	// a positive list of languages
	public function multilingual_field( $content, $lang_probs ) {
		static $all_langs = null;
		if ( !$all_langs ) {
			$lang_builder = new WPES_Analyzer_Builder();
			$all_langs = array_keys( $lang_builder->supported_languages );
		}

		$data = array(
			'default' => $content
		);
		foreach ( $lang_probs as $lang => $v ) {
			// lang-detect sometimes outputs en-gb or pt-br. We only want en or pt
			$lang = substr( $lang, 0, 2 );
			if ( in_array( $lang, $all_langs ) ) {
				$data[$lang] = $content;
			}
		}
		return $data;
	}

	public function build_url_object( $url ) {

		$data['url'] = $this->remove_url_scheme( $this->clean_string( $url ) );
		$parsed_url = parse_url( 'http://' . $data['url'] );
		$data['host'] = $this->clean_string( $parsed_url['host'] );
		$data['host_reversed'] = $this->clean_string( $this->reverse_host( $parsed_url['host'] ) );
		return $data;
	}

}
