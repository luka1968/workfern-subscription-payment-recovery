<?php
/**
 * Database access layer for the plugin.
 *
 * Handles all direct database interactions using WordPress $wpdb
 * with prepared statements for security.
 *
 * @since      1.0.0
 * @package    WorkfernSubscriptionPaymentRecovery
 * @subpackage WorkfernSubscriptionPaymentRecovery/includes
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
/**
 * Database access class.
 *
 * Provides methods for CRUD operations on the plugin's custom tables:
 * workfern_failed_payments and workfern_recovery_stats.
 *
 * @since 1.0.0
 */
class WORKFERN_Database
{

    /**
     * The single instance of this class.
     *
     * @since  1.0.0
     * @access private
     * @var    WORKFERN_Database|null
     */
    private static $instance = null;

    /**
     * WordPress database object.
     *
     * @since  1.0.0
     * @access private
     * @var    wpdb
     */
    private $wpdb;

    /**
     * Failed payments table name (prefixed).
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $table_failed_payments;


    /**
     * Recovery stats table name (prefixed).
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $table_recovery_stats;

    /**
     * Returns the single instance of this class.
     *
     * @since  1.0.0
     * @return WORKFERN_Database
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the class.
     *
     * Sets up table name references using the WordPress table prefix.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        global $wpdb;

        $wpdb = $wpdb;
        $this->table_failed_payments = $wpdb->prefix . 'workfern_failed_payments';
        $this->table_recovery_stats  = $wpdb->prefix . 'workfern_recovery_stats';
    }

    /*
    |--------------------------------------------------------------------------
    | Failed Payments
    |--------------------------------------------------------------------------
    */

