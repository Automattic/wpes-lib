<?php


class WPES_WP_Comment_Iterator extends WPES_Abstract_Iterator {

	var $blog_id = null;
	var $sql_where = '';
	
	public function init( $args ) {
		$defaults = array(
			'blog_id' => false,
			'sql_where' => "comment_approved = '1'",
		);
		$args = wp_parse_args( $args, $defaults );

		parent::init( $args );

		$this->blog_id = $args['blog_id'];
		$this->sql_where = $args['sql_where'];

	}

	public function get_ids( $doc_args ) {
		global $wpdb;
		$this->curr_id = $this->last_id + 1;
		if ( '' == $where )
			$where =  'comment_ID >= ' . ( (int) $this->curr_id );
		else
			$where =  $this->sql_where . 'AND comment_ID >= ' . ( (int) $this->curr_id );

		$query = $wpdb->prepare(
			"SELECT comment_ID FROM wp_%d_comments WHERE $where" .
			" ORDER BY comment_ID ASC LIMIT %d",
			$this->blog_id,
			$this->batch_size
		);
		$comments = $wpdb->get_results( $query );

		if ( empty( $comments ) ) {
			$this->done = true;
			return false;
		}

		$this->curr_ids = array();
		$this->last_id = end( $comments )->comment_ID;
		$this->first_id = reset( $comments )->comment_ID;

		foreach( $comments as $comment ) {
			$is_indexable = $this->doc_builder->is_indexable( array(
				'blog_id' => $this->blog_id,
				'id' => $comment->comment_ID,
			) );
			if ( $is_indexable ) {
				$this->curr_ids[] = $comment->comment_ID;
			}
		}

		return $this->curr_ids;
	}

	public function count_potential_docs() {
		global $wpdb;
		$where =  $this->sql_where;

		if ( '' == $where )
			$q = "SELECT COUNT(comment_ID) FROM wp_%d_comments";
		else
			$q = "SELECT COUNT(comment_ID) FROM wp_%d_comments WHERE $where";

		$query = $wpdb->prepare( $q, $this->blog_id );
		$cnt = $wpdb->get_var( $query );

		return $cnt;
	}

	public function get_pre_delete_filter() {
		$delete_posts_struct = array( 'and' => array(
			array( 'term' => array( 'blog_id' => $this->blog_id ) ),
			array( 'range' => array( 'commment_id' => array( 'gte' => 0 ) ) ),
		) );
		return $delete_posts_struct;
	}

	public function get_delete_filter() {
		$delete_posts_struct = $this->get_pre_delete_filter();

		if ( $this->last_id >= $this->delete_last_id ) {
			$delete_posts_struct['and'][1]['range']['commment_id']['gte'] = $this->delete_last_id;
			$this->delete_last_id = $this->last_id + ( ( $this->last_id - $this->first_id ) * $this->delete_batch_multiple );
				$delete_posts_struct['and'][1]['range']['commment_id']['lte'] = $delete_last_id;
		} else {
			$delete_posts_struct = false;
		}

		return $delete_posts_struct;
	}

	public function get_post_delete_filter() {
		$delete_posts_struct = $this->get_pre_delete_filter();

		$delete_posts_struct['and'][1]['range']['commment_id']['gte'] = $this->delete_last_id;

		return $delete_posts_struct;
	}

}