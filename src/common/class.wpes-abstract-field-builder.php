<?php

abstract class WPES_Abstract_Field_Builder {

	abstract public function get_mappings( $args );
	abstract public function get_all_fields( $args );
	abstract public function get_update_script( $args );

	private $utf8from;

	////////////////////////////
	// Clean Input for ES indexing (prevent exceptions and failures)

	protected function clean_object( $obj ) {
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

		//convert content to utf-8 because non-utf8 chars will cause json_encode() to return null		$to_utf8 = null;
		if ( ! $to_utf8 )
			$to_utf8 = Jetpack__To_UTF8::init();
		$clean_obj = $to_utf8->convert( $obj );

		return $clean_obj;
	}

	protected function clean_string( $content ) {
		//convert content to utf-8 because non-utf8 chars will cause json_encode() to return null!
		$clean_content = $this->convert_to_utf8( $content );

		$clean_content = strip_tags( $clean_content );

		$clean_content = html_entity_decode( $clean_content );

		return $clean_content;
	}

	protected function remove_shortcodes( $content ) {
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
		return preg_replace( '/^[a-zA-z]+:\/\//', '', $url, 1 );
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

	protected function convert_to_utf8( $data, $from = null ) {
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

}
