<?php

/*
 * Example code taken from WP.com for how to use wpes-lib
 *   This code will absolutely not compile as is :)
 *
 * The es_api_* calls are our php APIs for indexing
 * The es_* calls are only used by the es_api_* calls to try and create a separation between
 *   our implementation and Elastica in case we ever want to switch to different client code
 *
 * Not included is our custom Elastica client which handles some WP.com specific
 *   server management, failure tracking, etc.
 *
 * I've also stripped out a lot of the logging and stats tracking, just makes the code
 *   more complicated.
 *
 * I've also left out es_api_search_index() since it is just a wrapper around es_query() with
 *   extra index management/selection, logging, and extraneous stuff.
 *
 */

require_once dirname( __FILE__ ) . '/class-es-a8c-search-helper.php';
require_once dirname( __FILE__ ) . '/class-es-a8c-index-helper.php';

////////////////////////////////
// Common Helper Functions

// Run the appropriate callback for  Document Builder classes
function es_api_run_callback( $callback, $command, $args ) {

	if ( class_exists( $callback ) ) {
		$obj = new $callback();
		if ( 'delete' == $command ) {
			$ndoc = new \Elastica\Document(
				$obj->get_id( $args ),
				array(),
				$obj->get_type( $args ),
				$obj->filter_index_name( $args['index_name'], $args )
			);
			$parent_id = $obj->get_parent_id( $args );
			if ( false !== $parent )
				$ndoc->setParent( $parent_id );
			return $ndoc;
		}
		$results = $obj->$command( $args );
		if ( 'doc' == $command ) {
			$ndoc = new \Elastica\Document(
				$obj->get_id( $args ),
				$results,
				$obj->get_type( $args ),
				$obj->filter_index_name( $args['index_name'], $args )
			);
			$parent_id = $obj->get_parent_id( $args );
			if ( false !== $parent )
				$ndoc->setParent( $parent_id );
			return $ndoc;
		}

	} else {
		return new WP_Error( 'es-callback', 'No doc callback defined: ' . $callback );
	}

	return $results;
}


//Log to error log and/or to wp-cli output depending on what is available
function es_api_error_log( $msg ) {
	if ( !( ES_ENABLE_ERR_LOG || ( defined( 'WP_CLI' ) && WP_CLI ) ) )
		return;

	if ( is_array( $msg ) || is_object( $msg ) )
		$str = print_r( $msg, true );
	else
		$str = $msg;

	if ( ES_ENABLE_ERR_LOG ) 
		error_log( $str );
	if ( defined( 'WP_CLI' ) && WP_CLI ) 
		WP_CLI::line( $msg );

}

/////////////////////////////////////////////////
// ES API Indexing functions


