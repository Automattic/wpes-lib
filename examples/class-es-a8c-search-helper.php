<?php

require_once dirname( __FILE__ ) . '/class-es-a8c-index-helper.php';

class es_search_helper {

	private $_wp_elasticsearch_queries;
	private $_q;
	private $_args;

	public function __construct( $q, array $args ) {
		/**
		 * TODO: Refactor this global logger terribleness out.
		 */
		global $wp_elasticsearch_queries;
		$this->_wp_elasticsearch_queries =& $wp_elasticsearch_queries;

		$this->_q = $q;
		$this->_args = $args;
	}

	public function validate_args( array $required_fields ) {
		foreach( $required_fields as $field ) {
			if ( !isset( $this->_args[$field] ) )
				return false;
		}

		return true;
	}

	public function clean_args() {
		/**
		 * TODO: Refactor this so that the consumer of this class does not also
		 * store a copy of args.
		 */
		if ( !isset( $this->_args['type'] ) )
			$this->_args['type'] = false;

		if ( !isset( $this->_args['routing'] ) )
			$this->_args['routing'] = false;

		return $this->_args;
	}

	public function get_es_client( $mode ) {
		$config = array();

		if ( isset( $this->_args['cluster'] ) )
			$config['cluster'] = $this->_args['cluster'];
		else
			$config['cluster'] = 'es_cluster';

		try {
			$esclient = new ES_WPCOM_Client( $mode, $config );
		}
		catch ( Exception $e ) {
			$this->_log_query_with_info( array(
				'error' => 'No available ES servers!',
			) );
			return false;
		}

		return $esclient;
	}

}

