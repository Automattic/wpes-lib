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

	public function __construct() {
		$this->_Analyzer_Builder = new WPES_Analyzer_Builder();
	}

	public function primitive( $type ) {
		switch ( $type ) {
			case 'boolean':
			case 'byte':
			case 'short':
			case 'integer':
			case 'long':
			case 'float':
			case 'double':
				return array(
					'type' => $type,
				);
			default:
				return $this->keyword();
		}
	}

	public function primitive_stored( $type ) {
		return $this->_store( $this->primitive( $type ) );
	}

	public function datetime() {
		return array(
			'type' => 'date',
			'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd',
			'doc_values' => 'yes',
		);
	}

	public function datetime_stored() {
		return $this->_store( $this->datetime() );
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
		return array(
			'type' => 'geo_point',
			'lat_lon' => true,
			'geohash' => true,
		);
	}

	public function keyword() {
		return array(
			'type' => 'string',
			'index' => 'not_analyzed',
		);
	}

	public function keyword_lcase() {
		return array(
			'type' => 'string',
			'index' => 'analyzed',
			'analyzer' => 'lowercase_analyzer',
		);
	}

	public function tagcat( $fieldname ) {
		return array(
			'type' => 'object',
			'properties' => array(
				'name' => array(
					'type' => 'multi_field',
					'fields' => array(
						'name' => array(
							'type' => 'string',
							'index' => 'analyzed',
							'index_name' => $fieldname,
							'similarity' => 'BM25',
						),
						'raw' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
							'index_name' => "{$fieldname}.raw",
						),
						'raw_lc' => array(
							'type' => 'string',
							'index' => 'analyzed',
							'analyzer' => 'lowercase_analyzer',
							'index_name' => "{$fieldname}.raw_lc",
						),
					),
				),
				'slug' => $this->keyword(),
				'term_id' => $this->primitive( 'long' ),
			),
		);
	}

	public function tagcat_ml( $fieldname ) {
		$mapping = array(
			'type' => 'object',
			'properties' => array(
				'name' => array(
					'type' => 'object',
					'properties' => array(
						'default' => array(
							'type' => 'string',
							'index' => 'analyzed',
							'analyzer' => 'default',
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
			)
		);

		$mapping['properties']['name']['properties'] = $this->_ml( $mapping['properties']['name']['properties'] );

		return $mapping;
	}

	public function text() {
		return array(
			'type' => 'string',
			'index' => 'analyzed',
			'similarity' => 'BM25',
		);
	}

	public function text_count( $fieldname ) {
		return array(
			'type' => 'multi_field',
			'fields' => array(
				"$fieldname" => array(
					'type' => 'string',
					'index' => 'analyzed',
					'similarity' => 'BM25',
				),
				'word_count' => array(
					'type' => 'token_count',
					'analyzer' => 'default',
				),
			),
		);
	}

	public function text_lcase_raw( $fieldname ) {
		return array(
			'type' => 'multi_field',
			'fields' => array(
				$fieldname => array(
					'type' => 'string',
					'index' => 'analyzed',
				),
				'raw' => $this->keyword(),
				'raw_lc' => $this->keyword_lcase(),
			)
		);
	}

	public function text_ml( $fieldname ) {
		$mapping = array(
			'type' => 'object',
			'properties' => array(
				'default' => array(
					'type' => 'string',
					'index' => 'analyzed',
					'analyzer' => 'default',
				),
				'word_count' => array(
					'type' => 'token_count',
					'analyzer' => 'default',
				),
			)
		);

		$mapping['properties'] = $this->_ml( $mapping['properties'] );

		return $mapping;
	}

	public function text_ngram() {
		return array(
			'type' => 'string',
			'index' => 'analyzed',
			'analyzer' => 'ngram_analyzer',
			'similarity' => 'BM25',
			'term_vector' => 'with_positions_offsets',
		);
	}

	public function text_ngramedge() {
		return array(
			'type' => 'string',
			'index' => 'analyzed',
			'analyzer' => 'edgengram_analyzer',
			'similarity' => 'BM25',
			'term_vector' => 'with_positions_offsets',
		);
	}

	public function text_raw( $fieldname ) {
		return array(
			'type' => 'multi_field',
			'fields' => array(
				$fieldname => array(
					'type' => 'string',
					'index' => 'analyzed',
					'similarity' => 'BM25',
				),
				'raw' => $this->keyword(),
			),
		);
	}

	protected function _ml( $properties ) {
		foreach ( $this->_Analyzer_Builder->supported_languages as $lang => $analyzer ) {
			if ( 2 !== strlen( $lang ) )
				continue;
			$properties[$lang] = array(
				'type' => 'string',
				'index' => 'analyzed',
				'similarity' => 'BM25',
				'analyzer' => $analyzer['name'],
			);
		}
		return $properties;
	}

	protected function _store( array $mapping ) {
		$mapping['store'] = 'yes';
		return $mapping;
	}

}

