<?php
/*
Plugin Name: Character Assassin
Description: A plugin for torture testing WordPress themes. DO NOT USE ON A PRODUCTION SITE.
Author: Alex Shiels
Author URI: https://flightpath.blog/
Version: 0.1
*/
define( 'TW_CA_BAD_CHARACTERS', '<"\'**CA**"\'>' );

class CharacterAssassin {
	private $tw_heap = [];

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'tw_ca_init' ) );
	}

	/**
	 * Add a bunch of filters to mangle data that is commonly output on the front end of a site in an unsafe context.
	 * In theory a theme should escape every instance of these before output.
	 */
	public function tw_ca_init() {
		// We can't force-filter get_bloginfo() because of its $raw parameter, so filter its data sources instead.
		#add_filter( 'home_url', 'tw_ca_mangle_tail' );
		add_filter( 'option_blogdescription', array( $this, 'tw_ca_mangle' ) );
		add_filter( 'option_admin_email', array( $this, 'tw_ca_mangle' ) );
		#add_filter( 'option_blog_charset', 'tw_ca_mangle' );
		add_filter( 'option_html_type', array( $this, 'tw_ca_mangle' ) );
		add_filter( 'option_blogname', array( $this, 'tw_ca_mangle' ) );

		// Filter all non-core translated strings
		add_filter( 'gettext', array( $this, 'tw_ca_mangle_gettext' ), 10, 3 );

		// Special escaping filter to reverse placeholders
		add_filter( 'esc_html', array( $this, 'tw_esc_html' ), 10, 2 );
		add_filter( 'attribute_escape', array( $this, 'tw_esc_attr' ), 10, 2 );
		add_filter( 'clean_url', array( $this, 'tw_esc_html' ), 10, 2 );

		ob_start( array( $this, 'tw_ca_footer') );
	}

	/**
	 * Adds bad characters to the beginning and end of the filtered parameter.
	 */
	function tw_ca_push_to_heap( $param ) {
		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
		$ignore_funcs = [ __FUNCTION__, 'tw_ca_mangle', 'tw_ca_mangle_tail', 'tw_ca_mangle_head', 'tw_ca_mangle_gettext', 'apply_filters', 'translate', '__' ];
		$found_frame = null;
		foreach ( $bt as $frame_i => $frame ) {
			if ( isset( $frame['file'] )
				&& strpos( $frame['file'], WP_CONTENT_DIR ) !== false
				&& strpos( $frame['file'], plugin_dir_path( __FILE__ ) ) === false ) {
				// The first function in a file is probably the one we want (except if it's in this plugin)
				$found_frame = $frame;
				break;
			}
		}
		if ( !$found_frame ) {
			// If we didn't find a frame in a plugin or theme, look for the first frame that isn't in our ignore list
			foreach ( $bt as $frame_i => $frame ) {
				if ( isset( $frame['function'] ) && !in_array( $frame['function'], $ignore_funcs ) ) {

					$found_frame = $frame;
					break;
				}
			}
		}

		if ( $found_frame ) {
			$func = $found_frame['function'];
			$line = $found_frame['line'];
			$file = basename( $found_frame['file'] );
			$param_key = "[$func:$line:$file] $param";
		} else {
			$param_key = $param;
		}

		$unique = uniqid();
		$this->tw_heap[ $unique ] = [
			'param' => $param,
			'param_key' => $param_key,
			'frame' => $found_frame,
		];

		return $unique;
	}

	function tw_ca_mangle( $param ) {
		$unique = $this->tw_ca_push_to_heap( $param );
		return TW_CA_BAD_CHARACTERS . $unique . TW_CA_BAD_CHARACTERS;
	}

	function tw_ca_mangle_gettext( $translation, $text, $domain ) {
		// Don't mangle core strings; traditionally they're not escaped
		if ( 'default' === $domain ) {
			return $translation;
		}
		$unique = $this->tw_ca_push_to_heap( $text );
		return TW_CA_BAD_CHARACTERS . $unique . TW_CA_BAD_CHARACTERS;
	}


	/**
	 * Adds bad characters to the end of the filtered parameter.
	 */
	function tw_ca_mangle_tail( $param ) {
		$unique = $this->tw_ca_push_to_heap( $param );
		return $unique . TW_CA_BAD_CHARACTERS;
	}

	// apply_filters( 'esc_html', $safe_text, $text );
	function tw_esc_html( $safe_text, $text ) {

		if ( strpos( $text, TW_CA_BAD_CHARACTERS ) === false ) {
			return $safe_text;
		}

		// Look for a mangled string anywhere in the text.
		// Note that we can't count on the exact text matching because of code like `esc_html( 'foo ' . get_bloginfo('name') . ' bar' )`
		$found = false;
		$id = null;
		$found = preg_match( '#(?:' . preg_quote(TW_CA_BAD_CHARACTERS) . ')?(\w+)' . preg_quote(TW_CA_BAD_CHARACTERS) . '#', $text, $match );
		if ( $found ) {
			$id = $match[1];
		}


		if ( $id && isset( $this->tw_heap[ $id ] ) ) {
			$safe_text = wp_check_invalid_utf8( $this->tw_heap[ $id ]['param'] );
			$safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
		}


		return $safe_text;
	}

	// apply_filters( 'attribute_escape', $safe_text, $text );
	function tw_esc_attr( $safe_text, $text ) {

		$safe_text = $this->tw_esc_html( $safe_text, $text );
		return $safe_text;
	}

	function tw_ca_footer( $content ) {

		$info = [];
		foreach ( $this->tw_heap as $key => $data ) {
			if ( false !== strpos( $content, $key ) ) {
				$info[ $key ] = $data;
			}
		}

		$extra = '';
		if ( $info ) {
			$extra = '<div id="character-assassin" style="position:absolute;left:0;top:6em;width:50%;margin-left:25%;background-color:#ccc;opacity:0.9;"><h2>Character Assassin</h2><h3>Unescaped strings</h3><ul>';
			foreach ( $info as $key => $data ) {
				$extra .= '<li><code>' . esc_html( $data['param'] ) .
				'</code> - <code>' .
				esc_html( $data['frame']['file'] ) .
				'</code> line ' .
				esc_html( $data['frame']['line'] ) .
				' in function <code>' .
				esc_html( $data['frame']['function'] ) .
				'()</code></li>';
			}
			$extra .= '</ul>';
			$extra .= '<p>Items identified: ' . count( $this->tw_heap ) . '<br/>';
			$extra .= 'Safe items: ' . count( $this->tw_heap ) - count( $info ) . '<br/>';
			$extra .= 'Unescaped items: ' . count( $info ) . '</p>';
			$extra .= '</div>';
		}

		return $content . $extra;
	}
}

new CharacterAssassin();