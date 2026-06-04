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

### VS Mailer

Complete SMTP, Brevo, and Mailgun email delivery plugin for WordPress.

- **Version**: 1.0.0
- **Requires**: WordPress 6.9+, PHP 7.2.24+
- **Requires Plugins**: v-secrets-manager
- **License**: GPL v3
- **Directory**: `vs-mailer/`

#### Features

- **SMTP Mode**: TLS/SSL support, custom port, authentication
- **Brevo API**: Transactional email API v3
- **Mailgun API**: US/EU region support with custom domain
- Secure credential storage via VSecrets Manager
- Test email tool and optional email logging
- Full header support (CC, BCC, Reply-To, Content-Type)

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

# Social Feed e2e tests
cd social-feed/tests/e2e
npm install && npx playwright install chromium
npm test

# VS Mailer e2e tests
cd vs-mailer/tests/e2e
npm install && npx playwright install chromium
npm test
```

### CI/CD

All plugins have GitHub Actions workflows that run on push/PR to `main` and `develop`:

- **e2e tests**: Playwright tests in `tests/e2e/`
- **Static analysis**: PHPCS (WordPress Coding Standards) and PHPStan
- **Build**: Plugin ZIP creation and artifact upload
- **Release**: Automatic ZIP upload to GitHub Releases on tag publish

Workflows:
- `.github/workflows/build-vs-secrets-manager.yml`
- `.github/workflows/build-social-feed.yml`
- `.github/workflows/build-vs-mailer.yml`

## License

GPL v3 - See [LICENSE](LICENSE) for details.

## Author

Vicedomini Softworks - [https://vicedominisoftworks.com](https://vicedominisoftworks.com)