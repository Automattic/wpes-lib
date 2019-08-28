<?php

class WPES_Util_JVMNumeric {

	/**
	 *  We need to clamp numeric ranges per Java / ES docs:
	 *    https://docs.oracle.com/javase/specs/jvms/se9/html/jvms-2.html#jvms-2.3.1
	 *    https://docs.oracle.com/javase/specs/jvms/se9/html/jvms-2.html#jvms-2.3.2
	 *    https://www.elastic.co/guide/en/elasticsearch/reference/current/number.html
	 */
	private $_jvm_ranges;

	public function __construct() {
		$this->_jvm_ranges = array(
			'byte'  => array(                 -128,                 127 ),
			'short' => array(               -32768,               32767 ),
			'int'   => array(          -2147483648,          2147483647 ),
			'long'  => array( -9223372036854775808, 9223372036854775807 ),

			// Exponent width:         5 bits (less 1 bit = 16)
			// Significand precision: 11 bits
			'half_float' => array(
				( 1 - pow( 2, -11 ) ) * pow( 2, 16 ) * -1,
				( 1 - pow( 2, -11 ) ) * pow( 2, 16 ),
				pow( 2, -14 ),
			),
			// Exponent width:         8 bits (less 1 bit = 128)
			// Significand precision: 24 bits
			'float' => array(
				( 1 - pow( 2, -24 ) ) * pow( 2, 128 ) * -1,
				( 1 - pow( 2, -24 ) ) * pow( 2, 128 ),
				pow( 2, -126 ),
			),
			// Exponent width:        11 bits (less 1 bit = 1024)
			// Significand precision: 53 bits
			'double' => array(
				( 1 - pow( 2, -53 ) ) * pow( 2, 1024 ) * -1,
				( 1 - pow( 2, -53 ) ) * pow( 2, 1024 ),
				pow( 2, -1022 ),
			),
		);
	}

	public function clamp( $type, $val, $field ) {
		switch( $type ) {
			case 'byte':
			case 'short':
			case 'int':
			case 'long':
				$val = intval( $val );
				$is_decimal = false;
				break;

			case 'half_float':
			case 'float':
			case 'double':
				$val = floatval( $val );
				$is_decimal = true;
				break;

			default:
				error_log( "Number clamping to an invalid type [Field:$field] [Type:$type]" );
				return null;
		}

		// Extra checks for decimal numbers
		if ( $is_decimal ) {
			// Exponent is all 1 and Significand all 0
			if ( !is_finite( $val ) ) {
				return null;
			}
			// Check for minimum positive normal value
			if ( 0 != $val && abs( $val ) < $this->_jvm_ranges[ $type ][ 2 ] ) {
				error_log( "Number out of range [Field:$field] [Type:$type] [Value:$val] [Smallest:{$this->_jvm_ranges[ $type ][ 2 ]}]" );
				return 0;
			}
		}

		if ( $val < $this->_jvm_ranges[ $type ][ 0 ] ) {
			error_log( "Number out of range [Field:$field] [Type:$type] [Value:$val] [Min:{$this->_jvm_ranges[ $type ][ 0 ]}]" );
			return $this->_jvm_ranges[ $type ][ 0 ];
		}
		if ( $val > $this->_jvm_ranges[ $type ][ 1 ] ) {
			error_log( "Number out of range [Field:$field] [Type:$type] [Value:$val] [Max:{$this->_jvm_ranges[ $type ][ 1 ]}]" );
			return $this->_jvm_ranges[ $type ][ 1 ];
		}

		return $val;
	}

}
