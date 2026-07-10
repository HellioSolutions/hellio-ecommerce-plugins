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

## Cutting a release

Releases are published as GitHub releases under
[HellioSolutions/hellio-ecommerce-plugins](https://github.com/HellioSolutions/hellio-ecommerce-plugins),
and the docs link straight to the release assets. To ship a new version (using
`1.1.0` as the example):

1. **Bump the version in each plugin's manifest.**
   - WooCommerce: `Version:` in `woocommerce/hellio-sms/hellio-sms.php` and
     `Stable tag:` in `readme.txt`.
   - OpenCart: the version string in
     `opencart/upload/admin/controller/extension/module/hellio_sms.php`.
   - PrestaShop: `$this->version` in `prestashop/helliosms/helliosms.php` and
     `<version>` in `config.xml`.
   - Magento: `version` in `magento2/Hellio/Sms/composer.json`.

2. **Build the archives** (the version becomes part of each file name):

   ```bash
   ./plugins/build.sh 1.1.0
   ```

3. **Publish the GitHub release** with the four archives as assets:

   ```bash
   gh release create v1.1.0 \
     plugins/dist/hellio-sms-woocommerce-1.1.0.zip \
     plugins/dist/hellio-sms-opencart-1.1.0.ocmod.zip \
     plugins/dist/helliosms-prestashop-1.1.0.zip \
     plugins/dist/hellio-sms-magento2-1.1.0.zip \
     --repo HellioSolutions/hellio-ecommerce-plugins \
     --title v1.1.0 --notes "What changed in this release."
   ```

4. **Point the docs at the new files.** In the Hellio app, edit
   `config/plugins.php`: set `$version` to `1.1.0` and update the asset file
   names in `downloads` (the WooCommerce, PrestaShop and Magento names carry the
   version; the OpenCart one keeps the `.ocmod.zip` suffix). Commit and deploy so
   the download links on `/docs/plugins` resolve to the new release.

Keep the plugin manifest version, the `build.sh` file names, and
`config/plugins.php` in sync. They are the three places a version lives.
