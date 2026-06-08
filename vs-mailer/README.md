# VS Mailer

Complete SMTP, Brevo, and Mailgun email delivery plugin for WordPress.

- **Version**: 1.0.0
- **Requires**: WordPress 7.0+, PHP 8.1+
- **Requires Plugins**: v-secrets-manager
- **License**: GPL v3

## Features

- **SMTP Mode**: TLS/SSL support, custom port, authentication
- **Brevo API**: Transactional email API v3
- **Mailgun API**: US/EU region support with custom domain
- Secure credential storage via VSecrets Manager
- Test email tool and optional email logging
- Full header support (CC, BCC, Reply-To, Content-Type)

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

Workflow: `.github/workflows/build-vs-mailer.yml`

## License

GPL v3 - See [LICENSE](../LICENSE) for details.

## Author

Vicedomini Softworks - [https://vicedominisoftworks.com](https://vicedominisoftworks.com)
