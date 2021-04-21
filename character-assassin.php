<?php
/*
Plugin Name: Character Assassin
Description: A plugin for torture testing WordPress themes. DO NOT USE ON A PRODUCTION SITE.
Author: Alex Shiels
Author URI: https://flightpath.blog/
Version: 0.1
*/

define( 'TW_CA_BAD_CHARACTERS', '<"\'**CA**"\'>' );

/**
 * Add a bunch of filters to mangle data that is commonly output on the front end of a site in an unsafe context.
 * In theory a theme should escape every instance of these before output.
 */
function tw_ca_init() {
	// We can't force-filter get_bloginfo() because of its $raw parameter, so filter its data sources instead.
	add_filter( 'home_url', 'tw_ca_mangle_tail' );
	add_filter( 'option_blogdescription', 'tw_ca_mangle' );
	add_filter( 'option_admin_email', 'tw_ca_mangle' );
	add_filter( 'option_blog_charset', 'tw_ca_mangle' );
	add_filter( 'option_html_type', 'tw_ca_mangle' );
	add_filter( 'option_blogname', 'tw_ca_mangle' );

	// Filter all translated strings
	add_filter( 'gettext', 'tw_ca_mangle' );
}

/**
 * Adds bad characters to the beginning and end of the filtered parameter.
 */
function tw_ca_mangle( $param ) {
	return TW_CA_BAD_CHARACTERS . $param . TW_CA_BAD_CHARACTERS;
}


/**
 * Adds bad characters to the end of the filtered parameter.
 */
function tw_ca_mangle_tail( $param ) {
	return $param . TW_CA_BAD_CHARACTERS;
}


add_action( 'template_redirect', 'tw_ca_init' );
