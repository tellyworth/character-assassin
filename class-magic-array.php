<?php
namespace CharacterAssassin;

// Class that implements a magic array to mock $_GET and other superglobals.
// This essentially implements an object that imitates an array.

class MagicArray implements \ArrayAccess, \Countable {
	private $data = [];
	private $callback = null;

	private function should_mangle( $name ) {
		// Don't interfere with logins
		if ( is_login() ) {
			return false;
		}
		if ( is_admin() ) {
			// Don't mess with values that are needed for menus and cap checks to work correctly.
			if ( in_array( $name, [ 'page', 'plugin', 'action', '_wpnonce' ] ) ) {
				return false;
			}
		}
		return true;
	}

	public function __construct( $data, $callback = null ) {
		$this->data = $data;
		$this->callback = $callback;
	}

	public function offsetSet($offset,$value) {
		if (is_null($offset)) {
			$this->data[] = $value;
		} else {
			$this->data[$offset] = $value;
		}
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetUnset($offset) {
		if ($this->offsetExists($offset)) {
			unset($this->data[$offset]);
		}
	}

	public function offsetGet($offset) {
		if ( isset( $this->data[$offset] ) && is_callable( $this->callback ) ) {
			if ( $this->should_mangle( $offset ) ) {
				return call_user_func( $this->callback, $this->data[$offset] );
			}
		}
		return $this->offsetExists($offset) ? $this->data[$offset] : null;
	}

	public function count() {
		return count( $this->data );
	}
}