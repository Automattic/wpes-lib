<?php

abstract class WPES_Abstract_Document_Builder {

	abstract public function get_id( $args );
	abstract public function get_type( $args );
	abstract public function doc( $args );
	abstract public function update( $args );
	abstract public function is_indexable( $args );

	public function get_parent_id( $args ) {
		return false;
	}


}

