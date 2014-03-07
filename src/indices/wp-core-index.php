<?php


class WPES_Core_Index_Builder extends WPES_Abstract_Index_Builder {

	public function get_config( $args ) {
		$defaults = array(
			'lang' => 'en',
		);
		$args = wp_parse_args( $args, $defaults );

		$config = array();
		$config['index_name'] = $args['index_name'] . '-' . $args['version'];
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

	public function get_settings( $args ) {
		$defaults = array(
			'lang' => 'en',
		);
		$args = wp_parse_args( $args, $defaults );

		$analyzer_builder = new WPES_Analyzer_Builder();
		$analyzers = $analyzer_builder->build_analyzers( array( $args['lang'], 'lowercase' ) );

		//use the lang analyzer as the default analyzer for this index
		$analyzer_name = $analyzer_builder->get_analyzer_name( $args['lang'] );
		$analyzers['analyzer']['default'] = $analyzers['analyzer'][$analyzer_name];

		//simple settings
		$global_settings = array(
			'number_of_shards' => 2,
			'number_of_replicas' => 1,
	 		'analysis' => $analyzers,
		);

		return $global_settings;
	}

	public function get_mappings( $args ) {
		$post_fld_bldr = new WPES_WP_Post_Field_Builder();
		$comment_fld_bldr = new WPES_WP_Comment_Field_Builder();

		////////////////Post Mapping//////////////////

		$post_mapping = $post_fld_bldr->get_mappings( array(
			'index_meta' => true,
			'index_mlt_content' => true,
		) );

		unset( $post_mapping['_analyzer'] ); //always use the default analyzer

		///////////////Comment mapping///////////

		$comment_mapping = $comment_fld_bldr->get_mappings( array(
			'index_meta' => true,
			'index_mlt_content' => true,
		) );

		unset( $comment_mapping['_analyzer'] ); //always use the default analyzer

		//Add custom fields here

		return array(
			'post' => $post_mapping,
			'comment' => $comment_mapping,
		);
	}

	public function get_doc_callbacks() {
		return array(
			'post' => 'WPES_Core_Post_Doc_Builder', 
			'comment' => 'WPES_Core_Comment_Doc_Builder', 
		);
	}

}


class WPES_Core_Post_Doc_Builder extends WPES_Abstract_Document_Builder {

	public function get_id( $args ) {
		return $args['blog_id'] . '-p-' . $args['id'];
	}

	public function get_type( $args ) {
		return 'post';
	}

	public function get_parent_id( $args ) {
		return false;
	}

	public function doc( $args ) {
		$post_fld_bldr = new WPES_WP_Post_Field_Builder();

		switch_to_blog( $args['blog_id'] );

		$fld_args = array(
			'blog_id' => $args['blog_id'],
			'post_id' => $args['id'],
			'index_meta' => true,
		);
		$data = $post_fld_bldr->get_all_fields( $fld_args );

		restore_current_blog();
		return $data;
	}

	public function update( $args ) {
		$post_fld_bldr = new WPES_WPCOM_Post_Field_Builder();
		return $post_fld_bldr->get_update_script( $args );
	}

	public function is_indexable( $args ) {
		$post_fld_bldr = new WPES_WPCOM_Post_Field_Builder();
		return $post_fld_bldr->is_post_indexable( $args['blog_id'], $args['id'] );
	}

	protected function is_xpost( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );
		$xpost = get_post_meta( $post_id, '_xpost_original_permalink', true );
		restore_current_blog();
		return !empty( $xpost );
	}

}

class WPES_Core_Comment_Doc_Builder extends WPES_Abstract_Document_Builder {

	public function get_id( $args ) {
		return $args['blog_id'] . '-c-' . $args['id'];
	}

	public function get_type( $args ) {
		return 'comment';
	}

	public function get_parent_id( $args ) {
		return false;
	}

	public function is_indexable( $args ) {
		$post_fld_bldr = new WPES_WPCOM_Comment_Field_Builder();
		return $post_fld_bldr->is_comment_indexable( $args['blog_id'], $args['id'] );
	}

	public function doc( $args ) {
		$comment_fld_bldr = new WPES_WPCOM_Comment_Field_Builder();

		switch_to_blog( $args['blog_id'] );

		$fld_args = array(
			'blog_id' => $args['blog_id'],
			'comment_id' => $args['id'],
			'index_meta' => true,
		);
		$data = $comment_fld_bldr->get_all_fields( $fld_args );

		restore_current_blog();
		return $data;
	}

	public function update( $args ) {
		return false;
	}


}

