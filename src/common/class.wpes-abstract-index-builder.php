<?php

abstract class WPES_Abstract_Index_Builder {
	 //The full config for the index (settings and mappings)
	abstract public function get_config( $args );

	 //Index settings: shards, repliacs, analyzers
	abstract public function get_settings( $args );

	//document mappings
	abstract public function get_mappings( $args );
	
	 //Document Name to Document Builder Mappings
	abstract public function get_doc_callbacks();


	//Unless the index is complicated (auto creating new indices over time)
	// then the config is almost always this. Hasn't changed in years.
	protected function _build_standard_index_config( $args ) {
		$config = array();
		$config['index_name'] = $args['index_name'] . '-' . $args['version'];
		$config['rd_index_name'] = $config['index_name'] . '-rd';
		$config['wr_index_name'] = $config['index_name'] . '-wr';

		$config['settings_json'] = json_encode( array(
			'settings' => $this->get_settings( $args ),
		) );
		$config['mappings_json'] = json_encode( $this->get_mappings( $args ) );
		$config['doc_callbacks_serial'] = serialize( $this->get_doc_callbacks() );
	
		$config['rd_alias_json'] = json_encode( array(
			'index' => $config['index_name'],
			'alias' => $config['rd_index_name'],
		) );
		$config['wr_alias_json'] = json_encode( array(
			'index' => $config['index_name'],
			'alias' => $config['wr_index_name'],
		) );
		
		return $config;
	}
	
}
