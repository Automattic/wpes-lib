<?php

class WPES_Analyzer_Builder {

	/* Analyzer Strategy
	 * -Use very light or minimal stemming to avoid losing semantic information
	 *  (eg informed and inform have very different meanings)
	 *   See:
	 *    http://www.searchworkings.org/blog/-/blogs/388936
	 *    http://www.ercim.eu/publication/ws-proceedings/CLEF2/savoy.pdf
	 * -Use stop words for those languages we have them for (and add Hebrew)
	 * -Use ICU Tokenizer for most all cases except where there is a better lang specific tokenizer (eg Japanese)
	 *   See details on tokenizers:
	 *    ICU (good on most all Unicode): http://www.unicode.org/reports/tr29/
	 *    kuromoji (Japanese word segmentation): http://www.atilika.org/
	 *    smart-cn (Chinese sentence and word segmentation): http://lucene.apache.org/core/old_versioned_docs/versions/3_5_0/api/contrib-smartcn/org/apache/lucene/analysis/cn/smart/SmartChineseAnalyzer.html
	 *    cjk (Tokenizes characters into bigrams, used for Korean because we don't have a smarter tokenizer): http://lucene.apache.org/core/3_6_0/api/all/org/apache/lucene/analysis/cjk/package-summary.html
	 * -ICU Folding and Normalization to make characters consistent (this handles lowercasing).
	 *   See:
	 *    http://www.unicode.org/reports/tr30/tr30-4.html
	 *    http://userguide.icu-project.org/transforms/normalization
	 * -'default' analyzer
	 *   -For Indexing Languages without custom analyzers/stemmers/stopwords
	 *   -Search queries use the 'default' analyzer to _mostly_ work across all languages (stemmed words don't!)
	 *
	 * A more detailed explanation is available here:
	 *   http://gibrown.wordpress.com/2013/05/01/three-principles-for-multilingal-indexing-in-elasticsearch/
	 */