// Generic Bulk Index API using WPES doc builders and WPES doc iterators
function es_api_bulk_index( $args ) {
	global $wpdb;
	$defaults = array(
		'blog_id' => false,
		'name' => false,
		'index' => null, //optionally pass in the index
		'type' => false,   //false == all, or a comma separated list post, comment, etc
		'start' => 0,
	);
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	$index = es_api_get_index_by_args( $args, true, false );
	if ( !$index ) {
		es_api_error_log( 'ES Bulk Index: no index name: ' . $args['name'] . ' blog_id: ' . $args['blog_id'] );
		return new WP_Error( 'es-no-index', 'no index name: ' . $args['name'] . ' blog_id: ' . $args['blog_id'] );
	}

	$mem_limit = ini_get( 'memory_limit' );
	$percent = 60;
	if ( ( -1 == $mem_limit ) && WPCOM_SANDBOXED ) {
		ini_set( 'memory_limit', '700m' );
		$percent = 110;
	}
	require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/async-jobs/includes/memory-handler.php';
	$memory_handler = new Memory_Handler( null, $percent );

	//bail immediately if we don't have enough memory
	if ( ! $memory_handler->check_memory_usage() ) {
		es_api_error_log( "Bailing on bulk indexing because we're running out of memory. " );
		return $args['start'];
	}

	if ( false === $args['type'] )
		$types = array_keys( $index->doc_callbacks );
	else
		$types = explode( ',', $args['type'] );

	$delete_args = array(
		'index' => $index,
		'type' => false,
		'blog_id' => $args['blog_id'],
		'name' => $args['name'],
	);

	$count = 0;
	while( $type = array_shift( $types ) ) {
		$delete_args['type'] = $type;
		$callback = $index->doc_callbacks[$type];
		if ( ! class_exists( $callback ) )
			return new WP_Error( 'es-no-doc-builder', 'no doc builder named ' . $callback );
			
		$bldr = new $callback();

		if ( ! method_exists( $bldr, 'get_doc_iterator' ) )
			return new WP_Error( 'es-no-doc-iterator', 'no doc iterator for class ' . $callback );

		$it_args = $args;
		$it = $bldr->get_doc_iterator( $args );

		//Check whether this doc type is completely disabled
		if ( ! $bldr->is_indexing_enabled( $args ) ) {
			$del_f = $it->get_pre_delete_filter();
			if ( $del_f ) {
				$delete_args['filter'] = $del_f;
				$status = es_api_bulk_delete( $delete_args );
				if ( ! $status ) {
					return $status;
				}
			}
			continue;
		}

		//For small numbers of docs, just delete all at once
		if ( $it->count_potential_docs() < 3000 ) {
			$del_f = $it->get_pre_delete_filter();
			if ( $del_f ) {
				$delete_args['filter'] = $del_f;
				$status = es_api_bulk_delete( $delete_args );
				if ( ! $status ) {
					return $status;
				}
			}
			$delete_done = true;
		} else {
			$delete_done = false;
		}

		es_api_error_log("Importing " . $type . " docs to " . $index->name . ", start id " . $args['start'] . ".\n");

		$doc_args = array(
			'index_name' => $index->wr_index_name,
		);

		$loops = 0;
		while( ! $it->is_done() ) {
			$ids = $it->get_ids( $doc_args );
			if ( is_wp_error( $ids ) )
				return $ids;

			es_api_error_log( $it->get_curr_id() . '...' );

			if ( ! $delete_done ) {
				$del_f = $it->get_delete_filter();
				if ( $del_f ) {
					$delete_args['filter'] = $del_f;
					$status = es_api_bulk_delete( $delete_args );
					if ( ! $status ) {
						return $status;
					}
				}
			}

			if ( empty( $ids ) ) {
				if ( $loops > 1000 ) {
					 //break out of any infinite loops
					return new WP_Error( 'es-bulk-index-inf', 'Infinite loop while bulk indexing' );
				}
				$loops++;
				continue; //done?
			}
			$loops = 0;

			$docs = array();
			foreach( $ids as $id ) {
				$callback_args = $args;
				$callback_args['id'] = $id;
				$ndoc = es_api_run_callback( $index->doc_callbacks[$type], 'doc', array(
					'blog_id' => $args['blog_id'],
					'id' => $id,
					'type' => $type,
					'index_name' => $index->wr_index_name,
				) );
				if ( false !== $ndoc )
					$docs[] = $ndoc;
				
				//batches are pretty big, check memory along the way
				if ( ! $memory_handler->check_memory_usage() ) {
					es_api_error_log( "Bailing on bulk indexing because we're running out of memory. " );

					return array(
						'start' => $it->get_curr_id(),
						'type' => implode( ',', array_merge( array( $type ), $types ) ),
					);
				}
			}

			es_api_error_log( 'Indexing ' . count( $docs ) . ' documents.' );
			if ( ! empty( $docs ) ) {
				if ( ! es_index_docs( $docs, array( 'cluster' => $index->cluster ) ) ) {
					es_api_error_log( "Server error: Unable to index some docs!" );
					return false;
				}
				$count += count( $docs );
			}
			unset( $docs, $ids );
		}

		if ( ! $delete_done ) {
			$del_f = $it->get_post_delete_filter();
			if ( $del_f ) {
				$delete_args['filter'] = $del_f;
				$status = es_api_bulk_delete( $delete_args );
				if ( ! $status ) {
					return $status;
				}
			}
		}

		es_api_error_log( $type . "Done ($count). " );
		unset( $bldr, $it );
	}

	return true;
}

