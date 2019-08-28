<?php

/*
 * Jetpack Search Index for Elasticsearch 2.x
 *
 */

class Jetpack_Search_v2_Index_Builder extends WPES_Abstract_Index_Builder {

	public function get_config( $args ) {
		$defaults = array(
		);
		$args = wp_parse_args( $args, $defaults );

		$config = $this->_build_standard_index_config( $args );

		return $config;
	}

	public function get_settings( $args ) {
		$defaults = array(
		);
		$args = wp_parse_args( $args, $defaults );

		$analyzer_builder = new WPES_Analyzer_Builder();
		$analyzers = $analyzer_builder->build_jp_search_analyzers();

		$global_settings = array(
			'number_of_shards' => 300,
			'number_of_replicas' => 2,
			'analysis' => $analyzers,
		);

		return $global_settings;
	}

	//This index uses some experimental analyzers and mappings
	protected function map_ml_engram() {
		$mappings = new WPES_WP_Mappings();
		$lang_analyzers = $mappings->get_all_lang_analyzers();
		$langs = array_keys( $lang_analyzers );
		$langs[] = 'default';

		$mapping = [
			'type' => 'object',
			'properties' => [],
		];

		foreach( $langs as $l ) {
			$a_name = $l . ( ( $l == 'default' ) ? '' : '_analyzer' );
			$mapping['properties'][$l] = [
				'type' => 'string',
				'index' => 'analyzed',
				'analyzer' => $a_name,
				'similarity' => 'BM25',
				'fields' => [
					'engram' => [
						'type' => 'string',
						'index' => 'analyzed',
						'similarity' => 'BM25',
						'analyzer' => $l . '_edge_analyzer',
						'search_analyzer' => $a_name,
					],
					'word_count' => [
						'type' => 'token_count',
						'store' => true,
						'analyzer' => 'default',
					],
				],
			];
		}

		return $mapping;
	}

	//This index uses some experimental analyzers and mappings
	protected function map_ml_text( $highlighting = false ) {
		$mappings = new WPES_WP_Mappings();
		$lang_analyzers = $mappings->get_all_lang_analyzers();
		$langs = array_keys( $lang_analyzers );
		$langs[] = 'default';

		$mapping = [
			'type' => 'object',
			'properties' => [],
		];

		foreach( $langs as $l ) {
			$a_name = $l . ( ( $l == 'default' ) ? '' : '_analyzer' );
			$mapping['properties'][$l] = [
				'type' => 'string',
				'index' => 'analyzed',
				'analyzer' => $a_name,
				'similarity' => 'BM25',
				'fields' => [
					'word_count' => [
						'type' => 'token_count',
						'store' => true,
						'analyzer' => 'default',
					],
				],
			];
			if ( $highlighting ) {
				$mapping['properties'][$l]['store'] = true;
				$mapping['properties'][$l]['term_vector'] = 'with_positions_offsets';
			}
		}

		return $mapping;
	}

	//optimized for storing data and returning it quickly
	// can not be searched or aggregated on
	public function map_store_html() {
		return array(
			'type' => 'string',
			'index' => 'no',
			'store' => true,
		);
	}

