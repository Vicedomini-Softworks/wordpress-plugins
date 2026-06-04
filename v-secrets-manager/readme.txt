=== VSecrets Manager ===
Contributors: vicedominisoftworks
Tags: secrets, security, encryption, aws secrets manager, vault, hashicorp, openbao
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.2.24
Stable tag: 1.0.0
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Manage secrets with multiple providers: encrypted database, AWS Secrets Manager, and Hashicorp Vault/OpenBao.

== Description ==

VSecrets Manager lets you securely store and retrieve secrets using one of three providers:

* **Encrypted Database** — AES-256-CBC encryption using your WordPress salt. Zero external dependencies.
* **AWS Secrets Manager** — Store and retrieve secrets via the AWS SDK. Requires `aws/aws-sdk-php`.
* **Hashicorp Vault / OpenBao** — KV v2 engine via HTTP API. Token-based authentication with optional namespace support.

All three providers are accessed through a single unified interface: `vs_secrets_manager_get( 'secret_name' )`.

**Features:**
* Three provider backends with identical interface
* Secrets never appear in plaintext in the admin list (toggle to reveal)
* Full REST API for headless / third-party integration
* Admin UI to create, edit, and delete secrets
* Settings page for AWS and Vault credentials
* Encrypted-at-rest for the database provider
* Cron-based secret rotation support

**REST API:**
All endpoints under `wp-json/vs-secrets-manager/v1/`:
* `GET /secrets` — list metadata (never values)
* `GET /secrets/{id}` — single secret with decrypted value
* `POST /secrets` — create
* `PUT /secrets/{id}` — update
* `DELETE /secrets/{id}` — delete
* `POST /test-connection` — test a provider connection
* `GET /settings` — get provider configuration
* `POST /settings` — save provider configuration

== Installation ==

1. Upload the `v-secrets-manager` folder to `/wp-content/plugins/`
2. Activate the plugin in *Plugins > Installed Plugins*
3. Go to *VSecrets Manager > Add New* to create your first secret
4. For AWS or Vault providers, configure credentials under *VSecrets Manager > Settings*
5. Retrieve secrets in your code: `vs_secrets_manager_get( 'my_api_key' )`

== Frequently Asked Questions ==

= Is the database provider secure? =

Yes. Values are encrypted with AES-256-CBC using a key derived from your WordPress salt (`wp_salt('auth')`). Even with direct database access, secrets remain encrypted.

= Do I need the AWS SDK? =

No. The database and Vault providers work out of the box. The AWS provider requires `aws/aws-sdk-php` installed via Composer. The plugin checks for its presence at runtime.

= Can I switch a secret's provider after creation? =

Yes — delete the old secret and create a new one with the same name and the new provider.

== Changelog ==

= 1.0.0 =
* Initial release

== Development ==

* **e2e Tests:** Playwright tests in `tests/e2e/`
* **CI:** GitHub Actions runs e2e tests on push/PR

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
