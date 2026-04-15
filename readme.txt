=== Workfern Subscriptions Recovery for WooCommerce ===
Contributors: workfern, arinoach
Tags: woocommerce, subscriptions, payment recovery, stripe, failed payments
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.1.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

Automatically recover failed subscription renewal payments in WooCommerce. Tracks failed and recovered revenue via an analytics dashboard.

== Description ==

**Workfern Subscriptions Recovery for WooCommerce** automatically detects failed subscription renewal payments and helps you win back lost revenue without any manual effort.

This is the **free version** of the plugin. It includes full payment failure detection, recovery tracking, and an analytics dashboard. For automated recovery emails and advanced features, check out the [Pro version](https://wordpress.workfern.com/).

= Free Features =

* **Failed Payment Detection**  - ?Automatically detects when a subscription renewal payment fails via WooCommerce internal hooks.
* **Recovery Analytics Dashboard**  - ?Monitor failed payments, recovered payments, recovery rate, and revenue at a glance.
* **Recovery Log**  - ?Full log of every failed payment attempt and its current status (pending, recovered, failed).
* **Works with WooCommerce Subscriptions**  - ?Compatible with WooCommerce Subscriptions.
* **No Stripe Webhooks Required**  - ?Uses WooCommerce internal hooks to detect payment failures. No external webhook configuration needed.
* **HPOS Compatible**  - ?Fully supports WooCommerce High-Performance Order Storage (HPOS / Custom Order Tables).

= Pro Features =

Upgrade to [Subscriptions Payment Recovery Pro](https://wordpress.workfern.com/) for additional features:

* **Automated Recovery Emails**  - ?Sends a 3-step drip sequence (Day 1, Day 3, Day 5) to customers whose payments have failed, with a direct link to update their payment method.
* **Customizable Email Templates**  - ?Personalize recovery email subjects and body content for each reminder step to match your brand voice.
* **Smart Retry Scheduling**  - ?Configure automatic payment retry intervals and maximum attempts.
* **Priority Email Support**  - ?Get dedicated support from our team.

[Get the Pro version ->(https://wordpress.workfern.com/)

== Installation ==

1. Upload the `workfern-subscription-payment-recovery` folder to the `/wp-content/plugins/` directory, or install the plugin through the **WordPress Plugins** screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Make sure **WooCommerce** is installed and activated.
4. Optionally, install **WooCommerce Subscriptions** to enable automatic detection of subscription renewal failures.
5. Navigate to **WooCommerce ->?Subscriptions Payment Recovery** to view your dashboard and configure settings.

== Frequently Asked Questions ==

= Does this plugin require Stripe webhooks? =

No. This plugin listens directly to WooCommerce's internal events (e.g., subscription status changes and order status transitions). No Stripe webhook configuration is required.

= Which payment gateways are supported? =

The plugin works with any payment gateway supported by WooCommerce Subscriptions. It monitors WooCommerce-level payment failure events, not gateway-specific APIs.

= Is there a Pro version? =

Yes! The [Pro version](https://wordpress.workfern.com/) adds automated recovery emails (a 3-step drip sequence), customizable email templates, smart retry scheduling, and priority support. You can upgrade at any time from the "Go Pro" tab in the plugin dashboard.

= Will recovery emails be sent in the free version? =

The free version does not send recovery emails. Automated recovery emails are available in the [Pro version](https://wordpress.workfern.com/). The free version provides full payment failure detection, tracking, analytics.

= Is the plugin compatible with WooCommerce HPOS (High-Performance Order Storage)? =

Yes. The plugin is fully compatible with WooCommerce's HPOS (Custom Order Tables) feature.

== External services ==

This plugin connects to the **Stripe API** (`https://api.stripe.com`) to confirm and recover failed subscription payments.

= What data is sent =

* **Payment Intent IDs**  - ?used to retrieve the status of failed payments and to confirm (retry) them via the Stripe API.
* **API credentials**  - ?your Stripe Secret Key is sent as an authorization header with each request. It is never shared with any third party.

No customer personal data is sent directly by this plugin to Stripe beyond what Stripe already holds as your payment processor.

= When the connection occurs =

* When the plugin automatically retries a failed payment.
* When verifying Stripe API credentials on the settings page.

= Stripe policies =

* [Stripe Terms of Service](https://stripe.com/legal)
* [Stripe Privacy Policy](https://stripe.com/privacy)

By using this plugin, you agree to Stripe's Terms of Service and Privacy Policy.

== Changelog ==

= 2.1.2 =
* Fix: Resolved PHPCS warning related to nonce verification on plugin activation checks.

= 2.1.0 =
* Improvement: Clean free version for WordPress.org  - ?no third-party SDKs.
* Fix: Removed Pro email reminder and retry logic to comply with WordPress.org guidelines.
* Improvement: Added "Go Pro" tab with feature comparison and upgrade link.
* Improvement: Added "Settings" and "Go Pro" action links in Plugins page.
* Improvement: Standard uninstall.php for proper data cleanup.
* Improvement: All admin styles consolidated into external CSS file.

= 2.0.1 =
* Fix: Removed development testing script for directory compliance.

= 2.0.0 =
* Initial public release.
* Recovery analytics dashboard with charts.
* Full recovery log with pagination.
* WooCommerce Subscriptions compatibility.
* HPOS / Custom Order Tables compatibility.

== Upgrade Notice ==

= 2.1.0 =
Clean free version. Removed third-party SDK for full WordPress.org compliance.

= 2.0.1 =
Minor compliance update.

= 2.0.0 =
Initial release. No upgrade required.
