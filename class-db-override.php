<?php
namespace CharacterAssassin;

// This will only work with SQLite, ie when using wp-now or playground.

// We need to override the DB class to intercept escaping functions and detect unescaped strings in queries.


class DB_Override extends \WP_SQLite_DB {
	protected $callback = null;
	public $queries_to_check = [];

	public function tw_ca_set_callback( $callback ) {
		$this->callback = $callback;
	}

	public function _real_escape( $data ) {
		if ( is_callable( $this->callback ) ) {
			$data = call_user_func( $this->callback, $data );
		}
		return parent::_real_escape( $data );
	}

	public function query( $query ) {
		// Log all queries so we can check them later.
		// TODO: limit to queries that are likely to contain user input.
		$this->queries_to_check[] = $query;
		return parent::query( $query );
	}

}