<?php

class WPES_WP_Comment_Field_Builder extends WPES_Abstract_Field_Builder {

	public function get_mappings( $args = array() ) {
		$defaults = array(
			'all_field_enabled' => false,
			'index_meta' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$mappings = new WPES_WP_Mappings();

		$dynamic_templates = array(
			array(
				"has_template" => array(
					"path_match" => "has.*",
					"mapping" => $mappings->primitive( 'short' ),
			) ),
			array(
				"shortcode_args_template" => array(
					"path_match" => "shortcode.*.id",
					"mapping" => $mappings->keyword(),
			) ),
			array(
				"shortcode_count_template" => array(
					"path_match" => "shortcode.*.count",
					"mapping" => $mappings->primitive( 'short' ),
			) ),
		);

		if ( $args['index_meta'] ) {
			$dynamic_templates[] = array(
				"meta_str_template" => array(
					"path_match" => "meta.*.value",
					"mapping" => $mappings->text_lcase_raw( 'value' ),
			) );
			$dynamic_templates[] = array(
				"meta_long_template" => array(
					"path_match" => "meta.*.long",
					"mapping" => $mappings->primitive( 'long' ),
			) );
			$dynamic_templates[] = array(
				"meta_bool_template" => array(
					"path_match" => "meta.*.boolean",
					"mapping" => $mappings->primitive( 'boolean' ),
			) );
			$dynamic_templates[] = array(
				"meta_float_template" => array(
					"path_match" => "meta.*.double",
					"mapping" => $mappings->primitive( 'double' ),
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

				'comment_id'            => $mappings->primitive_stored( 'long' ),
				'post_id'               => $mappings->primitive_stored( 'long' ),
				'blog_id'               => $mappings->primitive_stored( 'integer' ),
				'site_id'               => $mappings->primitive( 'short' ),
				'comment_type'          => $mappings->keyword(),
				'comment_status'        => $mappings->keyword(),
				'public'                => $mappings->primitive( 'boolean' ),

				'lang'                  => $mappings->keyword(),
				'lang_analyzer'         => $mappings->keyword(),

				'url'                   => $mappings->text_raw( 'url' ),

				'date'                  => $mappings->datetime(),
				'date_token'            => $mappings->datetimetoken(),
				'date_gmt'              => $mappings->datetime(),
				'date_gmt_token'        => $mappings->datetimetoken(),

				//////////////////////////////////
				//Post Content fields

				'author'                => $mappings->text_raw( 'author' ),
				'author_login'          => $mappings->keyword(),
				'author_id'             => $mappings->primitive( 'integer' ),
				'post_author_id'        => $mappings->primitive( 'integer' ),
				'post_author_login'     => $mappings->keyword(),
				'post_author'           => $mappings->text_raw( 'post_author' ),
				'title'                 => $mappings->text_count( 'title' ),
				'content'               => $mappings->text_count( 'content' ),

				//////////////////////////////////
				//Embedded Media/Shortcodes/etc

				//has.* added as dynamic template

				'link' => array(
					'type' => 'object',
					'properties' => array(
						'url'           => $mappings->text_raw( 'url' ),
						'host'          => $mappings->keyword(),
						'host_reversed' => $mappings->keyword(),
					),
				),
				'image' => array(
					'type' => 'object',
					'properties' => array(
						'url'           => $mappings->keyword(),
					),
				),
				'shortcode_types'       => $mappings->keyword(),
				'embed' => array(
					'type' => 'object',
					'properties' => array(
						'url'           => $mappings->keyword(),
					),
				),
				'hashtag' => array(
					'type' => 'object',
					'properties' => array(
						'name'          => $mappings->keyword(),
					),
				),
				'mention' => array(
					'type' => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'multi_field',
							'fields' => array(
								'name'  => $mappings->keyword(),
								'lc'    => $mappings->keyword_lcase(),
							),
						),
					),
				),

				//////////////////////////////////
				//Comment Threading

				'parent_commenter_id'   => $mappings->primitive( 'integer' ),
				'parent_comment_id'     => $mappings->primitive( 'long' ),
				'ancestor_comment_ids'  => $mappings->primitive( 'long' ),

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

		$comment = get_comment( $args['comment_id'] );
		if ( !$comment ) {
			restore_current_blog();
			return false;
		}

		$blog = get_blog_details( $args['blog_id'] );

		$data = array(
			'blog_id'      => $this->clean_int( $args['blog_id'], 'blog_id' ),
			'site_id'      => $this->clean_short( $blog->site_id, 'site_id' ),
		);

		$lang_data = $this->comment_lang( $args['blog_id'] );
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

		foreach ( $args['updates'] as $op => $update_args ) {
			switch ( $op ) {
				case 'update_status' :
					switch_to_blog( $args['blog_id'] );
					$comment_status = wp_get_comment_status( $args['id'] );
					if ( false == $comment_status )
						$comment_status = 'none';

					$update_script['doc']['comment_status'] = $comment_status;
					restore_current_blog();
					break;
				case 'update_public' :
					$update_script['doc']['public'] = (boolean) $this->is_comment_public( $args['blog_id'], $args['id'] );
					break;
				case 'update_post_author' :
					switch_to_blog( $args['blog_id'] );
					$comment = get_comment( $args['id'] );
					$update_script['doc'] = array_merge( $this->post_author( $args['blog_id'], $comment ), $update_script['doc'] );
					restore_current_blog();
					break;
			}
		}

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

		//verify that parent post is public
		if ( !$this->is_post_public( $blog_id, $comment->comment_post_ID ) ) {
			restore_current_blog();
			return false;
		}

		restore_current_blog();
		return true;
	}

	function is_post_public( $blog_id, $post_id ) {
		$wp_post_bldr = new WPES_WP_Post_Field_Builder();
		return $wp_post_bldr->is_post_public( $blog_id, $post_id );
	}

	function is_comment_indexable( $blog_id, $comment_id ) {
		if ( $comment_id == false )
			return false;

		return $this->is_comment_public( $blog_id, $comment_id );
	}

	function comment_fields( $comment, $lang ) {
		global $blog_id;
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
		$post_author = $this->post_author( $blog_id, $comment );
		$data = array_merge( $data, $author, $post_author );

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

		return $data;
	}

	public function post_author( $blog_id, $comment ) {
		$fld_bldr = new WPES_WP_Post_Field_Builder();
		$post = get_post( $comment->comment_post_ID );
		$data = $fld_bldr->post_author( $blog_id, $post );
		$comment_data['post_author'] = $data['author'];
		$comment_data['post_author_login'] = $data['author_login'];
		$comment_data['post_author_id'] = $data['author_id'];
		return $comment_data;
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

	public function comment_lang( $blog_id, $comment = null ) {
		$fld_bldr = new WPES_WP_Blog_Field_Builder();
		return $fld_bldr->blog_lang( $blog_id );
	}

	public function extract_media( $blog_id, $comment ) {
		require_lib('class.wpcom-media-meta-extractor');
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
		//clean urls (we don't want the scheme so we can do prefix matching)
		if ( isset( $data['image'] ) ) {
			foreach ( $data['image'] as $idx => $obj) {
				$data['image'][$idx]['url'] = $this->remove_url_scheme( $obj['url'] );
			}
		}
		//clean urls to get rid of non utf-8 chars
		if ( isset( $data['link'] ) ) {
			foreach ( $data['link'] as $idx => $obj) {
				$data['link'][$idx]['url'] = $this->remove_url_scheme( $obj['url'] );
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

