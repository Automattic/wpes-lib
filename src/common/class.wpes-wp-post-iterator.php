<?php


class WPES_WP_Post_Iterator extends WPES_Abstract_Iterator {

	var $blog_id = null;
	var $sql_where = '';

	public function init( $args ) {
		$defaults = array(
			'blog_id' => false,
			'sql_where' => "post_status = 'publish'",
		);
		$args = wp_parse_args( $args, $defaults );

		parent::init( $args );

		$this->blog_id = $args['blog_id'];
		$this->sql_where = $args['sql_where'];
	}

	public function get_ids( $doc_args ) {
		global $wpdb;
		$this->curr_id = $this->last_id + 1;
		if ( '' == $this->sql_where )
			$where =  ' ID >= ' . ( (int) $this->curr_id );
		else
			$where =  $this->sql_where . ' AND ID >= ' . ( (int) $this->curr_id );

		$query = $wpdb->prepare(
			"SELECT ID FROM wp_%d_posts USE INDEX ( PRIMARY ) WHERE $where " .
			" ORDER BY ID ASC LIMIT %d",
			$this->blog_id,
			$this->batch_size
		);
		$posts = $wpdb->get_results( $query );

		if ( empty( $posts ) ) {
			if ( $wpdb->last_error )
				return new WP_Error( 'wpes-post-iterator-db-error', $wpdb->last_error );

			$this->done = true;
			return false;
		}

		$this->curr_ids = array();
		$this->last_id = end( $posts )->ID;
		$this->first_id = reset( $posts )->ID;

		if ( $this->max_id && ( $this->last_id > $this->max_id ) )
			$this->last_id = $this_max_id;

		foreach( $posts as $post ) {
			if ( $this->max_id && ( $post->ID > $this->max_id ) ) {
				$this->done = true;
				return $this->curr_ids;
			}
			$is_indexable = $this->doc_builder->is_indexable( array(
				'blog_id' => $this->blog_id,
				'id' => $post->ID,
			) );
			if ( $is_indexable ) {
				$this->curr_ids[] = $post->ID;
			}
		}

		return $this->curr_ids;
	}

	public function count_potential_docs() {
		global $wpdb;

		if ( '' == $this->sql_where )
			$q = "SELECT COUNT(ID) FROM wp_%d_posts";
		else
			$q = "SELECT COUNT(ID) FROM wp_%d_posts WHERE {$this->sql_where}";

		$query = $wpdb->prepare( $q, $this->blog_id );
		$cnt = $wpdb->get_var( $query );

		return $cnt;
	}


	public function get_pre_delete_filter() {
		$delete_posts_struct = array( 'and' => array(
			array( 'term' => array( 'blog_id' => $this->blog_id, '_cache' => false ) ),
			array( 'range' => array( 'post_id' => array( 'gte' => 0 ) ) ),
		) );
		return $delete_posts_struct;
	}

	public function get_delete_filter() {
		$delete_posts_struct = $this->get_pre_delete_filter();

		if ( $this->curr_id < $this->delete_last_id )
			$this->delete_last_id = $this->curr_id;
		else
			$this->delete_last_id++;
		$delete_posts_struct['and'][1]['range']['post_id']['gte'] = (int) $this->delete_last_id;
		$this->delete_last_id = $this->last_id;
		$delete_posts_struct['and'][1]['range']['post_id']['lte'] = (int) $this->delete_last_id;

		return $delete_posts_struct;
	}

	public function get_post_delete_filter() {
		$delete_posts_struct = $this->get_pre_delete_filter();

		$delete_posts_struct['and'][1]['range']['post_id']['gte'] = (int) $this->delete_last_id;

		return $delete_posts_struct;
	}

}