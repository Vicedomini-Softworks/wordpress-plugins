<?php
/**
 * OpenProvider for WooCommerce
 *
 * @package OpenProviderWooCommerce
 * @link https://vicedominisoftworks.com/products/openprovider-woocommerce/
 */

# OpenProvider WooCommerce Plugin

A production-ready WooCommerce plugin for domain registration via OpenProvider REST API.

## Features (v1)

- **Domain Search**: AJAX-powered domain availability check with TLD filtering
- **Registration Period Selection**: Choose 1–10 years at search time
- **Automatic Registration**: Domains registered automatically on order completion
- **Premium Domain Support**: Configurable markup with rounding rules
- **TLD Contact Validation**: Required fields for .it/.eu domains (fiscal code, VAT)
- **Settings Page**: Configure API credentials, allowed TLDs, cache TTLs
- **REST API**: Rate-limited endpoints for search/check/pricing
- **Gutenberg Block**: Modern domain search block
- **Shortcode**: Legacy `[openprovider_domain_search]` support

## Requirements

- WordPress 7.0+
- PHP 8.1+
- WooCommerce 10+
- OpenProvider account (sandbox or production)

## Installation

1. Run `composer install` in plugin directory
2. Upload plugin to `/wp-content/plugins/openprovider-woocommerce/`
3. Activate via WordPress admin
4. Configure OpenProvider credentials in WooCommerce → OpenProvider Domains

## Configuration

### API Credentials

The plugin supports two credential storage methods:

1. **v-secrets-manager** (recommended): If the `v-secrets-manager` plugin is active, configure secret names in settings
2. **Encrypted wp_options** (fallback): Credentials stored encrypted in database using AES-256-CBC

### Sandbox Mode

Enable sandbox mode to test with OpenProvider's CTE (Community Test Environment) without affecting production data. Requires a separate CTE account.

### Allowed TLDs

Configure which TLDs customers can search/register. Common TLDs: `.com`, `.net`, `.org`, `.it`, `.eu`.

### Premium Domain Markup

Configure markup percentage and cap for premium domains:
- Default markup: 20%
- Max cap: 50%
- Rounding modes: nearest .99, nearest 1.00, nearest 5.00

## Known Limitations

- **Multi-currency not supported**: Store currency must match OpenProvider reseller account currency
- **No currency conversion**: Prices returned by OpenProvider are used as-is
- **Default nameservers only**: Custom nameserver configuration available in Phase 2

## Development

```bash
# Install dependencies
composer install

# Run code style checks
composer run phpcs

# Auto-fix code style issues
composer run phpcbf

# Run static analysis
composer run phpstan

# Run unit tests
composer run test
```

## Directory Structure

```
openprovider-woocommerce/
├── src/
│   ├── Api/           # OpenProvider API services
│   ├── Support/       # Logger, Crypto, Settings, RateLimiter
│   ├── WooCommerce/   # WC integration classes
│   ├── Admin/         # Settings page
│   ├── Rest/          # REST API controllers
│   ├── Frontend/      # Shortcode, Block
│   └── Activation/    # Database schema, activator
├── templates/         # PHP templates
├── assets/           # CSS, JS
├── blocks/           # Gutenberg blocks
├── docs/             # Documentation
└── tests/unit/       # PHPUnit tests
```

## License

GPL v3. See [LICENSE](LICENSE) for details.

## Support

- Documentation: [docs/](docs/)
- Issues: GitHub Issues
- Email: support@vicedominisoftworks.com
