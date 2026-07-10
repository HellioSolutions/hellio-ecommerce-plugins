# Hellio Messaging for OpenCart 3.x

Send transactional and marketing SMS, and verify customers at checkout with a
one time code, straight from your OpenCart store. Powered by the
[Hellio Messaging](https://helliomessaging.com) API.

- **Vendor:** Hellio Solutions
- **Support:** support@helliomessaging.com
- **Version:** 1.0.0
- **Compatibility:** OpenCart 3.0.x

## Features

1. **Customer order-status SMS.** Pick which order statuses text the customer,
   and edit each message. The order telephone number is used.
2. **Admin new-order alert.** Text one or more staff numbers whenever a new
   order is placed.
3. **Checkout OTP.** Ask customers to verify their phone with a one time code
   before the order is placed. Verification is enforced server side: the order
   cannot be confirmed until the code is verified. The API token never reaches
   the browser.
4. **Bulk / marketing SMS.** Compose a message, choose an audience (all
   customers, customers filtered by order status, or a pasted list), and send.
   Recipients go out in batches of 500 and the page reports sent and failed
   counts.

## Requirements

- OpenCart 3.0.x
- PHP 7.0 or newer with cURL enabled
- A Hellio Messaging account and an approved Sender ID
- A Hellio API token (a personal access token generated in your Hellio
  dashboard) with the `sms:send`, `otp`, and `balance` abilities

## Install

### Via the Extension Installer (recommended)

1. Zip the `upload/` folder together with `install.xml` so the archive root
   contains `upload/` and `install.xml`.
2. In the admin, go to **Extensions > Installer** and upload the zip.
3. Go to **Extensions > Modifications** and click **Refresh** so the checkout
   OTP modification is applied.
4. Go to **Extensions > Extensions**, choose **Modules** in the type filter,
   find **Hellio Messaging**, and click the blue **Install** (+) button. This
   registers the order events.
5. Click the **Edit** (pencil) button to open the settings.

### Manual upload

1. Copy the contents of `upload/` into your OpenCart root, merging the
   `admin/`, `catalog/`, and `system/` folders.
2. Copy `install.xml` somewhere you can upload it, then add it under
   **Extensions > Installer** (or place it as an `.ocmod.xml`), and click
   **Refresh** under **Extensions > Modifications**.
3. Install the module under **Extensions > Extensions > Modules** as above.

If you ever see stale behaviour after an upgrade, refresh the modification
cache under **Extensions > Modifications** and clear the theme/SEO caches under
**Dashboard > (gear) > Storage**.

## Configure

Open **Extensions > Extensions > Modules > Hellio Messaging > Edit**.

- **General**
  - **Enable extension** master toggle.
  - **API base URL** default `https://api.helliomessaging.com`.
  - **Connect with your Hellio login** enter your Hellio email and password and
    click **Connect**. The plugin exchanges them for a token server side (POST
    `/v1/auth/token`) and stores it for you, so you never handle a raw token. If
    your account has two factor enabled, a code field appears; enter the code
    and connect again. Once connected you see "Connected as your@email" and a
    **Disconnect** button. Your password is never stored.
  - **API token** your Bearer token, as an alternative to connecting with your
    login. It is stored on the server and never echoed back into the form.
    Leave the field blank to keep the saved token.
  - **Default Sender ID** up to 11 characters, must be approved on your Hellio
    account.
  - **Default dial code** for example `233`. Applied to local numbers that have
    no plus sign or country code: a single leading zero is stripped and the
    dial code is prepended.
  - **Test connection** calls `GET /v1/balance` and shows the balance or the
    error.
- **Order Status SMS** per status enable toggle and editable message.
- **Admin Alerts** enable, comma separated staff numbers, and the alert
  message.
- **Checkout OTP** enable, code length (4 to 10), expiry in minutes (1 to
  1440).
- **Bulk SMS** compose, pick an audience, and send.
- **Send SMS** enter one number or many (separated by comma, space, or new
  line), a Sender ID (prefilled from your default but editable), and a message
  that can use the placeholders. The placeholders are rendered once against your
  most recent order (or left blank if you have none yet), the message is sent
  (chunked at 500 for large lists), and the accepted-recipient count with the
  API reference and status, or the error, is shown.

## Template placeholders

Use these in any message template. Unknown placeholders render empty.

| Placeholder             | Meaning                                  |
| ----------------------- | ---------------------------------------- |
| `{order_id}`            | Order id                                 |
| `{order_number}`        | Order number (same as the id)            |
| `{order_status}`        | Current order status name                |
| `{order_total}`         | Order total, formatted                   |
| `{currency}`            | Order currency code                      |
| `{customer_name}`       | Customer full name                       |
| `{customer_first_name}` | Customer first name                      |
| `{store_name}`          | Store name                               |
| `{shop_url}`            | Store URL                                |
| `{tracking_url}`        | Link to the customer's order information |
| `{date}`                | Current date (Y-m-d)                     |

## How it works

- **Order events.** On install the module registers two events:
  `catalog/model/checkout/order/addOrderHistory/after` (storefront order
  confirmation: customer SMS and the new-order admin alert) and
  `admin/model/sale/order/addOrderHistory/after` (admin status changes:
  customer SMS). Handlers live in
  `catalog/controller/extension/module/hellio_sms_event.php` and
  `admin/controller/extension/module/hellio_sms_event.php`.
- **Checkout OTP.** The `install.xml` OCMOD injects the OTP UI into the
  checkout confirm step and adds a server side guard that blocks the confirm
  output until the session is verified. The browser only ever calls
  `extension/module/hellio_otp/send` and `.../verify`; the API token stays on
  the server.
- **Bulk SMS.** Handled in the admin controller's `bulk()` action, which
  resolves the audience, chunks recipients at 500, and calls `/v1/sms/send`.
- **API client.** `system/library/hellio/client.php` is a plain cURL client
  with a 15 second timeout, Bearer auth, JSON headers, a fresh
  `Idempotency-Key` on every POST, phone normalisation, and structured error
  returns. It never throws into checkout or order processing.

## Uninstall

Click the **Uninstall** (minus) button under **Extensions > Extensions >
Modules > Hellio Messaging** to remove the registered events, then disable and
delete the modification under **Extensions > Modifications** if you want to
remove the checkout injection.

## Changelog

### 1.0.0

- Initial release: order-status SMS, admin new-order alerts, checkout OTP, and
  bulk SMS, all on the Hellio Messaging API.
- Connect with your Hellio login (POST /v1/auth/token), with two factor support
  and a Disconnect action. Pasting a token by hand stays available.
- Send SMS panel: preview placeholders against your most recent order and send
  to one number or a pasted list (chunked at 500).
