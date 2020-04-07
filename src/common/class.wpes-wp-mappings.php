<?php

class WPES_WP_Mappings {

	/**
	 *  These mappings define a couple standard types of data structures used
	 *  in our ES documents. The first part of the function name before the
	 *  first `_` is the name of the custom data type. For example `keyword` or
	 *  `datetimetoken`. After the first `_` are decorators for example
	 *  `stored` for stored fields or `count` for fields which should be an
	 *  object with token counts in a nested field. Multiple decorators can be
	 *  used but must be written alphabetically if they are, for example
	 *  `text_count_raw`. Prefer new types or named decorators over parameters
	 *  as much as possible to make it easier to grep code.
	 */

	private $_Analyzer_Builder;

	private $_is_es5plus;

	public function __construct( $_is_es5plus = false ) {
		$this->_is_es5plus = (bool)$_is_es5plus;
		$this->_Analyzer_Builder = new WPES_Analyzer_Builder();
	}

	/**
	 * Start Depreciated Types
	 * These are here mostly for backwards compatibility please don't use
	 * unless you have to.
	 */

	// DO NOT USE : Inconsistent naming
	// REPLACEMENT: text_engram()
	public function text_ngramedge() {
		return $this->text_engram();
	}

	// DO NOT USE : Inconsistent naming
	// REPLACEMENT: text_ml_engram()
	public function text_ml_edgengram( $fieldname ) {
		return $this->text_ml_engram( $fieldname );
	}

	// DO NOT USE : Results in mapping with unexpected search behavior because
	//              root element is analyzed making something like
	//              'jetpack.com' and 'jetpacks.com' appear to be the same
	//              thing.
	// REPLACEMENT: url()
	public function url_analyzed() {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'analyzed' => $this->text(),
			'raw' => $this->keyword(),
			'url' => $this->keyword(),
		);

