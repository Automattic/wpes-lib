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

		// turn some utf characters into spaces
		$clean_content = preg_replace( '/[\xA6\xBA\xA7]/u', ' ', $clean_content );

		return $clean_content;
	}

	public function clean_strings( $values ) {
		if ( $values === null ) {
			return null;
		}
		$acc = [];
		foreach ( $values as $value ) {
			$acc[] = $this->clean_string( $value );
		}
		return $acc;
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

	private $_JVMNumericInstance;
	private function _JVMNumeric() {
		if ( !( $this->_JVMNumericInstance instanceof WPES_Util_JVMNumeric ) ) {
			$this->_JVMNumericInstance = new WPES_Util_JVMNumeric();
		}
		return $this->_JVMNumericInstance;
	}
	public function clean_long( $val, $field = 'unknown' ) {
		return $this->_JVMNumeric()->clamp( 'long', $val, $field );
	}
	public function clean_int( $val, $field = 'unknown' ) {
		return $this->_JVMNumeric()->clamp( 'int', $val, $field );
	}
	public function clean_short( $val, $field = 'unknown' ) {
		return $this->_JVMNumeric()->clamp( 'short', $val, $field );
	}
	public function clean_byte( $val, $field = 'unknown' ) {
		return $this->_JVMNumeric()->clamp( 'byte', $val, $field );
	}
	public function clean_double( $val, $field = 'unknown' ) {
		return $this->_JVMNumeric()->clamp( 'double', $val, $field );
	}
	public function clean_float( $val, $field = 'unknown' ) {
		return $this->_JVMNumeric()->clamp( 'float', $val, $field );
	}
	public function clean_half_float( $val, $field = 'unknown' ) {
		return $this->_JVMNumeric()->clamp( 'half_float', $val, $field );
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

	//Split up a url in ways to make it easier to match in an analyzed engram field
	public function expand_url_for_engrams( $url ) {
		$final_string = '';
		$url_clean = $this->remove_url_scheme( $url );
		$final_string .= ' ' . $url_clean;
		
		$parsed_url = parse_url( 'http://' . $url_clean );
		$parts = explode( '.', $parsed_url['host'] );

		//split host based on .
		$final_string .= ' ' . implode( ' ', $parts );

		//index subsets of the host. eg nytimes.com, blog.nytimes.com, rss.blog.nytimes.com
		$sub_hosts = $this->combine_string_parts( $parts, '.', array() );
		$final_string .= ' ' . implode( ' ', $sub_hosts );

		$parts = explode( '/', $parsed_url['path'] );
		foreach( $parts as $p ) {
			$final_string .= ' ' . $p;

			//split on camelCase and on '.'
			$dots = explode( '.', $p );
			if ( count( $dots ) > 1 )
				$final_string .= ' ' . implode( ' ', $dots );
			$camels = preg_split("/((?<=[a-z])(?=[A-Z])|(?=[A-Z][a-z]))/", $p );
			if ( count( $camels ) > 1 )
				$final_string .= ' ' . implode( ' ', $camels );
		}
		
		return $this->clean_string( $final_string );
	}

	//Recursively build a series of tokens out of the parts
	// example: array( 'blog', 'greg', 'wordpress', 'com' ) => array( 'wordpress.com', 'greg.wordpress.com', 'blog.greg.wordpress.com' )
	public function combine_string_parts( $parts, $glue, $strings = array() ) {
		if ( empty( $parts ) )
			return $strings;
		$one = array_pop( $parts );
		if ( empty( $strings ) ) {
			$last = $one;
			$one = array_pop( $parts );
		} else {
			$last = (string) end( $strings );
		}
		$strings[] = $one . $glue . $last;
		return $this->combine_string_parts( $parts, $glue, $strings );
	}
	
	public function retrieve_remote_file_meta( $url ) {
		$ch = curl_init( $url );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
		curl_setopt( $ch, CURLOPT_HEADER, TRUE );
		curl_setopt( $ch, CURLOPT_NOBODY, TRUE );

		//if we can't get headers in a second, then bail
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 1); //1 sec to connect
		curl_setopt( $ch, CURLOPT_TIMEOUT, 1 ); //1 sec to transfer

		$data = curl_exec($ch);
		if ( $errno = curl_errno( $ch ) ) {
			$error_message = curl_strerror( $errno );
			bump_stats_extras( 'ES-Curl-Attachment', 'curl-head-error-' . $errno );
			return false; //ignore TODO: log2logstash
		}
		bump_stats_extras( 'ES-Curl-Attachment', 'curl-head-success' );

		$data = array(
			'size'          => curl_getinfo( $ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD ),
			'type'          => curl_getinfo( $ch, CURLINFO_CONTENT_TYPE ),
			'http_code'     => curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
			'effective_url' => curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL ),
		);
		
		curl_close($ch);
		return $data;
	}


	public function retrieve_remote_file( $url ){
		$ch = curl_init( $url );

		curl_setopt( $ch, CURLOPT_AUTOREFERER, TRUE );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 1); //1 sec to connect

		//curl_setopt( $ch, CURLOPT_TIMEOUT, );
		//curl_setopt( $ch, CURLOPT_LOW_SPEED_LIMIT, );
			
		$file_contents = curl_exec( $ch );

		if ( $errno = curl_errno( $ch ) ) {
			$error_message = curl_strerror( $errno );
			bump_stats_extras( 'ES-Curl-Attachment', 'curl-get-error-' . $errno );
			return false; //ignore TODO: log2logstash
		}
		bump_stats_extras( 'ES-Curl-Attachment', 'curl-get-success' );

		return $file_contents;
	}
	
}