//remove all docs of a particular type(s) from the index
// Takes a butcher knife an index and does a delete by query to make sure we get everything
// Can accept an ES style filter to adjust what gets deleted (eg a range of post ids)
// Requires the specification of a blog_id (maybe this is unnecessary?)
// returns true unless the query should be retried because the cluster is unreachable
function es_api_bulk_delete( $args ) {
	global $wpdb;
	$defaults = array(
		'name' => false,
		'blog_id' => false,
		'index' => null, //optionally pass in the index
		'type' => 'post',   //ES doc type
		'filter' => false, //optional filter
	);
	$args = wp_parse_args( $args, $defaults );

	// Force use of name for index get
	$index_args = $args;
	$index_args['blog_id'] = false;
	$index = es_api_get_index_by_args( $index_args, true, false );
	if ( !$index ) {
		es_api_error_log( 'ES Delete From Index: no index named ' . $args['name'] );
		return new WP_Error( 'es-no-index', 'ES Delete From Index: no index named ' . $args['name'] );
	}

	if ( !isset( $args['name'] ) )
		$args['name'] = $index->name;
	do_action( 'es_bulk_delete_blog', $args );

	if ( $args['filter'] ) {
		$filter = array( 'and' => array(
			array( 'term' => array( 'blog_id' => $args['blog_id'] ) ),
			$args['filter'],
		) );
	} else {
		$filter = array(
			'term' => array( 'blog_id' => $args['blog_id'] ),
		);
	}

	//build the query
	$q_struct = array( 'query' => array( 'filtered' => array(
		'query' => array( 'match_all' => array() ),
		'filter' => $filter,
	) ) );

	$esQ = new \Elastica\Query();
	$esQ->setRawQuery( $q_struct );

	$options = array(
		'index' => $index->rd_index_name,
		'type' => $args['type'],
		'cluster' => $index->cluster,
	);

	$status = es_delete_docs_by_query( $esQ, $options );

	return $status;
}

function es_api_index_changed_item( $args ) {
	$defaults = array(
		'blog_id' => false,
		'id' => false,
		'name' => false,    //name of index (overrides blog_id; ie 'global' or 'mgs')
		'type' => 'post',
	);
	$args = wp_parse_args( $args, $defaults );

	if ( ! $args['id'] )
		return new WP_Error( 'es-no-index', 'No id specified for changed wp doc' );

	$index = es_api_get_index_by_args( $args );
	if ( isset( $index->doc_callbacks['blog'] ) ) {
		$is_indexable = es_api_run_callback( 
			$index->doc_callbacks['blog'], 
			'is_indexable',
			array( 'blog_id' => $args['blog_id'], 'id' => $args['blog_id'], 'type' => 'blog' )
		);
		if ( !$is_indexable )
			return true;
	}

	if ( ! isset( $index->doc_callbacks[ $args['type'] ] ) )
		return true;

	$is_indexable = es_api_run_callback( 
		$index->doc_callbacks[ $args['type'] ], 
		'is_indexable',
		array( 'blog_id' => $args['blog_id'], 'id' => $args['id'], 'type' => $args['type'] )
	);
	if ( $is_indexable ) {
		$status = es_api_index_item( array(
			'blog_id' => $args['blog_id'],
			'id' => $args['id'],
			'name' => $args['name'],
			'type' => $args['type'],
		) );

		if ( $status && ( 'post' == $args['type'] ) ) {
			//check if this index has comments, if so, then bulk index
			if ( isset( $index->doc_callbacks['comment'] ) ) {
				es_api_bulk_index_comments_by_post( array(
					'name' => $args['name'],
					'blog_id' => $args['blog_id'],
					'post_id' => $args['id'],
				) );
			}
		}
	} else {
		$status = es_api_delete_item( array(
			'blog_id' => $args['blog_id'],
			'id' => $args['id'],
			'name' => $args['name'],
			'type' => $args['type'],
		) );

		if ( $status && ( 'post' == $args['type'] ) ) {
			//check if this index has comments, if so, then bulk delete them
			if ( isset( $index->doc_callbacks['comment'] ) ) {
				$del_args = array(
					'name' => $index->name,
					'blog_id' => $args['blog_id'],
					'name' => $args['name'],
					'type' => 'comment',
					'filter' => array( 'term' => array( 'post_id' => $args['id'] ) ),
				);
				
				$status = es_api_bulk_delete( $del_args );
			}
		}


	}

	return $status;
}

