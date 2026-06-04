# WordPress Plugins

This repository contains two WordPress plugins developed by Vicedomini Softworks.

## Plugins

### VSecrets Manager

Manage secrets with multiple providers: encrypted database, AWS Secrets Manager, and Hashicorp Vault/OpenBao.

- **Version**: 1.0.0
- **Requires**: WordPress 6.9+, PHP 7.2.24+
- **License**: GPL v3
- **Directory**: `v-secrets-manager/`

#### Features

- **Database Provider**: AES-256-CBC encryption stored in local WordPress database
- **AWS Provider**: Integrate with AWS Secrets Manager (requires AWS SDK)
- **Vault Provider**: Connect to HashiCorp Vault / OpenBao via HTTP API
- REST API with 9 endpoints for secret management
- Custom database table for secret storage

#### Usage

```php
// Get a secret
$api_key = vs_secrets_manager_get( 'api_key' );
```

### Social Feed

Display social media feeds from Instagram, Facebook, TikTok, X, Threads, Bluesky, and YouTube.

- **Version**: 1.0.0
- **Requires**: WordPress 6.9+, PHP 7.2.24+
- **Requires Plugins**: v-secrets-manager
- **License**: GPL v3
- **Directory**: `social-feed/`

## Development

### Requirements

- WordPress 6.9+
- PHP 7.2.24+

### Linting

```bash
# Social Feed
cd social-feed && composer run phpcs

# PHPStan
cd social-feed && composer run phpstan
```

### Testing

```bash
# VSecrets Manager e2e tests
cd v-secrets-manager/tests/e2e
npm install && npx playwright install chromium
npm test
```

## License

GPL v3 - See [LICENSE](LICENSE) for details.

## Author

Vicedomini Softworks - [https://vicedominisoftworks.com](https://vicedominisoftworks.com)