	var $supported_languages = array(
		//lang => analyzer_name, analyzer, tokenizer, stop words, stemming
		'ar' => array(
			'name'      => 'ar_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_arabic_',
			'stemming'  => null
		),
		'bg' => array(
			'name'      => 'bg_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_bulgarian_',
			'stemming'  => null
		),
		//Not detected by ES langdetect, but left in (detected as spanish)
		'ca' => array(
			'name'      => 'ca_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_catalan_',
			'stemming'  => null
		),
		'cs' => array(
			'name'      => 'cs_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_czech_',
			'stemming'  => null
		),
		'da' => array(
			'name'      => 'da_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_danish_',
			'stemming'  => null
		),
		'de' => array(  //removes plurals only
			'name'      => 'de_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_german_',
			'stemming'  => 'minimal_german'
		),
		'el' => array(
			'name'      => 'el_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_greek_',
			'stemming'  => null
		),
		'en' => array(  //removes plurals only
			'name'      => 'en_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_english_',
			'stemming'  => 'minimal_english'
		),
		'es' => array( //removes plurals and masc/fem
			'name'      => 'es_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_spanish_',
			'stemming'  => 'light_spanish'
		),
		//Not detected by ES langdetect, but left in
		'eu' => array(
			'name'      => 'eu_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_basque_',
			'stemming'  => null
		),
		'fa' => array(
			'name'      => 'fa_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_persian_',
			'stemming'  => null
		),
		'fi' => array( //removes plurals and masc/fem
			'name'      => 'fi_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_finnish_',
			'stemming'  => 'light_finish'  //sic in ES
		),
		'fr' => array( //removes plurals only
			'name'      => 'fr_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_french_',
			'stemming'  => 'minimal_french'
		),
		'he' => array( //stopwords added by get_hebrew_stopwords()
			'name'      => 'he_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => null,
			'stemming'  => null,
		),
		'hi' => array(
			'name'      => 'hi_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_hindi_',
			'stemming'  => null
		),
		'hu' => array( //removes plurals and masc/fem?
			'name'      => 'hu_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_hungarian_',
			'stemming'  => 'light_hungarian'
		),
		//Not detected by ES langdetect
		'hy' => array(
			'name'      => 'hy_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_armenian_',
			'stemming'  => null
		),
		'id' => array(
			'name'      => 'id_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_indonesian_',
			'stemming'  => null
		),
		'it' => array( //removes plurals and masc/fem
			'name'      => 'it_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_italian_',
			'stemming'  => 'light_italian'
		),
		'ja' => array(
			'name'      => 'ja_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => "kuromoji_tokenizer",
			'stopwords' => null,
			'stemming'  => null
		),
		'ko' => array(
			'name'      => 'ko_analyzer',
			'analyzer'  => 'cjk',
			'tokenizer' => null,
			'stopwords' => null,
			'stemming'  => null
		),
		'nl' => array(
			'name'      => 'nl_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_dutch_',
			'stemming'  => null
		),
		'no' => array(
			'name'      => 'no_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_norwegian_',
			'stemming'  => null
		),
		'pt' => array(  //removes plurals only
			'name'      => 'pt_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_portuguese_',
			'stemming'  => 'minimal_portuguese'
		),
		'ro' => array(
			'name'      => 'ro_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_romanian_',
			'stemming'  => null
		),
		'ru' => array(  //removes plurals and masc/fem
			'name'      => 'ru_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_russian_',
			'stemming'  => 'light_russian'
		),
		'sv' => array(  //removes plurals and masc/fem?
			'name'      => 'sv_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_swedish_',
			'stemming'  => 'light_swedish'
		),
		'tr' => array(
			'name'      => 'tr_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => '_turkish_',
			'stemming'  => null
		),
		'zh' => array(  //uses a hidden markov model and dictionary to segment into words
			'name'      => 'zh_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'smartcn_tokenizer',
			'stopwords' => null,
			'stemming'  => null
		),
		// Non Language based
		'lowercase' => array(
			'name'      => 'lowercase_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'keyword',
			'stopwords' => null,
			'stemming'  => null,
		),
		'ngram' => array(
			'name'      => 'ngram_analyzer',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => null,
			'stemming'  => null,
		),
		'edgengram' => array(
				'name'      => 'edgengram_analyzer',
				'analyzer'  => 'custom',
				'tokenizer' => 'icu_tokenizer',
				'stopwords' => null,
				'stemming'  => null,
		),
		'edgengram_raw' => array(
				'name'      => 'edgengram_raw_analyzer',
				'analyzer'  => 'custom',
				'tokenizer' => 'keyword',
				'stopwords' => null,
				'stemming'  => null,
		),
		'default' => array( //general analyzer used as the default for search (works pretty well across langs)
			'name'      => 'default',
			'analyzer'  => 'custom',
			'tokenizer' => 'icu_tokenizer',
			'stopwords' => null,
			'stemming'  => null
		),
	);

	//The language is supported by the elasticsearch-langdetect plugin
	// see: https://github.com/jprante/elasticsearch-langdetect
	var $has_lang_detection = array( 'af' => true, 'ar' => true, 'bg' => true, 'bn' => true, 'cs' => true, 'da' => true, 'de' => true, 'el' => true, 'en' => true, 'es' => true, 'et' => true, 'fa' => true, 'fi' => true, 'fr' => true, 'gu' => true, 'he' => true, 'hi' => true, 'hr' => true, 'hu' => true, 'id' => true, 'it' => true, 'ja' => true, 'kn' => true, 'ko' => true, 'lt' => true, 'lv' => true, 'mk' => true, 'ml' => true, 'mr' => true, 'ne' => true, 'nl' => true, 'no' => true, 'pa' => true, 'pl' => true, 'pt' => true, 'ro' => true, 'ru' => true, 'sk' => true, 'sl' => true, 'so' => true, 'sq' => true, 'sv' => true, 'sw' => true, 'ta' => true, 'te' => true, 'th' => true, 'tl' => true, 'tr' => true, 'uk' => true, 'ur' => true, 'vi' => true, 'zh' => true );

	static $instance;

	function __construct() {
	}

