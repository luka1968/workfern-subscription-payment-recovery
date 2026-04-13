<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueuing
 * the admin-specific stylesheet, JavaScript, and menu pages.
 *
 * @since      1.0.0
 * @package    WooStripeRecoveryPro
 * @subpackage WooStripeRecoveryPro/admin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
/**
 * The admin-specific functionality of the plugin.
 *
 * @since 1.0.0
 */
class WORKFERN_Admin
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
     * The slug used for the admin menu page.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $menu_slug = 'workfern-dashboard';

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * Only loads on plugin-specific admin pages to avoid
     * polluting the global admin namespace.
     *
     * @since  1.0.0
     * @param  string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function enqueue_styles($hook_suffix)
    {
        // Only load on our own admin pages.
        if (false === strpos($hook_suffix, $this->menu_slug)) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            WORKFERN_PLUGIN_URL . 'admin/css/workfern-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * Only loads on plugin-specific admin pages to avoid
     * polluting the global admin namespace.
     *
     * @since  1.0.0
     * @param  string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function enqueue_scripts($hook_suffix)
    {
        // Only load on our own admin pages.
        if (false === strpos($hook_suffix, $this->menu_slug)) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . '-admin',
            WORKFERN_PLUGIN_URL . 'admin/js/workfern-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name . '-admin',
            'workfern_admin_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('workfern_admin_nonce'),
            )
        );
    }

    /**
     * Register the admin menu page under WooCommerce.
     *
     * Adds a submenu page under the WooCommerce top-level menu
     * so the plugin integrates naturally with the WooCommerce admin UI.
     *
     * @since  1.0.0
     * @return void
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Workfern Subscriptions Recovery for WooCommerce', 'workfern-subscription-payment-recovery'),
            __('Subscriptions Payment Recovery', 'workfern-subscription-payment-recovery'),
            'manage_woocommerce',
            $this->menu_slug,
            array($this, 'render_dashboard')
        );
    }

    /**
     * Register plugin settings with the WordPress Settings API.
     *
     * @since  1.0.0
     * @return void
     */
    public function register_settings()
    {
        register_setting(
            'workfern_settings_group',
            'workfern_settings',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'workfern_general_section',
            __('General Settings', 'workfern-subscription-payment-recovery'),
            array($this, 'render_general_section'),
            $this->menu_slug
        );

        add_settings_field(
            'workfern_enable_recovery',
            __('Enable Recovery', 'workfern-subscription-payment-recovery'),
            array($this, 'render_enable_recovery_field'),
            $this->menu_slug,
            'workfern_general_section'
        );
    }

    /**
     * Sanitize plugin settings before saving.
     *
     * @since  1.0.0
     *
     * @param  array $input Raw settings input from the form.
     * @return array Sanitized settings.
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();
        $old_settings = get_option('workfern_settings', array());

        $sanitized['enable_recovery'] = isset($input['enable_recovery']) ? 1 : 0;
        


        // Preserve webhook secret if not submitted.
        $sanitized['webhook_secret'] = isset($input['webhook_secret'])
            ? sanitize_text_field($input['webhook_secret'])
            : (isset($old_settings['webhook_secret']) ? $old_settings['webhook_secret'] : '');

        return $sanitized;
    }

    /**
     * Render the general settings section description.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_general_section()
    {
        echo '<p>' . esc_html__('Configure the Stripe payment recovery behaviour below.', 'workfern-subscription-payment-recovery') . '</p>';
    }

    /**
     * Render the Enable Recovery checkbox field.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_enable_recovery_field()
    {
        $options = get_option('workfern_settings', array());
        $checked = isset($options['enable_recovery']) ? $options['enable_recovery'] : 1;
        ?>
        <label for="workfern_enable_recovery">
            <input type="checkbox" id="workfern_enable_recovery" name="workfern_settings[enable_recovery]" value="1" <?php checked(1, $checked); ?> />
            <?php esc_html_e('Automatically attempt to recover failed Stripe payments.', 'workfern-subscription-payment-recovery'); ?>
        </label>
        <div class="workfern-email-teaser">
            <p style="margin-top: 12px;">
                <span class="dashicons dashicons-email-alt" style="color:#aaa;"></span>
                <strong><?php esc_html_e('Automated Email Reminders', 'workfern-subscription-payment-recovery'); ?></strong>
                <span class="workfern-pro-badge"><?php esc_html_e('Pro', 'workfern-subscription-payment-recovery'); ?></span>
            </p>
            <p class="description" style="margin-left: 24px;">
                <?php
                printf(
                    wp_kses(
                        /* translators: %s: URL to Pro version */
                        __('Send a 3-step recovery email drip sequence to customers with failed payments. <a href="%s" target="_blank">Upgrade to Pro</a> to unlock.', 'workfern-subscription-payment-recovery'),
                        array( 'a' => array( 'href' => array(), 'target' => array() ) )
                    ),
                    esc_url( WORKFERN_PRO_URL )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render the Stripe Webhook Secret field.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_webhook_secret_field()
    {
        $options = get_option('workfern_settings', array());
        $value = isset($options['webhook_secret']) ? $options['webhook_secret'] : '';
        ?>
        <input type="password" id="workfern_webhook_secret" name="workfern_settings[webhook_secret]"
            value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter the Stripe Webhook Signing Secret (starts with whsec_). Find this in your Stripe Dashboard or Stripe CLI output.', 'workfern-subscription-payment-recovery'); ?>
        </p>
        <?php
    }

    /**
     * Render the plugin dashboard page.
     *
     * This is the main admin page displayed when the user clicks
     * on the "Stripe Recovery" submenu item under WooCommerce.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_dashboard()
    {
        // Verify user capability.
        if (!current_user_can('manage_woocommerce')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'workfern-subscription-payment-recovery')
            );
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Workfern Subscription Payment Recovery', 'workfern-subscription-payment-recovery'); ?>
            </h1>

            <nav class="nav-tab-wrapper workfern-nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->menu_slug . '&tab=overview')); ?>"
                    class="nav-tab <?php echo 'overview' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Overview', 'workfern-subscription-payment-recovery'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->menu_slug . '&tab=recovery-log')); ?>"
                    class="nav-tab <?php echo 'recovery-log' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Recovery Log', 'workfern-subscription-payment-recovery'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->menu_slug . '&tab=settings')); ?>"
                    class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'workfern-subscription-payment-recovery'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->menu_slug . '&tab=upgrade')); ?>"
                    class="nav-tab workfern-upgrade-tab <?php echo 'upgrade' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Go Pro', 'workfern-subscription-payment-recovery'); ?>
                </a>
            </nav>

            <div class="workfern-tab-content">
                <?php
                switch ($active_tab) {
                    case 'recovery-log':
                        $this->render_recovery_log_tab();
                        break;

                    case 'settings':
                        $this->render_settings_tab();
                        break;

                    case 'upgrade':
                        $this->render_upgrade_tab();
                        break;

                    case 'overview':
                    default:
                        $this->render_overview_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Overview tab content.
     *
     * Displays high-level recovery statistics and status information.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function render_overview_tab()
    {
        $options = get_option('workfern_settings', array());
        $enabled = isset($options['enable_recovery']) ? (bool) $options['enable_recovery'] : true;

        // Calculate recovery rate
        $failed_count = (int) $this->get_stat_count('failed') + (int) $this->get_stat_count('pending');
        $recovered_count = (int) $this->get_stat_count('recovered');
        $total_attempts = $failed_count + $recovered_count;
        $recovery_rate = $total_attempts > 0 ? round(($recovered_count / $total_attempts) * 100, 1) : 0;
        ?>

        <div class="workfern-dashboard-wrapper">
            <!-- Header Banner -->
            <div class="workfern-header-card">
                <div class="workfern-header-info">
                    <h2><?php esc_html_e('Recovery Overview', 'workfern-subscription-payment-recovery'); ?></h2>
                    <p><?php esc_html_e('Monitor your Stripe or PayPal subscriptions failed payments and automated recovery performance.', 'workfern-subscription-payment-recovery'); ?>
                    </p>
                </div>
                <div>
                    <span class="workfern-status-badge">
                        <?php if ($enabled): ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Recovery is active', 'workfern-subscription-payment-recovery'); ?>
                            <br><small style="opacity:0.85;font-weight:400;text-transform:none;letter-spacing:0;"><?php esc_html_e('We are automatically recovering failed subscription payments', 'workfern-subscription-payment-recovery'); ?></small>
                        <?php else: ?>
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e('Recovery is inactive', 'workfern-subscription-payment-recovery'); ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="workfern-stats-grid">
                <!-- Failed -->
                <div class="workfern-stat-card">
                    <div class="workfern-stat-icon workfern-icon-failed">
                        <span class="dashicons dashicons-dismiss"></span>
                    </div>
                    <h3><?php esc_html_e('Failed Payments', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p class="workfern-stat-number"><?php echo esc_html($failed_count); ?></p>
                </div>

                <!-- Recovered -->
                <div class="workfern-stat-card">
                    <div class="workfern-stat-icon workfern-icon-recovered">
                        <span class="dashicons dashicons-saved"></span>
                    </div>
                    <h3><?php esc_html_e('Recovered Successfully', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p class="workfern-stat-number"><?php echo esc_html($recovered_count); ?></p>
                </div>

                <!-- Pending -->
                <div class="workfern-stat-card">
                    <div class="workfern-stat-icon workfern-icon-pending">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <h3><?php esc_html_e('Pending Retries', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p class="workfern-stat-number"><?php echo esc_html($this->get_stat_count('pending')); ?></p>
                </div>

                <!-- Recovery Rate -->
                <div class="workfern-stat-card">
                    <div class="workfern-stat-icon workfern-icon-rate">
                        <span class="dashicons dashicons-chart-pie"></span>
                    </div>
                    <h3><?php esc_html_e('Recovery Rate', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p class="workfern-stat-number"><?php echo esc_html($recovery_rate); ?>%</p>
                </div>
            </div>

            <!-- Revenue Stats -->
            <?php
            $revenue = $this->get_revenue_stats();
            ?>
            <h3 class="workfern-section-title"><?php esc_html_e('Revenue Overview', 'workfern-subscription-payment-recovery'); ?></h3>
            <div class="workfern-stats-grid">
                <!-- Failed Revenue -->
                <div class="workfern-stat-card">
                    <div class="workfern-stat-icon workfern-icon-revenue-failed">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <h3><?php esc_html_e('Failed Revenue', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p class="workfern-stat-number">$<?php echo esc_html(number_format($revenue['failed'], 2)); ?></p>
                </div>

                <!-- Recovered Revenue -->
                <div class="workfern-stat-card">
                    <div class="workfern-stat-icon workfern-icon-revenue-recovered">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <h3><?php esc_html_e('Recovered Revenue', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p class="workfern-stat-number">$<?php echo esc_html(number_format($revenue['recovered'], 2)); ?></p>
                </div>

                <!-- Outstanding Revenue -->
                <div class="workfern-stat-card">
                    <div class="workfern-stat-icon workfern-icon-revenue-outstanding">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <h3><?php esc_html_e('Outstanding Revenue', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p class="workfern-stat-number">$<?php echo esc_html(number_format($revenue['outstanding'], 2)); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Recovery Log tab content.
     *
     * Displays a table of recent recovery attempts with their status.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function render_recovery_log_tab()
    {
        global $wpdb;

        $table_name = esc_sql( $wpdb->prefix . 'workfern_failed_payments' );
        $per_page = 20;
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $offset = ($paged - 1) * $per_page;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}workfern_failed_payments");
        $total_pages = ceil($total_items / $per_page);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}workfern_failed_payments ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        ?>
        <div class="workfern-dashboard-wrapper">
            <div class="workfern-content-card workfern-recovery-log-wrap">
                <h2>
                    <?php esc_html_e('Recovery Log', 'workfern-subscription-payment-recovery'); ?>
                </h2>

            <?php if (empty($results)): ?>
                <p>
                    <?php esc_html_e('No recovery attempts have been logged yet.', 'workfern-subscription-payment-recovery'); ?>
                </p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col">
                                <?php esc_html_e('Order', 'workfern-subscription-payment-recovery'); ?>
                            </th>
                            <th scope="col">
                                <?php esc_html_e('Failure Code', 'workfern-subscription-payment-recovery'); ?>
                            </th>
                            <th scope="col">
                                <?php esc_html_e('Status', 'workfern-subscription-payment-recovery'); ?>
                            </th>
                            <th scope="col">
                                <?php esc_html_e('Retries', 'workfern-subscription-payment-recovery'); ?>
                            </th>
                            <th scope="col">
                                <?php esc_html_e('Last Retry', 'workfern-subscription-payment-recovery'); ?>
                            </th>
                            <th scope="col">
                                <?php esc_html_e('Created', 'workfern-subscription-payment-recovery'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td>
                                    <?php
                                    $order_edit_url = admin_url('post.php?post=' . absint($row->order_id) . '&action=edit');
                                    printf(
                                        '<a href="%s">#%d</a>',
                                        esc_url($order_edit_url),
                                        absint($row->order_id)
                                    );
                                    ?>
                                </td>
                                <td>
                                    <?php echo esc_html($row->failure_code); ?>
                                </td>
                                <td>
                                    <span class="workfern-status-badge workfern-status-<?php echo esc_attr($row->status); ?>">
                                        <?php echo esc_html(ucfirst($row->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($row->attempt_count); ?>
                                </td>
                                <td>
                                    <?php echo $row->updated_at ? esc_html(get_date_from_gmt($row->updated_at, get_option('date_format') . ' ' . get_option('time_format'))) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo esc_html(get_date_from_gmt($row->created_at, get_option('date_format') . ' ' . get_option('time_format'))); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo wp_kses_post(
                                paginate_links(
                                    array(
                                        'base' => add_query_arg('paged', '%#%'),
                                        'format' => '',
                                        'prev_text' => '&laquo;',
                                        'next_text' => '&raquo;',
                                        'total' => $total_pages,
                                        'current' => $paged,
                                    )
                                )
                            );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Settings tab content.
     *
     * Wraps the WordPress Settings API output in a form.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function render_settings_tab()
    {
        ?>
        <div class="workfern-dashboard-wrapper">
            <div class="workfern-content-card workfern-settings-wrap">
                <h2><?php esc_html_e('Settings', 'workfern-subscription-payment-recovery'); ?></h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('workfern_settings_group');
                    do_settings_sections($this->menu_slug);
                    submit_button(__('Save Settings', 'workfern-subscription-payment-recovery'));
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Upgrade to Pro tab content.
     *
     * Displays a pure-HTML promotional page for the Pro version.
     * No Pro code logic ï¿?just interface teasers with links.
     *
     * @since  2.1.0
     * @access private
     * @return void
     */
    private function render_upgrade_tab()
    {
        $pro_url = defined('WORKFERN_PRO_URL') ? WORKFERN_PRO_URL : 'https://wordpress.workfern.com/';
        ?>
        <div class="workfern-dashboard-wrapper">
            <div class="workfern-upgrade-hero">
                <h2><?php esc_html_e('Unlock the Full Power of Payment Recovery', 'workfern-subscription-payment-recovery'); ?></h2>
                <p><?php esc_html_e('Upgrade to the Pro version to recover more revenue with automated email reminders.', 'workfern-subscription-payment-recovery'); ?></p>
                <a href="<?php echo esc_url($pro_url); ?>" target="_blank" class="workfern-cta-button">
                    <?php esc_html_e('Get Pro Version', 'workfern-subscription-payment-recovery'); ?>
                </a>
            </div>

            <div class="workfern-pro-features-grid">
                <div class="workfern-pro-feature-card">
                    <div class="workfern-pro-feature-icon" style="background:#fef2f2;color:#ef4444;">
                        <span class="dashicons dashicons-email-alt"></span>
                    </div>
                    <h3><?php esc_html_e('Automated Recovery Emails', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p><?php esc_html_e('Send a 3-step email drip sequence (Day 1, Day 3, Day 5) to customers with failed payments. Each email includes a direct link to update their payment method.', 'workfern-subscription-payment-recovery'); ?></p>
                </div>

                <div class="workfern-pro-feature-card">
                    <div class="workfern-pro-feature-icon" style="background:#eff6ff;color:#3b82f6;">
                        <span class="dashicons dashicons-edit"></span>
                    </div>
                    <h3><?php esc_html_e('Customizable Email Templates', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p><?php esc_html_e('Personalize recovery email subjects and body content for each reminder step to match your brand voice and increase open rates.', 'workfern-subscription-payment-recovery'); ?></p>
                </div>

                <div class="workfern-pro-feature-card">
                    <div class="workfern-pro-feature-icon" style="background:#f0fdf4;color:#22c55e;">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <h3><?php esc_html_e('Smart Retry Scheduling', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p><?php esc_html_e('Configure automatic payment retry intervals and maximum attempts. The system intelligently spaces out retries to maximize recovery success.', 'workfern-subscription-payment-recovery'); ?></p>
                </div>

                <div class="workfern-pro-feature-card">
                    <div class="workfern-pro-feature-icon" style="background:#fdf4ff;color:#a855f7;">
                        <span class="dashicons dashicons-sos"></span>
                    </div>
                    <h3><?php esc_html_e('Priority Email Support', 'workfern-subscription-payment-recovery'); ?></h3>
                    <p><?php esc_html_e('Get dedicated priority support from our team. We will help you configure the plugin for maximum recovery rates.', 'workfern-subscription-payment-recovery'); ?></p>
                </div>
            </div>

            <div class="workfern-comparison-section">
                <h2><?php esc_html_e('Free vs Pro Comparison', 'workfern-subscription-payment-recovery'); ?></h2>
                <table class="workfern-comparison-table widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Feature', 'workfern-subscription-payment-recovery'); ?></th>
                            <th><?php esc_html_e('Free', 'workfern-subscription-payment-recovery'); ?></th>
                            <th><?php esc_html_e('Pro', 'workfern-subscription-payment-recovery'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php esc_html_e('Failed Payment Detection', 'workfern-subscription-payment-recovery'); ?></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Recovery Analytics Dashboard', 'workfern-subscription-payment-recovery'); ?></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Recovery Log & History', 'workfern-subscription-payment-recovery'); ?></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Manual Payment Retry', 'workfern-subscription-payment-recovery'); ?></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('HPOS Compatible', 'workfern-subscription-payment-recovery'); ?></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Automated Recovery Emails', 'workfern-subscription-payment-recovery'); ?></strong></td>
                            <td><span class="dashicons dashicons-minus" style="color:#cbd5e1;"></span></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Custom Email Templates', 'workfern-subscription-payment-recovery'); ?></strong></td>
                            <td><span class="dashicons dashicons-minus" style="color:#cbd5e1;"></span></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Priority Support', 'workfern-subscription-payment-recovery'); ?></strong></td>
                            <td><span class="dashicons dashicons-minus" style="color:#cbd5e1;"></span></td>
                            <td><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="workfern-upgrade-footer">
                <a href="<?php echo esc_url($pro_url); ?>" target="_blank" class="workfern-cta-button">
                    <?php esc_html_e('Upgrade to Pro Now', 'workfern-subscription-payment-recovery'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Retrieve the count of recovery log entries by status.
     *
     * @since  1.0.0
     * @access private
     *
     * @param  string $status The recovery status to count (e.g. 'pending', 'recovered', 'failed').
     * @return int The count of entries matching the given status.
     */
    private function get_stat_count($status)
    {
        global $wpdb;

        $table_name = esc_sql( $wpdb->prefix . 'workfern_failed_payments' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}workfern_failed_payments WHERE status = %s",
                $status
            )
        );

        return $count ? (int) $count : 0;
    }

    /**
     * Retrieve revenue totals by status.
     *
     * Queries the workfern_failed_payments table for SUM(amount)
     * grouped by failed vs recovered status.
     *
     * @since  1.0.0
     * @access private
     * @return array {
     *     @type float $failed      Total failed revenue (still in 'failed' status).
     *     @type float $recovered   Total recovered revenue.
     *     @type float $outstanding Difference: total ever failed minus recovered.
     * }
     */
    private function get_revenue_stats()
    {
        global $wpdb;

        $table_name = esc_sql( $wpdb->prefix . 'workfern_failed_payments' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            "SELECT status, SUM(amount) as total FROM {$wpdb->prefix}workfern_failed_payments GROUP BY status"
        );

        $stats = array(
            'failed'      => 0.00,
            'recovered'   => 0.00,
            'outstanding' => 0.00,
        );

        $total_ever_failed = 0.00;

        if ($results) {
            foreach ($results as $row) {
                $amount = floatval($row->total);
                $total_ever_failed += $amount;

                if ('recovered' === $row->status) {
                    $stats['recovered'] = $amount;
                } elseif ('failed' === $row->status) {
                    $stats['failed'] = $amount;
                }
            }
        }

        $stats['outstanding'] = $stats['failed'];

        return $stats;
    }
}