	public function get_mappings( $args ) {
		$defaults = array(
		);
		$args = wp_parse_args( $args, $defaults );

		$mappings = new WPES_WPCOM_Mappings();

		$dynamic_post_templates = array(
			// taxonomy.*
			array(
				"tax_template_name" => array(
					"path_match" => "taxonomy.*.name",
					"mapping" => $mappings->text_raw( 'name' ),
			) ),
			array(
				"tax_template_slug" => array(
					"path_match" => "taxonomy.*.slug",
					"mapping" => $mappings->keyword(),
			) ),
			array(
				"tax_template_term_id" => array(
					"path_match" => "taxonomy.*.term_id",
					"mapping" => $mappings->primitive( 'long' ),
			) ),

			// has.*
			array(
				"has_template" => array(
					"path_match" => "has.*",
					"mapping" => $mappings->primitive( 'short' ),
			) ),

			// shortcode.*
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

			// meta.*
			array(
				"meta_str_template" => array(
					"path_match" => "meta.*.value",
					"mapping" => $mappings->text_raw( 'value' ),
			) ),
			array(
				"meta_long_template" => array(
					"path_match" => "meta.*.long",
					"mapping" => $mappings->primitive( 'long' ),
			) ),
			array(
				"meta_bool_template" => array(
					"path_match" => "meta.*.boolean",
					"mapping" => $mappings->primitive( 'boolean' ),
			) ),
			array(
				"meta_float_template" => array(
					"path_match" => "meta.*.double",
					"mapping" => $mappings->primitive( 'double' ),
			) ),
			array(
				"meta_date_template" => array(
					"path_match" => "meta.*.date",
					"mapping" => $mappings->datetime(),
			) ),

			//probability that content is in a particular language
			array(
				"langs_template" => array(
					"path_match" => "langs.*",
					"mapping" => $mappings->primitive( 'float' ),
				) ),
		);

		$faq_template = $mappings->text_plus_engram( 'faq_ques' );
		$faq_template['fields']['raw']['store'] = 'yes';

		//same mapping for both pages, posts, all custom post types
		$post_mapping = array(
			'dynamic' => 'strict',
			'dynamic_templates' => $dynamic_post_templates,
			'_all' => array( 'enabled' => false ),
			'properties' => array(

				//////////////////////////////////
				//Home for our dynamic fields
				'taxonomy'              => $mappings->dynamic(),
				'has'                   => $mappings->dynamic(),
				'shortcode'             => $mappings->dynamic(),
				'meta'                  => $mappings->dynamic(),
				'langs'                 => $mappings->dynamic(),

				//////////////////////////////////
				//Blog/Post meta fields

				'post_id'               => $mappings->primitive_stored( 'long' ),
				'blog_id'               => $mappings->primitive_stored( 'integer' ),
				'site_id'               => $mappings->primitive( 'short' ),
				'post_type'             => $mappings->keyword(),
				'post_format'           => $mappings->keyword(),
				'post_mime_type'        => $mappings->text_raw( 'post_mime_type' ),
				'post_status'           => $mappings->keyword(),
				'public'                => $mappings->primitive( 'boolean' ),
				'has_password'          => $mappings->primitive( 'boolean' ),

				'parent_post_id'        => $mappings->primitive( 'long' ),
				'ancestor_post_ids'     => $mappings->primitive( 'long' ),

				'menu_order'            => $mappings->primitive( 'integer' ),

				'lang'                  => $mappings->keyword(),

				'permalink'             => $mappings->url_stored(),
				'slug'                  => $mappings->keyword(),

				'date'                  => $mappings->datetime_stored(),
				'date_token'            => $mappings->datetimetoken(),
				'date_gmt'              => $mappings->datetime_stored(),
				'date_gmt_token'        => $mappings->datetimetoken(),
				'modified'              => $mappings->datetime_stored(),
				'modified_token'        => $mappings->datetimetoken(),
				'modified_gmt'          => $mappings->datetime_stored(),
				'modified_gmt_token'    => $mappings->datetimetoken(),

				'sticky'                => $mappings->primitive( 'boolean' ),

				//////////////////////////////////
				//Post Content fields

				'author'                => $mappings->text_raw_stored( 'author' ),
				'author_login'          => $mappings->keyword_stored(),
				'author_id'             => $mappings->primitive( 'integer' ),
				'author_ext_id'         => $mappings->primitive( 'integer' ),

				'all_content'           => $this->map_ml_engram(),
				'title'                 => $this->map_ml_text( true ),
				'content'               => $this->map_ml_text( true ),
				'excerpt'               => $this->map_ml_text(),
				'comments'              => $this->map_ml_text( true ),

				'tag_cat_count'         => $mappings->primitive( 'short' ),
				'tag'                   => $mappings->tagcat_ml( 'tag' ),
				'category'              => $mappings->tagcat_ml( 'category' ),

				//'faq'                   => array(
				//	'type' => 'object',
				//	'properties' => array(
				//		'ques'              => $faq_template,
				//		'context'           => $mappings->long_text_engram( 'context' ),
				//		'anchor'            => $mappings->keyword_stored(),
				//	),
				//),

				//'file'                  => $mappings->file_attachment(),

				//taxonomy.*.* added as dynamic template

				//////////////////////////////////
				//Embedded Media/Shortcodes/etc

				//has.* added as dynamic template

				'link'                  => $mappings->url_analyzed(),
				'link_internal' => array(
					'type' => 'object',
					'properties' => array(
						'post_id' => $mappings->primitive( 'long' ),
						'post_type' => $mappings->keyword(),
						'comment_id' => $mappings->primitive( 'long' ),
					),
				),
				'image'                 => $mappings->url_stored(),
				'shortcode_types'       => $mappings->keyword(),
				'embed'                 => $mappings->url(),
				'featured_image'        => $mappings->keyword(),
				'featured_image_url'    => $mappings->url(),
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
				//Comments

				'commenter_ids'         => $mappings->primitive( 'integer' ),
				'comment_count'         => $mappings->primitive_stored( 'integer' ),

				//////////////////////////////////
				//WP.com extras

				'blog'                      => $mappings->bloginfo(),
				'date_added'                => $mappings->datetime(),
				'liker_ids'                 => $mappings->primitive( 'integer' ),
				'like_count'                => $mappings->primitive_stored( 'short' ),
				'is_reblogged'              => $mappings->primitive( 'boolean' ),
				'reblogger_ids'             => $mappings->primitive( 'integer' ),
				'reblog_count'              => $mappings->primitive( 'short' ),
				'location'                  => $mappings->geo(),


				//////////////////////////////////
				//WooCommerce

				'wc' => array(
					'type' => 'object',
					'properties' => array(
						'percent_of_sales' => $mappings->primitive( 'float' ),
						'price' => $mappings->primitive_stored( 'float' ),
						'min_price' => $mappings->primitive( 'float' ),
						'max_price' => $mappings->primitive( 'float' ),
					),
				),

				//////////////////////////////////
				//Data Storage

				'title_html'                => $this->map_store_html(),
				'excerpt_html'              => $this->map_store_html(),
				'gravatar_url'              => $this->map_store_html(),

			)
		);

		//TODO correct urls with the latest stuff from global2 index

		return array(
			'post' => $post_mapping,
		);
	}

