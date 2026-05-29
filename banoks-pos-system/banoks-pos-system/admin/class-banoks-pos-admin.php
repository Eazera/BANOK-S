<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Coordinates shared admin setup, menu registration, assets, and page traits.
 *
 * @package    Banoks_POS
 * @subpackage Banoks_POS/admin
 * @author     Christian Fulache
 */
class Banoks_POS_Admin {

    use Banoks_POS_Admin_Products;
    use Banoks_POS_Admin_Online_Orders;
    use Banoks_POS_Admin_Stock;
    use Banoks_POS_Admin_Delivery_Areas;
    use Banoks_POS_Admin_Requests;
    use Banoks_POS_Admin_Finance;
    use Banoks_POS_Admin_Reports;

    const PAYMONGO_OPTION_KEY = 'banoks_pos_paymongo_settings';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
        if ( ! $this->is_banoks_pos_screen() ) {
            return;
        }

        wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
            $this->plugin_name,
            BANOKS_POS_URL . 'admin/css/banoks-pos-admin.css',
            array(),
            $this->get_asset_version( 'admin/css/banoks-pos-admin.css' ),
            'all'
        );

        if ( current_user_can( 'banoks_use_pos' ) && ! current_user_can( 'manage_options' ) ) {
            wp_add_inline_style(
                $this->plugin_name,
                '
                html.wp-toolbar {
                    padding-top: 0 !important;
                }

                #wpadminbar,
                #adminmenumain,
                #screen-meta-links,
                #wpfooter {
                    display: none !important;
                }

                #wpcontent,
                #wpfooter {
                    margin-left: 0 !important;
                }

                #wpbody-content {
                    padding-bottom: 0 !important;
                }

                .auto-fold #wpcontent {
                    margin-left: 0 !important;
                }
                '
            );
        }
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
        if ( ! $this->is_banoks_pos_screen() ) {
            return;
        }

        if (
            $this->is_banoks_pos_screen( 'banoks-pos-reports' )
            && isset( $_GET['report_action'] )
            && 'export_pdf' === sanitize_key( wp_unslash( $_GET['report_action'] ) )
        ) {
            check_admin_referer( 'banoks_export_report_pdf' );
            $start_date = $this->get_request_date( 'start_date', wp_date( 'Y-m-01' ) );
            $end_date   = $this->get_request_date( 'end_date', wp_date( 'Y-m-d' ) );
            $branch_key = isset( $_GET['branch_key'] ) ? sanitize_key( wp_unslash( $_GET['branch_key'] ) ) : Banoks_POS_Repository::STOCK_LOCATION_MANUKAN;
            $this->export_report_pdf( $start_date, $end_date, $branch_key );
        }

        $deps = array( 'jquery' );

        if ( $this->is_banoks_pos_screen( 'banoks-pos-products' ) ) {
            wp_enqueue_media();
            $deps[] = 'jquery-ui-sortable';
        }

        if ( $this->is_banoks_pos_screen( 'banoks-pos-ios-pwa' ) ) {
            wp_enqueue_media();
        }

        if ( $this->is_banoks_pos_screen( 'banoks-pos-reports' ) ) {
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
            $deps[] = 'chart-js';
        }

		wp_enqueue_script(
            $this->plugin_name,
            BANOKS_POS_URL . 'admin/js/banoks-pos-admin.js',
            $deps,
            $this->get_asset_version( 'admin/js/banoks-pos-admin.js' ),
            true
        );
	}

    /**
     * Return a cache-busting version for a plugin asset.
     *
     * @since    1.2.4
     * @param    string $relative_path Path relative to the plugin root.
     * @return   string|int
     */
    private function get_asset_version( $relative_path ) {
        $path = BANOKS_POS_PATH . ltrim( $relative_path, '/\\' );

        return file_exists( $path ) ? filemtime( $path ) : $this->version;
    }

    /**
     * Check whether the current admin page belongs to Banoks POS.
     *
     * @since    1.0.1
     * @param    string $page Optional exact page slug.
     * @return   bool
     */
    private function is_banoks_pos_screen( $page = '' ) {
        $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( '' !== $page ) {
            return $current_page === $page;
        }

        return 0 === strpos( $current_page, 'banoks-pos' );
    }

    /**
     * Return a request date only when it is a valid Y-m-d value.
     *
     * @since    1.0.10
     * @param    string $key Request key.
     * @param    string $default Default date.
     * @return   string
     */
    private function get_request_date( $key, $default ) {
        if ( empty( $_GET[ $key ] ) ) {
            return $default;
        }

        $date = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return $default;
        }

        $parts = explode( '-', $date );
        if ( ! checkdate( intval( $parts[1] ), intval( $parts[2] ), intval( $parts[0] ) ) ) {
            return $default;
        }

        return $date;
    }

    /**
     * Return a request value only when it appears in an allowed list.
     *
     * @since    1.0.10
     * @param    string $key Request key.
     * @param    array  $allowed Allowed values.
     * @param    string $default Default value.
     * @return   string
     */
    private function get_request_choice( $key, $allowed, $default ) {
        if ( empty( $_GET[ $key ] ) ) {
            return $default;
        }

        $value = sanitize_key( wp_unslash( $_GET[ $key ] ) );
        return in_array( $value, $allowed, true ) ? $value : $default;
    }

    /**
     * Run additive schema updates for existing installs.
     *
     * @since    1.0.7
     */
    public function maybe_run_migrations() {
        if ( current_user_can( 'banoks_use_pos' ) && class_exists( 'Banoks_DB' ) ) {
            Banoks_DB::create_tables();
        }
    }

    /**
     * Ensure the products table has the current columns.
     *
     * @since    1.0.4
     */
    private function maybe_update_products_schema() {
        if ( class_exists( 'Banoks_DB' ) ) {
            Banoks_DB::create_tables();
        }
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'Banoks POS',
            'Banoks POS',
            'banoks_use_pos',
            $this->plugin_name,
            array( $this, 'display_plugin_setup_page' ),
            'dashicons-cart',
            30
        );

        foreach ( $this->get_admin_submenu_pages() as $page ) {
            add_submenu_page(
                $this->plugin_name,
                $page['page_title'],
                $page['menu_title'],
                $page['capability'],
                $this->plugin_name . $page['slug_suffix'],
                array( $this, $page['callback'] )
            );
        }
    }

    /**
     * Return Banoks POS submenu page definitions.
     *
     * @since    1.2.4
     * @return   array
     */
    private function get_admin_submenu_pages() {
        return array(
            array(
                'page_title'  => 'Walk-in Orders',
                'menu_title'  => 'Walk-in Orders',
                'capability'  => 'banoks_use_pos',
                'slug_suffix' => '-pos',
                'callback'    => 'display_pos_page',
            ),
            array(
                'page_title'  => 'Dashboard',
                'menu_title'  => 'Dashboard',
                'capability'  => 'manage_options',
                'slug_suffix' => '-owner-dashboard',
                'callback'    => 'display_owner_dashboard_page',
            ),
            array(
                'page_title'  => 'Product Management',
                'menu_title'  => 'Product Management',
                'capability'  => 'manage_options',
                'slug_suffix' => '-products',
                'callback'    => 'display_products_page',
            ),
            array(
                'page_title'  => 'Online Orders',
                'menu_title'  => 'Online Orders',
                'capability'  => 'banoks_use_pos',
                'slug_suffix' => '-online-orders',
                'callback'    => 'display_online_orders_page',
            ),
            array(
                'page_title'  => 'Delivery Areas',
                'menu_title'  => 'Delivery Areas',
                'capability'  => 'manage_options',
                'slug_suffix' => '-delivery-areas',
                'callback'    => 'display_delivery_areas_page',
            ),
            array(
                'page_title'  => 'Stock Management',
                'menu_title'  => 'Stock Management',
                'capability'  => 'manage_options',
                'slug_suffix' => '-stock-management',
                'callback'    => 'display_stock_management_page',
            ),
            array(
                'page_title'  => 'iOS PWA Settings',
                'menu_title'  => 'iOS PWA Settings',
                'capability'  => 'manage_options',
                'slug_suffix' => '-ios-pwa',
                'callback'    => 'display_ios_pwa_settings_page',
            ),
            array(
                'page_title'  => 'PayMongo Payments',
                'menu_title'  => 'PayMongo Payments',
                'capability'  => 'manage_options',
                'slug_suffix' => '-paymongo',
                'callback'    => 'display_paymongo_settings_page',
            ),
            array(
                'page_title'  => 'Business Reports',
                'menu_title'  => 'Reports',
                'capability'  => 'manage_options',
                'slug_suffix' => '-reports',
                'callback'    => 'display_reports_page',
            ),
            array(
                'page_title'  => 'Finance',
                'menu_title'  => 'Finance',
                'capability'  => 'manage_options',
                'slug_suffix' => '-cash-management',
                'callback'    => 'display_cash_management_page',
            ),
            array(
                'page_title'  => 'Requests',
                'menu_title'  => 'Requests',
                'capability'  => 'banoks_use_pos',
                'slug_suffix' => '-expenses',
                'callback'    => 'display_expenses_page',
            ),
        );
    }

    /**
     * Register iOS PWA settings.
     *
     * @since    1.3.9
     */
    public function register_ios_pwa_settings() {
        register_setting(
            'banoks_pos_ios_pwa_settings_group',
            Banoks_POS_IOS_PWA::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( 'Banoks_POS_IOS_PWA', 'sanitize_settings' ),
                'default'           => Banoks_POS_IOS_PWA::defaults(),
            )
        );
    }

    public function register_paymongo_settings() {
        register_setting(
            'banoks_pos_paymongo_settings_group',
            self::PAYMONGO_OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_paymongo_settings' ),
                'default'           => self::get_paymongo_setting_defaults(),
            )
        );
    }

    public static function get_paymongo_setting_defaults() {
        return array(
            'enabled'                => '0',
            'mode'                   => 'test',
            'public_key'             => '',
            'secret_key'             => '',
            'webhook_signing_secret' => '',
        );
    }

    public static function get_paymongo_settings() {
        $settings = get_option( self::PAYMONGO_OPTION_KEY, array() );
        return wp_parse_args( is_array( $settings ) ? $settings : array(), self::get_paymongo_setting_defaults() );
    }

    public function sanitize_paymongo_settings( $input ) {
        $input    = is_array( $input ) ? $input : array();
        $mode     = isset( $input['mode'] ) ? sanitize_key( wp_unslash( $input['mode'] ) ) : 'test';
        $defaults = self::get_paymongo_setting_defaults();

        return array(
            'enabled'                => ! empty( $input['enabled'] ) ? '1' : '0',
            'mode'                   => in_array( $mode, array( 'test', 'live' ), true ) ? $mode : $defaults['mode'],
            'public_key'             => isset( $input['public_key'] ) ? sanitize_text_field( wp_unslash( $input['public_key'] ) ) : '',
            'secret_key'             => isset( $input['secret_key'] ) ? sanitize_text_field( wp_unslash( $input['secret_key'] ) ) : '',
            'webhook_signing_secret' => isset( $input['webhook_signing_secret'] ) ? sanitize_text_field( wp_unslash( $input['webhook_signing_secret'] ) ) : '',
        );
    }

    public function display_paymongo_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'banoks-pos-system' ) );
        }

        $settings     = self::get_paymongo_settings();
        $option_name  = self::PAYMONGO_OPTION_KEY;
        $webhook_url  = rest_url( 'banoks-pos/v1/paymongo/webhook' );
        $return_url   = add_query_arg( 'banoks_paymongo_return', '1', $this->get_frontend_cart_url() );
        ?>
        <div class="wrap banoks-pos-admin banoks-paymongo-settings">
            <h1>PayMongo Payments</h1>
            <p class="description">Configure PayMongo GCash for online checkout. GCash still requires customer approval for each order.</p>

            <form method="post" action="options.php" class="banoks-settings-card">
                <?php settings_fields( 'banoks_pos_paymongo_settings_group' ); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">Enable PayMongo GCash</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>>
                                    Use PayMongo for GCash checkout
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-paymongo-mode">Mode</label></th>
                            <td>
                                <select id="banoks-paymongo-mode" name="<?php echo esc_attr( $option_name ); ?>[mode]">
                                    <option value="test" <?php selected( $settings['mode'], 'test' ); ?>>Test</option>
                                    <option value="live" <?php selected( $settings['mode'], 'live' ); ?>>Live</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-paymongo-public-key">Public Key</label></th>
                            <td>
                                <input type="text" id="banoks-paymongo-public-key" class="regular-text code" name="<?php echo esc_attr( $option_name ); ?>[public_key]" value="<?php echo esc_attr( $settings['public_key'] ); ?>" autocomplete="off">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-paymongo-secret-key">Secret Key</label></th>
                            <td>
                                <input type="password" id="banoks-paymongo-secret-key" class="regular-text code" name="<?php echo esc_attr( $option_name ); ?>[secret_key]" value="<?php echo esc_attr( $settings['secret_key'] ); ?>" autocomplete="off">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-paymongo-webhook-secret">Webhook Signing Secret</label></th>
                            <td>
                                <input type="password" id="banoks-paymongo-webhook-secret" class="regular-text code" name="<?php echo esc_attr( $option_name ); ?>[webhook_signing_secret]" value="<?php echo esc_attr( $settings['webhook_signing_secret'] ); ?>" autocomplete="off">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Webhook URL</th>
                            <td>
                                <code><?php echo esc_html( $webhook_url ); ?></code>
                                <p class="description">Register this URL in PayMongo and subscribe to payment events.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Return URL</th>
                            <td>
                                <code><?php echo esc_html( $return_url ); ?></code>
                                <p class="description">The checkout script sends this URL dynamically when attaching a GCash payment method.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( 'Save PayMongo Settings' ); ?>
            </form>
        </div>
        <?php
    }

    private function get_frontend_cart_url() {
        $fallback = home_url( '/' );
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
            if ( false !== strpos( (string) $page->post_content, '[banoks_cart' ) ) {
                return get_permalink( $page ) . '#banoks-cart';
            }
        }

        return $fallback;
    }

    /**
     * Display the iOS PWA settings page.
     *
     * @since    1.3.9
     */
    public function display_ios_pwa_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'banoks-pos-system' ) );
        }

        $settings      = Banoks_POS_IOS_PWA::get_settings();
        $option_name   = Banoks_POS_IOS_PWA::OPTION_KEY;
        $icon_180_url  = Banoks_POS_IOS_PWA::get_icon_url( $settings['icon_180_id'], 'thumbnail' );
        $icon_192_url  = Banoks_POS_IOS_PWA::get_icon_url( $settings['icon_192_id'], 'thumbnail' );
        $icon_512_url  = Banoks_POS_IOS_PWA::get_icon_url( $settings['icon_512_id'], 'thumbnail' );
        ?>
        <div class="wrap banoks-ios-pwa-settings">
            <h1>Banoks iOS PWA Settings</h1>
            <p class="description">Use these settings for iPhone/iPad Add to Home Screen branding. iOS users still install manually through Safari &rarr; Share &rarr; Add to Home Screen.</p>

            <form method="post" action="options.php" class="banoks-settings-card">
                <?php settings_fields( 'banoks_pos_ios_pwa_settings_group' ); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">Enable iOS PWA</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>>
                                    Enable iPhone/iPad Add to Home Screen meta tags
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-ios-app-name">App Name</label></th>
                            <td>
                                <input type="text" id="banoks-ios-app-name" class="regular-text" name="<?php echo esc_attr( $option_name ); ?>[app_name]" value="<?php echo esc_attr( $settings['app_name'] ); ?>">
                                <p class="description">Full name used by the web app/manifest.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-ios-short-name">Short Name</label></th>
                            <td>
                                <input type="text" id="banoks-ios-short-name" class="regular-text" name="<?php echo esc_attr( $option_name ); ?>[short_name]" value="<?php echo esc_attr( $settings['short_name'] ); ?>">
                                <p class="description">Short app label. Keep this short for the Home Screen.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-ios-site-title">iOS Home Screen Title</label></th>
                            <td>
                                <input type="text" id="banoks-ios-site-title" class="regular-text" name="<?php echo esc_attr( $option_name ); ?>[site_title]" value="<?php echo esc_attr( $settings['site_title'] ); ?>">
                                <p class="description">This controls the Apple mobile web app title.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-ios-theme-color">Theme Color</label></th>
                            <td>
                                <input type="text" id="banoks-ios-theme-color" class="regular-text" name="<?php echo esc_attr( $option_name ); ?>[theme_color]" value="<?php echo esc_attr( $settings['theme_color'] ); ?>" placeholder="#ef1010" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$">
                                <p class="description">Recommended Banoks red: <code>#ef1010</code>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="banoks-ios-status-bar-style">iOS Status Bar Style</label></th>
                            <td>
                                <select id="banoks-ios-status-bar-style" name="<?php echo esc_attr( $option_name ); ?>[status_bar_style]">
                                    <option value="default" <?php selected( $settings['status_bar_style'], 'default' ); ?>>Default</option>
                                    <option value="black" <?php selected( $settings['status_bar_style'], 'black' ); ?>>Black</option>
                                    <option value="black-translucent" <?php selected( $settings['status_bar_style'], 'black-translucent' ); ?>>Black Translucent</option>
                                </select>
                            </td>
                        </tr>
                        <?php
                        $icons = array(
                            'icon_180_id' => array( 'label' => 'Apple Touch Icon', 'hint' => 'Required for iPhone Home Screen. Recommended size: 180 × 180 PNG.', 'url' => $icon_180_url ),
                            'icon_192_id' => array( 'label' => 'Manifest Icon 192', 'hint' => 'Recommended size: 192 × 192 PNG.', 'url' => $icon_192_url ),
                            'icon_512_id' => array( 'label' => 'Manifest Icon 512', 'hint' => 'Recommended size: 512 × 512 PNG.', 'url' => $icon_512_url ),
                        );
                        foreach ( $icons as $field => $icon ) :
                            ?>
                            <tr>
                                <th scope="row"><?php echo esc_html( $icon['label'] ); ?></th>
                                <td>
                                    <div class="banoks-pwa-icon-field" data-target="<?php echo esc_attr( $field ); ?>">
                                        <input type="hidden" id="banoks-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $settings[ $field ] ); ?>">
                                        <div class="banoks-pwa-icon-preview <?php echo $icon['url'] ? 'has-image' : ''; ?>">
                                            <?php if ( $icon['url'] ) : ?>
                                                <img src="<?php echo esc_url( $icon['url'] ); ?>" alt="">
                                            <?php else : ?>
                                                <span>No icon selected</span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="button banoks-upload-pwa-icon">Select Icon</button>
                                        <button type="button" class="button banoks-remove-pwa-icon" <?php echo $icon['url'] ? '' : 'style="display:none;"'; ?>>Remove</button>
                                        <p class="description"><?php echo esc_html( $icon['hint'] ); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button( 'Save iOS PWA Settings' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Return cash source options for expense and stock purchase records.
     *
     * @since    1.0.13
     * @return   array
     */
    private function get_cash_source_options() {
        return array(
            'store_cash'    => 'Manukan Store Balance (Today\'s Sales)',
            'cash_on_hand'  => 'Cash on Hand',
            'gcash_balance' => 'GCash Balance',
            'bank_balance'  => 'Bank Balance',
        );
    }

    private function get_stock_location_options() {
        return array(
            Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION => 'Production Stock',
            Banoks_POS_Repository::STOCK_LOCATION_MANUKAN    => 'Manukan Branch',
        );
    }

    private function sanitize_stock_location_key( $location_key ) {
        $repository = new Banoks_POS_Repository();
        return $repository->sanitize_stock_location_key( $location_key );
    }

    /**
     * Return a valid cash source key.
     *
     * @since    1.0.13
     * @param    string $source Requested source.
     * @return   string
     */
    private function sanitize_cash_source( $source ) {
        $source = sanitize_key( $source );
        return isset( $this->get_cash_source_options()[ $source ] ) ? $source : 'store_cash';
    }

    /**
     * Return inventory units supported by stock management.
     *
     * @since    1.0.11
     * @return   array
     */
    private function get_stock_unit_options() {
        return array(
            'pcs'      => 'Pieces',
            'servings' => 'Servings',
            'sticks'   => 'Sticks',
            'bottles'  => 'Bottles',
            'packs'    => 'Packs',
            'kg'       => 'Kilograms',
            'g'        => 'Grams',
            'liters'   => 'Liters',
            'ml'       => 'Milliliters',
        );
    }

    /**
     * Render the POS ordering interface.
     * Render the Shared Header for Dashboard and POS.
     *
     * @since    1.0.0
     */
    public function display_admin_header() {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $walkin_sales = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(grand_total) FROM {$wpdb->prefix}banoks_orders WHERE date = %s AND status = 'completed'", $today ) ) ?: 0;
        $online_sales = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_amount) FROM {$wpdb->prefix}banoks_online_orders WHERE DATE(created_at) = %s AND order_status = 'completed'", $today ) ) ?: 0;
        $sales = floatval( $walkin_sales ) + floatval( $online_sales );
        $total_expenses = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$wpdb->prefix}banoks_expenses WHERE date = %s AND cash_source = 'store_cash'", $today ) ) ?: 0;
        $total_expenses += $this->get_stock_cash_expenses_for_period( $today, $today, 'store_cash' );
        $sales = $sales - $total_expenses;
        
        $current_user = wp_get_current_user();
        $cashier_name = ! empty( $current_user->display_name ) ? $current_user->display_name : $current_user->user_login;

        $show_nav      = true;
        $dashboard_url = admin_url( 'admin.php?page=banoks-pos&view=pending' );
        $pending_request_count = current_user_can( 'manage_options' ) ? $this->get_owner_request_count( 'pending' ) : 0;

        include BANOKS_POS_PATH . 'templates/parts/admin-header.php';
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page() {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        
        $repository = new Banoks_POS_Repository();
        $critical_inventory_alerts = $repository->get_inventory_stock_alerts( 5 );

        $active_date = $this->get_request_date( 'date', $today );
        $status_filter = $this->get_request_choice( 'status', array( 'all', 'pending', 'preparing', 'completed', 'cancelled' ), 'all' );
        $search_query = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        $has_date_param = isset( $_GET['date'] );

        $active_orders = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}banoks_orders
             WHERE status IN ('pending', 'preparing')
             ORDER BY entry_timestamp DESC
             LIMIT 100"
        );
        $history_orders = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}banoks_orders
             WHERE status IN ('completed', 'cancelled')
             ORDER BY entry_timestamp DESC
             LIMIT 300"
        );
        $orders = $active_orders;

        include_once plugin_dir_path( __FILE__ ) . 'partials/banoks-pos-admin-display.php';
    }

    public function display_owner_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'banoks-pos-system' ) );
        }

        $this->display_admin_header();
        $this->maybe_update_products_schema();

        $message = '';
        $error   = '';

        if ( isset( $_POST['banoks_owner_request_action'] ) ) {
            check_admin_referer( 'banoks_owner_request_action' );
            $result = $this->handle_owner_request_decision();
            if ( isset( $result['error'] ) ) {
                $error = $result['error'];
            } else {
                $message = $result['message'];
            }
        }

        $today                 = current_time( 'Y-m-d' );
        $dashboard_branch_key  = Banoks_POS_Repository::STOCK_LOCATION_MANUKAN;
        $dashboard_branch_name = 'Manukan Branch';
        foreach ( $this->get_active_branches() as $branch ) {
            if ( $dashboard_branch_key === sanitize_key( $branch->branch_key ) ) {
                $dashboard_branch_name = $branch->branch_name;
                break;
            }
        }

        $dashboard_total_sales    = $this->get_branch_sales_for_period( $today, $today, $dashboard_branch_key );
        $dashboard_total_expenses = $this->get_report_expense_total_for_branch( $today, $today, $dashboard_branch_key );
        $dashboard_final_sale     = $dashboard_total_sales - $dashboard_total_expenses;
        $dashboard_chart_expenses = max( 0, $dashboard_total_expenses );
        $dashboard_chart_final    = max( 0, $dashboard_final_sale );
        $dashboard_chart_total    = $dashboard_chart_expenses + $dashboard_chart_final;
        $dashboard_expense_pct    = $dashboard_chart_total > 0 ? min( 100, max( 0, ( $dashboard_chart_expenses / $dashboard_chart_total ) * 100 ) ) : 0;
        $dashboard_final_pct      = $dashboard_chart_total > 0 ? max( 0, 100 - $dashboard_expense_pct ) : 0;

        $pending_request_count = $this->get_owner_request_count( 'pending' );
        $owner_product_branches = $this->get_active_branches();
        $owner_cards      = array(
            array( 'label' => 'Product Management', 'url' => '#banoks-owner-product-branch-modal', 'desc' => 'Choose a branch before managing product stock.', 'modal' => 'banoks-owner-product-branch-modal' ),
            array( 'label' => 'Stock Management', 'url' => admin_url( 'admin.php?page=banoks-pos-stock-management' ), 'desc' => 'Manage Production and Manukan Branch stock.' ),
            array( 'label' => 'Requests', 'url' => admin_url( 'admin.php?page=banoks-pos-expenses' ), 'desc' => 'Review worker requests and expense history.', 'badge' => $pending_request_count ),
            array( 'label' => 'Finance', 'url' => admin_url( 'admin.php?page=banoks-pos-cash-management' ), 'desc' => 'Track GCash, bank, and claimed store sales.' ),
            array( 'label' => 'Reports', 'url' => admin_url( 'admin.php?page=banoks-pos-reports' ), 'desc' => 'Review sales, expenses, and transactions.' ),
            array( 'label' => 'Delivery Areas', 'url' => admin_url( 'admin.php?page=banoks-pos-delivery-areas' ), 'desc' => 'Manage online delivery areas and fees.' ),
        );

        include_once plugin_dir_path( __FILE__ ) . 'partials/banoks-pos-owner-dashboard-display.php';
    }

    /**
     * Render the POS ordering interface.
     *
     * @since    1.0.0
     */
    public function display_pos_page() {
        $repository = new Banoks_POS_Repository();
        $renderer   = new Banoks_POS_Renderer();
        $data       = $repository->get_pos_data(
            array(
                'active_date' => $this->get_request_date( 'date', current_time( 'Y-m-d' ) ),
            )
        );

        $data['show_header']   = true;
        $data['show_nav']      = true;
        $data['dashboard_url'] = admin_url( 'admin.php?page=banoks-pos&view=pending' );
        $data['is_shortcode']  = false;

        echo $renderer->render( 'pos', $data );
    }

}
