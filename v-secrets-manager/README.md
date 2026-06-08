# VSecrets Manager

Manage secrets with multiple providers: encrypted database, AWS Secrets Manager, and Hashicorp Vault/OpenBao.

- **Version**: 1.0.0
- **Requires**: WordPress 7.0+, PHP 8.1+
- **License**: GPL v3

## Features

- **Database Provider**: AES-256-CBC encryption stored in local WordPress database
- **AWS Provider**: Integrate with AWS Secrets Manager (requires AWS SDK)
- **Vault Provider**: Connect to HashiCorp Vault / OpenBao via HTTP API
- REST API with 9 endpoints for secret management
- Custom database table for secret storage

## Usage

```php
// Get a secret
$api_key = vs_secrets_manager_get( 'api_key' );
```

## Development

### Linting

```bash
composer run phpcs
composer run phpstan
```

### Testing

```bash
cd tests/e2e
npm install && npx playwright install chromium
npm test
```

### CI/CD

GitHub Actions workflow runs on push/PR to `main` and `develop`:

- **e2e tests**: Playwright tests in `tests/e2e/`
- **Static analysis**: PHPCS (WordPress Coding Standards) and PHPStan
- **Build**: Plugin ZIP creation and artifact upload
- **Release**: Automatic ZIP upload to GitHub Releases on tag publish

Workflow: `.github/workflows/build-vs-secrets-manager.yml`

## License

GPL v3 - See [LICENSE](../LICENSE) for details.

## Author

Vicedomini Softworks - [https://vicedominisoftworks.com](https://vicedominisoftworks.com)
