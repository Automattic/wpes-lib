<?php

require_once dirname( __FILE__ ) . '/class.wpes-abstract-document-builder.php';

class WPES_WP_Post_Document_Builder extends WPES_Abstract_Document_Builder {
	
	public function get_doc_id( $args ) {
		return $args['blog_id'] . '-p-' . $args['post_id'];
	}

	public function get_parent_doc_id( $args ) {
		return $args['blog_id'];
	}

	public function is_indexable( $args ) {
		if ( ! ( $args['id'] > 0 ) )
			return false;
	
		switch_to_blog( $args['blog_id'] );
	
		$post = get_post( $args['id'] );
		if ( ! $post ) {
			restore_current_blog();
			return false;
		}
	
		if ( 'publish' != $post->post_status ) {
			restore_current_blog();
			return false;
		}
	
		if ( 0 < strlen( $post->post_password ) ) {
			restore_current_blog();
			return false;
		}
	
		$blog_details = get_blog_details( $blog_id );
	
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

	public function get_document_data( $args ) {
		switch_to_blog( $args['blog_id'] );
		$post = get_post( $args['id'] );
	
		if ( !$post ) {
			restore_current_blog();
			return false;
		}
	
		if ( ! $this->is_indexable( $args ) ) {
			restore_current_blog();
			return false;
		}
		
		if ( 'publish' != $post->post_status ) {
			restore_current_blog();
			return false;
		}
	
		$user = get_userdata( $post->post_author );
	
		$blog = get_blog_details( $args['blog_id'] );
	
		$post = $this->clean_bad_post_dates( $post );
	
		$post_title = $this->clean_string( $post->post_title );
		$post_content = $this->clean_string( $post->post_content );
		$post_excerpt = $this->clean_string( $post->post_excerpt );
	
		$lang = get_lang_code_by_id( $blog->lang_id );
		if ( ! $lang ) {
			//default to English since that is the WP default
			$lang = 'en';
		}
		$lang_builder = WPES_Analyzer_Builder::init();
		$lang_analyzer = $lang_builder->get_analyzer_name( $lang );
	
		$date_gmt = $this->clean_date( $post->post_date_gmt );
		$already_added = get_post_meta( $post->ID, '_elasticsearch_indexed_on', true );
		if ( false == $already_added ) {
			$nt = time();
			$pt = strtotime( $date_gmt );
			if ( ( $nt - $pt ) > 5 * MINUTE_IN_SECONDS ) {
				//if the post was published more than 5 minutes ago and we haven't indexed it before, 
				// then we are probably bulk indexing, so use the posted on date
				$date_added = $date_gmt;
			} else {
				$date_added = date( 'Y-m-d h:i:s', $nt );
			}
			add_post_meta( $post->ID, '_elasticsearch_indexed_on', $date_added, true );
		} else {
			$date_added = $already_added;
		}
	
		// Build document data
		$data = array(
			'post_id'      => $this->clean_long( $post->ID, 'post_id' ),
			'blog_id'      => $this->clean_int( $blog_id, 'blog_id' ),
			'site_id'      => $this->clean_short( $blog->site_id, 'site_id' ),
			'post_type'    => $post->post_type,
			'lang'         => $lang,
			'lang_analyzer' => $lang_analyzer,
			'url'          => $this->remove_url_scheme( get_permalink( $post->ID ) ),
			'date'         => $this->clean_date( $post->post_date ),
			'date_gmt'     => $date_gmt,
			'date_added'   => $date_added,
			'author'       => $this->clean_string( $user->display_name ),
			'author_login' => $this->clean_string( $user->user_login ),
			'author_id'    => $this->clean_int( $user->ID, 'author_id' ),
			'title'        => $post_title,
			'content'      => $post_content,
			'excerpt'      => $post_excerpt,
			'content_word_count' => $this->clean_int( $this->word_count( $post_content, $lang ), 'word_count' ),
		);
	
		$tax_data = $fld_bldr->taxonomy( $post );
		$commenters_data = $fld_bldr->commenters( $blog_id, $post );
		$feat_img_data = $fld_bldr->featured_image( $post );
		$data = array_merge( $data, $tax_data, $commenters_data, $feat_img_data );
		
		restore_current_blog();
		return $data;
	}

	public function get_update_data( $args = array() ) {
		$update_script = array();
		if ( count( $args['updates'] ) != 1 )
			return new WP_Error( 'es-doc-callbacks', 'Don\'t currently support multiple updates in one op' );

		foreach ( $args['updates'] as $op => $update_args ) {
			switch ( $op ) {
				case 'add_comment' :
					$user_id = $update_args;
					if ( $user_id ) {
						$update_script['script'] = 'if ( !ctx._source.commenter_ids.contains(commenter) ) { ctx._source.commenter_ids += commenter; } ctx._source.comment_count = ctx._source.comment_count + 1;';
						$update_script['params'] = array( "commenter" => $update_args );
					} else {
						$update_script['script'] = 'ctx._source.comment_count = ctx._source.comment_count + 1;';
					}
					break;
				case 'remove_comment' :
					$remove = false;
					$user_id = $update_args;
					if ( $user_id ) { //user_id 0 is never in the list
						//check whether this commenter has any approved comments left on the post
						$comment_id = $wpdb->get_var( $wpdb->prepare( 'SELECT comment_ID FROM wp_%d_comments WHERE comment_post_ID = %d AND user_id = %d AND comment_approved = "1" LIMIT 1', $blog_id, $id, $user_id ) );
						if ( !$comment_id )
							$remove = true;
					}

					if ( $remove ) {
						$update_script['script'] = 'if ( ctx._source.commenter_ids.contains(commenter) ) { idx = ctx._source.commenter_ids.indexOf(commenter); ctx._source.commenter_ids.remove(idx); } ctx._source.comment_count = ctx._source.comment_count - 1;';
						$update_script['params'] = array( "commenter" => $user_id );
					} else {
						$update_script['script'] = 'ctx._source.comment_count = ctx._source.comment_count - 1;';
					}
					break;
				default:
					return new WP_Error( 'wpes-update-fail', 'Unknown update operation: ' . $op );
			}
		}
		return $update_script;
	}

	protected function taxonomy( $post ) {
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
				'name' => $this->clean_string( $term->name ),
				'slug' => $this->clean_string( $term->slug ), //clean in case of non utf-8
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

	protected function meta( $post ) {
		$data = array();
		$meta = get_post_meta( $post->ID );
		if ( !empty( $meta ) ) {
			$data['meta'] = array();
			foreach ( $meta as $key => $v ) {
				if ( !is_protected_meta( $key ) ) {
					$clean_key = $this->clean_object( $key );
					$clean_v = $this->clean_object( $v );
					$data['meta'][$clean_key] = array( 'value' => $clean_v );
				}
			}
		}
		return $data;
	}

	protected function commenters( $blog_id, $post ) {
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
				foreach ( $comment_ids as $comment_id ) {
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

	protected function featured_image( $post ) {
		$data = array();
		if ( has_post_thumbnail( $post->ID ) ) {
			$struct = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'post-thumbnail' );
			if ( isset( $struct[0] ) )
				$data['featured_image'] = $this->remove_url_scheme( $struct[0] );
		}

		return $data;
	}

	//probably want to call clean_string() on any content passed into this function first
	// handles word counts in multiple languages, but does not do a good job on mixed content (eg English and Japanese in one post)
	protected function word_count( $text, $lang ) {
		$non_asian_char_pattern = '/([^\p{L}]|[a-zA-Z0-9])/u';
		//The word to character ratios are based on the rates translators charge
		//so, very approximate, especially for mixed text
		switch ( $lang ) {
			case 'zh':
			case 'zh-tw':
			case 'zh-hk':
			case 'zh-cn':
				//use a ratio of 1.5:1 chars per word
				$clean_text = preg_replace( $non_asian_char_pattern, '', $text );
				$wc = mb_strlen( $clean_text );
				$wc = ceil( $wc / 1.5 ); //round up so if we have 1 char, then we'll always have 1 word
				break;
			case 'ja':
				//use a ratio of 2.5:1 chars per word
				$clean_text = preg_replace( $non_asian_char_pattern, '', $text );
				$wc = mb_strlen( $clean_text );
				$wc = ceil( $wc / 2.5 );
				break;
			case 'ko':
				//use a ratio of 2:1 chars per word
				$clean_text = preg_replace( $non_asian_char_pattern, '', $text );
				$wc = mb_strlen( $clean_text );
				$wc = ceil( $wc / 2 );
				break;
			default:
				$wc = preg_match_all( '/\S+/u', $text );
		}

		return $wc;
	}

}
