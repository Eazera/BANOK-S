<?php
/**
 * Public asset management for Banoks POS.
 *
 * @link       https://banoks.com
 * @since      1.7.5
 * @package    Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles enqueuing of public CSS/JS and shortcode page detection.
 *
 * @since      1.7.5
 * @package    Banoks_POS
 */
class Banoks_POS_Public_Assets {

    /**
     * Track if public assets were enqueued.
     *
     * @var bool
     */
    private static $assets_enqueued = false;

    /**
     * Enqueue public CSS/JS only when needed.
     *
     * @since    1.7.5
     * @return   void
     */
    public function enqueue() {
        if ( ! is_singular() ) {
            return;
        }

        $post = get_post();
        if ( ! $this->page_has_banoks_shortcode( $post ) ) {
            return;
        }

        $this->enqueue_public_assets();
    }

    /**
     * Enqueue public CSS/JS assets.
     *
     * @since    1.7.5
     * @return   void
     */
    public function enqueue_public_assets() {
        if ( self::$assets_enqueued ) {
            return;
        }

        self::$assets_enqueued = true;

        $css = BANOKS_POS_PATH . 'public/css/banoks-online-ordering.css';
        $js  = BANOKS_POS_PATH . 'public/js/banoks-online-ordering.js';

        wp_enqueue_style(
            'banoks-online-poppins',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600;700&display=swap',
            array(),
            null
        );

        $css_version = BANOKS_POS_VERSION . '-' . $this->get_public_css_version( $css );
        $js_version  = BANOKS_POS_VERSION . '-' . ( file_exists( $js ) ? filemtime( $js ) : time() );

        wp_enqueue_style(
            'banoks-online-ordering',
            BANOKS_POS_URL . 'public/css/banoks-online-ordering.css',
            array( 'banoks-online-poppins' ),
            $css_version
        );

        wp_style_add_data( 'banoks-online-ordering', 'preload', true );

        wp_enqueue_script(
            'banoks-online-ordering',
            BANOKS_POS_URL . 'public/js/banoks-online-ordering.js',
            array(),
            $js_version,
            true
        );

        wp_localize_script(
            'banoks-online-ordering',
            'banoksCustomerAuth',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'loginNonce'    => wp_create_nonce( 'banoks_customer_login' ),
                'registerNonce' => wp_create_nonce( 'banoks_customer_register' ),
                'addressNonce'  => wp_create_nonce( 'banoks_customer_add_address' ),
                'checkoutNonce' => wp_create_nonce( 'banoks_customer_checkout' ),
                'paymentStatusNonce' => wp_create_nonce( 'banoks_customer_order_payment_status' ),
                'cartAvailabilityNonce' => wp_create_nonce( 'banoks_cart_item_availability' ),
                'mainPageUrl'   => $this->get_shortcode_page_url( 'banoks_online_menu', '#banoks-online-menu' ),
                'cartPageUrl'   => $this->get_shortcode_page_url( 'banoks_cart', '#banoks-cart' ),
                'deleteIconUrl' => BANOKS_POS_URL . 'public/images/icons_delete.svg',
                'paymongo'      => $this->get_public_paymongo_config(),
            )
        );
    }

    /**
     * Return a cache-busting version for the public CSS manifest and partials.
     *
     * @param string $manifest_path Main public CSS manifest path.
     * @return int
     */
    private function get_public_css_version( $manifest_path ) {
        $latest = file_exists( $manifest_path ) ? filemtime( $manifest_path ) : time();
        $partials = array_merge(
            glob( BANOKS_POS_PATH . 'public/css/online-ordering/*.css' ) ?: array(),
            glob( BANOKS_POS_PATH . 'public/css/online-ordering/shortcodes/*.css' ) ?: array()
        );

        foreach ( $partials as $partial ) {
            $latest = max( $latest, filemtime( $partial ) );
        }

        return $latest;
    }

    /**
     * Preload the main Banoks public stylesheet while still applying it normally.
     *
     * @param string $html   Link tag HTML.
     * @param string $handle Style handle.
     * @param string $href   Stylesheet URL.
     * @param string $media  Media attribute.
     * @return string
     */
    public function preload_stylesheet( $html, $handle, $href, $media ) {
        if ( 'banoks-online-ordering' !== $handle ) {
            return $html;
        }

        $media_attr = $media ? esc_attr( $media ) : 'all';

        return sprintf(
            "<link rel='preload' id='%1\$s-preload' href='%2\$s' as='style' media='%3\$s' />\n<link rel='stylesheet' id='%1\$s-css' href='%2\$s' media='%3\$s' />\n",
            esc_attr( $handle ),
            esc_url( $href ),
            $media_attr
        );
    }

    /**
     * Check normal content and common builder data for Banoks public shortcodes.
     *
     * @param WP_Post|null $post Current post.
     * @return bool
     */
    public function page_has_banoks_shortcode( $post ) {
        if ( ! $post ) {
            return false;
        }

        if ( $this->content_has_text( $post->post_content, '[banoks_' ) ) {
            return true;
        }

        $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
        if ( $this->content_has_text( $elementor_data, '[banoks_' ) ) {
            return true;
        }

        $elementor_data = get_post_meta( $post->ID, '_elementor_page_settings', true );
        if ( $this->content_has_text( $elementor_data, '[banoks_' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check normal content and common builder data for one shortcode.
     *
     * @param WP_Post|null $post      Current post.
     * @param string       $shortcode Shortcode name without brackets.
     * @return bool
     */
    public function page_has_specific_shortcode( $post, $shortcode ) {
        if ( ! $post || '' === $shortcode ) {
            return false;
        }

        if ( $this->content_has_shortcode( $post->post_content, $shortcode ) ) {
            return true;
        }

        $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
        if ( $this->content_has_shortcode( $elementor_data, $shortcode ) ) {
            return true;
        }

        $elementor_data = get_post_meta( $post->ID, '_elementor_page_settings', true );
        if ( $this->content_has_shortcode( $elementor_data, $shortcode ) ) {
            return true;
        }

        return false;
    }

    /**
     * Recursively check text/array content for a plain text fragment.
     *
     * @param mixed  $content Content to inspect.
     * @param string $needle  Text fragment.
     * @return bool
     */
    private function content_has_text( $content, $needle ) {
        if ( is_array( $content ) ) {
            foreach ( $content as $value ) {
                if ( $this->content_has_text( $value, $needle ) ) {
                    return true;
                }
            }
            return false;
        }
        return is_string( $content ) && false !== strpos( $content, $needle );
    }

    /**
     * Recursively check content for an exact shortcode name.
     *
     * @param mixed  $content   Content to inspect.
     * @param string $shortcode Shortcode name without brackets.
     * @return bool
     */
    private function content_has_shortcode( $content, $shortcode ) {
        if ( is_array( $content ) ) {
            foreach ( $content as $value ) {
                if ( $this->content_has_shortcode( $value, $shortcode ) ) {
                    return true;
                }
            }
            return false;
        }

        if ( ! is_string( $content ) ) {
            return false;
        }

        return 1 === preg_match( '/\[' . preg_quote( $shortcode, '/' ) . '(?:[\s\]\/])/', $content );
    }

    /**
     * Get the URL for a page that renders a specific shortcode.
     *
     * @param string $shortcode Shortcode name without brackets.
     * @param string $fragment  Optional URL fragment.
     * @return string
     */
    public function get_shortcode_page_url( $shortcode, $fragment = '' ) {
        $fallback = get_permalink();
        if ( ! $fallback ) {
            $fallback = home_url( '/' );
        }

        $pages = get_posts(
            array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            )
        );

        foreach ( $pages as $page ) {
            if ( $this->page_has_specific_shortcode( $page, $shortcode ) ) {
                return get_permalink( $page ) . $fragment;
            }
        }

        return $fallback . $fragment;
    }

    /**
     * Get PayMongo configuration for the public frontend.
     *
     * @return array
     */
    public function get_public_paymongo_config() {
        $settings = $this->get_paymongo_settings();
        $enabled  = '1' === $settings['enabled'] && ! empty( $settings['public_key'] ) && ! empty( $settings['secret_key'] );

        return array(
            'enabled'   => $enabled,
            'mode'      => $settings['mode'],
            'publicKey' => $enabled ? $settings['public_key'] : '',
        );
    }

    /**
     * Get PayMongo settings with defaults.
     *
     * @return array
     */
    private function get_paymongo_settings() {
        if ( class_exists( 'Banoks_POS_Admin' ) ) {
            return Banoks_POS_Admin::get_paymongo_settings();
        }

        return array(
            'enabled'                => '0',
            'mode'                   => 'test',
            'public_key'             => '',
            'secret_key'             => '',
            'webhook_signing_secret' => '',
        );
    }
}