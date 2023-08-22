<?php
namespace CharacterAssassin;

// Class that implements a magic array to mock $_GET and other superglobals.
// This essentially implements an object that imitates an array.

class MagicArray implements \ArrayAccess {
	private $data = [];

	public function __construct( $data ) {
		$this->data = $data;
	}

	public function __set( $name, $value ) {
		$this->data[$name] = $value;
	}

	public function &__get( $name ) {
		return $this->data[$key];
	}

	public function __isset ($key) {
		return isset($this->data[$key]);
	}

	public function __unset ($key) {
		unset($this->data[$key]);
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
		return $this->offsetExists($offset) ? $this->data[$offset] : null;
	}
}