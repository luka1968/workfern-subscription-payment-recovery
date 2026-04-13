<?php
/**
 * Fired when the plugin is uninstalled (deleted from WP admin).
 *
 * Cleans up all plugin data including options, transients,
 * scheduled events, custom database tables, and user meta.
 *
 * @since   2.1.0
 * @package WooStripeRecoveryPro
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ── Options ──────────────────────────────────────────────────
delete_option( 'workfern_settings' );
delete_option( 'workfern_plugin_version' );
delete_option( 'workfern_db_version' );

// ── Transients ───────────────────────────────────────────────
delete_transient( 'workfern_activated' );

// ── Cron Events ──────────────────────────────────────────────
$workfern_hooks = array(
	'workfern_scheduled_recovery_check',
);

foreach ( $workfern_hooks as $workfern_hook ) {
	$workfern_timestamp = wp_next_scheduled( $workfern_hook );
	if ( $workfern_timestamp ) {
		wp_unschedule_event( $workfern_timestamp, $workfern_hook );
	}
	wp_clear_scheduled_hook( $workfern_hook );
}

// ── Custom Database Tables ───────────────────────────────────
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}workfern_failed_payments" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}workfern_recovery_stats" );
// phpcs:enable WordPress.DB.DirectDatabaseQuery

// ── User Meta ────────────────────────────────────────────────
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'workfern\_%'" );

// ── Object Cache ─────────────────────────────────────────────
wp_cache_flush();