	public function get_doc_callbacks() {
		return array(
			'post' => 'Jetpack_Search_v2_Post_Doc_Builder',
		);
	}

}

class Jetpack_Search_v2_Post_Doc_Builder extends WPES_Abstract_Document_Builder {

	public function __construct() {
		$this->lang_builder = new WPES_Analyzer_Builder();
		$this->supported_langs = array_keys( $this->lang_builder->supported_languages );
	}

	protected $_indexable_statii = array(
		'publish',
		'trash',
		'pending',
		'draft',
		'future',
		'private',
	);

	public function get_id( $args ) {
		return $args['blog_id'] . '-p-' . $args['id'];
	}

	public function get_type( $args ) {
		return 'post';
	}

	public function get_parent_id( $args ) {
		return false;
	}

	public function get_routing_id( $args ) {
		//blogs with more than 100k posts will be spread across 10 shards
		$id_range = intval( $args['id'] / 100000 ) % 10;
		return $args['blog_id'] . '-' . $id_range;
	}

	public function doc( $args ) {
		//uses the extended version of WPES_WP_Post_Field_Builder() so it
		// also handles Jetpack sites and custom WP.com stuff such as likes
		$post_fld_bldr = new WPES_WPCOM_Post_Field_Builder();
		$post_fld_bldr->index_media = true;
		$post_fld_bldr->index_internal_links = true;

		switch_to_blog( $args['blog_id'] );

		$post = get_post( $args['id'] );
		if ( !$post ) {
			restore_current_blog();
			return false;
		}

		$blog = get_blog_details( $args['blog_id'] );

		$data = array(
			'blog_id'      => $post_fld_bldr->clean_int( $args['blog_id'], 'blog_id' ),
			'site_id'      => $post_fld_bldr->clean_short( $blog->site_id, 'site_id' ),
		);
		$lang_data = $post_fld_bldr->post_lang( $args['blog_id'], $post, true );

		//todo: move into this method or into the common field gen
		$post_data = $post_fld_bldr->post_fields( $post, $lang_data['langs'], false, true, true );

		$post_data = $this->jp_search_filter_taxonomies( $post_data );

		$post_data['public'] = $post_fld_bldr->is_post_public( $args['blog_id'], $post->ID );
		$url = $post_data['url'];
		$post_data['permalink'] = $post_fld_bldr->build_url_object( $url );
		unset( $post_data['url'] );

		$added_on_data = $post_fld_bldr->added_on( $post );

		$commenters_data = $post_fld_bldr->commenters( $args['blog_id'], $post );
		$reblog_data = $post_fld_bldr->reblogs( $args['blog_id'], $post );
		$likers_data = $post_fld_bldr->likers( $args['blog_id'], $post );
		$geo_data = $post_fld_bldr->geo( $post );

		$feat_img_data = $post_fld_bldr->featured_image( $post );
		$media_data = $post_fld_bldr->extract_media( $args['blog_id'], $post );

		$meta_data = $this->jp_search_meta( $post );

		//$faq_data = $this->faqs( $post->ID );

		$data['comments'] = $this->_comment_text( $args['blog_id'], $args['id'], $lang_data['langs'] );

		$all_content = $this->concat_all_content( array(
			'content' => $post_data['content']['default'],
			'title' => $post_data['title']['default'],
			'comments' => $data['comments']['default'],
			'url' => $url,
			'author' => $post_data['author'],
			'author_login' => $post_data['author_login'],
			'excerpt' => $post_data['excerpt']['default'],
			'meta_content' => $meta_data['meta_content'],
			'tags' => isset( $post_data['tag'] ) ? wp_list_pluck( wp_list_pluck( $post_data['tag'], 'name' ), 'default' ) : array(),
			'cats' => isset( $post_data['category'] ) ? wp_list_pluck( wp_list_pluck( $post_data['category'], 'name' ), 'default' ) : array(),
			//'faqs' => isset( $faq_data['faqs'] ) ? $faq_data['faqs'] : array(),
		) );
		$data['all_content'] = $this->_build_ml_field( $all_content, $lang_data['langs'] );
		unset( $meta_data['meta_content'] );

		$data['post_mime_type'] = $post_fld_bldr->clean_string( $post->post_mime_type );
		//$data['file'] = $post_fld_bldr->attached_files( $args['blog_id'], $post );
		//if ( empty( $data['file'] ) )
		//	unset( $data['file'] );

		$data['title_html'] = $this->_build_title_html( $args['blog_id'], $args['id'] );
		$data['excerpt_html'] = $this->_build_excerpt_html( $args['blog_id'], $args['id'] );
		$data['gravatar_url'] = $this->_build_gravatar_url( $post_data['author_id'] );

		$data = array_merge(
			$data,
			$lang_data,
			$post_data,
			//$faq_data,
			$added_on_data,
			$commenters_data,
			$reblog_data,
			$likers_data,
			$geo_data,
			$media_data,
			$feat_img_data,
			$meta_data
		);

		restore_current_blog();
		return $data;
	}

