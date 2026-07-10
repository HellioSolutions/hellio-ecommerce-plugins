#!/usr/bin/env bash
#
# Package each Hellio Messaging e-commerce plugin into the installable archive
# its platform expects. Output lands in plugins/dist/. Safe to re-run.
#
#   ./plugins/build.sh            # build all four
#   ./plugins/build.sh 1.0.1      # override the version in the file names
#
set -euo pipefail

VERSION="${1:-1.0.0}"
BASE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST="$BASE/dist"

rm -rf "$DIST"
mkdir -p "$DIST"

# Drop macOS/editor cruft so it never lands in a shipped archive.
find "$BASE" -name '.DS_Store' -delete 2>/dev/null || true

zip_quiet() { zip -r -q -X "$@" -x '*.DS_Store' -x '__MACOSX*'; }

echo "Packaging Hellio plugins v$VERSION"

# WooCommerce: a WordPress plugin zip whose top folder is the plugin slug.
( cd "$BASE/woocommerce" && zip_quiet "$DIST/hellio-sms-woocommerce-$VERSION.zip" hellio-sms )
echo "  woocommerce -> hellio-sms-woocommerce-$VERSION.zip"

# OpenCart 3.x: an OCMOD zip carrying upload/ and install.xml at the root. The
# .ocmod.zip suffix makes the Extension Installer apply the modification.
( cd "$BASE/opencart" && zip_quiet "$DIST/hellio-sms-opencart-$VERSION.ocmod.zip" upload install.xml image.png README.md )
echo "  opencart    -> hellio-sms-opencart-$VERSION.ocmod.zip"

# PrestaShop 1.7/8: a module zip whose top folder matches the main class file.
( cd "$BASE/prestashop" && zip_quiet "$DIST/helliosms-prestashop-$VERSION.zip" helliosms )
echo "  prestashop  -> helliosms-prestashop-$VERSION.zip"

# Magento 2: an archive of the module tree; extract into app/code/ (so it lands
# at app/code/Hellio/Sms), then bin/magento setup:upgrade.
( cd "$BASE/magento2" && zip_quiet "$DIST/hellio-sms-magento2-$VERSION.zip" Hellio )
echo "  magento2    -> hellio-sms-magento2-$VERSION.zip"

echo ""
echo "Built into $DIST:"
( cd "$DIST" && for z in *.zip; do printf '  %-42s %s\n' "$z" "$(du -h "$z" | cut -f1)"; done )
