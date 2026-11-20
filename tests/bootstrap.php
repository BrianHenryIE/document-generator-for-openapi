<?php
/**
 * The generator is given the REST routes as an array, so its tests need no WordPress: only the few WordPress
 * functions it calls, defined here with fixed results.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return 'blogname' === $option ? 'document-generator-for-openapi' : $default;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'http://localhost:8080/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value ) {
		return $value;
	}
}