//index any generic item to an index using the appropriate doc callback
//  not specific to wp posts or content of any kind
function es_api_index_item( $args ) {
	$defaults = array(
		'blog_id' => false,
		'type' => 'post',   //post, page, comment, etc
		'name' => false,    //name of index (overrides blog_id; ie 'global' or 'mgs')
	);
	$args = wp_parse_args( $args, $defaults );

	$index = es_api_get_index_by_args( $args, true, false );
	if ( !$index )
		return new WP_Error( 'es-no-index', 'Index Item: No available index.' );

	$ndocs = array();
	//check the index for the blog as a document if it is specified
	// if it doesn't exist yet then we need to index it
	// this query should get nicely cached so it doesn't get run all that often
	if ( ( 'blog' != $args['type'] ) && array_key_exists ( 'blog', $index->doc_callbacks ) && $args['blog_id'] ) {
		$status = es_api_is_item_in_index( array(
			'name' => $args['name'],
			'blog_id' => $args['blog_id'],
			'id' => $args['blog_id'],
			'type' => 'blog',
		) );
		
		if ( ! $status ) {
			$ndoc = es_api_run_callback( $index->doc_callbacks['blog'], 'doc', array(
				'blog_id' => $args['blog_id'], 
				'index_name' => $index->wr_index_name,
				'id' => $args['blog_id']
			) );
			if ( false === $ndoc ) {
				//for some reason (now marked as deleted?) we are no longer indexing this doc
				return true;
			}
			$ndocs[] = $ndoc;
		}
	}

	$doc_args = $args;
	$doc_args['index_name'] = $index->wr_index_name;
	$callback = $index->doc_callbacks[ $args['type'] ];
	if ( 'blog' == $args['type'] ) {
		//Hack: blog docs are currently always indexable, and the is_blog_indexable call
		// indicates whether the whole blog is indexable or not. TODO: fixme!
		$is_indexable = true;
	} else {
		$is_indexable = es_api_run_callback( 
			$callback,
			'is_indexable',
			array( 'blog_id' => $args['blog_id'], 'id' => $args['id'], 'type' => $args['type'] )
		);
	}
	if ( $is_indexable ) {
		$ndoc = es_api_run_callback( $callback, 'doc', $doc_args );
		if ( false === $ndoc ) {
			//assume this document type was blacklisted to always return false, never want to retry
			return true;
		}
		$ndocs[] = $ndoc;
	}
	es_api_error_log( 'Indexing ' . count( $ndocs ) . ' documents.' );
	return es_index_docs( $ndocs, array( 'cluster' => $index->cluster ) );
}

