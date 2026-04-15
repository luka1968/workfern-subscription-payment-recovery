<?php
/**
 * Plugin Name:       Workfern Subscriptions Recovery for WooCommerce
 * Plugin URI:        https://wordpress.workfern.com/
 * Description:       Automatically recover failed subscription renewal payments in WooCommerce. Tracks failed/recovered revenue and provides an analytics dashboard. Works with any payment gateway.
 * Version:           2.1.4
 * Author:            Workfern
 * Author URI:        https://workfern.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       workfern-subscription-payment-recovery
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   9.6
 *
 * @package WooStripeRecoveryPro
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Plugin constants.
 */
define('WORKFERN_PLUGIN_VERSION', '2.1.4');
define('WORKFERN_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WORKFERN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WORKFERN_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WORKFERN_PRO_URL', 'https://wordpress.workfern.com/');

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 *
 * This plugin uses its own custom database tables (workfern_failed_payments,
 * workfern_recovery_stats) and does not directly query
 * wp_posts for order data. Order lookups use wc_get_orders() which is
 * HPOS-compatible.
 *
 * @since 1.0.0
 */
add_action('before_woocommerce_init', function () {
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

/**
 * Activation hook callback.
 *
 * Runs when the plugin is activated. Sets up default options
 * and flushes rewrite rules if needed.
 *
 * @return void
 */
function workfern_activate()
{
	// Load and run the activator to create database tables.
	require_once WORKFERN_PLUGIN_PATH . 'includes/class-workfern-activator.php';
	WORKFERN_Activator::activate();

	// Store the plugin version on activation.
	update_option('workfern_plugin_version', WORKFERN_PLUGIN_VERSION);

	// Set a flag so the plugin knows it was just activated.
	set_transient('workfern_activated', true, 30);
}
register_activation_hook(__FILE__, 'workfern_activate');

/**
 * Deactivation hook callback.
 *
 * Runs when the plugin is deactivated. Cleans up scheduled events
 * and transients.
 *
 * @return void
 */
function workfern_deactivate()
{
	// Clear any scheduled cron events the plugin may have registered.
	$timestamp = wp_next_scheduled('workfern_scheduled_recovery_check');
	if ($timestamp) {
		wp_unschedule_event($timestamp, 'workfern_scheduled_recovery_check');
	}

	// Remove the activation transient.
	delete_transient('workfern_activated');
}
register_deactivation_hook(__FILE__, 'workfern_deactivate');

/**
 * Check if WooCommerce is active.
 *
 * @return bool True if WooCommerce is active, false otherwise.
 */
function workfern_is_woocommerce_active()
{
	return in_array(
		'woocommerce/woocommerce.php',
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		apply_filters('active_plugins', get_option('active_plugins', array())),
		true
	);
}

/**
 * Check if WooCommerce Subscriptions (or a compatible subscription plugin) is active.
 *
 * Supports:
 * - WooCommerce Subscriptions (official)
 * - YITH WooCommerce Subscription
 *
 * @return bool True if a subscription plugin is active, false otherwise.
 */
function workfern_is_subscriptions_active()
{
	// Check for WooCommerce Subscriptions (official).
	if (in_array(
		'woocommerce-subscriptions/woocommerce-subscriptions.php',
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		apply_filters('active_plugins', get_option('active_plugins', array())),
		true
	)) {
		return true;
	}

	// Check for YITH WooCommerce Subscription.
	if (in_array(
		'yith-woocommerce-subscription/init.php',
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		apply_filters('active_plugins', get_option('active_plugins', array())),
		true
	)) {
		return true;
	}

	// Check for YITH WooCommerce Subscription Premium.
	if (in_array(
		'yith-woocommerce-subscription-premium/init.php',
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		apply_filters('active_plugins', get_option('active_plugins', array())),
		true
	)) {
		return true;
	}

	return false;
}

/**
 * Display an admin notice when WooCommerce is not active.
 *
 * @return void
 */
function workfern_woocommerce_missing_notice()
{
	$install_url = admin_url('plugin-install.php?s=woocommerce&tab=search&type=term');
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<strong><?php esc_html_e('Workfern Subscriptions Recovery for WooCommerce', 'workfern-subscription-payment-recovery'); ?>:</strong>
			<?php
			printf(
				wp_kses(
					/* translators: %s: URL to install WooCommerce */
					__('This plugin requires <strong>WooCommerce</strong> to be installed and activated. <a href="%s">Install WooCommerce</a>.', 'workfern-subscription-payment-recovery'),
					array(
						'strong' => array(),
						'a' => array('href' => array()),
					)
				),
				esc_url($install_url)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display an admin notice when no subscription plugin is active.
 *
 * @return void
 */
function workfern_subscriptions_missing_notice()
{
	$install_url = admin_url('plugin-install.php?s=woocommerce+subscriptions&tab=search&type=term');
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<strong><?php esc_html_e('Workfern Subscriptions Recovery for WooCommerce', 'workfern-subscription-payment-recovery'); ?>:</strong>
			<?php
			printf(
				wp_kses(
					/* translators: %s: URL to install WooCommerce Subscriptions */
					__('This plugin works best with <strong>WooCommerce Subscriptions</strong>. Without a subscription plugin, renewal payment failures cannot be detected. <a href="%s">Install WooCommerce Subscriptions</a>.', 'workfern-subscription-payment-recovery'),
					array(
						'strong' => array(),
						'a' => array('href' => array()),
					)
				),
				esc_url($install_url)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * Checks for WooCommerce dependency and loads the core plugin class
 * if all requirements are met.
 *
 * @return void
 */
function workfern_init()
{
	// Bail early if WooCommerce is not active.
	if (!workfern_is_woocommerce_active()) {
		add_action('admin_notices', 'workfern_woocommerce_missing_notice');
		return;
	}

	// Warn if no subscription plugin is active.
	if (!workfern_is_subscriptions_active()) {
		add_action('admin_notices', 'workfern_subscriptions_missing_notice');
	}

	// Load the main plugin class.
	require_once WORKFERN_PLUGIN_PATH . 'includes/class-workfern-plugin.php';

	// Boot the plugin.
	$plugin = WORKFERN_Plugin::instance();
	$plugin->run();
}
add_action('plugins_loaded', 'workfern_init');

/**
 * Add action links to the plugin listing in the Plugins page.
 *
 * Adds "Settings" and "Go Pro" links next to Deactivate.
 *
 * @since  2.1.0
 *
 * @param  array $links Existing plugin action links.
 * @return array Modified action links.
 */
function workfern_plugin_action_links($links)
{
	$settings_url = admin_url('admin.php?page=workfern-dashboard&tab=settings');
	$pro_url      = WORKFERN_PRO_URL;

	$custom_links = array(
		'settings' => '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'workfern-subscription-payment-recovery') . '</a>',
		'pro'      => '<a href="' . esc_url($pro_url) . '" target="_blank" style="color:#fff; font-weight:bold; background-color:#17d1c6; padding:0 6px; border-radius:3px; display:inline-block; line-height:1.6;">' . esc_html__('GO PRO', 'workfern-subscription-payment-recovery') . '</a>',
	);

	return array_merge($custom_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'workfern_plugin_action_links');

/**
 * Dependency checker: Verifies if WooCommerce Subscriptions is installed.
 * If not installed, gracefully stops the plugin and displays an admin notice.
 */
add_action('admin_init', 'workfern_check_dependencies');
function workfern_check_dependencies() {
    // Check if we are in the admin area, have permissions, and WC_Subscriptions is missing.
    if (is_admin() && current_user_can('activate_plugins') && !class_exists('WC_Subscriptions')) {
        
        // 1. Register a background admin notice.
        add_action('admin_notices', 'workfern_missing_wc_subscriptions_notice');
        
        // 2. Safely deactivate the plugin to prevent fatal errors.
        deactivate_plugins(plugin_basename(__FILE__));
        
        // 3. Prevent the default "plugin activated" green notice.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

/**
 * Render the error notice for missing dependency.
 */
function workfern_missing_wc_subscriptions_notice() {
    $class   = 'notice notice-error is-dismissible';
    $message = __('<strong>Activation failed:</strong> Workfern Subscriptions Recovery requires the <strong>WooCommerce Subscriptions</strong> plugin to function. The plugin has been safely deactivated.', 'workfern-subscription-payment-recovery');
    
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
}
