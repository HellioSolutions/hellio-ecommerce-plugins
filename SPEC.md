# Hellio Messaging e-commerce plugins, shared spec

One SMS/OTP integration, implemented idiomatically for four platforms:
WooCommerce, OpenCart 3.x, PrestaShop 1.7/8, and Magento 2. Every plugin talks
to the same Hellio Messaging REST API and exposes the same four features and the
same settings, so a merchant who uses two carts sees one product.

Product name: **Hellio Messaging**. Vendor: Hellio Solutions. Homepage:
https://helliomessaging.com. Support: support@helliomessaging.com. Version 1.0.0.

## The Hellio API (authoritative contract)

- Base URL: setting `api_base_url`, default `https://api.helliomessaging.com`.
- Auth: `Authorization: Bearer {api_token}` (a Sanctum personal access token the
  merchant generates in their Hellio dashboard).
- Every request also sends `Accept: application/json`. POSTs send
  `Content-Type: application/json` and a fresh `Idempotency-Key: {uuid}` header
  (safe to retry without double-charging).
- Timeout 15s. Never let an API failure break checkout or order processing:
  wrap every call in try/catch, log the error, and continue.

### POST /v1/sms/send  (token ability: `sms:send`)
Request body:
```json
{ "recipients": ["233241111111", "..."], "sender": "MyStore", "message": "..." }
```
- `recipients`: array of phone strings, each max 20 chars, 1..10000 items.
- `sender`: Sender ID, max 11 chars, must be approved on the Hellio account.
- `message`: max 1600 chars.
Success `202`:
```json
{ "data": { "reference": "uuid", "campaign_id": 123, "status": "queued",
  "accepted_recipients": 1, "invalid_recipients": 0, "segments": 1,
  "estimated_cost": "0.0350", "currency": "GHS" } }
```
Errors carry `{ "message": "...", "error": "sender_not_approved" }` with status
422 (`sender_not_approved`, `no_valid_recipients`, `traffic_not_routable`),
402 (`insufficient_balance`), 403 (`spend_limit_exceeded`).

### POST /v1/otp/send  (token ability: `otp`)
```json
{ "mobile_number": "233241111111", "sender": "MyStore",
  "length": 6, "expiry": 5, "purpose": "checkout" }
```
- `mobile_number` required, max 20. `sender` required, max 11.
- `length` optional 4..10 (digits). `expiry` optional 1..1440 (minutes).
- `purpose` optional, max 32.
Success `201`: `{ "data": { "reference": "uuid", "mobile_number": "...",
"expires_in_minutes": 5, "status": "queued" } }`. Same error envelope as above,
plus `429` (`throttled`, honour `Retry-After`).

### POST /v1/otp/verify  (token ability: `otp`)
```json
{ "mobile_number": "233241111111", "code": "123456" }
```
`200` `{ "data": { "verified": true, "status": "verified" } }`, or `422`
`{ "data": { "verified": false, "status": "invalid" } }`.

### GET /v1/balance  (token ability: `balance`)
Used by the "Test connection" button. `200` returns the wallet balance JSON.

### POST /v1/auth/token  (public, no token)
Exchanges account credentials for an API token, so the merchant connects the
plugin once with their Hellio login instead of pasting a token.
```json
{ "email": "me@store.com", "password": "...", "device_name": "WooCommerce",
  "two_factor_code": "123456" }
```
- `email`, `password` required. `device_name` optional (labels the token).
- `two_factor_code` only needed if the account has 2FA enabled.
Success `201`: `{ "data": { "token": "1|xxxxx", "abilities": ["sms:send", ...],
"user": { "name": "...", "email": "..." } } }`. Store `data.token` as the
`api_token` setting. Errors: `401` (`invalid_credentials`), `403`
(`email_unverified`, `account_locked`), `422` (`two_factor_required`), `429`
(`throttled`). The endpoint is rate-limited per email+IP; on
`two_factor_required` reveal a 2FA-code field and let the merchant retry.

## Settings (identical field set on every platform)

- **Connect with your Hellio login** (primary): email + password fields and a
  "Connect" button that POSTs to /v1/auth/token and stores the returned token,
  so the merchant authenticates once and never handles a raw token. If the
  response is `two_factor_required`, reveal a 2FA-code field and retry. Show the
  connected account's email once connected, with a "Disconnect" action. Pasting
  a token by hand stays available as an alternative.
- **Send SMS** panel (doubles as the test-send and a quick send-to-list): a
  recipients box that accepts one number or many pasted numbers (comma, space or
  newline separated), a Sender ID field (defaults to the saved Sender ID but
  editable), and a message box that accepts the template placeholders. A "Send"
  button renders the placeholders against a sample order (or blank if none),
  splits the pasted numbers, and sends them via /v1/sms/send in one call (chunk
  at 500 if a very long list is pasted), then shows the API result (accepted
  count, reference/status, or the error). This lets a merchant confirm sending,
  preview a personalised message, and fire off an ad-hoc blast without opening
  the full Bulk page. The Bulk page still exists for audience-based sends (all
  customers / by order status).
- `enabled` master toggle.
- `api_base_url` (default above), `api_token` (stored encrypted/masked).
- `sender_id` default Sender ID (max 11).
- `default_dial_code` e.g. `233`. Applied to local numbers: strip a leading `0`
  and prepend this when the number has no `+` and no country code.
- **Test connection** button, calls GET /v1/balance, shows balance or the error.
- Customer order-status SMS: a per-status enable toggle + editable template.
- Admin new-order alert: enable + admin recipient number(s), comma separated + template.
- Checkout OTP: enable + code length + expiry minutes.
- Bulk SMS: an admin page (compose + choose audience + send).

## Feature behaviour

1. **Customer order-status SMS.** On an order changing to a status the merchant
   enabled, render that status's template and SMS the order's billing phone.
2. **Admin new-order alert.** On a new order, SMS each configured admin number
   with the alert template.
3. **Checkout OTP.** Before an order is placed, the customer verifies their
   phone: a "Send code" action (server-side endpoint, token never reaches the
   browser) calls otp/send; a code field + verify calls otp/verify; the order is
   blocked server-side until verified. Skip gracefully if OTP disabled.
4. **Bulk / marketing SMS.** Admin page: compose a message, pick an audience
   (all customers, or customers filtered by order status, or a pasted list),
   send via /v1/sms/send in chunks of 500, report sent/failed counts.

## Template placeholders (map each to the platform's order object)

`{order_id}` `{order_number}` `{order_status}` `{order_total}` `{currency}`
`{customer_name}` `{customer_first_name}` `{store_name}` `{shop_url}`
`{tracking_url}` `{date}`. Unknown placeholders render empty.

## Non-negotiables

- Server-side sends only; the API token never reaches the browser.
- Defensive: an API/network failure logs and returns, never throwing into
  checkout or order save.
- Escape/sanitise all admin I/O; use the platform's CSRF/nonce mechanism.
- No em-dash character anywhere in code or copy. Use a period, comma, colon,
  parentheses, or a plain hyphen.
- Ship a README per plugin (install, configure, placeholder list, changelog)
  and the manifest/metadata the platform requires. Run `php -l` on every PHP
  file; all must be syntax-clean.
