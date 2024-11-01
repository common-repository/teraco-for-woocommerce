#!/usr/bin/env bash

echo "=== Packaging Teraco for WooCommerce for release ==="
rm teraco-for-woocommerce.zip
zip -r teraco-for-woocommerce.zip . -x@exclude.lst
echo "=== Teraco for WooCommerce ready for release ==="