//delete any generic item from an index using the appropriate doc callback
//  not specific to wp posts or content of any kind
function es_api_delete_item( $args ) {
	$defaults = array(
		'blog_id' => false,
		'id' => false,      //id of post, page, comment, etc
		'type' => 'post',   //post, page, comment, etc
		'name' => false,    //name of index (overrides blog_id; ie 'global' or 'mgs')
	);
	$args = wp_parse_args( $args, $defaults );

	$index = es_api_get_index_by_args( $args, true, false );
	if ( !$index )
		return new WP_Error( 'es-no-index', 'Delete Item: No available index.' );

	$doc_args = $args;
	$doc_args['index_name'] = $index->wr_index_name;
	$callback = $index->doc_callbacks[ $args['type'] ];

	$ndoc = es_api_run_callback( $callback, 'delete', $doc_args );
	if ( false === $ndoc ) {
		//assume this document type was blacklisted to always return false, never want to retry.
		return true;
	}
	return es_delete_docs_by_id( array( $ndoc->getId() ), array( 'cluster' => $index->cluster, 'index' => $ndoc->getIndex(), 'type' => $ndoc->getType() ) );
}


//Update some of the fields in a document
//	If the doc doesn't exist in the index, then we just fully index it
function es_api_update_item( $args ) {
	$defaults = array(
		'blog_id' => false,
		'type' => 'post',	 //post, page, comment, etc
		'name' => false,		 //name of index (overrides blog_id; ie 'global' or 'mgs')
	);
	$args = wp_parse_args( $args, $defaults );

	if ( !isset( $args['id'] ) )
		return new WP_Error( 'es-update-doc-no-id', 'No document id specified' );

	//Check that the original doc is in the index
	$status = es_api_is_item_in_index( array(
		'name' => $args['name'],
		'blog_id' => $args['blog_id'],
		'type' => $args['type'],
		'id' => $args['id']
	) );

	if ( !$status ) {
		//not already in index, so index the whole doc
		$status = es_api_index_item( $args );
		return $status;
	}

	$index = es_api_get_index_by_args( $args, true, false );
	if ( is_wp_error( $index ) || !$index )
		return $index;

	$callback = $index->doc_callbacks[ $args['type'] ];
	$update_data = es_api_run_callback( $callback, 'update', $args );
	if ( is_wp_error( $update_data ) )
		return $update_data;

	$doc_id = es_api_run_callback( $callback, 'get_id', $args );
	$parent_doc_id = es_api_run_callback( $callback, 'get_parent_id', $args );

	$index_name = $index->rd_index_name;

	$options = array(
		'index' => $index_name,
		'type' => $args['type'],
		'cluster' => $index->cluster,
	);

	if ( $args['routing'] )
		$options['routing'] = $args['routing'];
	if ( $parent_doc_id )
		$options['routing'] = $parent_doc_id;

	$status = es_update_doc( $doc_id, $update_data, $options );
	return $status;
}





///////////////////////////////////////
// Low Level Search/Get Operations (read ops)


//Query with the \Elastica\Query passed in by the callee
function es_query( $q, $args = array() ) {
	$helper = new es_search_helper( $q, $args );
	$index_helper = new es_index_helper();

	if ( !$helper->validate_args( array( 'index' ) ) )
		return false;

	$args = $helper->clean_args();

	$index_helper->bump_es_stat( 'Query-Op', $args['index'], 1, true );

	$esclient = $helper->get_es_client( 'search' );
	if ( false === $esclient )
		return false;

	$start_time = microtime( true );
	$eng_time = 0;
	try {
		//Build search options
		$options = array_filter( array_intersect_key(
			$args,
			array_flip( array(
				'scroll',
				'scroll_id',
			) )
		) );
		if ( false !== $args['routing'] )
			$options['routing'] = $args['routing'];

		// Run search
		if ( false === $args['type'] )
			$resultSet = $esclient->getIndex( $args['index'] )->search( $q, $options );
		else
			$resultSet = $esclient->getIndex( $args['index'] )->getType( $args['type'] )->search( $q, $options );

		if ( $resultSet ) {
			$resp = $resultSet->getResponse();
			try {
				$eng_time = $resp->getEngineTime();
			} catch ( Exception $e) {
				$eng_time = false;
			}
		}
	} catch ( \Elastica\Exception\ClientException $e ){
		$esclient->record_error( $e );
		$esclient->mark_server_down();

		$helper->log_query_with_time( $start_time, $eng_time, $e->getMessage() );
		return false;
	} catch ( Exception $e ){
		$esclient->record_error( $e );

		$helper->log_query_with_time( $start_time, $eng_time, $e->getMessage() );
		return false;
	}

	$helper->log_query_with_time( $start_time, $eng_time );
	return $resultSet;
}

