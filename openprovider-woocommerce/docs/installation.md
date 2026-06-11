# OpenProvider WooCommerce - Installation Guide

## Requirements

- WordPress 7.0+
- PHP 8.1+
- WooCommerce 10+
- OpenProvider account (sandbox or production)

## Installation Steps

### 1. Install Dependencies

```bash
cd openprovider-woocommerce
composer install
```

### 2. Upload to WordPress

Upload the `openprovider-woocommerce` directory to `/wp-content/plugins/` and activate via WordPress admin.

### 3. Configure OpenProvider Credentials

Go to **WooCommerce → OpenProvider Domains** in WordPress admin.

#### Option A: Using v-secrets-manager (Recommended)

If the `v-secrets-manager` plugin is active:

1. Create secrets in v-secrets-manager for your OpenProvider username and password
2. Enter the secret names in the settings page
3. Credentials are retrieved securely at runtime

#### Option B: Local Encrypted Storage

If v-secrets-manager is not active:

1. Enter your OpenProvider username and password directly
2. They are encrypted using AES-256-CBC before storage
3. Encryption key is derived from WordPress `AUTH_KEY` salt

### 4. Configure Settings

- **Environment**: Choose Sandbox (CTE) for testing or Production
- **Allowed TLDs**: Comma-separated list (e.g., `com,net,org,it,eu`)
- **Default Registration Period**: 1-10 years
- **Premium Markup**: Configure percentage, cap, and rounding mode
- **Cache TTLs**: Search (default 5 min), Pricing (default 12 hours)
- **Debug Logging**: Enable for API request/response logging

### 5. Test Connection

Click the **Test Connection** button to verify API credentials work.

### 6. Add Domain Search to Your Site

#### Using Gutenberg Block

1. Edit a page/post
2. Add "Domain Search" block
3. Configure default TLDs and button label

#### Using Shortcode

```
[openprovider_domain_search tlds="com,net,org"]
```

## Sandbox (CTE) Setup

1. Register for a CTE account at OpenProvider
2. Enable sandbox mode in plugin settings
3. Use CTE credentials
4. Test domain searches and registrations without affecting production

## Known Limitations

- **Currency**: Store currency must match OpenProvider reseller account currency
- **No multi-currency support**: Currency conversion not implemented in v1
- **Default nameservers**: Domains registered with OpenProvider defaults (custom nameservers in Phase 2)

## Upgrade Guide

### From v1.0.x to v1.1.0

1. Backup your database
2. Deactivate the plugin
3. Replace files with new version
4. Reactivate the plugin
5. Database tables auto-update via `dbDelta()`

## Troubleshooting

### "Credentials not configured" error

- Verify v-secrets-manager secret names or local credentials are set
- Check that credentials are correct via Test Connection

### Domain registration fails

- Check WooCommerce logs (WooCommerce → Status → Logs → openprovider-woocommerce)
- Verify contact fields are complete for .it/.eu domains
- Ensure OpenProvider account has sufficient balance

### Cache not clearing

- Use the Admin → Flush Cache REST endpoint
- Or manually delete transients via database
