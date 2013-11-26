<?php

require_once dirname( __FILE__ ) . '/class.wpes-analyzer-builder.php';

abstract class WPES_Abstract_Index_Builder {
	var $doctype2builder = array();

	public function get_doc_builder( $type = false ) {
		if ( false === $type )
			return $this->doctype2builder;
		
		return $this->doctype2builder[$type];
	}

	abstract public function get_config( $args );
	abstract public function get_settings( $args );
	abstract public function get_mappings( $args );
	abstract public function get_doc_callbacks();

}