function es_mlt_query( \Elastica\Document $doc, \Elastica\Query $q, array $args ) {
	$helper = new es_search_helper( $q, $args );
	$index_helper = new es_index_helper();

	if ( !$helper->validate_args( array( 'search_indices', 'mlt_fields' ) ) )
		return false;

	$args = $helper->clean_args();

	$index_helper->bump_es_stat( 'MLT-Op', $args['search_indices'] );

	$esclient = $helper->get_es_client( 'search' );
	if ( false === $esclient )
		return false;

	$start_time = microtime( true );
	$eng_time = 0;
	try {
		//Build search options
		$options = array_filter( array_intersect_key(
			$args,
			array_flip( array(
				//MLT API fields
				'mlt_fields',
				'search_type',
				'search_indices',
				'search_types',
				'search_scroll',
				'search_size',
				'search_from',
				//MLT query fields
				'percent_terms_to_match',
				'min_term_freq',
				'max_query_terms',
				'stop_words',
				'min_doc_freq',
				'max_doc_freq',
				'min_word_len',
				'max_word_len',
				'boost_terms',
			) )
		) );
		if ( false !== $args['routing'] )
			$options['routing'] = $args['routing'];

		// Run search
		$resultSet = $esclient->getIndex( $doc->getIndex() )->getType( $doc->getType() )->moreLikeThis( $doc, $options, $q );

		if ( $resultSet ) {
			$resp = $resultSet->getResponse();
			try {
				$eng_time = $resp->getEngineTime();
			} catch ( Exception $e) {
				$eng_time = false;
			}
		}
	} catch ( \Elastica\Exception\ClientException $e ){
		$esclient->record_error( $e );
		$esclient->mark_server_down();

		$helper->log_query_with_time( $start_time, $eng_time, $e->getMessage() );
		return false;
	} catch ( Exception $e ){
		$esclient->record_error( $e );

		$helper->log_query_with_time( $start_time, $eng_time, $e->getMessage() );
		return false;
	}

	$helper->log_query_with_time( $start_time, $eng_time );
	return $resultSet;
}

function es_get_doc( $id, $args = array() ) {
	$config = array();
	$helper = new es_index_helper();

	if ( !isset( $args['index'] ) )
		return false;

	if ( !isset( $args['type'] ) )
		return false;

	$options = array();
	if ( isset( $args['routing'] ) )
		$options['routing'] = $args['routing'];

	if ( false !== $args['fields'] )
		$options['fields'] = $args['fields'];

	if ( isset( $args['cluster'] ) )
		$config['cluster'] = $args['cluster'];
	else
		$config['cluster'] = 'es_cluster';

	$helper->bump_es_stat( 'Get-Op', $args['index'] );
	try {
		$esclient = new ES_WPCOM_Client( 'get', $config );
	}
	catch ( Exception $e ) {
		return false;
	}

	try {
		$estype = $esclient->getIndex( $args['index'] )->getType( $args['type'] );
		$doc = $estype->getDocument( $id, $options );
	} catch ( \Elastica\Exception\NotFoundException $e ) {
		//no doc
		return false;
	} catch ( \Elastica\Exception\Client $e ) {
		$esclient->record_error( $e );
		$esclient->mark_server_down();
		return false;
	} catch ( Exception $e ) {
		$esclient->record_error( $e );
		return false;
	}

	return $doc;
}