		return array(
			'type' => 'object',
			'properties' => array(
				'url' => $mapping,
				'host' => $this->keyword(),
				'host_reversed' => $this->keyword(),
			)
		);
	}

	// DO NOT USE : This only mirrors an auto detected string field generated
	//              for missing mappings this is used to match up our mappings
	//              to previous mistakes.
	// REPLACEMENT: NONE
	public function text_autodetected() {
		return array(
			'type' => 'string',
		);
	}

	/**
	 * End Depreciated Types
	 */

	public function dynamic() {
		return array(
			'dynamic' => 'true',
			'properties' => new stdClass(),
		);
	}

	public function primitive( $type ) {
		switch ( $type ) {
			case 'boolean':
			case 'byte':
			case 'short':
			case 'integer':
			case 'ip':
			case 'long':
			case 'float':
			case 'double':
			case 'date':
				return array(
					'type' => $type,
				);
			default:
				return $this->keyword();
		}
	}

	public function primitive_stored( $type ) {
		return $this->store( $this->primitive( $type ) );
	}

	public function timestamp( $type = 'millis' ) {
		switch ( $type ) {
			case 'iso':
				return array(
					'type' => 'date',
					'format' => 'strict_date_time', // yyyy-MM-dd'T'HH:mm:ss.SSSZZ
				);
			case 'millis':
			default:
				return array(
					'type' => 'date',
					'format' => 'epoch_millis',
				);
		}
	}

	public function datetime() {
		return array(
			'type' => 'date',
			'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd',
		);
	}

	public function datetime_stored() {
		return $this->store( $this->datetime() );
	}

	public function datetimetoken() {
		return array(
			'type' => 'object',
			'properties' => array(
				'year' => array(
					'type' => 'short',
				),
				'month' => array(
					'type' => 'byte',
				),
				'day' => array(
					'type' => 'byte',
				),
				'day_of_week' => array(
					'type' => 'byte',
				),
				'week_of_year' => array(
					'type' => 'byte',
				),
				'day_of_year' => array(
					'type' => 'short',
				),
				'hour' => array(
					'type' => 'byte',
				),
				'minute' => array(
					'type' => 'byte',
				),
				'second' => array(
					'type' => 'byte',
				),
				'seconds_from_day' => array(
					'type' => 'integer',
				),
				'seconds_from_hour' => array(
					'type' => 'short',
				),
			),
		);
	}

	public function geo() {
		$mapping = array(
			'type' => 'geo_point',
		);

		if ( !$this->_is_es5plus ) {
			$mapping['lat_lon'] = true;
			$mapping['geohash'] = true;
		}

		return $mapping;
	}

	public function keyword() {
		if ( $this->_is_es5plus ) {
			return array(
				'type' => 'keyword',
				'ignore_above' => 1024,
			);
		}
		return array(
			'type' => 'string',
			'index' => 'not_analyzed',
		);
	}

	public function keyword_stored() {
		return $this->store( $this->keyword() );
	}

	public function keyword_lcase() {
		if ( $this->_is_es5plus ) {
			return array(
				'type' => 'keyword',
				'ignore_above' => 1024,
				'normalizer' => 'lowercase_normalizer',
			);
		}
		return array(
			'type' => 'string',
			'index' => 'analyzed',
			'analyzer' => 'lowercase_analyzer',
		);
	}

	public function keyword_plus_lcase() {
		$mapping = $this->keyword();
		$mapping['fields'] = array(
			'lc' => $this->keyword_lcase(),
		);
		return $mapping;
	}

	public function tagcat( $fieldname ) {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'raw' => $this->keyword(),
			'raw_lc' => $this->keyword_lcase(),
		);

		return array(
			'type' => 'object',
			'properties' => array(
				'name' => $mapping,
				'slug' => $this->keyword(),
				'term_id' => $this->primitive( 'long' ),
			),
		);
	}

	public function tagcat_ml( $fieldname, $lang_analyzers = [] ) {
		if ( empty( $lang_analyzers ) ) {
			$lang_analyzers = $this->get_all_lang_analyzers();
		}

		$mapping = [
			'type' => 'object',
			'properties' => [
				'name' => [
					'type' => 'object',
					'properties' => [
						'default' => $this->text(),
					],
				],
				'slug' => $this->keyword(),
				'term_id' => $this->primitive( 'long' ),
			],
		];

		$mapping['properties']['name']['properties'] = array_merge(
			$mapping['properties']['name']['properties'],
			$this->ml_wrapped_field( $lang_analyzers, function( $mappings, $lang ) {
				return $mappings->text();
			} )
		);

		return $mapping;
	}

	public function taxonomy_ml( $fieldname, $lang_analyzers = [] ) {
		if ( empty( $lang_analyzers ) ) {
			$lang_analyzers = $this->get_all_lang_analyzers();
		}

		$mapping = [
			'type' => 'object',
			'properties' => [
				'type' => $this->keyword(),
				'name' => [
					'type' => 'object',
					'properties' => [
						'default' => $this->text(),
					],
				],
				'slug' => $this->keyword(),
				'term_id' => $this->primitive( 'long' ),
			]
		];

		$mapping['properties']['name']['properties'] = array_merge(
			$mapping['properties']['name']['properties'],
			$this->ml_wrapped_field( $lang_analyzers, function( $mappings, $lang ) {
				return $mappings->text();
			} )
		);

		return $mapping;
	}

	public function text() {
		if ( $this->_is_es5plus ) {
			return array(
				'type' => 'text',
				'similarity' => 'BM25',
			);
		}
		return array(
			'type' => 'string',
			'index' => 'analyzed',
			'similarity' => 'BM25',
		);
	}

	public function text_stored() {
		return $this->store( $this->text() );
	}

	public function text_raw( $fieldname ) {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'raw' => $this->keyword(),
		);
		return $mapping;
	}

	public function text_raw_stored( $fieldname ) {
		return $this->store( $this->text_raw( $fieldname ) );
	}

	public function text_count( $fieldname ) {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'word_count' => array(
				'type' => 'token_count',
				'analyzer' => 'default',
			),
		);
		return $mapping;
	}

	public function text_lcase_raw( $fieldname ) {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'raw' => $this->keyword(),
			'raw_lc' => $this->keyword_lcase(),
		);
		return $mapping;
	}

	public function text_ml( $fieldname, $lang_analyzers = [] ) {
		if ( empty( $lang_analyzers ) ) {
			$lang_analyzers = $this->get_all_lang_analyzers();
		}

		$mapping = [
			'type' => 'object',
			'properties' => [
				'default' => $this->text_count( $fieldname ),
			],
		];

		$mapping['properties'] = array_merge(
			$mapping['properties'],
			$this->ml_wrapped_field( $lang_analyzers, function( $mappings, $lang, $analyzer_name ) {
				$subfield_mappings = $mappings->text_count( $lang );
				$subfield_mappings['fields']['word_count']['analyzer'] = $analyzer_name;
				return $subfield_mappings;
			} )
		);

		return $mapping;
	}

	public function text_ml_phrases_highlights( $fieldname, $lang_analyzers = [] ) {
		if ( empty( $lang_analyzers ) ) {
			$lang_analyzers = $this->get_all_lang_analyzers();
		}

		// enables term vectors and offsets for all fields
		//  this can then speed up phrase search and make highlighting results
		//  from this field much faster and also possible inside of aggregations
		$mapping = $this->text_ml( $fieldname, $lang_analyzers );
		$mapping['properties']['default']['term_vector'] = 'with_positions_offsets';

		$lang_mappings = $this->ml_wrapped_field( $lang_analyzers, function( $mappings, $lang, $analyzer_name ) {
			$subfield_mappings = $mappings->text_count( $lang );
			$subfield_mappings['fields']['word_count']['analyzer'] = $analyzer_name;
			$subfield_mappings['term_vector'] = 'with_positions_offsets';
			return $subfield_mappings;
		} );
		$mapping['properties'] = array_merge( $mapping['properties'], $lang_mappings );

		return $mapping;
	}

	public function text_ngram() {
		$mapping = $this->text();
		$mapping['analyzer'] = 'ngram_analyzer';
		$mapping['term_vector'] = 'with_positions_offsets';
		return $mapping;
	}

	public function text_engram() {
		$mapping = $this->text();
		$mapping['analyzer'] = 'edgengram_analyzer';
		$mapping['search_analyzer'] = 'default';
		$mapping['term_vector'] = 'with_positions_offsets';
		return $mapping;
	}

	public function text_plus_ngram( $fieldname ) {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'raw' => $this->keyword(),
			'ngram' => $this->text_ngram(),
		);
		return $mapping;
	}

	public function long_text_engram( $fieldname ) {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'engram' => $this->text_engram(),
		);
		return $mapping;
	}

	public function text_plus_engram( $fieldname ) {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'raw' => $this->keyword(),
			'engram' => $this->text_engram(),
		);
		return $mapping;
	}

	public function text_plus_ngram_engram( $fieldname ) {
		$mapping = $this->text();
		$mapping['fields'] = array(
			'raw' => $this->keyword(),
			'ngram' => $this->text_ngram(),
			'engram' => $this->text_engram(),
		);
		return $mapping;
	}

	public function text_ml_engram( $fieldname ) {
		//only add the edgengrams to the default field since
		// the tokenizer is the same for all langs

		//Add positions and offsets to the ngrams so we can
		// do faster optimistic phrase searches
		$mapping = $this->text_ml( $fieldname );
		$mapping['properties']['default']['fields']['engram'] = $this->text_engram();
		return $mapping;
	}

	public function text_ml_ngram_engram( $fieldname ) {
		//only add the edgengrams to the default field since
		// the tokenizer is the same for all langs

		//Add positions and offsets to the ngrams so we can
		// do faster optimistic phrase searches
		$mapping = $this->text_ml( $fieldname );
		$mapping['properties']['default']['fields']['ngram'] = $this->text_ngram();
		$mapping['properties']['default']['fields']['engram'] = $this->text_engram();
		return $mapping;
	}

	public function url() {
		$mapping = $this->keyword();
		$mapping['fields'] = array(
			'analyzed' => $this->text(),
			'raw' => $this->keyword(),
		);

		return array(
			'type' => 'object',
			'properties' => array(
				'url' => $mapping,
				'host' => $this->keyword(),
				'host_reversed' => $this->keyword(),
			)
		);
	}

	public function url_stored() {
		$mapping = $this->url();
		$mapping['properties']['url']['fields']['raw']['store'] = true;
		return $mapping;
	}

	public function url_ngram() {
		$mapping = $this->url();
		$mapping['properties']['url']['fields']['ngram'] = $this->text_ngram();
		return $mapping;
	}

	public function url_ngram_engram() {
		$mapping = $this->url();
		$mapping['properties']['url']['fields']['ngram'] = $this->text_ngram();
		$mapping['properties']['url']['fields']['engram'] = $this->text_engram();
		return $mapping;
	}

	public function completion() {
		return [ 'type' => 'completion' ];
	}

	public function completion_ml( $analyzers ) {
		$wrapped_field = $this->ml_wrapped_field( $analyzers, function( $mappings, $lang ) {
			return $mappings->completion();
		} );
		return [ 'properties' => $wrapped_field ];
	}

	public function file_attachment() {
		$children = array(
			'content'        => $this->text(),
			'title'          => $this->text_stored(),
			'name'           => $this->text_raw_stored( 'name' ),
			'date'           => $this->primitive_stored( 'date' ),
			'author'         => $this->text_raw_stored( 'author' ),
			'keywords'       => $this->text_raw_stored( 'keywords' ),
			'content_type'   => $this->text_raw_stored( 'content_type' ),
			'content_length' => $this->primitive_stored( 'long' ),
			'language'       => $this->keyword_stored(),
		);

		if ( $this->_is_es5plus ) {
			return array(
				'type' => 'object',
				'properties' => $children,
			);
		}
		return array(
			'type' => 'attachment',
			'fields' => $children,
		);
	}

	public function keyword_value_object( $fieldname, $keyword, $value ) {
		return array(
			'type' => 'object',
			'properties' => array(
				$keyword => $this->keyword(),
				$value => $this->primitive( 'double' )
			),
		);
	}

	protected function ml_wrapped_field( $lang_analyzers, $subfield_mapping_builder ) {
		$field_mappings = [];
		foreach ( $lang_analyzers as $lang => $analyzer ) {
			$subfield_mappings = $subfield_mapping_builder( $this, $lang, $analyzer['name'] );
			// $subfield_mappings = $this->$field_type( $lang );
			$subfield_mappings['analyzer'] = $analyzer['name'];
			$field_mappings[$lang] = $subfield_mappings;
		}
		return $field_mappings;
	}

	protected function store( array $mapping ) {
		$mapping['store'] = true;
		return $mapping;
	}

	public function get_all_lang_analyzers() {
		return array_filter( $this->_Analyzer_Builder->supported_languages, function ( $analyzer, $lang ) {
			// Filter-out all non-lang analyzers: `lowercase`, `ngram`, ...
			return 2 === strlen( $lang );
		}, ARRAY_FILTER_USE_BOTH );
	}

	public function get_filtered_lang_analyzers( $filtered_langs = [] ) {
		return array_filter( $this->get_all_lang_analyzers(), function( $analyzer, $lang ) use ( $filtered_langs ) {
			return in_array( $lang, $filtered_langs );
		}, ARRAY_FILTER_USE_BOTH );
	}
}
