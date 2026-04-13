<?php
/**
 * Plugin Settings Page.
 *
 * Registers the "Recovery Settings" admin page with
 * two sections: Recovery Settings and Email Settings.
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
 * Plugin Settings class.
 *
 * @since 1.0.0
 */
class WORKFERN_Plugin_Settings
{

    /**
     * Page slug.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $page_slug = 'workfern_settings';

    /**
     * Parent menu slug.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $parent_slug = 'workfern_failed_payments';

    /**
     * Settings group name.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $settings_group = 'workfern_settings_group';

    /**
     * Option name for all plugin settings.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $option_name = 'workfern_settings';

    /**
     * Required capability.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $capability = 'manage_options';

    /**
     * Initialize the settings page.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Registration
    |--------------------------------------------------------------------------
    */

    /**
     * Register the Settings submenu page under Stripe Recovery.
     *
     * @since  1.0.0
     * @return void
     */
    public function register_menu()
    {
        add_submenu_page(
            $this->parent_slug,
            __('Recovery Settings', 'workfern-subscription-payment-recovery'),
            __('Settings', 'workfern-subscription-payment-recovery'),
            $this->capability,
            $this->page_slug,
            array($this, 'render_page')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Settings Registration
    |--------------------------------------------------------------------------
    */

    /**
     * Register settings, sections, and fields via the Settings API.
     *
     * @since  1.0.0
     * @return void
     */
    public function register_settings()
    {
        register_setting(
            $this->settings_group,
            $this->option_name,
            array($this, 'sanitize_settings')
        );

        /*
        |----------------------------------------------------------------------
        | Section 1: Recovery Settings
        |----------------------------------------------------------------------
        */

        add_settings_section(
            'workfern_recovery_section',
            __('Recovery Settings', 'workfern-subscription-payment-recovery'),
            array($this, 'render_recovery_section'),
            $this->page_slug
        );



        add_settings_field(
            'abandon_after_days',
            __('Abandon After (days)', 'workfern-subscription-payment-recovery'),
            array($this, 'render_field_abandon_after_days'),
            $this->page_slug,
            'workfern_recovery_section'
        );



        add_settings_field(
            'admin_notification',
            __('Admin Notification', 'workfern-subscription-payment-recovery'),
            array($this, 'render_field_admin_notification'),
            'workfern_recovery_section'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Section Descriptions
    |--------------------------------------------------------------------------
    */

    /**
     * Render Recovery section description.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_recovery_section()
    {
        echo '<p>' . esc_html__('Configure how the plugin handles payment recovery retry logic.', 'workfern-subscription-payment-recovery') . '</p>';
    }



    /*
    |--------------------------------------------------------------------------
    | Recovery Fields
    |--------------------------------------------------------------------------
    */

    /**
     * Render Max Retry Attempts field.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_field_max_retry_attempts()
    {
        $value = $this->get_setting('max_retry_attempts', 5);
        ?>
        <input type="number" id="workfern_max_retry_attempts"
            name="<?php echo esc_attr($this->option_name); ?>[max_retry_attempts]" value="<?php echo esc_attr($value); ?>"
            class="small-text" min="1" max="10" step="1" />
        <p class="description">
            <?php esc_html_e('Maximum number of retry attempts per failed payment (1Ã¢â‚?0). Default: 5.', 'workfern-subscription-payment-recovery'); ?>
        </p>
        <?php
    }

    /**
     * Render Retry Interval field.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_field_retry_interval()
    {
        $value = $this->get_setting('retry_interval', 6);
        ?>
        <input type="number" id="workfern_retry_interval" name="<?php echo esc_attr($this->option_name); ?>[retry_interval]"
            value="<?php echo esc_attr($value); ?>" class="small-text" min="1" max="72" step="1" />
        <span class="description">
            <?php esc_html_e('hours', 'workfern-subscription-payment-recovery'); ?>
        </span>
        <p class="description">
            <?php esc_html_e('Time between automatic retry attempts (1Ã¢â‚?2 hours). Default: 6.', 'workfern-subscription-payment-recovery'); ?>
        </p>
        <?php
    }

    /**
     * Render Auto Retry enabled toggle.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_field_auto_retry_enabled()
    {
        $value = $this->get_setting('auto_retry_enabled', 'yes');
        ?>
        <label for="workfern_auto_retry_enabled">
            <input type="checkbox" id="workfern_auto_retry_enabled"
                name="<?php echo esc_attr($this->option_name); ?>[auto_retry_enabled]" value="yes" <?php checked($value, 'yes'); ?>
            />
            <?php esc_html_e('Automatically retry failed payments via WP-Cron', 'workfern-subscription-payment-recovery'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When disabled, you can still retry payments manually from the Failed Payments page.', 'workfern-subscription-payment-recovery'); ?>
        </p>
        <?php
    }

    /**
     * Render Abandon After Days field.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_field_abandon_after_days()
    {
        $value = $this->get_setting('abandon_after_days', 14);
        ?>
        <input type="number" id="workfern_abandon_after_days"
            name="<?php echo esc_attr($this->option_name); ?>[abandon_after_days]" value="<?php echo esc_attr($value); ?>"
            class="small-text" min="1" max="90" step="1" />
        <span class="description">
            <?php esc_html_e('days', 'workfern-subscription-payment-recovery'); ?>
        </span>
        <p class="description">
            <?php esc_html_e('Automatically mark a failed payment as abandoned after this many days (1Ã¢â‚?0). Default: 14.', 'workfern-subscription-payment-recovery'); ?>
        </p>
        <?php
    }



    /**
     * Render Admin Notification toggle.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_field_admin_notification()
    {
        $value = $this->get_setting('admin_notification', 'yes');
        ?>
        <label for="workfern_admin_notification">
            <input type="checkbox" id="workfern_admin_notification"
                name="<?php echo esc_attr($this->option_name); ?>[admin_notification]" value="yes" <?php checked($value, 'yes'); ?>
            />
            <?php esc_html_e('Send admin an email when a payment fails or is recovered', 'workfern-subscription-payment-recovery'); ?>
        </label>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | Sanitization
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize all settings before saving.
     *
     * @since 1.0.0
     *
     * @param array $input The raw input values from the form.
     * @return array Sanitized values.
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        // Recovery Settings.


        $sanitized['abandon_after_days'] = isset($input['abandon_after_days'])
            ? min(90, max(1, intval($input['abandon_after_days'])))
            : 14;



        $sanitized['admin_notification'] = isset($input['admin_notification']) && 'yes' === $input['admin_notification']
            ? 'yes'
            : 'no';

        /**
         * Filters the sanitized settings before they are saved.
         *
         * @since 1.0.0
         *
         * @param array $sanitized The sanitized settings.
         * @param array $input     The raw input.
         */
        return apply_filters('workfern_sanitize_settings', $sanitized, $input);
    }

    /*
    |--------------------------------------------------------------------------
    | Page Render
    |--------------------------------------------------------------------------
    */

    /**
     * Render the settings page.
     *
     * @since  1.0.0
     * @return void
     */
    public function render_page()
    {
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'workfern-subscription-payment-recovery'));
        }

        ?>
        <div class="wrap workfern-settings-wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Recovery Settings', 'workfern-subscription-payment-recovery'); ?>
            </h1>
            <hr class="wp-header-end">

            <form method="post" action="options.php">
                <?php
                settings_fields($this->settings_group);
                do_settings_sections($this->page_slug);
                submit_button(__('Save Settings', 'workfern-subscription-payment-recovery'));
                ?>
            </form>
        </div>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get a single setting value.
     *
     * @since 1.0.0
     *
     * @param string $key     The setting key.
     * @param mixed  $default Default value if not set.
     * @return mixed The setting value.
     */
    public function get_setting($key, $default = '')
    {
        $options = get_option($this->option_name, array());

        if (!is_array($options)) {
            return $default;
        }

        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Get all settings.
     *
     * @since 1.0.0
     *
     * @return array All plugin settings with defaults.
     */
    public function get_all_settings()
    {
        $defaults = array(
            'abandon_after_days' => 14,
            'admin_notification' => 'yes',
        );

        $saved = get_option($this->option_name, array());

        if (!is_array($saved)) {
            $saved = array();
        }

        return wp_parse_args($saved, $defaults);
    }

    /**
     * Static helper to quickly retrieve a setting.
     *
     * Usage: WORKFERN_Plugin_Settings::get( 'max_retry_attempts', 5 )
     *
     * @since 1.0.0
     *
     * @param string $key     The setting key.
     * @param mixed  $default Default value.
     * @return mixed The value.
     */
    public static function get($key, $default = '')
    {
        $options = get_option('workfern_settings', array());

        if (!is_array($options)) {
            return $default;
        }

        return isset($options[$key]) ? $options[$key] : $default;
    }
}
