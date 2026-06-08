# V-Auth

OpenID Connect single sign-on for WordPress. Lets site admins configure login with external identity providers; SAML support planned.

- **Version**: 1.0.0
- **Requires**: WordPress 7.0+, PHP 8.1+
- **Requires Plugins**: v-secrets-manager
- **License**: GPL v3

## Features

- Configure multiple OIDC providers via OpenID discovery (`/.well-known/openid-configuration`)
- Authorization-code flow with state/nonce verification and signed ID token validation (JWKS)
- "Login with…" buttons alongside wp-login, or full redirect-to-IdP mode
- Auto-provisions WordPress accounts on first login, mapped by email, with a configurable default role
- Client secrets stored securely via VSecrets Manager (AES-256-CBC)

## Development

### Linting

```bash
composer run phpcs
composer run phpstan
```

### Testing

End-to-end tests run the full OIDC authorization-code flow against a mock identity provider (`tests/e2e/mock-idp`) — a minimal Express server that serves discovery, JWKS, and RS256-signed ID tokens, so no external IdP is required:

```bash
cd tests/e2e
npm install
npm test
```

Playwright's `webServer` config starts both the mock IdP and a WordPress Playground instance with V-Auth and V-Secrets Manager active.

## License

GPL v3 - See [LICENSE](LICENSE) for details.

## Author

Vicedomini Softworks - [https://vicedominisoftworks.com](https://vicedominisoftworks.com)
