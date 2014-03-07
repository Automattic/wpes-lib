<?php

require_once dirname( __FILE__ ) . '/class.wpes-analyzer-builder.php';

abstract class WPES_Abstract_Index_Builder {
	 //The full config for the index (settings and mappings)
	abstract public function get_config( $args );

	 //Index settings: shards, repliacs, analyzers
	abstract public function get_settings( $args );

	//document mappings
	abstract public function get_mappings( $args );
	
	 //Document Name to Document Builder Mappings
	abstract public function get_doc_callbacks();
}
