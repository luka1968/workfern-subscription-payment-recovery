<?php
/**
 * WooCommerce Internal Event Listener.
 *
 * Hooks into WooCommerce Subscriptions and WooCommerce order status
 * transitions to detect subscription renewal payment failures and
 * successful recoveries Ã¢ï¿?without relying on Stripe Webhooks.
 *
 * This replaces the previous Stripe Webhook controller.
 *
 * @since      2.0.0
 * @package    WooStripeRecoveryPro
 * @subpackage WooStripeRecoveryPro/includes
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Internal Event Listener.
 *
 * Listens to WooCommerce internal hooks to detect:
 *  - Subscription renewal payment failures
 *  - Successful recovery of previously-failed payments
 *
 * Works with any payment gateway (Stripe, PayPal, Braintree, etc.)
 * because it relies on WooCommerce order status, not gateway-specific webhooks.
 *
 * @since 2.0.0
 */
class WORKFERN_WC_Listener
{

    /**
     * Database access layer.
     *
     * @since  2.0.0
     * @access private
     * @var    WORKFERN_Database
     */
    private $database;

    /**
     * Initialize the WooCommerce listener.
     *
     * @since 2.0.0
     *
     * @param WORKFERN_Database $database The database access layer instance.
     */
    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Register all WooCommerce hooks.
     *
     * Called from the plugin orchestrator (WORKFERN_Plugin) during
     * the hook registration phase.
     *
     * @since  2.0.0
     * @return void
     */
    public function register_hooks()
    {
        /*
         * ----------------------------------------------------------------
         * 1. Primary hook: WooCommerce Subscriptions renewal failure.
         *
         *    Fires when WCS attempts a renewal payment and the gateway
         *    returns a failure. This is the most precise hook because
         *    it only fires for subscription renewals, never for
         *    regular front-end checkout failures.
         *
         *    Parameters: WC_Subscription $subscription, WC_Order $renewal_order
         * ----------------------------------------------------------------
         */
        add_action(
            'woocommerce_subscription_renewal_payment_failed',
            array($this, 'on_renewal_payment_failed'),
            10,
            2
        );

        /*
         * ----------------------------------------------------------------
         * 2. Fallback hook: Generic WooCommerce order status Ã¢ï¿?failed.
         *
         *    This fires whenever ANY order transitions to 'failed'.
         *    We filter inside the callback to only process renewal
         *    orders, so regular checkout failures are ignored.
         *
         *    This acts as a safety net in case the primary hook above
         *    does not fire (e.g., custom gateway implementations).
         *
         *    Parameters: int $order_id, WC_Order $order
         * ----------------------------------------------------------------
         */
        add_action(
            'woocommerce_order_status_failed',
            array($this, 'on_order_status_failed'),
            10,
            2
        );

        /*
         * ----------------------------------------------------------------
         * 3. Recovery hook: Order transitions to paid statuses.
         *
         *    Uses the generic woocommerce_order_status_changed hook
         *    instead of the specific failed_to_processing hooks.
         *
         *    Why? When a customer pays a failed renewal order at checkout,
         *    WooCommerce first transitions the order to 'pending' before
         *    processing payment. The actual path is:
         *
         *        Failed Ã¢ï¿?Pending Ã¢ï¿?Processing
         *
         *    The old failed_to_processing hook only fires on a DIRECT
         *    transition, so it misses the recovery entirely.
         *
         *    This generic hook catches ALL status changes. We filter
         *    inside the callback to only act when the order lands on
         *    a paid status AND has a matching failure record.
         * ----------------------------------------------------------------
         */
        add_action(
            'woocommerce_order_status_changed',
            array($this, 'check_order_recovery_transition'),
            10,
            4
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Failure Handlers
    |--------------------------------------------------------------------------
    */

    /**
     * Handle WooCommerce Subscriptions renewal payment failure.
     *
     * This is the PRIMARY handler. Fires only for subscription
     * renewal attempts, which is exactly what we want.
     *
     * @since 2.0.0
     *
     * @param WC_Subscription $subscription  The WC Subscription object.
     * @param WC_Order        $renewal_order The renewal WC_Order object.
     * @return void
     */
    public function on_renewal_payment_failed($subscription, $renewal_order)
    {
        if (!$renewal_order || !is_a($renewal_order, 'WC_Order')) {
            return;
        }

        $order_id = $renewal_order->get_id();

        if (!$order_id) {
            return;
        }

        // Prevent duplicate processing.
        if ($this->is_already_recorded($order_id)) {
            $this->increment_existing_record($order_id);
            return;
        }

        $this->record_payment_failure($renewal_order, $subscription);
    }

    /**
     * Handle generic WooCommerce order status transition to 'failed'.
     *
     * This is the FALLBACK handler. It fires for ALL orders that
     * transition to 'failed', so we must filter to only process
     * subscription renewal orders.
     *
     * @since 2.0.0
     *
     * @param int      $order_id The WooCommerce order ID.
     * @param WC_Order $order    The WC_Order object (may not be passed by all WC versions).
     * @return void
     */
    public function on_order_status_failed($order_id, $order = null)
    {
        // Get the order object if not provided.
        if (!$order || !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        /*
         * CRITICAL FILTER: Only process subscription renewal orders.
         *
         * We check whether this order is a renewal order created by
         * WooCommerce Subscriptions. If it is a regular front-end
         * checkout order, we skip it completely to avoid false positives.
         */
        if (!$this->is_subscription_renewal_order($order)) {
            return;
        }

        // If the primary hook already handled this, skip.
        if ($this->is_already_recorded($order_id)) {
            return;
        }

        // Try to get the parent subscription.
        $subscription = $this->get_subscription_for_order($order);

        $this->record_payment_failure($order, $subscription);
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Recovery Handler
    |--------------------------------------------------------------------------
    */

    /**
     * Intercept all order status changes to detect recoveries.
     *
     * Only passes control to on_order_recovered() when the new status
     * is a "paid" status (processing, completed, on-hold).
     *
     * @since 2.0.1
     *
     * @param int      $order_id   The WooCommerce order ID.
     * @param string   $old_status The previous order status.
     * @param string   $new_status The new order status.
     * @param WC_Order $order      The WC_Order object.
     * @return void
     */
    public function check_order_recovery_transition($order_id, $old_status, $new_status, $order)
    {
        // Only react when the order lands on a paid/success status.
        $paid_statuses = array('processing', 'completed', 'on-hold');

        if (!in_array($new_status, $paid_statuses, true)) {
            return;
        }

        $this->on_order_recovered($order_id, $order);
    }

    /**
     * Handle order transition from failed to a successful status.
     *
     * When a previously-failed renewal order is paid (either by the
     * customer clicking the "Pay Now" link, or by admin manually
     * processing the payment), we mark the failure record as recovered.
     *
     * @since 2.0.0
     *
     * @param int      $order_id The WooCommerce order ID.
     * @param WC_Order $order    The WC_Order object.
     * @return void
     */
    public function on_order_recovered($order_id, $order = null)
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        // Only process subscription renewal orders.
        if (!$this->is_subscription_renewal_order($order)) {
            return;
        }

        // Find the existing failure record for this order.
        $existing = $this->get_failure_record_by_order($order_id);

        if (!$existing || 'recovered' === $existing->status) {
            return;
        }

        /*
         * ----------------------------------------------------------------
         * 1. Update status to recovered.
         * ----------------------------------------------------------------
         */
        $this->database->update_payment_status($existing->id, 'recovered');

        /*
         * ----------------------------------------------------------------
         * 2. Calculate recovered revenue.
         * ----------------------------------------------------------------
         */
        $recovered_amount = floatval($order->get_total());
        $currency = $order->get_currency();

        /*
         * ----------------------------------------------------------------
         * 3. Update monthly recovery stats.
         * ----------------------------------------------------------------
         */
        $current_month = current_time('Y-m');
        $this->update_monthly_recovery_stats($current_month, $recovered_amount);

        /*
         * ----------------------------------------------------------------
         * 4. Log recovery and fire action.
         * ----------------------------------------------------------------
         */
        $recovered_at = current_time('mysql', true);

        error_log( /* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log */ sprintf(
            '[WORKFERN] Payment recovered for order #%d. Amount: %s %s.',
            $order_id,
            $currency,
            number_format($recovered_amount, 2)
        ));

        /**
         * Fires after a failed payment has been successfully recovered.
         *
         * @since 2.0.0
         *
         * @param object   $existing         The failed payment database record.
         * @param WC_Order $order            The WooCommerce order object.
         * @param float    $recovered_amount The recovered revenue amount.
         * @param string   $currency         The currency code.
         * @param string   $recovered_at     The UTC timestamp of the recovery.
         */
        do_action('workfern_payment_recovered', $existing, $order, $recovered_amount, $currency, $recovered_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Record Payment Failure
    |--------------------------------------------------------------------------
    */

    /**
     * Record a subscription renewal payment failure in the database.
     *
     * Extracts all necessary data from the WooCommerce order object
     * and inserts a new record into the workfern_failed_payments table.
     *
     * @since  2.0.0
     * @access private
     *
     * @param WC_Order             $order        The failed renewal order.
     * @param WC_Subscription|null $subscription The parent subscription (optional).
     * @return void
     */
    private function record_payment_failure($order, $subscription = null)
    {
        $order_id = $order->get_id();
        $user_id = $order->get_user_id();

        /*
         * ----------------------------------------------------------------
         * 1. Extract customer information from the WC Order.
         * ----------------------------------------------------------------
         */
        $customer_email = $order->get_billing_email();

        if (empty($customer_email) && $user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $customer_email = $user->user_email;
            }
        }

        $amount = floatval($order->get_total());
        $currency = strtoupper($order->get_currency());

        /*
         * ----------------------------------------------------------------
         * 2. Extract Stripe-specific metadata (if available).
         *
         *    These are stored as order meta by the WooCommerce Stripe
         *    Gateway plugin. They are optional Ã¢ï¿?the plugin works
         *    without them (for non-Stripe gateways).
         * ----------------------------------------------------------------
         */
        $stripe_customer_id = $order->get_meta('_stripe_customer_id', true);
        $stripe_invoice_id = $order->get_meta('_stripe_invoice_id', true);

        // Fallback: try to get Stripe customer ID from user meta.
        if (empty($stripe_customer_id) && $user_id) {
            $stripe_customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
        }

        /*
         * ----------------------------------------------------------------
         * 3. Extract failure details.
         *
         *    WooCommerce stores the last payment error as an order note.
         *    We also check for gateway-specific failure metadata.
         * ----------------------------------------------------------------
         */
        $failure_code = '';
        $failure_message = '';

        // Try Stripe-specific failure metadata.
        $stripe_failure_code = $order->get_meta('_stripe_failure_code', true);
        $stripe_failure_message = $order->get_meta('_stripe_failure_message', true);

        if (!empty($stripe_failure_code)) {
            $failure_code = sanitize_text_field($stripe_failure_code);
        }

        if (!empty($stripe_failure_message)) {
            $failure_message = sanitize_textarea_field($stripe_failure_message);
        }

        // Fallback: get the last order note as the failure message.
        if (empty($failure_message)) {
            $failure_message = $this->get_last_order_note($order_id);
        }

        // Final fallback.
        if (empty($failure_message)) {
            $failure_message = __('Subscription renewal payment failed.', 'workfern-subscription-payment-recovery');
        }

        /*
         * ----------------------------------------------------------------
         * 4. Determine the payment gateway used.
         * ----------------------------------------------------------------
         */
        $payment_method = $order->get_payment_method();

        if (empty($failure_code) && !empty($payment_method)) {
            $failure_code = $payment_method . '_declined';
        }

        /*
         * ----------------------------------------------------------------
         * 5. Insert the failure record into the database.
         * ----------------------------------------------------------------
         */
        $insert_data = array(
            'order_id'           => absint($order_id),
            'user_id'            => absint($user_id),
            'customer_email'     => sanitize_email($customer_email),
            'stripe_invoice_id'  => sanitize_text_field($stripe_invoice_id),
            'stripe_customer_id' => sanitize_text_field($stripe_customer_id),
            'amount'             => $amount,
            'currency'           => $currency,
            'failure_code'       => $failure_code,
            'failure_message'    => $failure_message,
            'attempt_count'      => 0,
            'status'             => 'pending',
        );

        $insert_id = $this->database->insert_failed_payment($insert_data);

        if (false === $insert_id) {
            error_log( /* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log */ sprintf(
                '[WORKFERN] Failed to insert payment failure record for order #%d.',
                $order_id
            ));
            return;
        }

        // Mark the order with our tracking meta to prevent duplicates.
        $order->update_meta_data('_workfern_failure_recorded', 'yes');
        $order->update_meta_data('_workfern_failure_record_id', $insert_id);
        $order->save();

        error_log( /* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log */ sprintf(
            '[WORKFERN] Payment failure recorded for order #%d (record #%d). Amount: %s %s. Email: %s.',
            $order_id,
            $insert_id,
            $currency,
            number_format($amount, 2),
            $customer_email
        ));

        /*
         * ----------------------------------------------------------------
         * 6. Update monthly failure stats.
         * ----------------------------------------------------------------
         */
        $current_month = current_time('Y-m');
        $this->update_monthly_failure_stats($current_month, $amount);

        /**
         * Fires after a payment failure has been recorded.
         *
         * @since 2.0.0
         *
         * @param WC_Order             $order        The failed renewal order.
         * @param int                  $insert_id    The failure record ID.
         * @param WC_Subscription|null $subscription The parent subscription.
         */
        do_action('workfern_payment_failed_recorded', $order, $insert_id, $subscription);
    }

    /*
    |--------------------------------------------------------------------------
    | Monthly Stats
    |--------------------------------------------------------------------------
    */

    /**
     * Update monthly failure statistics.
     *
     * Adds the failed amount to the current month's failed_revenue total.
     *
     * @since  2.0.0
     * @access private
     *
     * @param string $month        The month in YYYY-MM format.
     * @param float  $failed_amount The failed revenue amount to add.
     * @return void
     */
    private function update_monthly_failure_stats($month, $failed_amount)
    {
        $stats_result = $this->database->get_recovery_stats(
            array(
                'month'    => $month,
                'per_page' => 1,
            )
        );

        $existing_stats = !empty($stats_result['items']) ? $stats_result['items'][0] : null;

        if ($existing_stats) {
            $new_failed = floatval($existing_stats->failed_revenue) + $failed_amount;
            $recovered = floatval($existing_stats->recovered_revenue);
            $recovery_rate = $new_failed > 0 ? round(($recovered / $new_failed) * 100, 2) : 0.00;

            $this->database->upsert_recovery_stats(
                array(
                    'month'             => $month,
                    'failed_revenue'    => $new_failed,
                    'recovered_revenue' => $recovered,
                    'recovery_rate'     => $recovery_rate,
                )
            );
        } else {
            $this->database->upsert_recovery_stats(
                array(
                    'month'             => $month,
                    'failed_revenue'    => $failed_amount,
                    'recovered_revenue' => 0.00,
                    'recovery_rate'     => 0.00,
                )
            );
        }
    }

    /**
     * Update monthly recovery statistics.
     *
     * Adds the recovered amount to the current month's recovered_revenue total.
     *
     * @since  2.0.0
     * @access private
     *
     * @param string $month            The month in YYYY-MM format.
     * @param float  $recovered_amount The recovered revenue amount to add.
     * @return void
     */
    private function update_monthly_recovery_stats($month, $recovered_amount)
    {
        $stats_result = $this->database->get_recovery_stats(
            array(
                'month'    => $month,
                'per_page' => 1,
            )
        );

        $existing_stats = !empty($stats_result['items']) ? $stats_result['items'][0] : null;

        if ($existing_stats) {
            $new_recovered = floatval($existing_stats->recovered_revenue) + $recovered_amount;
            $failed_revenue = floatval($existing_stats->failed_revenue);
            $recovery_rate = $failed_revenue > 0 ? round(($new_recovered / $failed_revenue) * 100, 2) : 0.00;

            $this->database->upsert_recovery_stats(
                array(
                    'month'             => $month,
                    'failed_revenue'    => $failed_revenue,
                    'recovered_revenue' => $new_recovered,
                    'recovery_rate'     => $recovery_rate,
                )
            );
        } else {
            $this->database->upsert_recovery_stats(
                array(
                    'month'             => $month,
                    'failed_revenue'    => 0.00,
                    'recovered_revenue' => $recovered_amount,
                    'recovery_rate'     => 0.00,
                )
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if a failure has already been recorded for an order.
     *
     * Uses order meta to prevent duplicate processing when both
     * the primary and fallback hooks fire for the same event.
     *
     * @since  2.0.0
     * @access private
     *
     * @param int $order_id The WooCommerce order ID.
     * @return bool True if already recorded, false otherwise.
     */
    private function is_already_recorded($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return false;
        }

        return 'yes' === $order->get_meta('_workfern_failure_recorded', true);
    }

    /**
     * Increment the attempt count for an existing failure record.
     *
     * Called when a repeat failure occurs for an order that already
     * has a record (e.g., WCS retries).
     *
     * @since  2.0.0
     * @access private
     *
     * @param int $order_id The WooCommerce order ID.
     * @return void
     */
    private function increment_existing_record($order_id)
    {
        $existing = $this->get_failure_record_by_order($order_id);

        if ($existing) {
            $this->database->increment_attempt_count($existing->id);
            $this->database->update_payment_status($existing->id, 'pending');

            error_log( /* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log */ sprintf(
                '[WORKFERN] Repeat payment failure for order #%d (record #%d). Attempt count incremented.',
                $order_id,
                $existing->id
            ));
        }
    }

    /**
     * Check whether a WooCommerce order is a subscription renewal order.
     *
     * Uses the WooCommerce Subscriptions helper function if available,
     * otherwise falls back to checking order meta.
     *
     * @since  2.0.0
     * @access private
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return bool True if the order is a subscription renewal.
     */
    private function is_subscription_renewal_order($order)
    {
        // Method 1: Use WCS helper function (most reliable).
        if (function_exists('wcs_order_contains_renewal')) {
            return wcs_order_contains_renewal($order);
        }

        // Method 2: Check order meta (fallback for custom subscription plugins).
        $is_renewal = $order->get_meta('_subscription_renewal', true);

        if (!empty($is_renewal)) {
            return true;
        }

        // Method 3: Check if the order type indicates it is a renewal.
        if (function_exists('wcs_is_subscription')) {
            // If the order itself is a subscription, it is not a renewal order.
            return false;
        }

        // Method 4: Check for YITH Subscriptions compatibility.
        $ywsbs_subscription = $order->get_meta('_ywsbs_subscription', true);

        if (!empty($ywsbs_subscription)) {
            // YITH marks renewal orders with this meta.
            return true;
        }

        return false;
    }

    /**
     * Get the parent WC_Subscription for a renewal order.
     *
     * @since  2.0.0
     * @access private
     *
     * @param WC_Order $order The renewal order.
     * @return WC_Subscription|null The subscription object, or null.
     */
    private function get_subscription_for_order($order)
    {
        if (!function_exists('wcs_get_subscriptions_for_renewal_order')) {
            return null;
        }

        $subscriptions = wcs_get_subscriptions_for_renewal_order($order);

        if (!empty($subscriptions)) {
            return reset($subscriptions);
        }

        return null;
    }

    /**
     * Get a failure record from the database by WooCommerce order ID.
     *
     * @since  2.0.0
     * @access private
     *
     * @param int $order_id The WooCommerce order ID.
     * @return object|null The record object, or null if not found.
     */
    private function get_failure_record_by_order($order_id)
    {
        $result = $this->database->get_failed_payments(
            array(
                'order_id' => absint($order_id),
                'per_page' => 1,
                'paged'    => 1,
                'orderby'  => 'created_at',
                'order'    => 'DESC',
            )
        );

        return !empty($result['items']) ? $result['items'][0] : null;
    }

    /**
     * Get the last order note for an order.
     *
     * Retrieves the most recent order note which typically contains
     * the payment failure reason from the gateway.
     *
     * @since  2.0.0
     * @access private
     *
     * @param int $order_id The WooCommerce order ID.
     * @return string The last note content, or empty string.
     */
    private function get_last_order_note($order_id)
    {
        $notes = wc_get_order_notes(
            array(
                'order_id' => $order_id,
                'limit'    => 1,
                'orderby'  => 'date_created',
                'order'    => 'DESC',
                'type'     => 'internal',
            )
        );

        if (!empty($notes) && isset($notes[0]->content)) {
            return sanitize_textarea_field(wp_strip_all_tags($notes[0]->content));
        }

        return '';
    }
}