///////////////////////////////////////
// Low Level Index/Delete/Update Operations (write ops)

//Index data structure to ElasticSearch
function es_index_docs( $docs, $config = array(), $retries=10 ) {
	$helper = new es_index_helper();

	if ( empty( $docs ) ) {
		return true;
	}

	$client = $helper->get_es_client( $config );
	if ( ( false == $client ) || is_wp_error( $client ) )
		return $client;

	$status = $helper->perform_op( $client, 'addDocuments', array( $docs ) );
	if ( ( false == $status ) || is_wp_error( $status ) )
		return $status;

	try {
		$idx = $docs[0]->getIndex();
		$helper->bump_es_stat( 'Index-Op', $idx, count( $docs ) );
	} catch ( Exception $e ) {
		//no index set, ignore
	}

	return true;
}

function es_update_doc( $id, $data, $args = array() ) {
	$config = array();
	$options = array( 'retry_on_conflict' => 3 );
	$helper = new es_index_helper();

	if ( !isset( $args['index'] ) )
		return $helper->return_error( 'es_update_doc-bad-request', 'No index set' );

	if ( !isset( $args['type'] ) )
		return $helper->return_error( 'es_update_doc-bad-request', 'No type set' );

	if ( isset( $args['routing'] ) )
		$options['routing'] = $args['routing'];

	if ( isset( $args['fields'] ) )
		$options['fields'] = $args['fields'];

	if ( isset( $args['cluster'] ) )
		$config['cluster'] = $args['cluster'];
	else
		$config['cluster'] = 'es_cluster';

	$client = $helper->get_es_client( $config );
	if ( ( false == $client ) || is_wp_error( $client ) )
		return $client;

	$status = $helper->perform_op( $client, 'updateDocument', array( $id, $data, $args['index'], $args['type'], $options ) );
	if ( ( false == $status ) || is_wp_error( $status ) )
		return $status;

	$helper->bump_es_stat( 'Update-Op', $args['index'] );
	return true;
}

//Delete docs in ElasticSearch
function es_delete_docs_by_id( $doc_ids, $config ) {
	if ( empty( $doc_ids ) )
		return true;

	$helper = new es_index_helper();
	$client = $helper->get_es_client( $config );
	if ( ( false == $client ) || is_wp_error( $client ) )
		return $client;

	$status = $helper->perform_op( $client, 'deleteIds', array( $doc_ids, $config['index'], $config['type'] ) );
	if ( ( false == $status ) || is_wp_error( $status ) )
		return $status;

	$helper->bump_es_stat( 'Delete-Op', $config['index'], count( $doc_ids ) );
	return true;
}

function es_delete_docs_by_query( $q, $args = array() ) {
	$config = array();
	$helper = new es_index_helper();

	if ( !isset( $args['index'] ) )
		return $helper->return_error( 'es_delete_docs_by_query-bad-request', 'No index set' );
	if ( !isset( $args['type'] ) )
		return $helper->return_error( 'es_delete_docs_by_query-bad-request', 'No type set' );

	if ( isset( $args['cluster'] ) )
		$config['cluster'] = $args['cluster'];
	else
		$config['cluster'] = 'es_cluster';

	$options = array();
	if ( isset( $args['routing'] ) )
		$options['routing'] = $args['routing'];

	$client = $helper->get_es_client( $config );
	if ( ( false == $client ) || is_wp_error( $client ) )
		return $client;

	try {
		$type = $client->getIndex( $args['index'] )->getType( $args['type'] );
	} catch ( Exception $e ) {
		return $helper->return_error( 'delete-by-query-bad-request', $e->getMessage() );
	}
	$status = $helper->perform_op( $type, 'deleteByQuery', array( $q, $options, $client->cluster_is_1x ) );
	if ( ( false == $status ) || is_wp_error( $status ) )
		return $status;

	$helper->bump_es_stat( 'Bulk-Delete-Op', $args['index'] );
	return true;
}