	function init() {
		if ( ! self::$instance ) {
			self::$instance = new Jetpack__To_UTF8;
		}

		return self::$instance;
	}

	function get_analyzer_name( $lang ) {
		if ( isset( $this->supported_languages[$lang] ) )
			return $this->supported_languages[$lang]['name'];

		//handle special re-mappings
		switch( $lang ) {
			case 'zh-cn': //Simplified Chinese
			case 'zh-tw': //Traditional Chinese (or Taiwan?)
			case 'zh-hk': //Hong Kong Chinese
				return $this->supported_languages['zh']['name']; //Traditional Chinese
		}

		return $this->supported_languages['default']['name'];
	}

	function get_analyzer_id( $lang ) {
		// An ID is the first two characters of a $lang_code. The ID must be present
		// in supported_languages or be a special re-mapping.
		if ( isset( $this->supported_languages[$lang] ) )
			return $lang;

		//handle special re-mappings
		switch( $lang ) {
			case 'zh-cn': //Simplified Chinese
			case 'zh-tw': //Traditional Chinese (or Taiwan?)
			case 'zh-hk': //Hong Kong Chinese
				return 'zh'; //Traditional Chinese
		}

		return 'default';
	}

	function can_detect_lang( $lang ) {
		$normalized = strtok( $lang, '-_' );
		return isset( $this->has_lang_detection[$normalized] ) && $this->has_lang_detection[$normalized];
	}

