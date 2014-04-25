<?php

class es_index_helper {

	private $esclient = null;

	public function get_es_client( $args, $mode = 'index' ) {
		$config = array();

		if ( isset( $args['cluster'] ) )
			$config['cluster'] = $args['cluster'];
		else
			$config['cluster'] = 'es_cluster';

		try {
			$esclient = new ES_WPCOM_Client( $mode, $config );
		}
		catch ( Exception $e ) {
			//no server available or indexing is stopped, job should be retried later
			return false;
		}

		return $this->esclient = $esclient;
	}

	public function perform_op( $obj, $method_name, $args ) {
		if ( !method_exists( $obj, $method_name ) ) {
			return $this->return_error( 'Method name ' . $method_name . ' does not exist on class ' . get_class( $obj ) );
		}

		try {
			$result = call_user_func_array( array( $obj, $method_name ), $args );
		} catch ( \Elastica\Exception\Client $e ) {
			$this->esclient->record_error( $e );
			$this->esclient->mark_server_down();
			return false; //retry job later
		} catch ( \Elastica\Exception\ResponseException $e ) {
			if ( false !== strpos( $e->getMessage(), 'ClusterBlockException' ) ) {
				//index has been set to read only
				$this->esclient->record_error( $e );
				$this->esclient->mark_server_down();
				return false; //retry job later
			}
			if ( false !== strpos( $e->getMessage(), 'UnavailableShardsException' ) ) {
				//can't get to a shard, something bad is happening, delay indexing
				$this->esclient->record_error( $e );
				$this->esclient->mark_server_down();
				return false; //retry job later
			}
			$this->esclient->record_error( $e );
			return $this->return_error( $method_name . '-bad-request', $e->getMessage() );
		} catch ( Exception $e ){
			$this->esclient->record_error( $e );
			return $this->return_error( $method_name . '-bad-request', $e->getMessage() );
		}

		return $result;
	}

	//Polldaddy doesn't support WP_Error so just return false in those cases
	function return_error( $code, $msg ) {
		if ( class_exists( 'WP_Error' ) ) {
			return new WP_Error( $code, $msg );
		}

		error_log( $code . ': ' . $msg ); 
		return false;
	}

}

