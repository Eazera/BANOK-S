<?php
/**
 * Shared template renderer for Banoks POS.
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Banoks_POS_Renderer {

    /**
     * Render a template and return its HTML.
     *
     * @since    1.0.0
     * @param    string $template Template file name without extension.
     * @param    array  $data     Template variables.
     * @return   string
     */
    public function render( $template, $data = array() ) {
        $template_path = BANOKS_POS_PATH . 'templates/' . $template . '.php';

        if ( ! file_exists( $template_path ) ) {
            return '';
        }

        ob_start();
        extract( $data, EXTR_SKIP );
        include $template_path;

        return ob_get_clean();
    }
}
