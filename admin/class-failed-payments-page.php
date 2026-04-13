<?php
/**
 * Failed Payments Admin Page Controller.
 *
 * Registers the "Stripe Recovery" admin menu, processes row/bulk
 * actions, and renders the failed-payments list-table page.
 *
 * @since      1.0.0
 * @package    WooStripeRecoveryPro
 * @subpackage WooStripeRecoveryPro/admin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Failed Payments Page controller.
 *
 * @since 1.0.0
 */
class WORKFERN_Failed_Payments_Page
{

    /**
     * Menu / page slug.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $page_slug = 'workfern_failed_payments';

    /**
     * Required capability to access the page.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $capability = 'manage_options';

    /**
     * Database layer instance.
     *
     * @since  1.0.0
     * @access private
     * @var    WORKFERN_Recovery_DB
     */
    private $db;

    /**
     * List table instance.
     *
     * @since  1.0.0
     * @access private
     * @var    WORKFERN_Failed_Payments_Table|null
     */
    private $list_table = null;

    /**
     * Initialize the controller.
     *
     * Hooks into WordPress to register the menu and handle actions.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->db = WORKFERN_Recovery_DB::instance();

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Registration
    |--------------------------------------------------------------------------
    */

    /**
     * Register the admin menu and submenu pages.
     *
     * Creates:
     * - Top-level menu: "Stripe Recovery"
     * - Submenu item:   "Failed Payments"
     *
     * @since  1.0.0
     * @return void
     */
    public function register_menu()
    {
        add_menu_page(
            __('Stripe Recovery', 'workfern-subscription-payment-recovery'),
            __('Stripe Recovery', 'workfern-subscription-payment-recovery'),
            $this->capability,
            $this->page_slug,
            array($this, 'render_page'),
            'dashicons-update',
            58
        );

        add_submenu_page(
            $this->page_slug,
            __('Failed Payments', 'workfern-subscription-payment-recovery'),
            __('Failed Payments', 'workfern-subscription-payment-recovery'),
            $this->capability,
            $this->page_slug,
            array($this, 'render_page')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Action Handler
    |--------------------------------------------------------------------------
    */

    /**
     * Handle row-level and bulk actions before any output is sent.
     *
     * Runs on admin_init so redirects work correctly.
     *
     * Supported single actions (via GET):
     * - retry
     * - mark_recovered
     * - delete
     *
     * Supported bulk actions (via POST):
     * - bulk_retry
     * - bulk_recover
     * - bulk_delete
     *
     * @since  1.0.0
     * @return void
     */
    public function handle_actions()
    {
        // Only process on our page.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!isset($_GET['page']) || $this->page_slug !== $_GET['page']) {
            return;
        }

        // Permission gate.
        if (!current_user_can($this->capability)) {
            return;
        }

        $this->handle_single_actions();
        $this->handle_bulk_actions();
    }

    /**
     * Process a single-row action.
     *
     * Each action is nonce-protected with check_admin_referer().
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function handle_single_actions()
    {
        $action = isset($_GET['workfern_action']) ? sanitize_text_field(wp_unslash($_GET['workfern_action'])) : '';
        $record_id = isset($_GET['record_id']) ? intval($_GET['record_id']) : 0;

        if (empty($action) || $record_id < 1) {
            return;
        }

        $redirect = admin_url('admin.php?page=' . $this->page_slug);

        switch ($action) {

            /*
             * Retry Payment.
             *
             * Validates eligibility, increments retry count, calls the
             * Stripe API, and updates status based on the result.
             */
            case 'retry':
                check_admin_referer('workfern_retry_' . $record_id);

                $record = $this->db->get_failed_payment_by_id($record_id);

                if (!$record || 'failed' !== $record->status || intval($record->retry_count) >= 5) {
                    $redirect = add_query_arg('workfern_msg', 'retry_ineligible', $redirect);
                    break;
                }

                // Mark as retrying and increment counter.
                $this->db->update_status($record_id, 'retrying');
                $this->db->increment_retry_count($record_id);

                // Attempt the Stripe charge.
                $success = $this->call_stripe_confirm($record->payment_intent_id);

                if ($success) {
                    $this->db->mark_payment_recovered($record_id);
                    $redirect = add_query_arg('workfern_msg', 'recovered', $redirect);
                } else {
                    $this->db->update_status($record_id, 'failed');
                    $redirect = add_query_arg('workfern_msg', 'retry_failed', $redirect);
                }
                break;

            /*
             * Mark Recovered.
             *
             * Manually marks a record as recovered without calling Stripe.
             */
            case 'mark_recovered':
                check_admin_referer('workfern_recover_' . $record_id);

                $this->db->mark_payment_recovered($record_id);
                $redirect = add_query_arg('workfern_msg', 'recovered', $redirect);
                break;

            /*
             * Delete Record.
             *
             * Permanently removes the record from the database.
             */
            case 'delete':
                check_admin_referer('workfern_delete_' . $record_id);

                $this->db->delete_failed_payment($record_id);
                $redirect = add_query_arg('workfern_msg', 'deleted', $redirect);
                break;

            default:
                return;
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Process bulk actions submitted via the list table form.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function handle_bulk_actions()
    {
        if (!isset($_POST['_wpnonce']) || !isset($_POST['action'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['action']));

        // WP_List_Table sends '-1' for the top selector when the bottom is used.
        if ('-1' === $action && isset($_POST['action2'])) {
            $action = sanitize_text_field(wp_unslash($_POST['action2']));
        }

        if (!in_array($action, array('bulk_retry', 'bulk_recover', 'bulk_delete'), true)) {
            return;
        }

        check_admin_referer('bulk-failed_payments');

        $record_ids = isset($_POST['record_ids']) ? array_map('intval', (array) $_POST['record_ids']) : array();

        if (empty($record_ids)) {
            return;
        }

        $processed = 0;

        foreach ($record_ids as $rid) {
            if ($rid < 1) {
                continue;
            }

            switch ($action) {
                case 'bulk_retry':
                    $record = $this->db->get_failed_payment_by_id($rid);
                    if ($record && 'failed' === $record->status && intval($record->retry_count) < 5) {
                        $this->db->update_status($rid, 'retrying');
                        $this->db->increment_retry_count($rid);

                        if ($this->call_stripe_confirm($record->payment_intent_id)) {
                            $this->db->mark_payment_recovered($rid);
                        } else {
                            $this->db->update_status($rid, 'failed');
                        }
                        $processed++;
                    }
                    break;

                case 'bulk_recover':
                    $this->db->mark_payment_recovered($rid);
                    $processed++;
                    break;

                case 'bulk_delete':
                    $this->db->delete_failed_payment($rid);
                    $processed++;
                    break;
            }
        }

        $redirect = add_query_arg(
            array(
                'page' => $this->page_slug,
                'workfern_msg' => 'bulk_done',
                'workfern_count' => $processed,
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Page Render
    |--------------------------------------------------------------------------
    */

    /**
     * Render the complete admin page.
     *
     * Outputs:
     * 1. Page title.
     * 2. Admin notices.
     * 3. Stats summary cards.
     * 4. Search box + status filter tabs.
     * 5. The WP_List_Table.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_page()
    {
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'workfern-subscription-payment-recovery'));
        }

        // Lazy-load the list table class.
        if (!class_exists('WORKFERN_Failed_Payments_Table')) {
            require_once plugin_dir_path(__FILE__) . '../admin/class-failed-payments-table.php';
        }

        $this->list_table = new WORKFERN_Failed_Payments_Table();
        $this->list_table->prepare_items();

        ?>
        <div class="wrap">

            <h1 class="wp-heading-inline">
                <?php esc_html_e('Stripe Failed Payments', 'workfern-subscription-payment-recovery'); ?>
            </h1>
            <hr class="wp-header-end">

            <?php $this->render_notices(); ?>
            <?php $this->render_stats(); ?>

            <!-- Search & Filter (GET form) -->
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($this->page_slug); ?>" />
                <?php if (isset($_GET['status'])):  // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <input type="hidden" name="status"
                        value="<?php echo esc_attr(sanitize_key(wp_unslash($_GET['status']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
                <?php endif; ?>
                <?php
                $this->list_table->views();
                $this->list_table->search_box(__('Search by Email', 'workfern-subscription-payment-recovery'), 'workfern_search');
                ?>
            </form>

            <!-- Table (POST form for bulk actions) -->
            <form method="post">
                <?php $this->list_table->display(); ?>
            </form>

        </div>

        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Notices
    |--------------------------------------------------------------------------
    */

    /**
     * Render admin notices based on query-string flags.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function render_notices()
    {
        if (!isset($_GET['workfern_msg'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $msg_key = sanitize_key(wp_unslash($_GET['workfern_msg'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $count = isset($_GET['workfern_count']) ? intval($_GET['workfern_count']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $notices = array(
            'recovered' => array(
                'type' => 'success',
                'text' => __('Payment marked as recovered successfully.', 'workfern-subscription-payment-recovery'),
            ),
            'retry_failed' => array(
                'type' => 'error',
                'text' => __('Payment retry was not successful. The Stripe charge could not be completed.', 'workfern-subscription-payment-recovery'),
            ),
            'retry_ineligible' => array(
                'type' => 'warning',
                'text' => __('This payment is not eligible for retry. It may already be recovered or has exceeded the maximum retry limit.', 'workfern-subscription-payment-recovery'),
            ),
            'deleted' => array(
                'type' => 'success',
                'text' => __('Record has been permanently deleted.', 'workfern-subscription-payment-recovery'),
            ),
            'bulk_done' => array(
                'type' => 'success',
                'text' => sprintf(
                    /* translators: %d: number of records processed */
                    __('%d record(s) processed successfully.', 'workfern-subscription-payment-recovery'),
                    $count
                ),
            ),
        );

        if (!isset($notices[$msg_key])) {
            return;
        }

        $notice = $notices[$msg_key];

        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($notice['type']),
            esc_html($notice['text'])
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stats Cards
    |--------------------------------------------------------------------------
    */

    /**
     * Render summary statistics cards above the table.
     *
     * Displays:
     * - Total Failed Payments
     * - Recovered Payments (with rate %)
     * - Total Recovered Amount
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function render_stats()
    {
        $stats = $this->db->get_stats();
        ?>
        <div class="workfern-stats-row">

            <div class="workfern-stat-card" style="border-left:4px solid #dc3232;">
                <h3><?php esc_html_e('Total Failed Payments', 'workfern-subscription-payment-recovery'); ?></h3>
                <p class="workfern-stat-value" style="color:#dc3232;">
                    <?php echo esc_html(number_format($stats['total_failed'])); ?>
                </p>
            </div>

            <div class="workfern-stat-card" style="border-left:4px solid #46b450;">
                <h3><?php esc_html_e('Recovered Payments', 'workfern-subscription-payment-recovery'); ?></h3>
                <p class="workfern-stat-value" style="color:#46b450;">
                    <?php echo esc_html(number_format($stats['total_recovered'])); ?>
                    <?php if ($stats['recovery_rate'] > 0): ?>
                        <span style="font-size:14px;font-weight:400;color:#646970;">
                            (<?php echo esc_html($stats['recovery_rate']); ?>%)
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="workfern-stat-card" style="border-left:4px solid #0073aa;">
                <h3><?php esc_html_e('Total Recovered Amount', 'workfern-subscription-payment-recovery'); ?></h3>
                <p class="workfern-stat-value" style="color:#0073aa;">
                    $<?php echo esc_html(number_format($stats['recovered_amount'], 2)); ?>
                </p>
            </div>

        </div>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | Stripe API Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Call the Stripe API to confirm a PaymentIntent.
     *
     * @since  1.0.0
     * @access private
     *
     * @param string $payment_intent_id The Stripe PaymentIntent ID.
     * @return bool True if the PaymentIntent status is 'succeeded'.
     */
    private function call_stripe_confirm($payment_intent_id)
    {
        if (empty($payment_intent_id)) {
            return false;
        }

        $secret_key = $this->get_stripe_secret_key();

        if (empty($secret_key)) {
            return false;
        }

        $url = 'https://api.stripe.com/v1/payment_intents/'
            . urlencode(sanitize_text_field($payment_intent_id))
            . '/confirm';

        $response = wp_remote_post(
            $url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $secret_key,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'timeout' => 30,
                'body' => array(),
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        if (wp_remote_retrieve_response_code($response) >= 400) {
            return false;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($decoded) && isset($decoded['status']) && 'succeeded' === $decoded['status'];
    }

    /**
     * Retrieve the Stripe secret key from WooCommerce Stripe settings.
     *
     * Automatically selects test or live key based on the testmode setting.
     *
     * @since  1.0.0
     * @access private
     * @return string The Stripe secret key, or empty string if not configured.
     */
    private function get_stripe_secret_key()
    {
        $settings = get_option('woocommerce_stripe_settings', array());
        $testmode = isset($settings['testmode']) && 'yes' === $settings['testmode'];

        if ($testmode) {
            return isset($settings['test_secret_key']) ? $settings['test_secret_key'] : '';
        }

        return isset($settings['secret_key']) ? $settings['secret_key'] : '';
    }
}
