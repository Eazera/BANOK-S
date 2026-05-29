<?php
/**
 * iOS-focused Add to Home Screen support for Banoks POS.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Outputs iOS web app meta tags and a lightweight manifest.
 */
class Banoks_POS_IOS_PWA {

    const OPTION_KEY = 'banoks_pos_ios_pwa_settings';

    /**
     * Default settings.
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'enabled'          => '1',
            'app_name'         => get_bloginfo( 'name' ),
            'short_name'       => get_bloginfo( 'name' ),
            'site_title'       => get_bloginfo( 'name' ),
            'theme_color'      => '#ef1010',
            'status_bar_style' => 'black-translucent',
            'icon_180_id'      => 0,
            'icon_192_id'      => 0,
            'icon_512_id'      => 0,
        );
    }

    /**
     * Get saved settings merged with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args( $saved, self::defaults() );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input.
     * @return array
     */
    public static function sanitize_settings( $input ) {
        $defaults = self::defaults();
        $input    = is_array( $input ) ? $input : array();

        $theme_color = isset( $input['theme_color'] ) ? sanitize_hex_color( $input['theme_color'] ) : $defaults['theme_color'];
        if ( empty( $theme_color ) ) {
            $theme_color = $defaults['theme_color'];
        }

        $status_bar_style = isset( $input['status_bar_style'] ) ? sanitize_key( $input['status_bar_style'] ) : $defaults['status_bar_style'];
        $allowed_status   = array( 'default', 'black', 'black-translucent' );
        if ( ! in_array( $status_bar_style, $allowed_status, true ) ) {
            $status_bar_style = $defaults['status_bar_style'];
        }

        return array(
            'enabled'          => empty( $input['enabled'] ) ? '0' : '1',
            'app_name'         => isset( $input['app_name'] ) ? sanitize_text_field( wp_unslash( $input['app_name'] ) ) : $defaults['app_name'],
            'short_name'       => isset( $input['short_name'] ) ? sanitize_text_field( wp_unslash( $input['short_name'] ) ) : $defaults['short_name'],
            'site_title'       => isset( $input['site_title'] ) ? sanitize_text_field( wp_unslash( $input['site_title'] ) ) : $defaults['site_title'],
            'theme_color'      => $theme_color,
            'status_bar_style' => $status_bar_style,
            'icon_180_id'      => isset( $input['icon_180_id'] ) ? absint( $input['icon_180_id'] ) : 0,
            'icon_192_id'      => isset( $input['icon_192_id'] ) ? absint( $input['icon_192_id'] ) : 0,
            'icon_512_id'      => isset( $input['icon_512_id'] ) ? absint( $input['icon_512_id'] ) : 0,
        );
    }

    /**
     * Register frontend hooks.
     */
    public function __construct() {
        add_action( 'wp_head', array( $this, 'output_ios_meta_tags' ), 2 );
        add_action( 'wp_ajax_banoks_ios_manifest', array( $this, 'render_manifest' ) );
        add_action( 'wp_ajax_nopriv_banoks_ios_manifest', array( $this, 'render_manifest' ) );
    }

    /**
     * Output iOS PWA tags.
     */
    public function output_ios_meta_tags() {
        $settings = self::get_settings();

        if ( '1' !== $settings['enabled'] ) {
            return;
        }

        $icon_180 = self::get_icon_url( $settings['icon_180_id'], '180x180' );
        $title    = $settings['site_title'] ? $settings['site_title'] : $settings['app_name'];
        ?>
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $title ); ?>">
        <meta name="application-name" content="<?php echo esc_attr( $settings['app_name'] ); ?>">
        <meta name="apple-mobile-web-app-status-bar-style" content="<?php echo esc_attr( $settings['status_bar_style'] ); ?>">
        <meta name="theme-color" content="<?php echo esc_attr( $settings['theme_color'] ); ?>">
        <link rel="manifest" href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=banoks_ios_manifest' ) ); ?>">
        <?php if ( $icon_180 ) : ?>
            <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $icon_180 ); ?>">
        <?php endif; ?>
        <?php
    }

    /**
     * Render a lightweight manifest for Add to Home Screen support.
     */
    public function render_manifest() {
        $settings = self::get_settings();

        if ( '1' !== $settings['enabled'] ) {
            status_header( 404 );
            exit;
        }

        $icons = array();
        foreach ( array( '192' => 'icon_192_id', '512' => 'icon_512_id' ) as $size => $key ) {
            $url = self::get_icon_url( $settings[ $key ], $size . 'x' . $size );
            if ( $url ) {
                $icons[] = array(
                    'src'   => esc_url_raw( $url ),
                    'sizes' => $size . 'x' . $size,
                    'type'  => 'image/png',
                );
            }
        }

        $manifest = array(
            'name'             => $settings['app_name'],
            'short_name'       => $settings['short_name'],
            'start_url'        => home_url( '/menu/' ),
            'scope'            => home_url( '/' ),
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => $settings['theme_color'],
            'icons'            => $icons,
        );

        wp_send_json( $manifest );
    }

    /**
     * Get an icon URL from an attachment ID.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $size          Image size slug or dimensions.
     * @return string
     */
    public static function get_icon_url( $attachment_id, $size = 'full' ) {
        $attachment_id = absint( $attachment_id );
        if ( ! $attachment_id ) {
            return '';
        }

        $image = wp_get_attachment_image_src( $attachment_id, $size );
        if ( ! empty( $image[0] ) ) {
            return $image[0];
        }

        return wp_get_attachment_url( $attachment_id );
    }
}
