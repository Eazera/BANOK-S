<?php
/**
 * The file that defines the core plugin class
 *
 * Loads plugin dependencies and registers WordPress hooks.
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define plugin hooks and shared dependencies.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes
 * @author     Christian Fulache
 */
class Banoks_POS {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Banoks_POS_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies and set the hooks for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'BANOKS_POS_VERSION' ) ) {
			$this->version = BANOKS_POS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'banoks-pos';

		$this->load_dependencies();
		$this->define_admin_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Includes the loader, AJAX handlers, public frontend hooks, shared services,
	 * database schema handler, and admin controller.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		$includes_path = plugin_dir_path( __FILE__ );
		$plugin_path   = plugin_dir_path( dirname( __FILE__ ) );
		$dependencies  = array(
			$includes_path . 'class-banoks-pos-loader.php',
			$includes_path . 'class-banoks-pos-ajax.php',
			$includes_path . 'public/class-banoks-pos-public-assets.php',
			$includes_path . 'public/class-banoks-customer-auth.php',
			$includes_path . 'public/class-banoks-pos-checkout.php',
			$includes_path . 'public/class-banoks-pos-public-shortcodes.php',
			$includes_path . 'class-banoks-pos-public.php',
			$includes_path . 'class-banoks-pos-ios-pwa.php',
			$includes_path . 'database/class-banoks-db.php',
			$includes_path . 'repositories/class-banoks-product-repository.php',
			$includes_path . 'repositories/class-banoks-order-repository.php',
			$includes_path . 'repositories/class-banoks-customer-repository.php',
			$includes_path . 'repositories/class-banoks-inventory-repository.php',
			$includes_path . 'repositories/class-banoks-online-order-repository.php',
			$includes_path . 'repositories/class-banoks-delivery-area-repository.php',
			$includes_path . 'class-banoks-pos-repository.php',
			$includes_path . 'class-banoks-pos-renderer.php',
			$plugin_path . 'admin/traits/trait-banoks-pos-admin-products.php',
			$plugin_path . 'admin/traits/trait-banoks-pos-admin-online-orders.php',
			$plugin_path . 'admin/traits/trait-banoks-pos-admin-stock.php',
			$plugin_path . 'admin/traits/trait-banoks-pos-admin-delivery-areas.php',
			$plugin_path . 'admin/traits/trait-banoks-pos-admin-requests.php',
			$plugin_path . 'admin/traits/trait-banoks-pos-admin-finance.php',
			$plugin_path . 'admin/traits/trait-banoks-pos-admin-reports.php',
			$plugin_path . 'admin/class-banoks-pos-login.php',
			$plugin_path . 'admin/class-banoks-pos-admin.php',
		);

		foreach ( $dependencies as $dependency ) {
			require_once $dependency;
		}

		$this->loader = new Banoks_POS_Loader();
		new Banoks_POS_Ajax();
		new Banoks_POS_Public();
		new Banoks_POS_IOS_PWA();
		new Banoks_POS_Login();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Banoks_POS_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'maybe_run_migrations' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_ios_pwa_settings' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_paymongo_settings' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'wp_ajax_banoks_pos_save_product_order', $plugin_admin, 'ajax_save_product_order' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Banoks_POS_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