	//build a list of analyzers for an ES index
	function build_analyzers( $langs = array() ) {
		if ( empty( $langs ) )
			$langs = array_keys( $this->supported_languages );

		$settings = array( 'filter' => array(), 'analyzer' => array() );

		//$settings['filter']['bigram_filter'] = array(
		//	'type' => 'shingle',
		//	'min_shingle_size' => 2,
		//	'max_shingle_size' => 2,
		//	'output_unigrams' => true
		//);

		//japanese needs custom tokenizer
		if ( in_array( 'ja', $langs ) ) {
			$settings['tokenizer'] = array(
				'kuromoji' => array(
					'type' => 'kuromoji_tokenizer',
					'mode' => 'search'
			) );
		}

		//ngram needs custom filters
		if ( in_array( 'ngram', $langs ) ) {
			$settings['filter']['unique_filter'] = array(
				'type' => 'unique',
				'only_on_same_position' => true
			);
			$settings['filter']['ngram_filter'] = array(
				'type' => 'nGram',
				'min_gram' => '3',
				'max_gram' => '5',
			);
		}

		if ( in_array( 'edgengram', $langs ) ) {
			$settings['filter']['unique_filter'] = array(
					'type' => 'unique',
					'only_on_same_position' => true
			);
			$settings['filter']['edgengram_filter'] = array(
					'type' => 'edgeNGram',
					'min_gram' => '3',
					'max_gram' => '15',
			);
		}


		foreach ( $langs as $lang ) {
			$config = $this->supported_languages[$lang];
			$settings['analyzer'][ $config['name'] ] = array(
				'type' => $config['analyzer'],
				'filter' => array(),
			);

			////////////////////////////////
			// Lang specific customizations

			if ( 'ja' == $lang ) {
				////From: http://tech.gmo-media.jp/post/70245090007/elasticsearch-kuromoji-japanese-fulltext-search
				$settings['analyzer'][ $config['name'] ]['tokenizer'] = $config['tokenizer'];
				$settings['filter'][$lang . '_pos_filter'] = array(
					'type' => "kuromoji_part_of_speech",
					'stoptags' => array( "助詞-格助詞-一般", "助詞-終助詞" ),
				);
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'kuromoji_baseform';
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'ja_pos_filter'; //stopwords
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'icu_normalizer';
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'icu_folding';
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'cjk_width';
				continue;
			}

			if ( 'de' == $lang ) {
				////From: http://gibrown.wordpress.com/2013/05/01/three-principles-for-multilingal-indexing-in-elasticsearch/#comment-857
				$settings['analyzer'][ $config['name'] ]['tokenizer'] = $config['tokenizer'];
				$settings['filter'][$lang . '_stop_filter'] = array(
					'type' => 'stop',
					'stopwords' => array( $config['stopwords'] )
				);
				$settings['filter'][ $lang . '_stem_filter' ] = array(
					'type' => 'stemmer',
					'name' => $config['stemming']
				);
				$settings['char_filter'][$lang . '_char_filter'] = array(
					'type' => "mapping",
					'mappings' => array( 'ß=>ss', 'Ä=>ae', 'ä=>ae', 'Ö=>oe', 'ö=>oe', 'Ü=>ue', 'ü=>ue', 'ph=>f' ),
				);
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'icu_normalizer';
				$settings['analyzer'][ $config['name'] ]['filter'][] = $lang . '_stop_filter';
				$settings['analyzer'][ $config['name'] ]['filter'][] = $lang . '_stem_filter';
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'icu_folding';
				$settings['analyzer'][ $config['name'] ]['char_filter'] = array( $lang . '_char_filter' );
				continue;
			}

			if ( preg_match( "/ngram/", $lang ) ) {
				// Based partly off of https://qbox.io/blog/an-introduction-to-ngrams-in-elasticsearch
				// but we need Asian langs, so we want to tokenize with icu tokenizer
				// and then duplicate those tokens before creating ngrams so we can match on single chars
				// which may be words
				// We use ngrams rather than edge-ngrams when we want to look within urls, usernames, etc
				// eg photomatt (match Matt), www.nytimes.com (match nytimes), etc
				$settings['analyzer'][ $config['name'] ]['tokenizer'] = $config['tokenizer'];
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'icu_normalizer';
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'icu_folding';
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'keyword_repeat';
				if ( 'ngram' === $lang ) {
					$settings['analyzer'][ $config['name'] ]['filter'][] = 'ngram_filter';
				} elseif ( ( 'edgengram' === $lang ) || ( 'edgengram_raw' === $lang ) ) {
					$settings['analyzer'][ $config['name'] ]['filter'][] = 'edgengram_filter';
				}
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'unique_filter'; //remove dupes at the same location
				continue;
			}


			/////////////////////////////////////////////////
			//First filter is normalization
			// normalization needs to be before stopwords so we combine UTF-8 characters (eg ê)
			if ( $config['tokenizer'] ) {
				$settings['analyzer'][ $config['name'] ]['tokenizer'] = $config['tokenizer'];
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'icu_normalizer';
			}

			//////////////
			//Stopwords

			if ( 'he' == $lang ) {
				//hebrew has its own custom stopword list (no built in ES one)
				$settings['filter'][$lang . '_stop_filter'] = array(
					'type' => 'stop',
					'stopwords' => $this->get_hebrew_stopwords()
				);
				$settings['analyzer'][ $config['name'] ]['filter'][] = $lang . '_stop_filter';
			}

			if ( 'fr' == $lang ) {
				//French has elision's that need to be removed
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'elision';
			}

			if ( 'fr' == $lang ) {
				//French has elision's that need to be removed
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'elision';
			}

			if ( $config['stopwords'] ) {
				$settings['filter'][$lang . '_stop_filter'] = array(
					'type' => 'stop',
					'stopwords' => array( $config['stopwords'] )
				);
				$settings['analyzer'][ $config['name'] ]['filter'][] = $lang . '_stop_filter';
			}


			////////////////
			// Stemming
			if ( $config['stemming'] ) {
				$settings['filter'][ $lang . '_stem_filter' ] = array(
					'type' => 'stemmer',
					'name' => $config['stemming']
				);
				$settings['analyzer'][ $config['name'] ]['filter'][] = $lang . '_stem_filter';
			}

			/////////////////////////////////////////////////
			//final filters (character folding and bigrams)
			//  character folding must be after stopwords
			if ( $config['tokenizer'] ) {
				$settings['analyzer'][ $config['name'] ]['filter'][] = 'icu_folding';
				//if ( 'lowercase' != $config['name'] )
				//	$settings['analyzer'][ $config['name'] ]['filter'][] = 'bigram_filter';
			}

		}

		return $settings;
	}

