<?php

class WPES_WP_Comment_Field_Builder extends WPES_Abstract_Field_Builder {

	public function get_mappings( $args = array() ) {
		$defaults = array(
			'all_field_enabled' => false,
			'index_meta' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$dynamic_templates = array( 
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
			$dynamic_templates[] = array(
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
			$dynamic_templates[] = array(
				"meta_long_template" => array(
					"path_match" => "meta.*.long",
					"mapping" => array(
						'type' => 'long'
					),
			) );
			$dynamic_templates[] = array(
				"meta_bool_template" => array(
					"path_match" => "meta.*.boolean",
					"mapping" => array(
						'type' => 'boolean'
					),
			) );
			$dynamic_templates[] = array(
				"meta_float_template" => array(
					"path_match" => "meta.*.double",
					"mapping" => array(
						'type' => 'double'
					),
			) );
		}
	
		//same mapping for both pages, posts, all custom post types
		$comment_mapping = array(
			'dynamic_templates' => $dynamic_templates,
			'_all' => array( 'enabled' => $args['all_field_enabled'] ),
			'_analyzer' => array( 'path' => 'lang_analyzer' ),
			'properties' => array(
		
				//////////////////////////////////
				//Blog/Post/Comment meta fields
		
				'comment_id' => array( 
					'type' => 'long', 
					'store' => 'yes'
				),
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
				'comment_type' => array( 
					'type' => 'string', 
					'index' => 'not_analyzed' 
				),
				'comment_status' => array( 
					'type' => 'string', 
					'index' => 'not_analyzed' 
				),
				'public' => array( 
					'type' => 'boolean',
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
					)
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
				'post_author_id' => array( 
					'type' => 'integer'
				),
				'post_author_login' => array( 
					'type' => 'string', 
					'index' => 'not_analyzed'
				),
				'post_author' => array(
					'type' => 'multi_field', 
					'fields' => array(
						'post_author' => array(
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
					'type' => 'object',
					'properties' => array(
						'url' => array(
							'type' => 'string', 
							'index' => 'not_analyzed',
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
				//Comment Threading
		
				'parent_commenter_id' => array(
					'type' => 'integer', 
				),
				'parent_comment_id' => array(
					'type' => 'long', 
				),
				'ancestor_comment_ids' => array(
					'type' => 'long', 
				),
			)
		);

		return $comment_mapping;
	}

	public function get_all_fields( $args = array() ) {
		$defaults = array(
			'index_meta' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		switch_to_blog( $args['blog_id'] );

		$post = get_comment( $args['comment_id'] );
		if ( !$comment ) {
			restore_current_blog();
			return false;
		}

		$blog = get_blog_details( $args['blog_id'] );

		$data = array(
			'blog_id'      => $this->clean_int( $args['blog_id'], 'blog_id' ),
			'site_id'      => $this->clean_short( $blog->site_id, 'site_id' ),
		);

		$lang_data = $this->blog_lang( $blog_id );
		$comment_data = $this->comment_fields( $comment, $lang_data['lang'] );
		$media_data = $this->extract_media( $args['blog_id'], $comment );
		if ( $args['index_meta'] ) {
			$meta_data = $this->meta( $comment );
		} else {
			$meta_data = array();
		}

		$data = array_merge( 
			$data,
			$lang_data,
			$comment_data,
			$media_data, 
			$meta_data
		);
		restore_current_blog();
		return $data;
	}

	public function get_update_script( $args ) {
		$update_script = array();
		return $update_script;
	}

	function is_comment_public( $blog_id, $comment_id ) {
		switch_to_blog( $blog_id );

		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			restore_current_blog();
			return false;
		}

		if ( 1 != $comment->comment_approved ) {
			restore_current_blog();
			return false;
		}

		//verify that parent post is indexable
		$wp_post_bldr = new WPES_WP_Post_Field_Builder();
		if ( !$wp_post_bldr->is_post_indexable( $blog_id, $comment->comment_post_ID ) ) {
			restore_current_blog();
			return false;
		}

		restore_current_blog();
		return true;
	}

	function is_comment_indexable( $blog_id, $comment_id ) {
		if ( $comment_id == false )
			return false;

		return $this->is_comment_public( $blog_id, $comment_id );
	}

	function comment_fields( $comment, $lang ) {
		$content = $this->remove_shortcodes( $this->clean_string( $comment->comment_content ) );

		$comment_status = wp_get_comment_status( $comment->comment_ID );
		if ( false == $comment_status )
			$comment_status = 'none';

		$data = array(
			'comment_id'    => $this->clean_long( $comment->comment_ID, 'commend_id' ),
			'post_id'       => $this->clean_long( $comment->comment_post_ID, 'comment_post_ID' ),
			'comment_status' => $comment_status,
			'public'        => (boolean) $this->is_comment_public( $blog_id, $comment->comment_ID ),

			'url'           => $this->remove_url_scheme( get_comment_link( $comment->comment_ID ) ),
			'date'          => $this->clean_date( $comment->comment_date ),
			'date_gmt'      => $this->clean_date( $comment->comment_date_gmt ),
			'date_token'     => $this->date_object( $comment->comment_date ),
			'date_gmt_token' => $this->date_object( $comment->comment_date_gmt ),
			'content'       => $content,
			'comment_type'  => $this->clean_string( $comment->comment_type ),
		);

		$author = $this->comment_author( $comment );
		$post_author = $this->comment_author( $comment );
		$data = array_merge( $data, $author );

		if ( $comment->comment_parent ) {
			$parent_comment = get_comment( $comment->comment_parent );
			if ( $parent_comment ) {
				$data['parent_comment_id'] = $this->clean_long( $comment->comment_parent, 'comment_parent' );
				$data['ancestor_comment_ids'] = $this->get_comment_ancestors( $comment->comment_ID );

				$parent_author = $this->comment_author( $parent_comment );
				$data['parent_commenter_id'] = $parent_author['author_id'];
			}
		}

		return $data;
	}

	public function comment_author( $comment ) {
		if ( $comment->user_id ) {
			$user = get_userdata( $comment->user_id );

			$data = array(
				'author'        => $this->clean_string( $user->display_name ),
				'author_login'  => $this->clean_string( $user->user_login ),
				'author_id'     => $this->clean_int( $user->ID, 'author_id' ),
			);
		} else {
			$data = array(
				'author'        => $this->clean_string( $comment->comment_author ),
				'author_id'     => 0,
			);
		}

		$post = get_post( $comment->comment_post_ID );
		$post_user = get_userdata( $post->post_author );
		$data['post_author'] = $this->clean_string( $post_user->display_name );
		$data['post_author_login'] = $this->clean_string( $post_user->user_login );
		$data['post_author_id'] = $this->clean_int( $post_user->ID, 'author_id' );

		return $data;
	}

	public function meta( $comment, $blacklist = array() ) {
		$data = array();
		$meta = get_comment_meta( $comment->comment_ID );
		if ( !empty( $meta ) ) {
			$data['meta'] = array();
			foreach ( $meta as $key => $v ) {
				if ( in_array( $key, $blacklist ) )
					continue;
				if ( !is_protected_meta( $key ) ) {
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
						$data['meta'][$clean_key]['value'][] = $this->clean_string( (string) $val );
						$data['meta'][$clean_key]['long'][] = $this->clean_long( (int) $val, 'meta.' . $clean_key . '.long' );
						$data['meta'][$clean_key]['double'][] = (float) $val;
						if ( ( "false" === $val ) || ( "FALSE" === $val ) ) {
							$bool = false;
						} elseif ( ( 'true' === $val ) || ( 'TRUE' === $val ) ) {
							$bool = true;
						} else {
							(boolean) $val;
						}
						$data['meta'][$clean_key]['boolean'][] = $bool;
					}
				}
			}
		}
		return $data;
	}

	public function blog_lang( $blog_id ) {
		$fld_bldr = new WPES_WP_Blog_Field_Builder();
		return $fld_bldr->blog_lang( $blog_id );
	}

	public function extract_media( $blog_id, $comment ) {
		$data = Jetpack_Media_Meta_Extractor::extract_from_content( $comment->comment_content, Jetpack_Media_Meta_Extractor::ALL );

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

		return $data;
	}

	//based on get_post_ancestors()
	function get_comment_ancestors( $comment_id ) {
		$comment = get_comment( $comment_id );
	
		if ( ! $comment || empty( $comment->comment_parent ) || $comment->comment_parent == $comment->comment_ID )
			return array();
	
		$ancestors = array();
	
		$id = $ancsetors[] = (int) $comment->comment_parent;
	
		while ( $ancestor = get_comment( $id ) ) {
			// Loop detection: If the ancestor has been seen before, break.
			if ( empty( $ancestor->comment_parent ) || ( $ancestor->comment_parent == $comment->comment_ID ) || in_array( $ancestor->comment_parent, $ancestors ) )
				break;
	
			$id = $ancestors[] = (int) $ancestor->comment_parent;
		}
	
		return $ancestors;
	}

}

