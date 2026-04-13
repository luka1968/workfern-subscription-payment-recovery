<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation,
 * including database table creation and rewrite rule flushing.
 *
 * @since      1.0.0
 * @package    WorkfernSubscriptionPaymentRecovery
 * @subpackage WorkfernSubscriptionPaymentRecovery/includes
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin activation.
 *
 * @since 1.0.0
 */
class WORKFERN_Activator
{

    /**
     * Handle plugin activation.
     *
     * Creates the required database tables and flushes rewrite rules
     * so that any custom post types or endpoints registered by the
     * plugin are immediately available.
     *
     * @since  1.0.0
     * @return void
     */
    public static function activate()
    {
        self::create_tables();
        flush_rewrite_rules();
    }

    /**
     * Create all custom database tables required by the plugin.
     *
     * Uses WordPress dbDelta() for safe, idempotent table creation
     * and schema updates. Tables are only created if they do not
     * already exist.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        /*
         * =====================================================================
         * 1. Failed Payments Table
         *
         * Stores every failed Stripe payment event along with the associated
         * order, user, invoice ID, failure details, and retry count.
         * =====================================================================
         */
        $table_failed_payments = $wpdb->prefix . 'workfern_failed_payments';

        $sql_failed_payments = "CREATE TABLE {$table_failed_payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_email varchar(255) NOT NULL DEFAULT '',
			stripe_invoice_id varchar(255) NOT NULL DEFAULT '',
			stripe_customer_id varchar(255) NOT NULL DEFAULT '',
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(10) NOT NULL DEFAULT '',
			failure_code varchar(100) NOT NULL DEFAULT '',
			failure_message text NOT NULL,
			attempt_count int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY user_id (user_id),
			KEY customer_email (customer_email),
			KEY stripe_invoice_id (stripe_invoice_id),
			KEY status (status)
		) {$charset_collate};";

        dbDelta($sql_failed_payments);

        /*
         * =====================================================================
         * 2. Recovery Stats Table
         *
         * Stores monthly aggregated statistics for failed and recovered
         * revenue, along with the calculated recovery rate.
         * =====================================================================
         */
        $table_recovery_stats = $wpdb->prefix . 'workfern_recovery_stats';

        $sql_recovery_stats = "CREATE TABLE {$table_recovery_stats} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			month varchar(7) NOT NULL DEFAULT '',
			failed_revenue decimal(15,2) NOT NULL DEFAULT 0.00,
			recovered_revenue decimal(15,2) NOT NULL DEFAULT 0.00,
			recovery_rate float NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY month (month)
		) {$charset_collate};";

        dbDelta($sql_recovery_stats);

        // Store the current database version for future migrations.
        update_option('workfern_db_version', WORKFERN_PLUGIN_VERSION);
    }
}
