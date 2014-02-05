<?php

class WPES_WP_Blog_Field_Builder extends WPES_Abstract_Field_Builder {

	public function get_mappings( $args = array() ) {
		//TODO: implement
		return false;
	}

	public function get_all_fields( $args = array() ) {
		//TODO: implement
		return false;
	}

	public function get_update_script( $args ) {
		return false;
	}

	function blog_lang( $blog_id ) {
		$blog = get_blog_details( $blog_id );
		$blog_lang = get_lang_code_by_id( $blog->lang_id );
		$lang = $blog_lang;
		if ( ! $lang ) {
			//default to English since that is the WP default
			$lang = 'en';
		}
		$lang_builder = new WPES_Analyzer_Builder();
		$lang_analyzer = $lang_builder->get_analyzer_name( $lang );

		$data = array(
			'lang'         => $lang,
			'lang_analyzer' => $lang_analyzer,
		);
		return $data;
	}

}