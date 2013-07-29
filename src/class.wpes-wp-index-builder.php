<?php

require_once dirname( __FILE__ ) . '/class.wpes-abstract-index-builder.php';

class WPES_WP_Index_Builder extends WPES_Abstract_Index_Builder {

	public function __constructor() {
		$this->doctype2builder['post'] = 'WPES_WP_Post_Document_Builder';
	}

	// Parameters
	//  $args['number_of_shards']
	//  $args['number_of_replicas']
	public function get_settings( $args = array() ) {
		$lang_builder = ES_WPCOM_Analyzer_Builder::init();
		$analyzers = $lang_builder->build_analyzers();

		$settings = array(
			'number_of_shards' => isset( $args['number_of_shards'] ) ? $args['number_of_shards'] : 2,
			'number_of_replicas' => isset( $args['number_of_replicas'] ) ? $args['number_of_replicas'] : 1,
	 		'analysis' => $analyzers,
		);
		//add lowercase keyword analyzer
		$settings['analysis']['analyzer']['wp_raw_lowercase_analyzer'] = array(
			'type' => 'custom',
			'tokenizer' => 'keyword',
			'filter' => array( 'lowercase' ),
		);

		return $settings;
	}

	public function get_mappings( $args = array() ) {
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
								'analyzer' => 'wp_raw_lowercase_analyzer',
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
			) ) )
		);
	
		//same mapping for both pages, posts, all custom post types
		$post_mapping = array(
			'dynamic_templates' => $dynamic_post_templates,
			'_all' => array( 'enabled' => false ),
			'_analyzer' => array( 'path' => 'lang_analyzer' ),
			'properties' => array(
		
				//////////////////////////////////
				//Blog/Post meta fields
		
				'post_id' => array( 
					'type' => 'long', 
					'store' => true
				),
				'blog_id' => array( 
					'type' => 'integer',
					'store' => true
				),
				'site_id' => array( 
					'type' => 'short',
				),
				'post_type' => array( 
					'type' => 'string', 
					'index' => 'not_analyzed' 
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
					'type' => 'string', 
					'index' => 'not_analyzed' 
				),
				'date' => array( 
					'type' => 'date',
					'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'
				),
				'date_gmt'  => array( 
					'type' => 'date',
					'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'
				),
				'date_added' => array( 
					'type' => 'date',
					'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'
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
					'type' => 'string', 
					'index' => 'analyzed', 
					'similarity' => 'BM25',
				),
				'content'  => array( 
					'type' => 'string', 
					'index' => 'analyzed', 
					'similarity' => 'BM25',
				),
				'content_word_count' => array( 
					'type' => 'integer',
				),
				'excerpt'  => array( 
					'type' => 'string', 
					'index' => 'analyzed', 
					'similarity' => 'BM25',
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
									'analyzer' => 'wp_raw_lowercase_analyzer',
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
									'analyzer' => 'wp_raw_lowercase_analyzer',
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
		
				'commenter_ids' => array(
					'type' => 'integer', 
				),
				'comment_count' => array(
					'type' => 'integer',
				)
			)
		);

		$mappings = array(
			'post' => $post_mapping
		);

		return $mappings;
	}

}