	public function get_coupled_docs( $args ) {
		switch_to_blog( $args['blog_id'] );
		$children = get_children( array(
			'post_parent' => $args['id'],
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => 'any',
		) );
		restore_current_blog();
		$children = array_keys( $children );

		if ( !empty( $children ) )
			return array( 'post' => $children );
		return false;
	}

	public function is_indexable( $args ) {
		if ( ! $this->is_indexing_enabled( $args ) )
			return false;

		//We index all post types and most all post statii
		switch_to_blog( $args['blog_id'] );

		$post = get_post( $args['id'] );
		if ( ! $post ) {
			restore_current_blog();
			return false;
		}

		if ( 'revision' == $post->post_type ) {
			restore_current_blog();
			return false;
		}

		$post_statii = $this->_get_allowed_statii();
		$post_status = get_post_status( $post->ID );
		if ( !in_array( $post_status, $post_statii ) ) {
			restore_current_blog();
			return false;
		}

		restore_current_blog();
		return true;
	}

	public function update( $args ) {
		$post_fld_bldr = new WPES_WPCOM_Post_Field_Builder();
		return $post_fld_bldr->get_update_script( $args );
	}

	public function get_doc_iterator( $args ) {
		$it = new WPES_WP_Post_Iterator();
		$it->init( array(
			'blog_id' => $args['blog_id'],
			'sql_where' => $this->_get_allowed_statii( true ),
			'doc_builder' => $this,
			'start' => $args['start'],
		) );
		return $it;
	}

