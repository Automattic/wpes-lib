<?php


class WPES_WP_Blog_Iterator extends WPES_Abstract_Iterator {

	var $blog_id = null;
	
	public function init( $args ) {
		$defaults = array(
			'blog_id' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		parent::init( $args );

		$this->blog_id = $args['blog_id'];

	}

	public function get_ids( $doc_args ) {
		$this->curr_id = $this->last_id + 1;
		$is_indexable = $this->doc_builder->is_indexable( array(
			'blog_id' => $this->blog_id,
			'id' => $this->blog_id,
		) );
		if ( $is_indexable ) {
			$this->curr_ids = array( $this->blog_id );
		} else {
			$this->done = true;
			return false;
		}

		$this->done = true;
		return $this->curr_ids;
	}

	public function count_potential_docs() {
		return 1;
	}

	public function get_pre_delete_filter() {
		//delete completely
		$delete_struct = array( 'bool' => array(
			'must' => array( array( 'term' => array( 'blog_id' => $this->blog_id ) ) ),
			'must_not' => array( array( 'type' => array( 'value' => 'blog' ) ) ),
		) );

		return $delete_struct;
	}

	public function get_delete_filter() {
		return false; //never delete, only overwrite
	}

	public function get_post_delete_filter() {
		return false; //only one doc
	}

}