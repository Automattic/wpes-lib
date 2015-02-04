<?php

class WPES_WP_Post_Field_Builder extends WPES_Abstract_Field_Builder {

	var $index_media = false;

	public function get_mappings( $args = array() ) {
		$defaults = array(
			'all_field_enabled' => false,
			'index_meta' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$dynamic_post_templates = array(
			array(
				"tax_template_name" => array(
					"path_match" => "taxonomy.*.name",
					"mapping" => array(
						'type' => 'multi_field',
						'fields' => array(
							'name' => array(
								'type' => 'string',
								'index' => 'analyzed',
							),
							'raw' => array(
								'type' => 'string',
								'index' => 'not_analyzed',
							),
							'raw_lc' => array(
								'type' => 'string',
								'index' => 'analyzed',
								'analyzer' => 'lowercase_analyzer',
							),
			) ) ) ),
			array(
				"tax_template_slug" => array(
					"path_match" => "taxonomy.*.slug",
					"mapping" => array(
						'type' => 'string',
						'index' => 'not_analyzed',
			) ) ),
			array(
				"tax_template_term_id" => array(
					"path_match" => "taxonomy.*.term_id",
					"mapping" => array(
						'type' => 'long',
			) ) ),
			array(
				"has_template" => array(
					"path_match" => "has.*",
					"mapping" => array(
						'type' => 'short',
			) ) ),
			array(
				"shortcode_args_template" => array(
					"path_match" => "shortcode.*.id",
					"mapping" => array(
						'type' => 'string',
						'index' => 'not_analyzed',
			) ) ),
			array(
				"shortcode_count_template" => array(
					"path_match" => "shortcode.*.count",
					"mapping" => array(
						'type' => 'short',
			) ) ),
		);

		if ( $args['index_meta'] ) {
			$dynamic_post_templates[] = array(
				"meta_str_template" => array(
					"path_match" => "meta.*.value",
					"mapping" => array(
						'type' => 'multi_field',
						'fields' => array(
							'value' => array(
								'type' => 'string',
								'index' => 'analyzed',
							),
							'raw' => array(
								'type' => 'string',
								'index' => 'not_analyzed',
							),
							'raw_lc' => array(
								'type' => 'string',
								'index' => 'analyzed',
								'analyzer' => 'lowercase_analyzer'
							),
						),
					),
			) );
			$dynamic_post_templates[] = array(
				"meta_long_template" => array(
					"path_match" => "meta.*.long",
					"mapping" => array(
						'type' => 'long'
					),
			) );
			$dynamic_post_templates[] = array(
				"meta_bool_template" => array(
					"path_match" => "meta.*.boolean",
					"mapping" => array(
						'type' => 'boolean'
					),
			) );
			$dynamic_post_templates[] = array(
				"meta_float_template" => array(
					"path_match" => "meta.*.double",
					"mapping" => array(
						'type' => 'double'
					),
			) );
		}

		//same mapping for both pages, posts, all custom post types
		$post_mapping = array(
			'dynamic_templates' => $dynamic_post_templates,
			'_all' => array( 'enabled' => $args['all_field_enabled'] ),
			'_analyzer' => array( 'path' => 'lang_analyzer' ),
			'properties' => array(

				//////////////////////////////////
				//Blog/Post meta fields

				'post_id' => array(
					'type' => 'long',
					'store' => 'yes'
				),
				'blog_id' => array(
					'type' => 'integer',
					'store' => 'yes'
				),
				'site_id' => array(
					'type' => 'short',
				),
				'post_type' => array(
					'type' => 'string',
					'index' => 'not_analyzed'
				),
				'post_format' => array(
					'type' => 'string',
					'index' => 'not_analyzed'
				),
				'post_status' => array(
					'type' => 'string',
					'index' => 'not_analyzed'
				),
				'public' => array(
					'type' => 'boolean',
				),
				'has_password' => array(
					'type' => 'boolean',
				),

				'parent_post_id' => array(
					'type' => 'long',
				),
				'ancestor_post_ids' => array(
					'type' => 'long',
				),

				'menu_order' => array(
					'type' => 'integer',
				),

				'lang' => array(
					'type' => 'string',
					'index' => 'not_analyzed',
				),
				'lang_analyzer' => array(
					'type' => 'string',
					'index' => 'not_analyzed',
				),

				'url' => array(
					'type' => 'multi_field',
					'fields' => array(
						'url' => array(
							'type' => 'string',
							'index' => 'analyzed',
							'similarity' => 'BM25',
						),
						'raw' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
					),
				),
				'slug' => array(
					'type' => 'string',
					'index' => 'not_analyzed',
				),

				'date' => array(
					'type' => 'date',
					'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'
				),
				'date_token' => array(
					'type' => 'object',
					'properties' => array(
						'year' => array(
							'type' => 'short'
						),
						'month' => array(
							'type' => 'byte'
						),
						'day' => array(
							'type' => 'byte'
						),
						'day_of_week' => array(
							'type' => 'byte'
						),
						'week_of_year' => array(
							'type' => 'byte'
						),
						'day_of_year' => array(
							'type' => 'short'
						),
						'hour' => array(
							'type' => 'byte'
						),
						'minute' => array(
							'type' => 'byte'
						),
						'second' => array(
							'type' => 'byte'
						),
						'seconds_from_day' => array(
							'type' => 'integer'
						),
						'seconds_from_hour' => array(
							'type' => 'short'
						),
					)
				),
				'date_gmt' => array(
					'type' => 'date',
					'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'
				),
				'date_gmt_token' => array(
					'type' => 'object',
					'properties' => array(
						'year' => array(
							'type' => 'short'
						),
						'month' => array(
							'type' => 'byte'
						),
						'day' => array(
							'type' => 'byte'
						),
						'day_of_week' => array(
							'type' => 'byte'
						),
						'week_of_year' => array(
							'type' => 'byte'
						),
						'day_of_year' => array(
							'type' => 'short'
						),
						'hour' => array(
							'type' => 'byte'
						),
						'minute' => array(
							'type' => 'byte'
						),
						'second' => array(
							'type' => 'byte'
						),
						'seconds_from_day' => array(
							'type' => 'integer'
						),
						'seconds_from_hour' => array(
							'type' => 'short'
						),
					)
				),
				'modified' => array(
					'type' => 'date',
					'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'
				),
				'modified_token' => array(
					'type' => 'object',
					'properties' => array(
						'year' => array(
							'type' => 'short'
						),
						'month' => array(
							'type' => 'byte'
						),
						'day' => array(
							'type' => 'byte'
						),
						'day_of_week' => array(
							'type' => 'byte'
						),
						'week_of_year' => array(
							'type' => 'byte'
						),
						'day_of_year' => array(
							'type' => 'short'
						),
						'hour' => array(
							'type' => 'byte'
						),
						'minute' => array(
							'type' => 'byte'
						),
						'second' => array(
							'type' => 'byte'
						),
						'seconds_from_day' => array(
							'type' => 'integer'
						),
						'seconds_from_hour' => array(
							'type' => 'short'
						),
					)
				),
				'modified_gmt' => array(
					'type' => 'date',
					'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'
				),
				'modified_gmt_token' => array(
					'type' => 'object',
					'properties' => array(
						'year' => array(
							'type' => 'short'
						),
						'month' => array(
							'type' => 'byte'
						),
						'day' => array(
							'type' => 'byte'
						),
						'day_of_week' => array(
							'type' => 'byte'
						),
						'week_of_year' => array(
							'type' => 'byte'
						),
						'day_of_year' => array(
							'type' => 'short'
						),
						'hour' => array(
							'type' => 'byte'
						),
						'minute' => array(
							'type' => 'byte'
						),
						'second' => array(
							'type' => 'byte'
						),
						'seconds_from_day' => array(
							'type' => 'integer'
						),
						'seconds_from_hour' => array(
							'type' => 'short'
						),
					)
				),

				'sticky' => array(
					'type' => 'boolean',
				),

				//////////////////////////////////
				//Post Content fields

				'author' => array(
					'type' => 'multi_field',
					'fields' => array(
						'author' => array(
							'type' => 'string',
							'index' => 'analyzed',
							'similarity' => 'BM25',
						),
						'raw' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
					),
				),
				'author_login' => array(
					'type' => 'string',
					'index' => 'not_analyzed'
				),
				'author_id' => array(
					'type' => 'integer'
				),
				'title'	=> array(
					'type' => 'multi_field',
					'fields' => array(
						'title' => array(
							'type' => 'string',
							'index' => 'analyzed',
							'similarity' => 'BM25',
						),
						'word_count' => array(
							'type' => 'token_count',
							'analyzer' => 'default',
						),
					),
				),
				'content'  => array(
					'type' => 'multi_field',
					'fields' => array(
						'content' => array(
							'type' => 'string',
							'index' => 'analyzed',
							'similarity' => 'BM25',
						),
						'word_count' => array(
							'type' => 'token_count',
							'analyzer' => 'default',
						),
					),
				),
				'excerpt'  => array(
					'type' => 'multi_field',
					'fields' => array(
						'excerpt' => array(
							'type' => 'string',
							'index' => 'analyzed',
							'similarity' => 'BM25',
						),
						'word_count' => array(
							'type' => 'token_count',
							'analyzer' => 'default',
						),
					),
				),
				'tag_cat_count'   => array(
					'type' => 'short',
				),
				'tag' => array(
					'type' => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'multi_field',
							'fields' => array(
								'name' => array(
									'type' => 'string',
									'index' => 'analyzed',
									'index_name' => 'tag',
									'similarity' => 'BM25',
								),
								'raw' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
									'index_name' => 'tag.raw',
								),
								'raw_lc' => array(
									'type' => 'string',
									'index' => 'analyzed',
									'analyzer' => 'lowercase_analyzer',
									'index_name' => 'tag.raw_lc',
								),
							),
						),
						'slug' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
						'term_id' => array(
							'type' => 'long',
						),
					),
				),
				'category' => array(
					'type' => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'multi_field',
							'fields' => array(
								'name' => array(
									'type' => 'string',
									'index' => 'analyzed',
									'index_name' => 'category',
									'similarity' => 'BM25',
								),
								'raw' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
									'index_name' => 'category.raw',
								),
								'raw_lc' => array(
									'type' => 'string',
									'index' => 'analyzed',
									'analyzer' => 'lowercase_analyzer',
									'index_name' => 'category.raw_lc',
								),
							),
						),
						'slug' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
						'term_id' => array(
							'type' => 'long',
						),
					),
				),

				//taxonomy.*.* added as dynamic template

				//////////////////////////////////
				//Embedded Media/Shortcodes/etc

				//has.* added as dynamic template

				'link' => array(
					'type' => 'object',
					'properties' => array(
						'url' => array(
							'type' => 'multi_field',
							'fields' => array(
								'url' => array(
									'type' => 'string',
									'index' => 'analyzed', //default analyzer
									'similarity' => 'BM25',
								),
								'raw' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
								),
							),
						),
						'host' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
						'host_reversed' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
					),
				),
				'image' => array(
					'type' => 'nested',
					'properties' => array(
						'url' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
						'mime_type' => array(
							'type' => 'multi_field',
							'fields' => array(
								'name' => array(
									'type' => 'string',
									'index' => 'lowercase_analyzer',
								),
								'raw' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
								),
							),
						),
						'title'	=> array(
							'type' => 'multi_field',
							'fields' => array(
								'title' => array(
									'type' => 'string',
									'index' => 'analyzed',
									'similarity' => 'BM25',
								),
								'word_count' => array(
									'type' => 'token_count',
									'analyzer' => 'default',
								),
							),
						),
						// This is post_content in the DB
						'description' => array(
							'type' => 'multi_field',
							'fields' => array(
								'content' => array(
									'type' => 'string',
									'index' => 'analyzed',
									'similarity' => 'BM25',
								),
								'word_count' => array(
									'type' => 'token_count',
									'analyzer' => 'default',
								),
							),
						),
						// This is post_excerpt in the DB
						'caption' => array(
							'type' => 'multi_field',
							'fields' => array(
								'excerpt' => array(
									'type' => 'string',
									'index' => 'analyzed',
									'similarity' => 'BM25',
								),
								'word_count' => array(
									'type' => 'token_count',
									'analyzer' => 'default',
								),
							),
						),
						'dimensions_token' => array(
							'type' => 'object',
							'properties' => array(
								'width' => array(
									'type' => 'short',
								),
								'height' => array(
									'type' => 'short',
								),
								'area' => array(
									'type' => 'integer',
								),
							),
						),
						'is_grayscale' => array(
							'type' => 'boolean',
						),
						'has_transparency' => array(
							'type' => 'boolean',
						),
						'color_token' => array(
							'type' => 'object',
							'properties' => array(
								'hue' => array(
									'type' => 'short'
								),
								'saturation' => array(
									'type' => 'short'
								),
								'value' => array(
									'type' => 'short'
								),
								'dominance' => array(
									'type' => 'byte'
								),
							),
						),
					),
				),
				'shortcode_types' => array(
					'type' => 'string',
					'index' => 'not_analyzed',
				),
				'embed' => array(
					'type' => 'object',
					'properties' => array(
						'url' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
					),
				),
				'hashtag' => array(
					'type' => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
						),
					),
				),
				'mention' => array(
					'type' => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'multi_field',
							'fields' => array(
								'name' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
								),
								'lc' => array(
									'type' => 'string',
									'index' => 'analyzed',
									'analyzer' => 'lowercase_analyzer',
								),
							),
						),
					),
				),

				//////////////////////////////////
				//Comments

				'commenter_ids' => array(
					'type' => 'integer',
				),
				'comment_count' => array(
					'type' => 'integer',
				)
			)
		);

		if ( $this->index_media ) {
			//Indexing media attachments also
			// Add additional fields here
		}
		
		return $post_mapping;
	}

	public function get_all_fields( $args = array() ) {
		$defaults = array(
			'index_meta' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		switch_to_blog( $args['blog_id'] );

		$post = get_post( $args['post_id'] );
		if ( !$post ) {
			restore_current_blog();
			return false;
		}

		$blog = get_blog_details( $args['blog_id'] );

		$data = array(
			'blog_id'      => $this->clean_int( $args['blog_id'], 'blog_id' ),
			'site_id'      => $this->clean_short( $blog->site_id, 'site_id' ),
		);
		$lang_data = $this->blog_lang( $blog_id );
		$post_data = $this->post_fields( $post, $lang_data['lang'] );
		$tax_data = $this->taxonomy( $post );
		$commenters_data = $this->commenters( $args['blog_id'], $post );
		$feat_img_data = $this->featured_image( $post );
		$media_data = $this->extract_media( $args['blog_id'], $post );
		if ( $args['index_meta'] ) {
			$meta_data = $this->meta( $post );
		} else {
			$meta_data = array();
		}

		$data = array_merge(
			$data,
			$lang_data,
			$post_data,
			$tax_data,
			$commenters_data,
			$media_data,
			$feat_img_data,
			$meta_data
		);
		restore_current_blog();
		return $data;
	}

	public function get_update_script( $args ) {
		global $wpdb;
		$update_script = array();

		if ( count( $args['updates'] ) != 1 )
			return new WP_Error( 'es-doc-callbacks', 'Don\'t currently support multiple updates in one op' );

		foreach ( $args['updates'] as $op => $update_args ) {
			switch ( $op ) {
				case 'add_comment' :
					$user_id = $update_args;
					$post = get_blog_post( $args['blog_id'], $args['id'] );
					if ( !$post )
						return array();
					if ( $user_id ) {
						$update_script['script'] = 'if ( !ctx._source.commenter_ids.contains(commenter) ) { ctx._source.commenter_ids += commenter; } ctx._source.comment_count = count;';
						$update_script['params'] = array( "commenter" => $update_args, 'count' => $post->comment_count );
					} else {
						$update_script['script'] = 'ctx._source.comment_count = count;';
						$update_script['params'] = array( 'count' => $post->comment_count );
					}
					break;
				case 'remove_comment' :
					$remove = false;
					$user_id = $update_args;
					$blog_details = get_blog_details( $blog_id );
					$post = get_blog_post( $args['blog_id'], $args['id'] );
					if ( !$post )
						return array();
					if ( $user_id ) { //user_id 0 is never in the list
						//check whether this commenter has any approved comments left on the post
						$comment_id = $wpdb->get_var( $wpdb->prepare( 'SELECT comment_ID FROM wp_%d_comments WHERE comment_post_ID = %d AND user_id = %d AND comment_approved = "1" LIMIT 1', $args['blog_id'], $args['id'], $user_id ) );
						if ( !$comment_id )
							$remove = true;
					}

					if ( $remove ) {
						$update_script['script'] = 'if ( ctx._source.commenter_ids.contains(commenter) ) { idx = ctx._source.commenter_ids.indexOf(commenter); ctx._source.commenter_ids.remove(idx); } ctx._source.comment_count = count;';
						$update_script['params'] = array( "commenter" => $update_args, 'count' => $post->comment_count );
					} else {
						$update_script['script'] = 'ctx._source.comment_count = count;';
						$update_script['params'] = array( 'count' => $post->comment_count );
					}
					break;
			}
		}
		return $update_script;
	}

	function is_post_public( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );

		$post = get_post( $post_id );
		if ( ! $post ) {
			restore_current_blog();
			return false;
		}

		$post_status = get_post_status( $post_id );
		$public_stati = get_post_stati( array( 'public' => true ) );

		if ( ! in_array( $post_status, $public_stati ) ) {
			restore_current_blog();
			return false;
		}

		if ( strlen( $post->post_password ) > 0 ) {
			restore_current_blog();
			return false;
		}
		
		$post_ok = true;
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( $post_type_obj->exclude_from_search ) {
			$post_ok = false;
		}

		if ( ! $post_type_obj->public ) {
			$post_ok = false;
		}

		restore_current_blog();
		return $post_ok;
	}

	function is_parent_post_public( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );
		$post = get_post( $post_id );
		//if there is no parent (eg unattached media) we consider it public
		if ( 0 == $post->post_parent )
			return true;

		restore_current_blog();
		return $this->is_post_public( $blog_id, $post->post_parent );
	}
	
	function is_post_indexable( $blog_id, $post_id ) {
		if ( $post_id == false )
			return false;

		return $this->is_post_public( $blog_id, $post_id );
	}

	function post_fields( $post, $lang ) {
		$blog_id = get_current_blog_id();
		$user = get_userdata( $post->post_author );

		$post_title = $this->remove_shortcodes( $this->clean_string( $post->post_title ) );
		$post_content = $this->remove_shortcodes( $this->clean_string( $post->post_content ) );
		$post_excerpt = $this->remove_shortcodes( $this->clean_string( $post->post_excerpt ) );

		$format = get_post_format( $post->ID );
		if ( false === $format ) {
			$format = 'standard';
		}

		if ( 'attachment' == $post->post_type ) {
			$url = wp_get_attachment_url( $post->ID );
			if ( false == $url )
				$url = get_permalink( $post->ID );
		} else {
			$url = get_permalink( $post->ID );
		}
		
		$data = array(
			'post_id'      => $this->clean_long( $post->ID, 'post_id' ),
			'post_type'    => $post->post_type,
			'post_format'  => $format,
			'post_status'  => get_post_status( $post->ID ),
			'parent_post_id' => $post->post_parent,
			'ancestor_post_ids' => get_post_ancestors( $post->ID ),
			'public'       => (boolean) $this->is_post_public( $blog_id, $post->ID ),
			'has_password' => ( strlen( $post->post_password ) > 0 ),
			'url'          => $this->remove_url_scheme( $url ),
			'slug'         => $post->post_name,
			'date'          => $this->clean_date( $post->post_date ),
			'date_gmt'      => $this->clean_date( $post->post_date_gmt ),
			'date_token'     => $this->date_object( $post->post_date ),
			'date_gmt_token' => $this->date_object( $post->post_date_gmt ),
			'modified'          => $this->clean_date( $post->post_modified ),
			'modified_gmt'      => $this->clean_date( $post->post_modified_gmt ),
			'modified_token'     => $this->date_object( $post->post_modified ),
			'modified_gmt_token' => $this->date_object( $post->post_modified_gmt ),
			'sticky'       => (boolean) is_sticky( $post->ID ),
			'title'        => $post_title,
			'content'      => $post_content,
			'excerpt'      => $post_excerpt,
			'menu_order'   => $this->clean_int( $post->menu_order, 'menu_order' ),
		);

		$author_data = $this->post_author( $blog_id, $post );
		$data = array_merge( $data, $author_data );

		return $data;
	}

	public function blog_lang( $blog_id ) {
		$fld_bldr = new WPES_WP_Blog_Field_Builder();
		return $fld_bldr->blog_lang( $blog_id );
	}

	public function post_author( $blog_id, $post ) {
		$post_user = get_userdata( $post->post_author );
		$data['author'] = $this->clean_string( $post_user->display_name );
		$data['author_login'] = $this->clean_string( $post_user->user_login );
		$data['author_id'] = $this->clean_int( $post_user->ID, 'author_id' );
		return $data;
	}

	public function taxonomy( $post ) {
		global $wpdb;
		$data = array();

		//get all terms associated with post and store as appropriate taxonomy
		//by Higgs this is ugly
		$query = $wpdb->prepare( "SELECT tt.taxonomy, t.name, t.slug, t.term_id FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = %d ", $post->ID );
		$terms = $wpdb->get_results( $query );

		$tax_list = array();
		foreach ( $terms as $term ) {
			$tax = $this->clean_string( $term->taxonomy );
			if ( ! isset( $tax_list[$tax] ) )
				$tax_list[$tax] = array();

			$tax_list[$tax][] = array(
				'name' => $this->clean_string( $term->name, 1024 ), // Limit at 1KB -- overkill varchar at 200
				'slug' => $this->clean_string( $term->slug, 1024 ), //clean in case of non utf-8
				'term_id' => $term->term_id
			);
		}

		if ( isset( $tax_list['post_tag'] ) ) {
			$data['tag'] = $tax_list['post_tag'];
			unset( $tax_list['post_tag'] );
		}

		if ( isset( $tax_list['category'] ) ) {
			$data['category'] = $tax_list['category'];
			unset( $tax_list['category'] );
		}

		$data['tag_cat_count'] = $this->clean_short( ( count( $data['tag'] ) + count( $data['category'] ) ), 'tag_cat_count' );

		if ( ! empty( $tax_list ) ) {
			$data['taxonomy'] = array();
			foreach ( $tax_list as $name => $term_list ) {
				$data['taxonomy'][$name] = $term_list;
			}
		}
		return $data;
	}

	public function meta( $post, $blacklist = array(), $whitelist = array() ) {
		$data = array();
		$meta = get_post_meta( $post->ID );
		if ( !empty( $meta ) ) {
			$data['meta'] = array();
			foreach ( $meta as $key => $v ) {
				if ( in_array( $key, $blacklist ) )
					continue;
				if ( in_array( $key, $whitelist ) || !is_protected_meta( $key ) ) {
					$unserialized = maybe_unserialize( $v ); //try one more unserialize op
					if ( $this->is_assoc_array( $unserialized ) )
						continue;

					if ( is_object( $unserialized ) )
						continue;

					if ( is_array( $unserialized ) && $this->is_multi_dim_array( $unserialized ) )
						continue;

					$clean_key = $this->clean_object( $key );
					if ( !is_array( $unserialized ) ) {
						$unserialized = array( $unserialized );
					}

					$data['meta'][$clean_key] = array(
						'value' => array(),
						'long' => array(),
						'double' => array(),
						'boolean' => array(),
					);
					foreach ( $unserialized as $val ) {
						$data['meta'][$clean_key]['value'][] = $this->clean_string( (string) $val, 4096 ); // limit at 4KB
						$data['meta'][$clean_key]['long'][] = $this->clean_long( (int) $val, 'meta.' . $clean_key . '.long' );
						$data['meta'][$clean_key]['double'][] = $this->clean_float( $val );
						if ( ( "false" === $val ) || ( "FALSE" === $val ) ) {
							$bool = false;
						} elseif ( ( 'true' === $val ) || ( 'TRUE' === $val ) ) {
							$bool = true;
						} else {
							$bool = (boolean) $val;
						}
						$data['meta'][$clean_key]['boolean'][] = $bool;
					}
				}
			}
		}
		return $data;
	}

	public function commenters( $blog_id, $post ) {
		global $wpdb;
		$data = array();

		$blog_details = get_blog_details( $blog_id );
		if ( 1 == $blog_details->site_id ) { //wp.com
			$query = $wpdb->prepare( "SELECT DISTINCT user_id FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = 1 AND user_id != 0", $post->ID );
			$commenter_ids = $wpdb->get_col( $query );
		} else {
			//jetpack comments from highlander have wpcom id stored in comment meta
			// if they aren't using highlander then the user ids don't mean anything since they are specific to the blog
			// unfortunately no cleaner way to do this query
			$query = $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = 1", $post->ID );
			$comment_ids = $wpdb->get_col( $query );

			$commenter_ids = array();
			if ( ! empty( $comment_ids ) ) {
				foreach ( $comment_ids as $idx => $comment_id ) {
					// Clear cache every 1000 calls for posts with thousands of commenters
					if ( 0 === ( $idx % 1000 ) )
						stop_the_insanity();

					$commenter_id = get_comment_meta( $comment_id, '_jetpack_wpcom_user_id', true );
					if ( $commenter_id )
						$commenter_ids[$commenter_id] = true;
				}
				$commenter_ids = array_keys( $commenter_ids );
			}
		}

		$data['commenter_ids'] = array();
		foreach ( $commenter_ids as $commenter_id ) {
			if ( $commenter_id ) //get rid of user id 0
				$data['commenter_ids'][] = $this->clean_int( $commenter_id, 'commenter_ids' );
		}
		$data['comment_count'] = $this->clean_int( $post->comment_count, 'comment_count' );

		return $data;
	}

	public function extract_media( $blog_id, $post ) {
		$data = Jetpack_Media_Meta_Extractor::extract( $blog_id, $post->ID, Jetpack_Media_Meta_Extractor::ALL );

		//only allow those top level fields that we expect to prevent accidentally creating new mappings
		$whitelist = array( 'has', 'link', 'image', 'shortcode', 'mention', 'hashtag', 'embed', 'shortcode_types' );
		foreach ( $data as $field => $d ) {
			if ( ! in_array( $field, $whitelist ) )
				unset( $data[$field] );
		}

		//clean longs, ints, shorts
		if ( isset( $data['has'] ) ) {
			foreach ( $data['has'] as $key => $cnt) {
				$data['has'][$key] = $this->clean_short( $cnt, 'has.' . $key );
			}
		}
		if ( isset( $data['shortcode'] ) ) {
			foreach ( $data['shortcode'] as $code => $obj) {
				$data['shortcode'][$code]['count'] = $this->clean_short( $obj['count'], 'shortcode.' . $code . '.count' );
			}
		}
		//clean urls (we don't want the scheme so we can do prefix matching)
		if ( isset( $data['image'] ) ) {
			foreach ( $data['image'] as $idx => $obj) {
				$data['image'][$idx]['url'] = $this->remove_url_scheme( $obj['src'] );
			}
		}

		return $data;
	}

	public function featured_image( $post ) {
		$data = array();
		if ( has_post_thumbnail( $post->ID ) ) {
			$struct = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'post-thumbnail' );
			if ( isset( $struct[0] ) )
				$data['featured_image'] = $this->remove_url_scheme( $struct[0] );
		}

		return $data;
	}

}

