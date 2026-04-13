<?php
/**
 * Failed Payments WP_List_Table.
 *
 * Extends WP_List_Table to display, sort, filter, and manage
 * failed Stripe payment records in the WordPress admin area.
 *
 * @since      1.0.0
 * @package    WooStripeRecoveryPro
 * @subpackage WooStripeRecoveryPro/admin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not already available.
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Failed Payments List Table.
 *
 * @since 1.0.0
 */
class WORKFERN_Failed_Payments_Table extends WP_List_Table
{

    /**
     * Database layer instance.
     *
     * @since  1.0.0
     * @access private
     * @var    WORKFERN_Recovery_DB
     */
    private $db;

    /**
     * Admin page slug.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $page_slug = 'workfern_failed_payments';

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        parent::__construct(
            array(
                'singular' => 'failed_payment',
                'plural' => 'failed_payments',
                'ajax' => false,
            )
        );

        $this->db = WORKFERN_Recovery_DB::instance();
    }

    /*
    |--------------------------------------------------------------------------
    | Column Definitions
    |--------------------------------------------------------------------------
    */

    /**
     * Define table columns.
     *
     * @since  1.0.0
     * @return array Column slug => Column label.
     */
    public function get_columns()
    {
        return array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'workfern-subscription-payment-recovery'),
            'email' => __('Email', 'workfern-subscription-payment-recovery'),
            'amount' => __('Amount', 'workfern-subscription-payment-recovery'),
            'currency' => __('Currency', 'workfern-subscription-payment-recovery'),
            'status' => __('Status', 'workfern-subscription-payment-recovery'),
            'retry_count' => __('Retry Count', 'workfern-subscription-payment-recovery'),
            'created_at' => __('Created', 'workfern-subscription-payment-recovery'),
            'actions' => __('Actions', 'workfern-subscription-payment-recovery'),
        );
    }

    /**
     * Define sortable columns.
     *
     * @since  1.0.0
     * @return array Column slug => array( orderby, is_desc_default ).
     */
    public function get_sortable_columns()
    {
        return array(
            'id' => array('id', false),
            'email' => array('email', false),
            'amount' => array('amount', false),
            'status' => array('status', false),
            'retry_count' => array('retry_count', false),
            'created_at' => array('created_at', true),
        );
    }

    /**
     * Columns that should not be sortable or visible by default.
     *
     * @since  1.0.0
     * @return array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /*
    |--------------------------------------------------------------------------
    | Column Renderers
    |--------------------------------------------------------------------------
    */

    /**
     * Checkbox column for bulk actions.
     *
     * @since 1.0.0
     *
     * @param object $item Current row item.
     * @return string HTML checkbox.
     */
    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="record_ids[]" value="%d" />',
            intval($item->id)
        );
    }

    /**
     * Default column renderer.
     *
     * @since 1.0.0
     *
     * @param object $item        Current row item.
     * @param string $column_name Column slug.
     * @return string Column output.
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'id':
                return intval($item->id);

            case 'email':
                return esc_html($item->email);

            case 'amount':
                return esc_html(number_format(floatval($item->amount), 2));

            case 'currency':
                return esc_html(strtoupper($item->currency));

            case 'status':
                return $this->render_status_badge($item->status);

            case 'retry_count':
                return intval($item->retry_count);

            case 'created_at':
                return esc_html($item->created_at);

            case 'actions':
                return $this->render_row_actions_column($item);

            default:
                return '';
        }
    }

    /**
     * Render a colour-coded status badge.
     *
     * @since  1.0.0
     * @access private
     *
     * @param string $status Record status.
     * @return string HTML badge.
     */
    private function render_status_badge($status)
    {
        $colors = array(
            'failed' => '#dc3232',
            'retrying' => '#ffb900',
            'recovered' => '#46b450',
            'abandoned' => '#826eb4',
        );

        $bg = isset($colors[$status]) ? $colors[$status] : '#999';

        return sprintf(
            '<span class="workfern-status-badge" style="display:inline-block;padding:4px 10px;border-radius:3px;background:%s;color:#fff;font-size:12px;font-weight:600;line-height:1.4;">%s</span>',
            esc_attr($bg),
            esc_html(ucfirst($status))
        );
    }

    /**
     * Render action buttons for a single row.
     *
     * Available actions:
     * - Retry Payment (only if status=failed and retry_count < 5)
     * - Mark Recovered (only if status != recovered)
     * - Delete
     *
     * @since  1.0.0
     * @access private
     *
     * @param object $item Current row item.
     * @return string HTML action buttons.
     */
    private function render_row_actions_column($item)
    {
        $buttons = array();

        // Retry Payment.
        if ('failed' === $item->status && intval($item->retry_count) < 5) {
            $retry_url = wp_nonce_url(
                admin_url('admin.php?page=' . $this->page_slug . '&workfern_action=retry&record_id=' . intval($item->id)),
                'workfern_retry_' . $item->id
            );

            $buttons[] = sprintf(
                '<a href="%s" class="button button-small" title="%s">%s</a>',
                esc_url($retry_url),
                esc_attr__('Retry this payment via Stripe', 'workfern-subscription-payment-recovery'),
                esc_html__('Retry', 'workfern-subscription-payment-recovery')
            );
        }

        // Mark Recovered.
        if ('recovered' !== $item->status) {
            $recover_url = wp_nonce_url(
                admin_url('admin.php?page=' . $this->page_slug . '&workfern_action=mark_recovered&record_id=' . intval($item->id)),
                'workfern_recover_' . $item->id
            );

            $buttons[] = sprintf(
                '<a href="%s" class="button button-small" title="%s">%s</a>',
                esc_url($recover_url),
                esc_attr__('Manually mark as recovered', 'workfern-subscription-payment-recovery'),
                esc_html__('Mark Recovered', 'workfern-subscription-payment-recovery')
            );
        }

        // Delete.
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=' . $this->page_slug . '&workfern_action=delete&record_id=' . intval($item->id)),
            'workfern_delete_' . $item->id
        );

        $buttons[] = sprintf(
            '<a href="%s" class="button button-small workfern-btn-delete" style="color:#a00;border-color:#a00;" title="%s" onclick="return confirm(\'%s\');">%s</a>',
            esc_url($delete_url),
            esc_attr__('Permanently delete this record', 'workfern-subscription-payment-recovery'),
            esc_js(__('Are you sure you want to delete this record? This cannot be undone.', 'workfern-subscription-payment-recovery')),
            esc_html__('Delete', 'workfern-subscription-payment-recovery')
        );

        return '<div class="workfern-row-actions" style="display:flex;gap:4px;flex-wrap:wrap;">' . implode('', $buttons) . '</div>';
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Actions
    |--------------------------------------------------------------------------
    */

    /**
     * Define bulk actions.
     *
     * @since  1.0.0
     * @return array Action slug => label.
     */
    public function get_bulk_actions()
    {
        return array(
            'bulk_retry' => __('Retry Selected', 'workfern-subscription-payment-recovery'),
            'bulk_recover' => __('Mark Selected Recovered', 'workfern-subscription-payment-recovery'),
            'bulk_delete' => __('Delete Selected', 'workfern-subscription-payment-recovery'),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Views (Status Filter Tabs)
    |--------------------------------------------------------------------------
    */

    /**
     * Render status filter links above the table.
     *
     * @since  1.0.0
     * @return array View slug => link HTML.
     */
    protected function get_views()
    {
        $current = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $views = array();

        // All.
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url(admin_url('admin.php?page=' . $this->page_slug)),
            empty($current) ? 'current' : '',
            esc_html__('All', 'workfern-subscription-payment-recovery'),
            $this->db->count_by_status()
        );

        // Per-status tabs.
        $statuses = array(
            'failed' => __('Failed', 'workfern-subscription-payment-recovery'),
            'retrying' => __('Retrying', 'workfern-subscription-payment-recovery'),
            'recovered' => __('Recovered', 'workfern-subscription-payment-recovery'),
            'abandoned' => __('Abandoned', 'workfern-subscription-payment-recovery'),
        );

        foreach ($statuses as $slug => $label) {
            $count = $this->db->count_by_status($slug);

            $views[$slug] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(admin_url('admin.php?page=' . $this->page_slug . '&status=' . $slug)),
                $current === $slug ? 'current' : '',
                esc_html($label),
                $count
            );
        }

        return $views;
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Items
    |--------------------------------------------------------------------------
    */

    /**
     * Prepare table items for display.
     *
     * Reads query parameters, fetches paginated data from the database,
     * and sets up column headers and pagination args.
     *
     * @since  1.0.0
     * @return void
     */
    public function prepare_items()
    {
        $per_page = 20;
        $paged = $this->get_pagenum();

        // Read sort parameters (no nonce needed for read-only GET display). phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'DESC';           // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';                     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $query_args = array(
            'status' => $status,
            'orderby' => $orderby,
            'order' => $order,
            'per_page' => $per_page,
            'paged' => $paged,
        );

        // If we have a search term, use it as an email filter.
        if (!empty($search)) {
            $query_args['email'] = $search;
        }

        $result = $this->db->get_failed_payments($query_args);

        $this->items = $result['items'];

        $this->set_pagination_args(
            array(
                'total_items' => $result['total_items'],
                'per_page' => $per_page,
                'total_pages' => $result['total_pages'],
            )
        );

        $this->_column_headers = array(
            $this->get_columns(),
            $this->get_hidden_columns(),
            $this->get_sortable_columns(),
        );
    }

    /**
     * Message displayed when no records are found.
     *
     * @since  1.0.0
     * @return void
     */
    public function no_items()
    {
        esc_html_e('No failed payment records found.', 'workfern-subscription-payment-recovery');
    }

    /*
    |--------------------------------------------------------------------------
    | Action Processing
    |--------------------------------------------------------------------------
    */

    /**
     * Process single-row and bulk actions.
     *
     * Should be called before prepare_items() in the page render method.
     * All destructive actions are protected by nonce verification.
     *
     * @since  1.0.0
     * @return void
     */
    public function process_actions()
    {
        $this->process_single_actions();
        $this->process_bulk_actions_handler();
    }

    /**
     * Handle single-row actions (retry, mark_recovered, delete).
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function process_single_actions()
    {
        $action = isset($_GET['workfern_action']) ? sanitize_key(wp_unslash($_GET['workfern_action'])) : '';
        $record_id = isset($_GET['record_id']) ? intval($_GET['record_id']) : 0;

        if (empty($action) || $record_id < 1) {
            return;
        }

        $redirect_url = admin_url('admin.php?page=' . $this->page_slug);

        switch ($action) {
            case 'retry':
                check_admin_referer('workfern_retry_' . $record_id);

                $record = $this->db->get_failed_payment_by_id($record_id);

                if ($record && 'failed' === $record->status && intval($record->retry_count) < 5) {
                    $this->db->update_status($record_id, 'retrying');
                    $this->db->increment_retry_count($record_id);

                    // Attempt Stripe API call.
                    $stripe_result = $this->attempt_stripe_retry($record->payment_intent_id);

                    if ($stripe_result) {
                        $this->db->mark_payment_recovered($record_id);
                        $redirect_url = add_query_arg('workfern_notice', 'recovered', $redirect_url);
                    } else {
                        $this->db->update_status($record_id, 'failed');
                        $redirect_url = add_query_arg('workfern_notice', 'retry_failed', $redirect_url);
                    }
                } else {
                    $redirect_url = add_query_arg('workfern_notice', 'retry_ineligible', $redirect_url);
                }
                break;

            case 'mark_recovered':
                check_admin_referer('workfern_recover_' . $record_id);
                $this->db->mark_payment_recovered($record_id);
                $redirect_url = add_query_arg('workfern_notice', 'recovered', $redirect_url);
                break;

            case 'delete':
                check_admin_referer('workfern_delete_' . $record_id);
                $this->db->delete_failed_payment($record_id);
                $redirect_url = add_query_arg('workfern_notice', 'deleted', $redirect_url);
                break;

            default:
                return;
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Handle bulk actions.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function process_bulk_actions_handler()
    {
        if (!isset($_POST['_wpnonce'])) {
            return;
        }

        $bulk_action = $this->current_action();

        if (!$bulk_action || !in_array($bulk_action, array('bulk_retry', 'bulk_recover', 'bulk_delete'), true)) {
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

            switch ($bulk_action) {
                case 'bulk_retry':
                    $record = $this->db->get_failed_payment_by_id($rid);
                    if ($record && 'failed' === $record->status && intval($record->retry_count) < 5) {
                        $this->db->update_status($rid, 'retrying');
                        $this->db->increment_retry_count($rid);

                        $stripe_result = $this->attempt_stripe_retry($record->payment_intent_id);

                        if ($stripe_result) {
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

        $redirect_url = add_query_arg(
            array(
                'page' => $this->page_slug,
                'workfern_notice' => 'bulk_done',
                'workfern_count' => $processed,
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Attempt to retry a Stripe PaymentIntent.
     *
     * Calls the Stripe API to confirm the PaymentIntent.
     * Returns true if the PaymentIntent status becomes 'succeeded'.
     *
     * @since  1.0.0
     * @access private
     *
     * @param string $payment_intent_id The Stripe PaymentIntent ID.
     * @return bool True if payment succeeded, false otherwise.
     */
    private function attempt_stripe_retry($payment_intent_id)
    {
        if (empty($payment_intent_id)) {
            return false;
        }

        $secret_key = $this->get_stripe_secret_key();

        if (empty($secret_key)) {
            return false;
        }

        $url = 'https://api.stripe.com/v1/payment_intents/' . urlencode(sanitize_text_field($payment_intent_id)) . '/confirm';

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

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return false;
        }

        return isset($decoded['status']) && 'succeeded' === $decoded['status'];
    }

    /**
     * Retrieve the Stripe secret key from WooCommerce Stripe settings.
     *
     * @since  1.0.0
     * @access private
     * @return string The secret key, or empty string.
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

    /*
    |--------------------------------------------------------------------------
    | Admin Notices
    |--------------------------------------------------------------------------
    */

    /**
     * Render admin notices based on action results.
     *
     * Call this method at the top of the admin page render.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_notices()
    {
        if (!isset($_GET['workfern_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['workfern_notice'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $count = isset($_GET['workfern_count']) ? intval($_GET['workfern_count']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $messages = array(
            'recovered' => array(
                'type' => 'success',
                'text' => __('Payment has been marked as recovered.', 'workfern-subscription-payment-recovery'),
            ),
            'retry_failed' => array(
                'type' => 'error',
                'text' => __('Payment retry was unsuccessful. The Stripe charge could not be completed.', 'workfern-subscription-payment-recovery'),
            ),
            'retry_ineligible' => array(
                'type' => 'warning',
                'text' => __('This record is not eligible for retry. It may have already been recovered or exceeded maximum retries.', 'workfern-subscription-payment-recovery'),
            ),
            'deleted' => array(
                'type' => 'success',
                'text' => __('Record has been permanently deleted.', 'workfern-subscription-payment-recovery'),
            ),
            'bulk_done' => array(
                'type' => 'success',
                'text' => sprintf(
                    /* translators: %d: number of records processed */
                    __('%d record(s) have been processed successfully.', 'workfern-subscription-payment-recovery'),
                    $count
                ),
            ),
        );

        if (!isset($messages[$notice])) {
            return;
        }

        $msg = $messages[$notice];

        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($msg['type']),
            esc_html($msg['text'])
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stats Summary
    |--------------------------------------------------------------------------
    */

    /**
     * Render summary statistics cards above the table.
     *
     * Shows: Total Failed, Recovered, and Total Recovered Amount.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_stats()
    {
        $stats = $this->db->get_stats();
        ?>
        <div class="workfern-stats-row" style="display:flex;gap:20px;margin:20px 0;">

            <div class="workfern-stat-card"
                style="flex:1;background:#fff;border:1px solid #ccd0d4;border-left:4px solid #dc3232;padding:15px 20px;border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin:0 0 5px;font-size:13px;color:#646970;text-transform:uppercase;letter-spacing:.5px;">
                    <?php esc_html_e('Total Failed', 'workfern-subscription-payment-recovery'); ?>
                </h3>
                <p style="margin:0;font-size:32px;font-weight:700;color:#dc3232;line-height:1.2;">
                    <?php echo esc_html(number_format($stats['total_failed'])); ?>
                </p>
            </div>

            <div class="workfern-stat-card"
                style="flex:1;background:#fff;border:1px solid #ccd0d4;border-left:4px solid #46b450;padding:15px 20px;border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin:0 0 5px;font-size:13px;color:#646970;text-transform:uppercase;letter-spacing:.5px;">
                    <?php esc_html_e('Recovered', 'workfern-subscription-payment-recovery'); ?>
                </h3>
                <p style="margin:0;font-size:32px;font-weight:700;color:#46b450;line-height:1.2;">
                    <?php echo esc_html(number_format($stats['total_recovered'])); ?>
                    <?php if ($stats['recovery_rate'] > 0): ?>
                        <span style="font-size:14px;font-weight:400;color:#646970;">
                            (
                            <?php echo esc_html($stats['recovery_rate']); ?>%)
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="workfern-stat-card"
                style="flex:1;background:#fff;border:1px solid #ccd0d4;border-left:4px solid #0073aa;padding:15px 20px;border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin:0 0 5px;font-size:13px;color:#646970;text-transform:uppercase;letter-spacing:.5px;">
                    <?php esc_html_e('Recovered Amount', 'workfern-subscription-payment-recovery'); ?>
                </h3>
                <p style="margin:0;font-size:32px;font-weight:700;color:#0073aa;line-height:1.2;">
                    $
                    <?php echo esc_html(number_format($stats['recovered_amount'], 2)); ?>
                </p>
            </div>

        </div>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | Full Page Render
    |--------------------------------------------------------------------------
    */

    /**
     * Render the complete admin page.
     *
     * Combines notices, stats, search box, views, and table display
     * into a single cohesive admin page.
     *
     * This method can be used as the menu page callback directly.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'workfern-subscription-payment-recovery'));
        }

        // Process any pending actions before rendering.
        $this->process_actions();

        // Prepare table data.
        $this->prepare_items();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Failed Stripe Payments', 'workfern-subscription-payment-recovery'); ?>
            </h1>
            <hr class="wp-header-end">

            <?php $this->render_notices(); ?>
            <?php $this->render_stats(); ?>

            <!-- Search / Filter Form (GET) -->
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($this->page_slug); ?>" />
                <?php
                if (isset($_GET['status'])):  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    ?>
                    <input type="hidden" name="status"
                        value="<?php echo esc_attr(sanitize_key(wp_unslash($_GET['status']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
                    <?php
                endif;

                $this->views();
                $this->search_box(__('Search by Email', 'workfern-subscription-payment-recovery'), 'workfern_search');
                ?>
            </form>

            <!-- Table Form (POST for bulk actions) -->
            <form method="post">
                <?php $this->display(); ?>
            </form>
        </div>

        <?php
    }
}
