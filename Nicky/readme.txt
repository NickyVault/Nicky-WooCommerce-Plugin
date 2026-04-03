=== Nicky.me ===
Contributors: NickyVault
Tags: woocommerce, payment gateway, cryptocurrency, bitcoin, ethereum
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure cryptocurrency payment gateway for WooCommerce. Accept Bitcoin, Ethereum, USDT and more.

== Description ==

Nicky.me is a secure and reliable cryptocurrency payment gateway for your WooCommerce store. Accept payments in Bitcoin, Ethereum, USDT and other popular cryptocurrencies with ease.

= External Service Usage =

This plugin connects to the Nicky.me payment processing service (https://nicky.me) to process cryptocurrency payments. When a customer makes a purchase:

* Order data (amount, currency, order ID) is sent to https://api-public.pay.nicky.me
* Payment status updates are received via webhook from Nicky.me servers
* Customer payment information is processed through Nicky.me's secure payment interface

By using this plugin, you agree to Nicky.me's Terms of Service and Privacy Policy:
* Terms of Service: https://nicky.me/terms
* Privacy Policy: https://nicky.me/privacy

= Data Processing & Privacy =

The plugin stores transaction data locally including:
* Order ID and payment status
* Transaction ID from Nicky.me
* Payment amounts and currency

No sensitive customer payment information (wallet addresses, private keys) is stored in your WordPress database.

**Features:**

* Accept multiple cryptocurrencies (Bitcoin, Ethereum, USDT, and more)
* Secure payment processing powered by Nicky.me
* Real-time payment status updates via webhook
* Automatic currency conversion
* Easy setup and configuration
* Compatible with WooCommerce Blocks
* HPOS (High-Performance Order Storage) compatible

= Source code and development =

This plugin is distributed with complete, human-readable source code (PHP, JavaScript, and CSS) in the plugin package—no obfuscated or packed code. For optional public development, issue tracking, or build documentation, see: https://github.com/upcode-at/Nicky-Woocommerce-Payment-Plugin

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/nicky-me` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce > Settings > Payments and configure the Nicky.me Payment Gateway.
4. Enter your API key from your Nicky.me account (create an account at https://nicky.me if you don't have one).
5. Select your preferred settlement currency from the available blockchain assets.
6. Save your settings and start accepting cryptocurrency payments!

**Requirements:**
* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* A Nicky.me account with API access

== Frequently Asked Questions ==

= Do I need a Nicky.me account? =

Yes, you need to create an account at https://nicky.me to get your API credentials.

= Which cryptocurrencies are supported? =

The plugin supports Bitcoin, Ethereum, USDT and other cryptocurrencies available in your Nicky.me account.

= Is the plugin compatible with WooCommerce Blocks? =

Yes, the plugin is fully compatible with the new WooCommerce Blocks checkout.

= What data does the plugin send to external services? =

The plugin sends order information (amount, currency, order ID) to Nicky.me API for payment processing. No sensitive customer data like passwords or full credit card numbers are transmitted or stored by the plugin. See the "External Service Usage" section for more details.

= Is the plugin GDPR compliant? =

The plugin stores minimal transaction data locally (order ID, transaction ID, payment status, amounts). Ensure your site's privacy policy mentions the use of Nicky.me for payment processing.

= Can I use this plugin in test mode? =

Yes, you can configure test mode settings in the gateway configuration. Contact Nicky.me support for test API credentials.

== Changelog ==

= 1.0.7 =
* Code improvements and WordPress.org review feedback addressed
* Enhanced security and sanitization
* Improved compatibility declarations
* Updated documentation and service disclosures
* Dashboard validation widget: optional hide/show per user  
* Source code / development note in readme  

= 1.0.2 =
* Updated WordPress compatibility (tested up to WP 6.9)
* Optimized dashboard widget performance with caching
* Fixed slow database query warning

= 1.0.1 =
* Updated WordPress and WooCommerce compatibility (tested up to WP 6.8 and WC 9.5)
* Added external service disclosure in compliance with WordPress.org guidelines
* Enhanced privacy and data processing documentation
* Improved readme with detailed service usage information

= 1.0.0 =
* Initial release
* Support for Bitcoin, Ethereum, USDT and more cryptocurrencies
* Automatic payment status updates via webhook
* WooCommerce Blocks compatibility
* HPOS compatibility

== Upgrade Notice ==

= 1.0.7 =
Maintenance release: guideline-aligned dashboard widget controls, documentation, and settings link fixes.

= 1.0.0 =
Initial release of Nicky.me payment gateway for WooCommerce.

== License ==

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see https://www.gnu.org/licenses/gpl-2.0.html.