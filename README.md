# Hellio Messaging e-commerce plugins

SMS, OTP and order-notification plugins that connect a store to the Hellio
Messaging API, one per platform, all sharing the contract in [SPEC.md](SPEC.md).

| Platform | Folder | Ships as |
|---|---|---|
| WooCommerce (WordPress) | `woocommerce/hellio-sms/` | `hellio-sms-woocommerce-<ver>.zip` |
| OpenCart 3.x | `opencart/` | `hellio-sms-opencart-<ver>.ocmod.zip` |
| PrestaShop 1.7 / 8 | `prestashop/helliosms/` | `helliosms-prestashop-<ver>.zip` |
| Magento 2 | `magento2/Hellio/Sms/` | `hellio-sms-magento2-<ver>.zip` |

Every plugin offers the same things: connect once with your Hellio login (or
paste an API token), a Send SMS panel that takes one or many pasted numbers with
an editable Sender ID, per-order-status customer SMS, admin new-order alerts,
checkout OTP verification, and an audience-based bulk sender.

## Build the installable archives

```bash
./plugins/build.sh          # writes all four into plugins/dist/
./plugins/build.sh 1.0.1     # override the version in the file names
```

`plugins/dist/` is git-ignored; run the script to (re)generate the archives.

## Install

- **WooCommerce**: WP Admin, Plugins, Add New, Upload Plugin, choose the zip,
  Activate. Then WooCommerce, Hellio SMS to configure.
- **OpenCart**: Extensions, Installer, upload the `.ocmod.zip`, then Extensions,
  Modifications, Refresh. Enable under Extensions, Modules, Hellio SMS.
- **PrestaShop**: Modules, Module Manager, Upload a module, choose the zip, then
  Configure.
- **Magento 2**: extract the zip into `app/code/` (so it lands at
  `app/code/Hellio/Sms`), then `bin/magento setup:upgrade` and
  `bin/magento cache:flush`. Configure under Stores, Configuration, Hellio
  Messaging.

## Configure

Open the plugin settings and either click Connect and sign in with your Hellio
account (enter a two-factor code if your account uses one), or paste an API
token from your Hellio dashboard. Set a default Sender ID, then use the Send SMS
panel to fire a test message. See each plugin's own README for the full field
list and the template placeholders.
