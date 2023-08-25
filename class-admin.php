<?php
namespace CharacterAssassin;

class Admin {
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new self();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_to_menu' ) );
	}

	public function add_to_menu() {
		$hook = add_submenu_page(
			'tools.php',
			esc_html__( 'Character Assassin', 'wporg-plugins' ),
			esc_html__( 'Character Assassin', 'wporg-plugins' ),
			'manage_options',
			'character-assassin',
			array( $this, 'show_self_test' )
		);
	}

	public function show_self_test() {
		var_dump( __METHOD__ );

		echo '<pre>';

		echo __( 'Unescaped translation string', 'character-assassin' ) . "\n";
		echo __( 'Unescaped translation string (default domain)' ) . "\n";
		echo esc_html__( 'Correctly escaped translation string', 'character-assassin' ) . "\n";

		printf( "Unescaped home url: %s\n", home_url() );
		printf( "Correctly escaped home url: %s\n", esc_url( home_url() ) );

		$_GET['foo'] = 'bar';
		printf( "Unescaped GET: %s\n", $_GET['foo'] );
		printf( "Correctly escaped GET: %s\n", esc_html( $_GET['foo'] ) );
		printf( "Attribute escaped GET: %s\n", esc_attr( $_GET['foo'] ) );

		global $wpdb;

		echo $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_title = '%s'", 'foo' ) . "\n";
		echo $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_title = '%s'", $_GET['foo'] ) . "\n";
		$wpdb->query( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_title = '%s'", $_GET['foo'] ) ); // Fine
		$wpdb->query( "SELECT * FROM {$wpdb->posts} WHERE post_title = '" . $_GET['foo'] . "'" ); // Bad
		$wpdb->query( "SELECT * FROM {$wpdb->posts} WHERE post_title = '" . esc_url( $_GET['foo'] ) . "'" ); // Ok
		$wpdb->query( "SELECT * FROM {$wpdb->posts} WHERE post_title = '" . sanitize_text_field( $_GET['foo'] ) . "'" ); // Bad

		echo '</pre>';
	}
}