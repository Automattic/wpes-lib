<?php

require_once dirname( __FILE__ ) . '/class.wpes-analyzer-builder.php';

class WPES_Abstract_Document_Builder {

	private $utf8from;

	abstract public function get_doc_id( $args );
	abstract public function get_document_data( $args );
	abstract public function get_update_data( $args );
	abstract public function is_indexable( $args );

	public function get_parent_doc_id( $args ) {
		return false;
	}

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

		//convert content to utf-8 because non-utf8 chars will cause json_encode() to return null!
		$to_utf8 = null;
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

		//strip shortcodes but keep any content enclosed in the shortcodes
		$clean_content = preg_replace("~(?:\[/?)[^/\]]+/?\]~s", '', $clean_content);

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

}

