# Hellio Messaging for WooCommerce

Send transactional and marketing SMS from your WooCommerce store through the [Hellio Messaging](https://helliomessaging.com) API. Version 1.0.0.

Four features, all server-side (your API token never reaches the browser):

1. Customer order-status SMS, with an editable template per order status.
2. Admin new-order alert to one or more staff numbers.
3. Checkout phone OTP verification, enforced server-side before an order is placed.
4. Bulk / marketing SMS to all customers, customers by last order status, or a pasted list.

## Requirements

- WordPress 5.8 or newer
- WooCommerce 6.0 or newer
- PHP 7.4 or newer
- A Hellio Messaging account with a personal access token (abilities: `sms:send`, `otp`, `balance`) and an approved Sender ID

## Install

1. Copy the `hellio-sms` folder into `wp-content/plugins/`, or zip it and upload it under Plugins > Add New > Upload Plugin.
2. Activate it. WooCommerce must already be active.
3. A "Settings" link appears on the plugin row, or go to WooCommerce > Hellio SMS.

## Configure

Open **WooCommerce > Hellio SMS** and set:

- **Enable Hellio SMS**: master switch. Nothing sends while this is off.
- **API base URL**: defaults to `https://api.helliomessaging.com`.
- **API token**: your Sanctum personal access token. Stored server-side.
- **Sender ID**: your approved sender, up to 11 characters.
- **Default dial code**: for example `233`. Local numbers lose a leading `0` and gain this code. Numbers that already start with `+` or the country code are left alone.
- **Test connection**: calls `GET /v1/balance` and shows your wallet balance or the error.

Then enable the order statuses you want to message on and edit each template, optionally enable the admin alert with comma-separated staff numbers, and optionally enable checkout OTP with a code length and expiry.

Send campaigns from **WooCommerce > Hellio Bulk SMS**.

## How it is wired

| Feature | Hook | File |
| --- | --- | --- |
| Order-status SMS | `woocommerce_order_status_changed` | `includes/class-hellio-order-sms.php` |
| Admin new-order alert | `woocommerce_checkout_order_processed`, `woocommerce_store_api_checkout_order_processed` | `includes/class-hellio-admin-alert.php` |
| Checkout OTP UI | `woocommerce_after_checkout_billing_form` | `includes/class-hellio-otp.php` |
| Checkout OTP send/verify | `wp_ajax(_nopriv)_hellio_sms_otp_send`, `wp_ajax(_nopriv)_hellio_sms_otp_verify` | `includes/class-hellio-otp.php` |
| Checkout OTP enforcement | `woocommerce_after_checkout_validation` | `includes/class-hellio-otp.php` |
| Bulk SMS | WooCommerce submenu page `hellio-sms-bulk` | `includes/class-hellio-bulk.php` |

The API client lives in `includes/class-hellio-client.php` and uses `wp_remote_post` / `wp_remote_get` with a 15 second timeout, a Bearer token, `Accept: application/json`, and a fresh `Idempotency-Key` on every POST.

## Template placeholders

Use these in any template. Unknown placeholders render empty.

`{order_id}` `{order_number}` `{order_status}` `{order_total}` `{currency}` `{customer_name}` `{customer_first_name}` `{store_name}` `{shop_url}` `{tracking_url}` `{date}`

## Endpoints used

- `POST /v1/sms/send` for order, admin, and bulk messages
- `POST /v1/otp/send` and `POST /v1/otp/verify` for checkout verification
- `GET /v1/balance` for the Test connection button

## Notes on reliability

- Every API call is wrapped in try/catch. A network or API error is logged (via the WooCommerce logger when available, source `hellio-sms`) and never breaks checkout or order save.
- POSTs carry an `Idempotency-Key`, so a retried request will not double-charge.
- The checkout OTP token stays on the server. The browser only ever sees a nonce.

## Changelog

### 1.0.0
- Initial release: customer order-status SMS, admin new-order alerts, checkout phone OTP, and bulk marketing SMS.

## Support

support@helliomessaging.com
