<?php

class WPES_WP_Null_Iterator extends WPES_Abstract_Iterator {
	public function init( $args ) {
		$this->done = true;
		return true;
	}

	public function count_potential_docs() { return 0; }
	public function get_ids( $doc_args ) { return array(); }
	public function get_pre_delete_filter() { return false; }
	public function get_delete_filter() { return false; }
	public function get_post_delete_filter() { return false; }
}