	//TODO: move into the post field builder
	protected function faqs( $post_id ) {
		$fld_bldr = new WPES_WP_Post_Field_Builder();
		$faq_meta = get_post_meta( $post_id, 'faqs' );
		if ( empty( $faq_meta ) ) {
			return array();
		}
		if ( ! is_array( $faq_meta ) ) {
			return array();
		}

		$questions = array();
		foreach( $faq_meta as $m ) {
			$questions[] = $fld_bldr->clean_string( $m, 5000 );
		}

		return array( 'faqs' => $questions );
	}

	protected function concat_all_content( $args ) {
		$fld_bldr = new WPES_WP_Post_Field_Builder();

		$all_content = '';
		$all_content .= $fld_bldr->clean_string( $args['title'], 5000 ) . "\n\n";
		if ( !empty( $args['tags'] ) ) {
			$all_content .= $fld_bldr->clean_string( implode( ' ', $args['tags'] ), 5000 ) . "\n";
		}
		if ( !empty( $args['cats'] ) ) {
			$all_content .= $fld_bldr->clean_string( implode( ' ', $args['cats'] ), 5000 ) . "\n";
		}

		//TODO: add all taxonomies?

		if ( $args['author'] ) {
			$all_content .= $fld_bldr->clean_string( $args['author'], 5000 ) . "\n\n";
		}
		if ( $args['author_login'] ) {
			$all_content .= $fld_bldr->clean_string( $args['author_login'], 5000 ) . "\n\n";
		}

		if ( $args['url'] ) {
			$all_content .= $fld_bldr->expand_url_for_engrams( $args['url'] ) . "\n\n";
		}

		//TODO: add all links?

		if ( !empty( $args['meta_content'] ) ) {
			$all_content .= $fld_bldr->clean_string( $args['meta_content'], 5000 ) . "\n\n";
		}

		if ( !empty( $args['faqs'] ) ) {
			foreach( $args['faqs'] as $q ) {
				$all_content .= $fld_bldr->clean_string( $q, 5000 ) . "\n\n";
			}
		}

		//2500 words in Mongolian (ave 12 chars per word), English is 3600 words
		// this field also gets used for search as you type matching
		$all_content .= $fld_bldr->clean_string( $args['content'], 30000 ) . "\n\n";
		$all_content .= $fld_bldr->clean_string( $args['excerpt'], 5000 ) . "\n\n";

		if ( ! empty( $args['comments'] ) ) {
			foreach( $args['comments'] as $c ) {
				$all_content .= $fld_bldr->clean_string( $c, 5000 ) . ' ';
			}
		}

		//TODO: extract product and other names, e.g. CamelCase

		return $all_content;
	}

	protected function _build_ml_field( $content, $langs ) {
		$fld = array();
		foreach ( array_keys( $langs ) as $lang ) {
			$lang = substr( $lang, 0, 2 ); // lang-detect sometimes outputs en-gb or pt-br. We only want en or pt
			if ( in_array( $lang, $this->supported_langs ) ) {
				$fld[$lang] = $content;
			}
			$fld['default'] = $content;
		}
		return $fld;
	}

	protected function _get_allowed_statii( $as_sql_in = false ) {
		if ( $as_sql_in ) {
			// Live dangerously
			return " post_status IN ( '" . implode( "','", $this->_indexable_statii ) .  "' ) ";
		} else {
			return $this->_indexable_statii;
		}
	}

