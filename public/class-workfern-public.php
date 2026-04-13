<?php
/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueuing
 * the public-facing stylesheet and JavaScript.
 *
 * @since      1.0.0
 * @package    WooStripeRecoveryPro
 * @subpackage WooStripeRecoveryPro/public
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * @since 1.0.0
 */
class WORKFERN_Public
{

    /**
     * The ID of this plugin.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * Only loads on WooCommerce checkout and my-account pages where
     * recovery-related UI elements may be displayed.
     *
     * @since  1.0.0
     * @return void
     */
    public function enqueue_styles()
    {
        // Only load on relevant WooCommerce pages.
        if (!$this->is_workfern_relevant_page()) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-public',
            WORKFERN_PLUGIN_URL . 'public/css/workfern-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * Only loads on WooCommerce checkout and my-account pages where
     * recovery-related interactions may occur.
     *
     * @since  1.0.0
     * @return void
     */
    public function enqueue_scripts()
    {
        // Only load on relevant WooCommerce pages.
        if (!$this->is_workfern_relevant_page()) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . '-public',
            WORKFERN_PLUGIN_URL . 'public/js/workfern-public.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name . '-public',
            'workfern_public_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('workfern_public_nonce'),
            )
        );
    }

    /**
     * Determine if the current page is relevant for recovery assets.
     *
     * Assets are only needed on the WooCommerce checkout page and
     * the My Account page where customers may see recovery prompts.
     *
     * @since  1.0.0
     * @access private
     * @return bool True if the current page needs recovery assets.
     */
    private function is_workfern_relevant_page()
    {
        if (!function_exists('is_checkout')) {
            return false;
        }

        return is_checkout() || is_account_page();
    }
}
