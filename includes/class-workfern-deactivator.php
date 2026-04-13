<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
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
 * Fired during plugin deactivation.
 *
 * @since 1.0.0
 */
class WORKFERN_Deactivator
{

    /**
     * Handle plugin deactivation.
     *
     * Flushes rewrite rules to clean up any custom rules that were
     * added by the plugin during activation or runtime.
     *
     * @since  1.0.0
     * @return void
     */
    public static function deactivate()
    {
        flush_rewrite_rules();
    }
}
