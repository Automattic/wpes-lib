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

	var $max_id = false;
	
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
		$this->delete_last_id = $args['start'] - 1;

		return true;
	}

	public function set_batch_size( $size ) {
		if ( $size < 10 ) {
			$this->batch_size = 10;
		} else if ( $size > $this->target_batch_size * 5 ) {
			//never let the total batch size be more than 5x to precent big DB reads
			$this->batch_size = $this->target_batch_size * 5;
		} else {
			$this->batch_size = $size;
		}
	}

	public function set_max_id( $max ) {
		$this->max_id = $max;
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

	/* Adaptively adjust the iteration batch size.
	 * 
	 * Because the number of rows we grab from the DB may not equal the number we index
	 *  (because some may be excluded from being indexed) we want to adjust how big our
	 *  batches are on the fly to optimize indexing speed. For instance, some subset of
	 *  blog posts may be revisions that we want to skip. Revision to indexable post
	 *  ratios are pretty stable, but change from site to site, and within ranges on
	 *  a single site.
	 *
	 * Adjust the batch size up and down to try and get the target_batch_size +/- 10%
	 *  - Increase batch by 100% if at less than half of target.
	 *  - Otherwise, increase by 30% and decrease by 25%, so we will overshoot a bit, and
	 *  then settle in on something stable.
	 */
	public function update_batch_size( $doc_count ) {
		if ( $doc_count < ( $this->target_batch_size * 0.5 ) ) {
			$this->set_batch_size( ceil( $this->batch_size * 2 ) );
		} else if ( $doc_count > ( $this->target_batch_size * 1.1 ) ) {
			$this->set_batch_size( ceil( $this->batch_size * 0.8 ) );
		} else if ( $doc_count < ( $this->target_batch_size * 0.9 ) ) {
			$this->set_batch_size( ceil( $this->batch_size * 1.3 ) );
		} else {
			//keep batch size the same
		}
	}
}

