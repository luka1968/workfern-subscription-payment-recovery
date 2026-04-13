<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks,
 * and public-facing site hooks.
 *
 * @since      1.0.0
 * @package    WooStripeRecoveryPro
 * @subpackage WooStripeRecoveryPro/includes
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The core plugin class.
 *
 * Coordinates the plugin by loading dependencies, registering hooks,
 * and initializing admin and public functionality.
 *
 * @since 1.0.0
 */
class WORKFERN_Plugin
{

    /**
     * The single instance of this class.
     *
     * @since  1.0.0
     * @access private
     * @var    WORKFERN_Plugin|null
     */
    private static $instance = null;

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since  1.0.0
     * @access protected
     * @var    WORKFERN_Loader
     */
    protected $loader;

    /**
     * The database access layer instance.
     *
     * @since  1.0.0
     * @access protected
     * @var    WORKFERN_Database
     */
    protected $database;

    /**
     * The WooCommerce event listener instance.
     *
     * @since  2.0.0
     * @access protected
     * @var    WORKFERN_WC_Listener
     */
    protected $wc_listener;

    /**
     * The unique identifier of this plugin.
     *
     * @since  1.0.0
     * @access protected
     * @var    string
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since  1.0.0
     * @access protected
     * @var    string
     */
    protected $version;

    /**
     * Returns the single instance of this class.
     *
     * @since  1.0.0
     * @return WORKFERN_Plugin Single instance of this class.
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area
     * and the public-facing side of the site.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->plugin_name = 'workfern-subscription-payment-recovery';
        $this->version = defined('WORKFERN_PLUGIN_VERSION') ? WORKFERN_PLUGIN_VERSION : '1.0.0';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_wc_listener_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - WORKFERN_Loader           Ã¢â‚?Orchestrates the hooks of the plugin.
     * - WORKFERN_Database          Ã¢â‚?Handles all database interactions.
     * - WORKFERN_WC_Listener       Ã¢â‚?Listens to WooCommerce internal events.
     * - WORKFERN_Admin             Ã¢â‚?Defines all hooks for the admin area.
     * - WORKFERN_Public            Ã¢â‚?Defines all hooks for the public side of the site.
     *
     * Create instances of the loader and database which will be used
     * throughout the plugin.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once WORKFERN_PLUGIN_PATH . 'includes/class-workfern-loader.php';

        /**
         * The class responsible for all database interactions.
         */
        require_once WORKFERN_PLUGIN_PATH . 'includes/class-workfern-database.php';

        /**
         * The class responsible for listening to WooCommerce internal events
         * (subscription renewal failures, order status transitions).
         */
        require_once WORKFERN_PLUGIN_PATH . 'includes/class-workfern-wc-listener.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once WORKFERN_PLUGIN_PATH . 'admin/class-workfern-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once WORKFERN_PLUGIN_PATH . 'public/class-workfern-public.php';

        $this->loader = new WORKFERN_Loader();
        $this->database = WORKFERN_Database::instance();
        $this->wc_listener = new WORKFERN_WC_Listener($this->database);
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new WORKFERN_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function define_public_hooks()
    {
        $plugin_public = new WORKFERN_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Register the WooCommerce event listener hooks.
     *
     * Hooks into WooCommerce Subscriptions and WooCommerce order status
     * changes to detect subscription renewal payment failures and
     * recoveries Ã¢â‚?without relying on external Stripe Webhooks.
     *
     * @since  2.0.0
     * @access private
     * @return void
     */
    private function define_wc_listener_hooks()
    {
        // The WC Listener registers its own hooks directly via add_action
        // because the events must be available immediately (not deferred
        // through the loader which runs on a later priority).
        $this->wc_listener->register_hooks();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since  1.0.0
     * @return void
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since  1.0.0
     * @return string The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since  1.0.0
     * @return WORKFERN_Loader Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * The reference to the database access layer.
     *
     * @since  1.0.0
     * @return WORKFERN_Database The database access instance.
     */
    public function get_database()
    {
        return $this->database;
    }

    /**
     * The reference to the WooCommerce event listener.
     *
     * @since  2.0.0
     * @return WORKFERN_WC_Listener The WC listener instance.
     */
    public function get_wc_listener()
    {
        return $this->wc_listener;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since  1.0.0
     * @return string The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }
}
