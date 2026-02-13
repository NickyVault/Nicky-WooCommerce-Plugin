## Nicky Payment Gateway — Shop Owner Guide

This README is intended for shop owners who want to install, configure, and operate the Nicky WooCommerce payment plugin.

In short: the plugin adds a Nicky.me crypto gateway to WooCommerce. It supports test and live modes, webhook handling, and transaction overview in the admin area.

## For Developers

📦 **Building for Production:** See [BUILD.md](BUILD.md) for development workflow  
🚀 **Release Process:** See [RELEASE.md](RELEASE.md) for version management and release workflow

### Quick Build Command

```bash
# Interactive build with version management
./build-production.sh

# Build specific version
./build-production.sh 1.0.2

# Build without version update
./build-production.sh --no-version
```

## Quick overview

- Main plugin file: `nicky-payment-gateway.php`
- Settings: WooCommerce → Settings → Payments → Nicky (or "Nicky Payment")
- Requirements: WordPress + WooCommerce

## Requirements

- WordPress 5.0+
- WooCommerce 4.0+
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.6+ or compatible

Ensure your hosting allows outgoing HTTPS requests and supports TLS 1.2+.

## Installation

There are two common ways to install the plugin:

1) Using WordPress admin (recommended):
   - Upload plugin ZIP: Plugins → Add New → Upload Plugin → choose ZIP → Install.
   - Activate the plugin after upload.

2) Manual (SFTP/FTP):
   - Unzip `nicky-woocommerce-plugin` into `wp-content/plugins/`.
   - Go to Plugins in WP admin and activate "Nicky Payment Gateway".

After activation proceed to configuration.

## Configuration (first run)

1. Open WooCommerce → Settings → Payments.
2. Find "Nicky Payment" (or similar) and click Manage / Setup.
3. Configure the most important options:
   - Enable gateway: toggle to enable payments via Nicky.
   - Title: label shown to customers at checkout (e.g. "Credit Card (Nicky)").
   - Description: short description visible on checkout page.
   - API keys: enter Test and Live API keys in the appropriate fields. Never use Live keys in test mode.
   - Webhook URL / secret: if your payment provider requires it, register the plugin webhook URL in the provider dashboard and copy the secret if applicable.
4. Save settings.

## Admin tasks / daily operations

- View transactions: open an order and look for payment/transaction details.
- Refunds: issue refunds via WooCommerce or your payment provider dashboard. If the API supports it, the plugin may offer in-dashboard refunds.
- Monitor webhooks: check WooCommerce → Status → Logs or the plugin logs to confirm webhook deliveries.

## Troubleshooting — common issues

1) Checkout broken / JS errors
   - Inspect browser console for errors.
   - Temporarily disable other plugins or try a default theme to find conflicts.

2) API errors (401 / 403)
   - Verify API keys and correct mode (test vs live).
   - Confirm your host allows outgoing HTTPS requests.

3) Webhooks not received
   - Verify the webhook URL is publicly accessible and not blocked by a firewall.
   - Check webhook secret/signature configuration if the provider uses verification.

4) Order status not updated
   - Check plugin logs and webhook delivery status at the provider side.
   - Update order status manually if required and investigate logs.

5) Card charged but order incomplete
   - Inspect transaction details at the provider dashboard and plugin logs.
   - Contact support with order ID and transaction reference.

## Security & privacy

- Do not commit API keys to version control.
- Use HTTPS for your site.
- The plugin stores transaction metadata — ensure your privacy policy discloses this as required.

## Support

When requesting help, please provide:
- WordPress and WooCommerce versions
- PHP version
- Example order ID and short error description
- Relevant logs (without API keys)

Support channels:
- Email: markus@shortdot.bond
- GitHub issues: [https://github.com/upcode-at/nicky-woocommerce-plugin](https://github.com/upcode-at/Nicky-Woocommerce-Payment-Plugin)

## FAQ

- Q: How do I enable test payments?
  A: Activate Test mode in the plugin settings and add the test API key.

- Q: Can I refund payments from the WooCommerce admin?
  A: If the payment provider supports API refunds and the plugin implements it, refunds can be issued from the order page — otherwise use the provider dashboard.

- Q: Where are logs stored?
  A: WooCommerce → Status → Logs or the plugin's log files (if configured).

## Changelog (short)

- 1.0.0 — Initial public release: basic card processing, test mode, admin overview
