# Hellio Messaging for Magento 2

Send order-status SMS, admin new-order alerts, checkout phone OTP verification,
and bulk marketing SMS through the [Hellio Messaging](https://helliomessaging.com)
API. All API calls are server-side; your API token never reaches the browser.

- Vendor: Hellio Solutions
- Module: `Hellio_Sms` (`hellio/module-sms`)
- Version: 1.0.0
- Support: support@helliomessaging.com
- Requires: Magento 2.3+ / 2.4+, PHP 7.4 to 8.3

## Features

1. Customer order-status SMS. When an order changes to a status you enabled,
   the module renders that status's template and texts the order's billing phone.
2. Admin new-order alert. On each new order, every configured admin number gets
   an SMS built from the alert template.
3. Checkout phone OTP. Before an order is placed the shopper verifies their
   phone: a server-side "Send code" endpoint calls the Hellio API, a code field
   plus a "Verify" endpoint confirms it, and order placement is blocked
   server-side until the phone is verified. Skips gracefully when OTP is off.
4. Bulk / marketing SMS. An admin page to compose a message, pick an audience
   (all customers, customers by order status, or a pasted list), and send in
   chunks of 500 with a sent / failed report.

## Install

### Composer (recommended)

```
composer require hellio/module-sms
bin/magento module:enable Hellio_Sms
bin/magento setup:upgrade
bin/magento setup:di:compile        # production mode only
bin/magento cache:flush
```

### Manual (app/code)

Copy this directory to `app/code/Hellio/Sms` in your Magento root, then:

```
bin/magento module:enable Hellio_Sms
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configure

Stores > Configuration > Hellio Messaging > Hellio Messaging
(`Admin > Stores > Settings > Configuration`, section `hellio_sms`).

- General: master enable, API base URL (default `https://api.helliomessaging.com`),
  a "Connect with your Hellio login" panel, API token (stored encrypted),
  Sender ID, default dial code, a Test Connection button that calls
  `GET /v1/balance`, and a Send Test SMS panel.
- Connect with your Hellio login: enter your Hellio account email and password
  and click Connect. The module calls `POST /v1/auth/token` server-side, stores
  the returned token encrypted, and shows "Connected as {email}". If the account
  has two-factor auth, a code field appears; enter the code and connect again.
  Your password is never stored. Pasting a token by hand stays available as an
  alternative, and Disconnect clears the stored token.
- Send Test SMS: enter a recipient, a Sender ID (pre-filled from the saved one,
  editable), and a message that may use the template placeholders. The message
  renders against your most recent order (blank when there is none) and sends
  via `POST /v1/sms/send`, showing the API reference and status or the error.
- Customer Order-Status SMS: enable, plus a dynamic-rows grid mapping each order
  status to a message template. Only listed statuses trigger an SMS.
- Admin New-Order Alert: enable, comma separated admin numbers, and a template.
- Checkout Phone OTP: enable, code length (4 to 10), expiry (1 to 1440 minutes).

Bulk SMS lives under the top-level `Hellio Messaging > Bulk SMS` admin menu.

### Default dial code

Local numbers are normalized before sending: a leading `0` is stripped and the
dial code is prepended (for example dial code `233` turns `0241111111` into
`233241111111`). Numbers with a `+` or an international `00` prefix keep their
country code.

## Template placeholders

Use these in any order-status or admin-alert template. Unknown placeholders
render empty.

| Placeholder | Value |
|-------------|-------|
| `{order_id}` | Internal order entity id |
| `{order_number}` | Customer-facing increment id |
| `{order_status}` | Current order status code |
| `{order_total}` | Grand total, two decimals |
| `{currency}` | Order currency code |
| `{customer_name}` | Full billing name |
| `{customer_first_name}` | Billing first name |
| `{store_name}` | Store front name |
| `{shop_url}` | Store base URL |
| `{tracking_url}` | Order view URL |
| `{date}` | Order created date |

## Checkout OTP notes

The server-side pieces are complete: the `hellio_sms/otp/send` and
`hellio_sms/otp/verify` controllers, the `OtpSession` flag, the
`ConfigProvider`, and the `EnforceOtpPlugin` / `EnforceOtpGuestPlugin` that block
`savePaymentInformationAndPlaceOrder` until the session is verified.

The UI component (`view/frontend/web/js/view/checkout-otp.js` and its template)
is injected into the checkout by `Model/Checkout/LayoutProcessor`, which targets
the shipping step (falling back to the payment step). Checkout themes vary. If
your theme uses a custom checkout layout and the widget does not appear, add the
`hellio_otp` component to your checkout layout at the step you prefer, pointing
at `Hellio_Sms/js/view/checkout-otp`. The enforcement plugin guarantees the
order cannot be placed unverified regardless of where the widget renders.

## API contract

- `POST /v1/sms/send`, `POST /v1/otp/send`, `POST /v1/otp/verify`, `GET /v1/balance`.
- `Authorization: Bearer {token}`, `Accept: application/json`, POSTs add
  `Content-Type: application/json` and a fresh `Idempotency-Key` UUID.
- 15 second timeout. Every call is wrapped in try/catch: an API or network
  failure is logged and swallowed, never breaking order save or checkout.

## Changelog

### 1.0.0

- Initial release: order-status SMS, admin new-order alerts, checkout phone OTP,
  bulk SMS, Test Connection button, encrypted token storage.
- Connect with your Hellio login (one-click token exchange via
  `POST /v1/auth/token`, with two-factor support and Disconnect).
- Send Test SMS panel that previews template placeholders against a sample order.
