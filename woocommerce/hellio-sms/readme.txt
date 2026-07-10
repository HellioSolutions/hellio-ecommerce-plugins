=== Hellio Messaging for WooCommerce ===
Contributors: helliosolutions
Tags: sms, woocommerce, otp, order notifications, bulk sms
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send order-status SMS, admin new-order alerts, checkout phone OTP, and bulk marketing SMS through the Hellio Messaging API.

== Description ==

Hellio Messaging for WooCommerce connects your store to the Hellio Messaging SMS platform. It adds four features, all driven server-side so your API token never reaches the browser.

* Customer order-status SMS. Text the customer whenever an order reaches a status you enabled, using an editable per-status template.
* Admin new-order alert. Text one or more staff numbers as soon as an order is placed.
* Checkout phone OTP. Ask the customer to verify their phone with a one-time code before the order is placed. Verification is enforced server-side.
* Bulk / marketing SMS. Compose a message and send it to all customers, to customers filtered by last order status, or to a pasted list of numbers, in chunks of 500.

All messages are sent through the Hellio REST API using the WordPress HTTP API. Every request carries a Bearer token, and every POST carries a fresh Idempotency-Key so retries never double-charge. API failures are logged and never break checkout or order saving.

= Template placeholders =

Use these in any template. Unknown placeholders render empty.

`{order_id}` `{order_number}` `{order_status}` `{order_total}` `{currency}` `{customer_name}` `{customer_first_name}` `{store_name}` `{shop_url}` `{tracking_url}` `{date}`

== Installation ==

1. Upload the `hellio-sms` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin through the Plugins screen. WooCommerce must be active.
3. Go to WooCommerce > Hellio SMS.
4. Turn on the master switch, paste your API token from your Hellio dashboard, set your Sender ID and default dial code.
5. Click Test connection to confirm your token works.
6. Enable the order statuses you want to message on and edit their templates.
7. Optional: enable the admin alert and add staff numbers. Enable checkout OTP if you want phone verification.
8. Send campaigns from WooCommerce > Hellio Bulk SMS.

== Frequently Asked Questions ==

= Where do I get an API token? =

Generate a personal access token in your Hellio Messaging dashboard. The token needs the sms:send, otp, and balance abilities.

= Does my token reach the customer's browser? =

No. All Hellio API calls run on your server. The checkout OTP send and verify go through admin-ajax and never expose the token.

= What happens if the API is down during checkout? =

Nothing breaks. Failures are logged and order processing continues. The OTP step, when enabled, will show the customer an error and let them retry.

== Changelog ==

= 1.0.0 =
* Initial release: customer order-status SMS, admin new-order alerts, checkout phone OTP, and bulk marketing SMS.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