    /**
     * Insert a new failed payment record.
     *
     * @since 1.0.0
     *
     * @param array $data {
     *     Payment data to insert.
     *
     *     @type int    $order_id          WooCommerce order ID.
     *     @type int    $user_id           WordPress user ID.
     *     @type string $stripe_invoice_id Stripe invoice identifier.
     *     @type string $failure_code      Stripe failure code.
     *     @type string $failure_message   Human-readable failure message.
     *     @type int    $attempt_count     Number of payment attempts so far.
     *     @type string $status            Record status (pending|retrying|recovered|failed).
     * }
     * @return int|false The inserted row ID on success, false on failure.
     */
    public function insert_failed_payment($data)
    {
        $defaults = array(
            'order_id' => 0,
            'user_id' => 0,
            'customer_email' => '',
            'stripe_invoice_id' => '',
            'stripe_customer_id' => '',
            'amount' => 0.00,
            'currency' => '',
            'failure_code' => '',
            'failure_message' => '',
            'attempt_count' => 0,
            'status' => 'pending',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        );

        $data = wp_parse_args($data, $defaults);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert(
            $this->table_failed_payments,
            array(
                'order_id' => absint($data['order_id']),
                'user_id' => absint($data['user_id']),
                'customer_email' => sanitize_email($data['customer_email']),
                'stripe_invoice_id' => sanitize_text_field($data['stripe_invoice_id']),
                'stripe_customer_id' => sanitize_text_field($data['stripe_customer_id']),
                'amount' => floatval($data['amount']),
                'currency' => sanitize_text_field($data['currency']),
                'failure_code' => sanitize_text_field($data['failure_code']),
                'failure_message' => sanitize_textarea_field($data['failure_message']),
                'attempt_count' => absint($data['attempt_count']),
                'status' => sanitize_key($data['status']),
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at'],
            ),
            array(
                '%d', // order_id
                '%d', // user_id
                '%s', // customer_email
                '%s', // stripe_invoice_id
                '%s', // stripe_customer_id
                '%f', // amount
                '%s', // currency
                '%s', // failure_code
                '%s', // failure_message
                '%d', // attempt_count
                '%s', // status
                '%s', // created_at
                '%s', // updated_at
            )
        );

        if (false === $result) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get a failed payment record by its ID.
     *
     * @since 1.0.0
     *
     * @param int $id The record ID.
     * @return object|null The record object on success, null if not found.
     */
    public function get_failed_payment_by_id($id)
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}workfern_failed_payments WHERE id = %d",
                absint($id)
            )
        );
    }

    /**
     * Update the status of a failed payment record.
     *
     * @since 1.0.0
     *
     * @param int    $payment_id The failed payment record ID.
     * @param string $status     The new status (pending|retrying|recovered|failed).
     * @return bool True on success, false on failure.
     */
    public function update_payment_status($payment_id, $status)
    {
        $allowed_statuses = array('pending', 'retrying', 'recovered', 'failed');

        if (!in_array($status, $allowed_statuses, true)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_failed_payments,
            array(
                'status' => sanitize_key($status),
                'updated_at' => current_time('mysql', true),
            ),
            array(
                'id' => absint($payment_id),
            ),
            array(
                '%s', // status
                '%s', // updated_at
            ),
            array(
                '%d', // id
            )
        );

        return false !== $result;
    }

    /**
     * Increment the attempt count for a failed payment record.
     *
     * Uses a direct SQL query with prepared statement to perform
     * an atomic increment operation.
     *
     * @since 1.0.0
     *
     * @param int $payment_id The failed payment record ID.
     * @return bool True on success, false on failure.
     */
    public function increment_attempt_count($payment_id)
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}workfern_failed_payments
				SET attempt_count = attempt_count + 1,
				    updated_at = %s
				WHERE id = %d",
                current_time('mysql', true),
                absint($payment_id)
            )
        );

        return false !== $result;
    }

    /**
     * Retrieve failed payment records with optional filtering.
     *
     * @since 1.0.0
     *
     * @param array $args {
     *     Optional. Query arguments.
     *
     *     @type string $status   Filter by status. Default empty (all statuses).
     *     @type int    $user_id  Filter by user ID. Default 0 (all users).
     *     @type int    $order_id Filter by order ID. Default 0 (all orders).
     *     @type string $orderby  Column to order by. Default 'created_at'.
     *     @type string $order    Sort direction (ASC|DESC). Default 'DESC'.
     *     @type int    $per_page Number of records per page. Default 20.
     *     @type int    $paged    Current page number. Default 1.
     * }
     * @return array {
     *     @type array $items       Array of result objects.
     *     @type int   $total_items Total number of matching records.
     *     @type int   $total_pages Total number of pages.
     * }
     */
    public function get_failed_payments($args = array())
    {
        $defaults = array(
            'status' => '',
            'user_id' => 0,
            'order_id' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'per_page' => 20,
            'paged' => 1,
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clauses.
        $where = array();
        $prepare = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $prepare[] = sanitize_key($args['status']);
        }

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $prepare[] = absint($args['user_id']);
        }

        if (!empty($args['order_id'])) {
            $where[] = 'order_id = %d';
            $prepare[] = absint($args['order_id']);
        }

        $where_sql = esc_sql('');
        if (!empty($where)) {
            $where_sql = esc_sql('WHERE ' . implode(' AND ', $where));
        }

        // Whitelist orderby column.
        $allowed_orderby = array('id', 'order_id', 'user_id', 'attempt_count', 'status', 'created_at', 'updated_at');
        $orderby = esc_sql(in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at');
        $order = esc_sql('ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC');

        $per_page = absint($args['per_page']);
        $paged = absint($args['paged']);
        $offset = ($paged - 1) * $per_page;

        // Count total items.
        if (!empty($prepare)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $total_items = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}workfern_failed_payments {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ...$prepare
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $total_items = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}workfern_failed_payments"
            );
        }

        // Fetch paginated results.
        $limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $per_page, $offset);

        if (!empty($prepare)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}workfern_failed_payments {$where_sql} ORDER BY {$orderby} {$order} {$limit_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ...$prepare
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $items = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}workfern_failed_payments ORDER BY {$orderby} {$order} {$limit_clause}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }

        return array(
            'items' => $items ? $items : array(),
            'total_items' => $total_items,
            'total_pages' => $per_page > 0 ? (int) ceil($total_items / $per_page) : 1,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Recovery Stats
    |--------------------------------------------------------------------------
    */

    /**
     * Retrieve recovery statistics with optional filtering.
     *
     * @since 1.0.0
     *
     * @param array $args {
     *     Optional. Query arguments.
     *
     *     @type string $month    Filter by specific month (YYYY-MM). Default empty (all months).
     *     @type string $orderby  Column to order by. Default 'month'.
     *     @type string $order    Sort direction (ASC|DESC). Default 'DESC'.
     *     @type int    $per_page Number of records per page. Default 12.
     *     @type int    $paged    Current page number. Default 1.
     * }
     * @return array {
     *     @type array $items       Array of result objects.
     *     @type int   $total_items Total number of matching records.
     *     @type int   $total_pages Total number of pages.
     * }
     */
    public function get_recovery_stats($args = array())
    {
        $defaults = array(
            'month' => '',
            'orderby' => 'month',
            'order' => 'DESC',
            'per_page' => 12,
            'paged' => 1,
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause.
        $where_sql = esc_sql('');
        $prepare = array();

        if (!empty($args['month'])) {
            $where_sql = esc_sql('WHERE month = %s');
            $prepare[] = sanitize_text_field($args['month']);
        }

        // Whitelist orderby column.
        $allowed_orderby = array('id', 'month', 'failed_revenue', 'recovered_revenue', 'recovery_rate', 'created_at');
        $orderby = esc_sql(in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'month');
        $order = esc_sql('ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC');

        $per_page = absint($args['per_page']);
        $paged = absint($args['paged']);
        $offset = ($paged - 1) * $per_page;

        // Count total items.
        if (!empty($prepare)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $total_items = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}workfern_recovery_stats {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ...$prepare
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $total_items = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}workfern_recovery_stats"
            );
        }

        // Fetch paginated results.
        $limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $per_page, $offset);

        if (!empty($prepare)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}workfern_recovery_stats {$where_sql} ORDER BY {$orderby} {$order} {$limit_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ...$prepare
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $items = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}workfern_recovery_stats ORDER BY {$orderby} {$order} {$limit_clause}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }

        return array(
            'items' => $items ? $items : array(),
            'total_items' => $total_items,
            'total_pages' => $per_page > 0 ? (int) ceil($total_items / $per_page) : 1,
        );
    }

    /**
     * Insert or update monthly recovery stats.
     *
     * Uses REPLACE INTO to upsert based on the unique month key.
     *
     * @since 1.0.0
     *
     * @param array $data {
     *     Stats data to upsert.
     *
     *     @type string $month             The month in YYYY-MM format.
     *     @type float  $failed_revenue    Total failed revenue for the month.
     *     @type float  $recovered_revenue Total recovered revenue for the month.
     *     @type float  $recovery_rate     Recovery rate percentage.
     * }
     * @return int|false The row ID on success, false on failure.
     */
    public function upsert_recovery_stats($data)
    {
        $defaults = array(
            'month' => '',
            'failed_revenue' => 0.00,
            'recovered_revenue' => 0.00,
            'recovery_rate' => 0.00,
            'created_at' => current_time('mysql', true),
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['month'])) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $result = $wpdb->replace(
            $this->table_recovery_stats,
            array(
                'month' => sanitize_text_field($data['month']),
                'failed_revenue' => floatval($data['failed_revenue']),
                'recovered_revenue' => floatval($data['recovered_revenue']),
                'recovery_rate' => floatval($data['recovery_rate']),
                'created_at' => $data['created_at'],
            ),
            array(
                '%s', // month
                '%f', // failed_revenue
                '%f', // recovered_revenue
                '%f', // recovery_rate
                '%s', // created_at
            )
        );

        if (false === $result) {
            return false;
        }

        return $wpdb->insert_id;
    }



    /**
     * Get a failed payment record by Stripe invoice ID.
     *
     * @since 1.0.0
     *
     * @param string $stripe_invoice_id The Stripe invoice identifier.
     * @return object|null The record object on success, null if not found.
     */
    public function get_failed_payment_by_invoice($stripe_invoice_id)
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}workfern_failed_payments WHERE stripe_invoice_id = %s",
                sanitize_text_field($stripe_invoice_id)
            )
        );
    }
}