	protected function _build_title_html( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );
		$t = get_the_title( $post_id );
		restore_current_blog();
		return $t;
	}

	protected function _build_excerpt_html( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );
		$e = get_the_excerpt( $post_id );
		restore_current_blog();
		return $e;
	}

	protected function _build_gravatar_url( $user_id ) {
		if ( has_gravatar( $user_id ) ) {
			$resp = wpcom_get_avatar_url( $user_id );
			if ( is_array( $resp ) ) {
				$url = strtok( $resp[0], '?' ); //remove all sizing info
				return $url;
			}
		}
		return null;
	}

	protected function _comment_text( $blog_id, $post_id, $post_langs ) {
		switch_to_blog( $blog_id );
		$comments = get_comments( [
			'post_id' => $post_id,
			'number' => 100,
		] );
		restore_current_blog();

		if ( empty( $comments ) ) {
			return [];
		}

		$comment_data = [ 'default' => [] ];
		$mappings = new WPES_WP_Mappings();
		$lang_analyzers = $mappings->get_all_lang_analyzers();
		$langs = [ 'default' ];
		foreach( array_keys( $post_langs ) as $l ) {
			$lang = substr( $l, 0, 2 );
			if ( isset( $lang_analyzers[$lang] ) ) {
				$langs[] = $lang;
				$comment_data[$lang] = [];
			}
		}

		$fld_bldr = new WPES_WP_Post_Field_Builder();
		foreach( $comments as $c ) {
			$txt = $fld_bldr->clean_string( $c->comment_content );
			foreach( $langs as $l ) {
				$comment_data[$l][] = $txt;
			}
		}

		return $comment_data;
	}

	var $meta2method = [
		'wc.percent_of_sales' => 'wc_perc_sales',
		'wc.price' => 'wc_max_price',       //possibly needs better implementation
		//'wc.min_price' => 'wc_min_price', //needs more testing
		'wc.max_price' => 'wc_max_price',
	];

	protected function jp_search_meta( $post ) {
		require_once( ABSPATH . 'wp-content/mu-plugins/jetpack/sync/class.jetpack-sync-module-search.php' );

		$data = array();

		$meta = get_post_meta( $post->ID );
		if ( ! empty( $meta ) ) {
			$fld_bldr = new WPES_WP_Post_Field_Builder();
			$data['meta'] = array();
			$data['meta_content'] = '';

			foreach ( $meta as $key => $v ) {
				if ( ! isset( Jetpack_Sync_Module_Search::public_postmeta[ $key ] ) ) {
					continue;
				}

				if ( is_array( $v ) ) {
					 //take only the first value as if $single==true in get_post_meta()
					$unserialized = maybe_unserialize( $v[0] );
				} else {
					$unserialized = maybe_unserialize( $v );
				}

				if ( is_array( $unserialized ) && $fld_bldr->is_assoc_array( $unserialized ) ) {
					//can't really index this
					continue;
				}

				if ( is_object( $unserialized ) ) {
					//can't really index this
					continue;
				}

				if ( is_array( $unserialized ) && $fld_bldr->is_multi_dim_array( $unserialized ) ) {
					//can't really index this
					continue;
				}

				if ( ! is_array( $unserialized ) ) {
					//standardize a bit
					$unserialized = array( $unserialized );
				}

				$def = Jetpack_Sync_Module_Search::public_postmeta[$key];

				//create standard meta fields
				if ( ( ! isset( $def['available'] ) ) || $def['available'] ) {
					$clean_key = $fld_bldr->clean_object( $key );
					$data['meta'][$clean_key] = array();
					foreach ( $unserialized as $val ) {
						$v = $fld_bldr->clean_string( (string) $val, 4096 ); // limit at 4KB
						if ( '' != $v ) {
							$data['meta'][$clean_key]['value'][] = $v;
							if ( isset( Jetpack_Sync_Module_Search::public_postmeta[$key]['searchable_in_all_content'] ) ) {
								$data['meta_content'] .= ' ' . $v;
							}
						}

						if ( is_numeric( $val ) ) {
							$v = $fld_bldr->clean_long( (int) $val, 'meta.' . $clean_key . '.long' );
							if ( ! is_null( $v ) ) {
								$data['meta'][$clean_key]['long'][] = $v;
							}
						}

						if ( is_numeric( $val ) ) {
							$v = $fld_bldr->clean_float( $val );
							if ( ! is_null( $v ) ) {
								$data['meta'][$clean_key]['double'][] = $v;
							}
						}

						if ( is_string( $val ) ) {
							$v = $fld_bldr->clean_date( $val );
							if ( '1970-01-01 00:00:00' != $v ) {
								$data['meta'][$clean_key]['date'][] = $v;
							}
						}

						$v = null;
						if ( is_bool( $val ) ) {
							$v = $val;
						} elseif (
							( "false" === strtolower( $val ) ) ||
							( "null" === strtolower( $val ) ) ||
							( "no" === strtolower( $val ) ) ||
							( "0" === $val ) ||
							( is_numeric( $val ) && ( $val == 0 ) ) ||
							( false === $val ) ||
							( null === $val ) ||
							( "" === $val )
						) {
							$v = false;
						} elseif (
							( "true" === strtolower( $val ) ) ||
							( "yes" === strtolower( $val ) ) ||
							( "1" === $val ) ||
							( is_numeric( $val ) && ( $val > 0 ) ) ||
							( true === $val )
						) {
							$v = true;
						}
						if ( ! is_null( $v ) ) {
							$data['meta'][$clean_key]['boolean'][] = $v;
						}
					}

					if ( empty( $data['meta'][$clean_key] ) ) {
						unset( $data['meta'][$clean_key] );
					}

				}

				//create alternative meta fields based on definitions
				if ( isset( $def['alternatives'] ) ) {
					foreach( $def['alternatives'] as $f ) {
						if ( isset( $this->meta2method[$f] ) ) {
							$method = $this->meta2method[$f];
							$v = $this->$method( $unserialized, $post );
							if ( null !== $v ) {
								//we have to create the
								$path = explode( '.', $f );
								$ref =& $data;
								foreach( $path as $p ) {
									if ( ! is_array( $ref[$p] ) ) {
										$ref[$p] = [];
									}
									$ref =& $ref[$p];
								}
								$ref = $v;
							}
						}
					}
				}

			}
		}
		return $data;
	}

	protected function wc_perc_sales( $meta_val, $post ) {
		global $wpdb;
		static $total_orders = null;

		if ( null === $total_orders ) {
			$total_orders = (int) $wpdb->get_var( "SELECT count(*) FROM $wpdb->posts WHERE post_type='shop_order'");
		}
		if ( $total_orders == 0 ) {
			return null;
		}

		if ( is_array( $meta_val ) ) {
			$v = $meta_val[0];
		} else {
			$v = $meta_val;
		}

		$fld_bldr = new WPES_WP_Post_Field_Builder();
		return $fld_bldr->clean_float( $v / $total_orders );
	}

	protected function wc_min_price( $meta_val, $post ) {
		$fld_bldr = new WPES_WP_Post_Field_Builder();
		if ( is_array( $meta_val ) ) {
			$v = $fld_bldr->clean_float( $meta_val[0] );
		} else {
			$v = $fld_bldr->clean_float( $meta_val );
		}
		if ( is_null( $v ) ) {
			return null;
		}
		return $v;
	}

	protected function wc_max_price( $meta_val, $post ) {
		$fld_bldr = new WPES_WP_Post_Field_Builder();
		if ( is_array( $meta_val ) ) {
			$v = $fld_bldr->clean_float( $meta_val[0] );
		} else {
			$v = $fld_bldr->clean_float( $meta_val );
		}
		if ( is_null( $v ) ) {
			return null;
		}
		return $v;
	}

	protected function jp_search_filter_taxonomies( $post_data ) {
		require_once( ABSPATH . 'wp-content/mu-plugins/jetpack/sync/class.jetpack-sync-module-search.php' );

		if ( ! isset( $post_data['taxonomy'] ) ) {
			return $post_data;
		}

		//1k+ taxonomies so let's make it faster to do the lookups
		static $slug2bool = null;
		if ( is_null( $slug2bool ) ) {
			$slug2bool = [];
			foreach( Jetpack_Sync_Module_Search::taxonomies as $slug ) {
				$slug2bool[$slug] = true;
			}
		}

		foreach( $post_data['taxonomy'] as $slug => $obj ) {
			if ( ! isset( $slug2bool[$slug] ) ) {
				unset( $post_data['taxonomy'][$slug] );
			}
		}
		if ( empty( $post_data['taxonomy'] ) ) {
			unset( $post_data['taxonomy'] );
		}

		return $post_data;
	}

	//Expand how we index authors
	// - Handle authors which don't have wp.com accounts, data in post meta
	// - co-authors-plus stores author data in the author taxonomy
	//   - also has guest users in custom post types
	// - Also can be authors in author or author_name post meta
	protected function jp_search_filter_authors( $post_data, $post ) {
		//TODO: test
		if ( $post_data['author_id'] == 0 ) {
			$post_data = $this->add_wporg_author( $post_data, $post->ID );
		}

		$ext_id = get_post_meta( $post->ID, '_jetpack_post_author_external_id' );
		if ( ! empty( $ext_id ) ) {
			$post_data['author_ext_id'] = $this->clean_int( $ext_id, 'author_ext_id' );
		}

		//convert all author fields to be arrays so we can support multiple authors
		$post_data['author'] = [ $post_data['author'] ];
		$post_data['author_login'] = [ $post_data['author_login'] ];
		$post_data['author_id'] = [ $post_data['author_id'] ];
		$post_data['author_ext_id'] = [ $post_data['author_ext_id'] ];

		$post_data = $this->add_cap_author( $post_data, $post );
		$post_data = $this->add_other_author( $post_data, $post );
		return $post_data;
	}

	protected function cap_add_author( $data, $slug ) {
		$cap_tax = 'author';
		//$cap_cpt = 'guest-author';

		if ( isset( $data['taxonomy'] ) && isset( $data['taxonomy'][$cap_tax] ) ) {
			foreach( $data['taxonomy'][$cap_tax]['slug'] as $cap_slug ) {
				//$coauthor_slug = preg_replace( '#^cap\-#', '', $cap_slug );
				//TODO: implement me
			}
		}

		return $data;
	}

	protected function add_other_author( $data, $post_id ) {
		$post_meta_fields = [
			//'author', //???
			'author_name',
		];
		foreach( $post_meta_fields as $f ) {
			$author = get_post_meta( $post_id, $f );
			if ( ! empty( $author ) ) {
				if ( ! in_array( $author, $data['author'] ) ) {
					$data['author'][] = $this->clean_string( $author );
				}
			}
		}

		return $data;
	}

	protected function add_wporg_author( $post_data, $post_id ) {
		$author = get_post_meta( $post_id, '_jetpack_author' );
		if ( ! empty( $author ) ) {
			$post_data['author'] = $this->clean_string( $author );
			$post_data['author_login'] = '';
			$post_data['author_id'] = 0;
		}

		return $post_data;
	}


	protected function get_post_stats( $blog_id, $post_id ) {
		$stats_data = [];
		if ( $post_id <= 0 ) {
			return [];
		}

		static $total_last_30_days = null;
		if ( is_null( $total_last_30_days ) ) {
			$end_date = false;
			$days = 30;
			$history = stats_get_daily_history( false, $blog_id, 'views', null, $end_date, $days );
			$total_last_30_days = array_sum( $history );
		}

		$end_date = false;
		$num_days = 30;
		$history = stats_get_postviews_summary( $blog_id, $end_date, $num_days, "AND post_id = '$post_id'", 0, false );

		$total_post_last_30_days = 0; //TODO
		$total_post_last_7_days = 0; //TODO

		$stats_data['stats']['30_day_views_ratio'] = $total_post_last_30_days / $total_last_30_days;
		$stats_data['stats']['7_day_views_ratio'] = $total_post_last_7_days / $total_last_30_days;
		$stats_data['last_index_date'] = date( 'Y-m-d H:i:s' );

		return $stats_data;
	}


	//experimental version of clean string to handle stripping html better
	//TODO: fix me and try using this
	protected function _clean_string( $content, $truncate_at = 100000 ) {

		static $fld_bldr = null;
		if ( empty( $fld_bldr ) ) {
			$fld_bldr = new WPES_WP_Post_Field_Builder();
		}

		//convert content to utf-8 because non-utf8 chars will cause json_encode() to return null!
		$clean_content = $fld_bldr->convert_to_utf8( $content );

		//ensure that tags between content are treated as spaces.
		// may be overly aggressive
		$clean_content = strip_tags( str_replace( '<', ' <', $clean_content ) );

		$clean_content = html_entity_decode( $clean_content );

		if ( 0 < $truncate_at && mb_strlen( $clean_content ) > $truncate_at ) {
			$clean_content = mb_substr( $clean_content, 0, $truncate_at );
		}

		// strip any remaining bad characters
		$clean_content = preg_replace( WPES_WP_Post_Field_Builder::$strip_bad_utf8_regex, '$1', $clean_content );

		// turn some utf characters into spaces
		$clean_content = preg_replace( '/[\xA6\xBA\xA7]/u', ' ', $clean_content );

		return $clean_content;
	}

}
