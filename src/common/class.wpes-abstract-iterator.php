<?php

abstract class WPES_Abstract_Iterator {

	var $doc_builder = null;

	//parameters controlling batch sizing
	var $target_batch_size = 150;
	var $batch_size = 150;
	var $delete_batch_multiple = 20; //reduce number of deleteByQuery ops

	//id tracking
	var $curr_id = 0;
	var $first_id = 0;
	var $last_id = 0;
	var $delete_last_id = 0;

	var $done = false;
	var $curr_ids = array();

	public function init( $args ) {
		$defaults = array(
			'start' => 0,

			// a Doc Builder object
			'doc_builder' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['doc_builder'] )
			return new WP_Error( 'wpes-iterator-no-doc-builder', 'No document builder specified for Iterator' );

		$this->doc_builder = $args['doc_builder'];
		$this->curr_id = $args['start'];
		$this->last_id = $args['start'] - 1;
		$this->delete_last_id = $args['start'];

		return true;
	}

	public function set_batch_size( $size ) {
		$this->batch_size = $size;
		$this->target_batch_size = $size;
	}

	abstract public function count_potential_docs();

	// Prepare a set of docs for bulk indexing
	//   returns: 
	//     false - no more docs to prepare
	//     array( <int> ) - List of ids to be indexed
	//     WP_Error
	abstract public function get_ids( $doc_args );

	abstract public function get_pre_delete_filter();  //deletes all
	abstract public function get_delete_filter();      //deletes a range
	abstract public function get_post_delete_filter(); //deletes anything after the final bulk indexing

	public function get_curr_id() {
		return $this->curr_id;
	}

	public function is_done() {
		return $this->done;
	}

	public function update_batch_size( $doc_count ) {
		//reduce DB reads by adjusting the number of items we query for each time
		if ( 0 == $doc_count )
			$this->batch_size = $this->target_batch_size;
		else
			$this->batch_size = (int) ( $this->target_batch_size / ( $doc_count / $this->batch_size ) );

		//impose some boundaries on how much the batch size gets adjusted
		if ( $this->batch_size < $this->target_batch_size )
			$this->batch_size = $this->target_batch_size;
		if ( $this->batch_size > $this->target_batch_size * 5 )
			$this->batch_size = $this->target_batch_size * 5;

	}

}