//////////////////////////////////////////////
// API Functions that should be deprecated

//Take a list of documents, and apply a set of updates to them
//if the update fails because the document version is incorrect,
//then retrieve the most recent versions of the documents, and try again
function es_update_docs( $docs, $updates, $retries=10 ) {
	if ( 0 >= $retries ) {
		a8c_bump_stat( 'Elastic-Search-Error', 'Update-Docs-Failed' );
		return false; //failed to update doc
	}

	es_apply_doc_updates( $docs, $updates );

	try {
		$client = new ES_WPCOM_Client( 'index' );
	}
	catch ( Exception $e ) {
		sleep( 300 ); //no available servers, wait 300 sec and try again
		return es_update_docs( $ndocs, $updates, $retries-5 ); //don't retry too many times
	}

	try {	
		$client->addDocuments( $docs );
	} catch ( \Elastica\Exception\ClientException $e ) {
		$client->mark_server_down();
		$client->record_error( $e );
		//retry until $retries has gone to zero
		usleep( rand( 1, 500 ) );
		return es_update_docs( $docs, $updates, $retries-1 );
	} catch ( \Elastica\Exception\Bulk\ResponseException $e ) {
		//presumably because the version did not match
		//reload all docs
		$ndocs = array();
		foreach ( $docs as $doc_key => $doc ) {
			$data = $doc->getData();
			$idx = es_get_index_for_blog( $data[ 'blog_id' ] );
			$ndocs[ $doc_key ] = es_get_doc_old( $doc_key, $idx[ 'index' ], $idx[ 'type' ] );
		}
		return es_update_docs( $ndocs, $updates, $retries-1 );
	} catch ( Exception $e ) {
		//non-client connection error, don't retry
		$client->record_error( $e );
		return false;
	}	

	return true;
}

//append the updates to the existing documents
//for strings, the update string is appended
//for other types we add to the field
function es_apply_doc_updates( &$docs, $updates ) {

	foreach ( $updates as $doc_key => $update ){
		$data = $docs[ $doc_key ]->getData();
		foreach ( $update as $fld => $val ) {
			if ( is_string( $data[ $fld ] ) ) {
				$data[ $fld ] .= $val;
			}
			else {
				$data[ $fld ] += $val;
			}
		}
		$docs[ $doc_key ]->setData( $data );
	}

}

//Retrieve a document from ES
function es_get_doc_old( $doc_key, $index_name, $type_name, $retries = 10 ) {
	if ( 0 >= $retries ) {
		a8c_bump_stat( 'Elastic-Search-Error', 'Get-Doc-Failed' );
		return false; //failed to connect and get document
	}

	try {
		$client = new ES_WPCOM_Client( 'search' );
	} catch ( Exception $e ) {
		sleep( 300 ); //no available servers, wait 300 sec and try again
		return es_get_doc_old( $doc_key, $index_name, $type_name, $retries-5 ); //don't retry too many times
	}

	try {
		$type = $client->getIndex( $index_name )->getType( $type_name );

		$doc = $type->getDocument( $doc_key );
		$doc->setIndex( $index_name );
		$doc->setType( $type_name );
		return $doc;
	} catch ( \Elastica\Exception\ClientException $e ) {
		$client->mark_server_down();
		$client->record_error( $e );
		//retry until $retries has gone to zero
		usleep( rand( 1, 500 ) );
		return es_get_doc_old( $doc_key, $index_name, $type_name, $retries-1 );
	} catch ( \Elastica\Exception\NotFoundException $e ) {
		//no document with that doc_key
		$client->record_error( $e );
		if ( defined( 'WPCOM_SANDBOXED' ) )
			error_log( "No doc with key " . $doc_key . " in " . $index_name . ", " . $type_name . "\n" );
		return false;
	} catch ( Exception $e ) {
		//non-client connection error, don't retry
		$client->record_error( $e );
		return false;
	}	
	return false;

}