	//hebrew stopwords taken from http://wiki.korotkin.co.il/Hebrew_stopwords
	//  removed the two word "stopwords" per @ranh
	function get_hebrew_stopwords() {
		$stopwords = array(
			'אבל',
			'או',
			'אולי',
			'אותה',
			'אותה',
			'אותו',
			'אותו',
			'אותו',
			'אותי',
			'אותך',
			'אותם',
			'אותן',
			'אותנו',
			'אז',
			'אחר',
			'אחר',
			'אחרות',
			'אחרי',
			'אחרי',
			'אחרי',
			'אחרים',
			'אחרת',
			'אי',
			'איזה',
			'איך',
			'אין',
			'אין',
			'איפה',
			'איתה',
			'איתו',
			'איתי',
			'איתך',
			'איתכם',
			'איתכן',
			'איתם',
			'איתן',
			'איתנו',
			'אך',
			'אך',
			'אל',
			'אל',
			'אלה',
			'אלה',
			'אלו',
			'אלו',
			'אם',
			'אם',
			'אנחנו',
			'אני',
			'אס',
			'אף',
			'אצל',
			'אשר',
			'אשר',
			'את',
			'את',
			'אתה',
			'אתכם',
			'אתכן',
			'אתם',
			'אתן',
			'באמצע',
			'באמצעות',
			'בגלל',
			'בין',
			'בלי',
			'בלי',
			'במידה',
			'ברם',
			'בשביל',
			'בתוך',
			'גם',
			'דרך',
			'הוא',
			'היא',
			'היה',
			'היכן',
			'היתה',
			'היתי',
			'הם',
			'הן',
			'הנה',
			'הרי',
			'ואילו',
			'ואת',
			'זאת',
			'זה',
			'זה',
			'זות',
			'יהיה',
			'יוכל',
			'יוכלו',
			'יותר',
			'יכול',
			'יכולה',
			'יכולות',
			'יכולים',
			'יכל',
			'יכלה',
			'יכלו',
			'יש',
			'כאן',
			'כאשר',
			'כולם',
			'כולן',
			'כזה',
			'כי',
			'כיצד',
			'כך',
			'ככה',
			'כל',
			'כלל',
			'כמו',
			'כמו',
			'כן',
			'כן',
			'כפי',
			'כש',
			'לא',
			'לאו',
			'לאן',
			'לבין',
			'לה',
			'להיות',
			'להם',
			'להן',
			'לו',
			'לי',
			'לכם',
			'לכן',
			'לכן',
			'למה',
			'למטה',
			'למעלה',
			'למרות',
			'למרות',
			'לנו',
			'לעבר',
			'לעיכן',
			'לפיכך',
			'לפני',
			'לפני',
			'מאד',
			'מאחורי',
			'מאין',
			'מאיפה',
			'מבלי',
			'מבעד',
			'מדוע',
			'מדי',
			'מה',
			'מהיכן',
			'מול',
			'מחוץ',
			'מי',
			'מכאן',
			'מכיוון',
			'מלבד',
			'מן',
			'מנין',
			'מסוגל',
			'מעט',
			'מעטים',
			'מעל',
			'מעל',
			'מצד',
			'מתחת',
			'מתחת',
			'מתי',
			'נגד',
			'נגר',
			'נו',
			'עד',
			'עד',
			'עז',
			'על',
			'על',
			'עלי',
			'עליה',
			'עליהם',
			'עליהן',
			'עליו',
			'עליך',
			'עליכם',
			'עלינו',
			'עם',
			'עם',
			'עצמה',
			'עצמהם',
			'עצמהן',
			'עצמו',
			'עצמי',
			'עצמם',
			'עצמן',
			'עצמנו',
			'פה',
			'רק',
			'רק',
			'שוב',
			'שוב',
			'של',
			'שלה',
			'שלהם',
			'שלהן',
			'שלו',
			'שלי',
			'שלך',
			'שלכם',
			'שלכן',
			'שלנו',
			'שם',
			'תהיה',
			'תחת',
		);
		return $stopwords;
	}
}
