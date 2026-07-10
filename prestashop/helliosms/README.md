# Hellio Messaging for PrestaShop

Send order-status SMS, admin new-order alerts, checkout phone OTP verification,
and bulk marketing SMS through the [Hellio Messaging](https://helliomessaging.com)
API. Built for PrestaShop 1.7 and 8 (PHP 7.2+).

- Vendor: Hellio Solutions
- Support: support@helliomessaging.com
- Version: 1.0.0

## What it does

1. **Customer order-status SMS.** When an order changes to a status you have
   enabled, the customer gets an SMS built from that status's template.
2. **Admin new-order alert.** Every new order can text one or more admin numbers.
3. **Checkout OTP.** The customer verifies their phone with a one-time code
   before the order is placed. The API token stays server-side, and the checkout
   step is blocked until verification succeeds. Disable it and checkout is
   untouched.
4. **Bulk / marketing SMS.** An admin page to compose a message, pick an
   audience (all customers, customers filtered by order status, or a pasted
   list), and send in chunks of 500 with a sent/failed report.

## Install

1. Copy the `helliosms` folder into your shop's `modules/` directory (so you
   have `modules/helliosms/helliosms.php`). Or zip the folder and upload it from
   Modules > Module Manager > Upload a module.
2. Find "Hellio Messaging" in the Module Manager and click Install.
3. Click Configure.

## Configure

Open **Modules > Hellio Messaging > Configure**.

- **Connect with your Hellio login** (recommended): enter your Hellio email and
  password and click Connect. The module exchanges them server-side for an API
  token (`POST /v1/auth/token`), stores the token, and shows "Connected as
  {email}". The password is never stored. If your account has two-factor
  enabled, a 2FA code field appears; re-enter your password, add the code, and
  connect again. Use Disconnect to clear the stored token. Pasting a token by
  hand (below) stays available as an alternative.
- **Send SMS** (doubles as a test send and a quick send-to-list): a recipients
  box that accepts one number or many pasted numbers (comma, space, or new line
  separated), a Sender ID (pre-filled from your saved Sender ID but editable),
  and a message that may include the template placeholders. Send renders the
  placeholders once against your most recent order (or leaves them blank if you
  have none), sends via `POST /v1/sms/send` (chunked at 500 for long lists), and
  shows the accepted-recipients count with the reference and status, or the
  error. For audience-based sends (all customers, by order status), use the
  Bulk SMS page.
- **Connection**
  - Enable Hellio Messaging (master toggle).
  - API base URL (default `https://api.helliomessaging.com`).
  - API token: the Bearer token from your Hellio dashboard. It is stored in
    Configuration and shown masked. Leave the masked value in place to keep the
    saved token.
  - Default Sender ID (approved on your Hellio account, max 11 characters).
  - Default dial code, for example `233`. Local numbers get their leading `0`
    stripped and this code prepended.
  - **Test connection** calls `GET /v1/balance` and shows your balance or the
    error.
- **Admin new-order alert**: enable, comma separated admin numbers, and a
  template.
- **Checkout OTP**: enable, code length (4 to 10), expiry in minutes (1 to 1440).
- **Customer order-status SMS**: a per-status toggle and editable template for
  every order status in your shop.

The **Bulk SMS** page lives under **Advanced Parameters area / Modules** as
"Hellio Bulk SMS" (menu entry `AdminHellioBulk`).

## Template placeholders

Use these in any template (unknown placeholders render empty):

- `{order_id}` numeric order id
- `{order_number}` order reference
- `{order_status}` current status name
- `{order_total}` formatted order total
- `{currency}` currency ISO code
- `{customer_name}` full name
- `{customer_first_name}` first name
- `{store_name}` shop name
- `{shop_url}` shop base URL
- `{tracking_url}` order tracking URL
- `{date}` order date

## API

The module calls the Hellio REST API with `Authorization: Bearer {token}`,
`Accept: application/json`, and on POSTs a `Content-Type: application/json` plus
a fresh `Idempotency-Key`. Timeout is 15 seconds. Any network or API failure is
logged through PrestaShopLogger and swallowed, so order processing and checkout
never break.

Endpoints used: `POST /v1/auth/token`, `POST /v1/sms/send`, `POST /v1/otp/send`,
`POST /v1/otp/verify`, `GET /v1/balance`.

## Security

- The API token is never exposed to the browser. OTP send and verify go through
  the module front controller (`controllers/front/otp.php`), which is protected
  by the PrestaShop front controller token.
- The bulk page is protected by the admin token.
- Admin input is sanitized and output is escaped in templates.

## Changelog

### 1.0.0

- Initial release: order-status SMS, admin new-order alerts, checkout OTP, and
  bulk SMS.
