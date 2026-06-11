=== OpenProvider for WooCommerce ===
Contributors: vicedominisoftworks
Tags: woocommerce, domains, openprovider, domain registration
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Domain search, pricing, and automatic registration via OpenProvider REST API, fully integrated with WooCommerce.

== Description ==

OpenProvider for WooCommerce enables your store to sell domain registrations. Customers search for domains, add them to cart alongside products, and complete checkout — the plugin automatically registers the domain via OpenProvider.

**Features:**

* Real-time domain availability check
* Registration period selection (1–10 years)
* Premium domain support with configurable markup
* TLD-specific contact validation (.it, .eu)
* Automatic registration on order completion
* Admin notifications on registration failure
* Gutenberg block + shortcode support
* Rate-limited REST API
* Sandbox mode for testing

== Installation ==

1. Upload the plugin to `/wp-content/plugins/openprovider-woocommerce/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce → OpenProvider Domains to configure settings
4. Enter your OpenProvider API credentials
5. Test with sandbox mode first

== Frequently Asked Questions ==

= Does this work with OpenProvider's sandbox? =
Yes, enable sandbox mode in settings to use OpenProvider's CTE environment.

= What currencies are supported? =
The plugin uses whatever currency your OpenProvider reseller account is configured for. Your WooCommerce store currency should match this.

= Can I configure custom nameservers? =
Not in v1. Domains are registered with OpenProvider's default nameservers. Custom nameserver configuration is planned for a future release.

== Changelog ==

= 1.0.0 =
* Initial release
* Domain search and availability check
* Registration period selection
* Premium domain markup
* TLD contact validation (.it, .eu)
* Automatic registration on order completion
* Settings page
* REST API endpoints
* Gutenberg block + shortcode
