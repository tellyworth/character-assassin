<?php
namespace CharacterAssassin;
/*
Plugin Name: Character Assassin
Description: A plugin for torture testing WordPress themes. DO NOT USE ON A PRODUCTION SITE.
Author: Alex Shiels
Author URI: https://flightpath.blog/
Version: 0.1
*/

require_once( __DIR__ . '/class-magic-array.php' );
use CharacterAssassin\MagicArray;
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
		add_filter( 'home_url', array( $this, 'tw_ca_mangle_tail' ) );
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
		add_filter( 'clean_url', array( $this, 'tw_esc_url' ), 10, 2 );
		add_filter( 'pre_kses', array( $this, 'tw_ca_pre_kses' ), 10, 3);
		add_filter( 'sanitize_text_field', array( $this, 'tw_ca_sanitize_text_field' ), 10, 2 );

		wp_enqueue_style('character-assassin', plugins_url( '/character-assassin.css', __FILE__), array(), '0.1.0', 'all');

		// Need to do this later in order to avoid crashing wp_magic_quotes()
		add_action( 'sanitize_comment_cookies', array( $this, 'tw_ca_mock_superglobals' ) );

		ob_start( array( $this, 'tw_ca_footer') );

		add_action( 'sanitize_comment_cookies', array( $this, 'tw_ca_mock_wpdb' ) );

		// Load this early so we can use the menu hooks.
		if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
			require_once( __DIR__ . '/class-admin.php' );
			Admin::instance();
		}

	}

	function tw_ca_mock_wpdb() {
		// Don't try this at home, kids.
		if ( class_exists( 'WP_SQLite_DB') && 'WP_SQLite_DB' === get_class( $GLOBALS['wpdb'] ) ) {
			require_once( __DIR__ . '/class-db-override.php' );
			$GLOBALS['wpdb'] = new DB_Override( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$GLOBALS['wpdb']->tw_ca_set_callback( array( $this, 'tw_ca_real_escape' ) );
			wp_set_wpdb_vars();
		}
	}

	function tw_ca_mock_superglobals() {
		#global $pagenow;
		#if ( is_login() || wp_doing_ajax() || 'plugins.php' === $pagenow || 'admin.php' === $pagenow ) {
		#	return;
		#}
		$_GET = new MagicArray( $_GET, array( $this, 'tw_ca_mangle_superglobal' ) );
		$_POST = new MagicArray( $_POST, array( $this, 'tw_ca_mangle_superglobal' ) );
		$_REQUEST = new MagicArray( $_REQUEST, array( $this, 'tw_ca_mangle_superglobal' ) );
	}

	function tw_ca_mangle_superglobal( $param ) {
		$unique = $this->tw_ca_push_to_heap( $param );
		return TW_CA_BAD_CHARACTERS . $unique . TW_CA_BAD_CHARACTERS;
	}

	/**
	 * Adds bad characters to the beginning and end of the filtered parameter.
	 */
	function tw_ca_push_to_heap( $param ) {
		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
		$ignore_funcs = [ __FUNCTION__, 'tw_ca_mangle', 'tw_ca_mangle_tail', 'tw_ca_mangle_head', 'tw_ca_mangle_gettext', 'tw_ca_mangle_superglobal', 'apply_filters', 'translate', '__', 'call_user_func' ];
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
		return $param . '/' . TW_CA_BAD_CHARACTERS . $unique . TW_CA_BAD_CHARACTERS;
	}

	function tw_esc_replace_placeholders( $text, $strip_bad_chars = null ) {

		foreach ( array_keys( $this->tw_heap ) as $id ) {
			$text = str_replace( $id, $this->tw_heap[ $id ]['param'], $text );
		}

		if ( $strip_bad_chars && is_string( $strip_bad_chars ) || is_array( $strip_bad_chars ) ) {
			$text = str_replace( $strip_bad_chars, '', $text );
		}

		return $text;
	}

	function tw_esc_replace_trailing_placeholders( $text, $strip_bad_chars = null ) {

		foreach ( array_keys( $this->tw_heap ) as $id ) {
			$text = str_replace( $id, '', $text );
		}

		if ( $strip_bad_chars && is_string( $strip_bad_chars ) || is_array( $strip_bad_chars ) ) {
			$text = str_replace( $strip_bad_chars, '', $text );
		}

		return $text;
	}


	function tw_ca_real_escape( $data ) {
		$data = $this->tw_esc_replace_placeholders( $data, TW_CA_BAD_CHARACTERS );
		return $data;
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

	function tw_esc_url( $safe_text, $text ) {
		/*
		$found = false;
		$id = null;

		// The URL might have been escaped already, so we need to look for the escaped version of the bad characters.
		$delim = preg_quote( TW_CA_BAD_CHARACTERS ) . '|' . preg_quote( urlencode( TW_CA_BAD_CHARACTERS ) );

		$found = preg_match( '#(?:' . $delim . ')?(\w+)(?:' . $delim . ')?#', $text, $match );
		if ( $found ) {
			$id = $match[1];
		}

		if ( $id && isset( $this->tw_heap[ $id ] ) ) {
			$safe_text = preg_replace( '#(?:' . $delim . ')' . preg_quote( $id ) . '(?:' . $delim . ')#', urlencode( $this->tw_heap[ $id ]['param'] ), $text );
		}
		*/

		$safe_text = $this->tw_esc_replace_trailing_placeholders( $text, [ '/' . TW_CA_BAD_CHARACTERS, TW_CA_BAD_CHARACTERS ] );
		return $safe_text;
	}

	function tw_ca_pre_kses( $content, $html, $context ) {
		if ( strpos( $content, TW_CA_BAD_CHARACTERS ) === false ) {
			return $content;
		}

		$id = null;
		$found = preg_match( '#(?:' . preg_quote(TW_CA_BAD_CHARACTERS) . ')?(\w+)' . preg_quote(TW_CA_BAD_CHARACTERS) . '#', $content, $match );
		if ( $found ) {
			$id = $match[1];
		}

		// Replace the ID with the original string. We can let kses itself strip off the bad characters.
		if ( $id && isset( $this->tw_heap[ $id ] ) ) {
			$content = str_replace( $id, $this->tw_heap[ $id ]['param'], $content );
		}
		return $content;
	}

	function tw_ca_sanitize_text_field( $filtered, $unfiltered ) {
		// sanitize_text_field is a special case. For one, it strips out our entire string because it looks like a tag even though it isn't.
		// Also, it's used in a mix of places - sometimes incorrectly as a substitute for html/sql escaping; sometimes correctly in logical checks.

		// What we'll do here is restore the original string and strip the bad characters, but keep the placeholder intact.
		if ( strpos( $unfiltered, TW_CA_BAD_CHARACTERS ) === false ) {
			return $filtered;
		}
		return $text = str_replace( TW_CA_BAD_CHARACTERS, '', $unfiltered );
	}

	function tw_ca_trim_abspath( $path ) {
		$abspath = realpath( ABSPATH );
		if ( 0 === strpos( $path, $abspath ) ) {
			$path = substr( $path, strlen( $abspath ) );
		}
		return $path;
	}

	function tw_ca_footer( $content ) {

		$info = [];
		foreach ( $this->tw_heap as $key => $data ) {
			if ( false !== strpos( $content, $key ) ) {
				$info[ $key ] = $data;
			}
		}

		$extra = '';
		$extra = '<div id="character-assassin"><div class="content"><h2>Character Assassin</h2>';
		if ( $info ) {
			$extra .= '<details><summary>Show ' . count( $info ) . ' unescaped strings</summary>';
			$extra .= '<ul>';

			foreach ( $info as $key => $data ) {
				$extra .= '<li><code>' .
				esc_html( $data['frame']['function'] ) .
				'( \'' . esc_html( $data['param'] ) . '\' )' .
				'</code><br/><code>' .
				esc_html( $this->tw_ca_trim_abspath( $data['frame']['file'] ) ) .
				'</code> line ' .
				esc_html( $data['frame']['line'] ) .
				'</code></li>';
			}
			$extra .= '</ul></details>';
		}

		global $wpdb;
		$queries = [];
		foreach ( $wpdb->queries_to_check as $query ) {
			foreach ( $this->tw_heap as $key => $data ) {
				if ( false !== strpos( $query, $key ) ) {
					$data['query'] = $query;
					$queries[ $key ] = $data;
				}
			}
		}
		if ( $queries ) {
			$extra .= '<details><summary>Show ' . count( $queries ) . ' unescaped SQL queries</summary>';
			$extra .= '<ul>';

			foreach ( $queries as $key => $data ) {
				#$extra .= var_export( $data, true );
				$_query = str_replace( TW_CA_BAD_CHARACTERS, '', $data['query'] );
				$_query = esc_html( $_query );
				$_query = str_replace( $key, '<em style="color:red">' . $data['param'] . '</em>', $data['query'] );
				$extra .= '<li><code>' . $_query .
				'</code><br/><code>' .
				esc_html( $this->tw_ca_trim_abspath( $data['frame']['file'] ) ) .
				'</code> line ' .
				esc_html( $data['frame']['line'] ) .
				'</li>';
			}
			$extra .= '</ul></details>';
		}

		$extra .= '<p>Found <b>' . count( $info ) . ' unescaped</b> and '. count( $this->tw_heap ) - count( $info ) . ' escaped items.</p>';

		$extra .= '</div></div>';

		return $content . $extra;
	}
}

new CharacterAssassin();