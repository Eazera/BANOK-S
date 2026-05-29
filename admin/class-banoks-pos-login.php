<?php
/**
 * Login page customization for Banoks POS.
 *
 * @package Banoks_POS
 * @subpackage Banoks_POS/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customizes the WordPress login screen with Banoks branding.
 */
class Banoks_POS_Login {

	/**
	 * Register login page hooks.
	 */
	public function __construct() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );
		add_filter( 'login_headerurl', array( $this, 'get_login_header_url' ) );
		add_filter( 'login_headertext', array( $this, 'get_login_header_text' ) );
	}

	/**
	 * Enqueue the custom login stylesheet.
	 */
	public function enqueue_login_assets() {
		wp_enqueue_style(
			'banoks-pos-login',
			BANOKS_POS_URL . 'admin/css/banoks-pos-login.css',
			array(),
			$this->get_asset_version( 'admin/css/banoks-pos-login.css' )
		);
	}

	/**
	 * Send the logo link to the site home page.
	 *
	 * @return string
	 */
	public function get_login_header_url() {
		return home_url( '/' );
	}

	/**
	 * Return accessible logo text.
	 *
	 * @return string
	 */
	public function get_login_header_text() {
		return get_bloginfo( 'name' ) . ' Login';
	}

	/**
	 * Return a cache-busting version for a plugin asset.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 * @return string|int
	 */
	private function get_asset_version( $relative_path ) {
		$path = BANOKS_POS_PATH . ltrim( $relative_path, '/\\' );

		return file_exists( $path ) ? filemtime( $path ) : BANOKS_POS_VERSION;
	}
}
