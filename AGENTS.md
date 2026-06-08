# AGENTS.md

## Repo

GPL v3 monorepo — two WordPress plugins in top-level directories. Target WP 7.0+, PHP 8.1+.

| Directory | Plugin | Prefix | Depends on |
|-----------|--------|--------|------------|
| `social-feed/` | Social Feed | `Social_Feed_*` | v-secrets-manager (`Requires Plugins:` + runtime check) |
| `v-secrets-manager/` | VSecrets Manager | `VS_Secrets_Manager_*` | — |

## Architecture rules (both plugins)

- **All classes are static** — no constructor, no `new`. Pattern: `ClassName::init()` → `load_dependencies()` → `register_hooks()`.
- Bootstrap file contains only: constants, `require_once` loader, `register_activation_hook`/`register_deactivation_hook` at top scope, `add_action( 'plugins_loaded', [ 'Loader', 'init' ] )`.
- Admin code isolated behind `is_admin()` or admin-only hooks.
- No `vendor/`, no `composer.lock` — dependencies are **never installed locally**, only in CI.

## social-feed specifics

- `social-feed.php` declares `Requires Plugins: v-secrets-manager`.
- On `init()` it calls `vs_secrets_manager_get()` (from v-secrets-manager) to load provider credentials.
- `composer.json` has dev deps for phpcs + phpstan but **no phpunit dependency** — tests do not exist.
- No CI workflow.

## v-secrets-manager specifics

- Three providers: `db` (AES-256-CBC in local DB), `aws` (via SDK, optional), `vault` (HTTP API, no deps).
- AWS SDK (`aws/aws-sdk-php`) is bundled in CI build only (`composer install --no-dev`).
- REST API at `vs-secrets-manager/v1` — 9 endpoints, permission: `manage_options`.
- Custom table `{$wpdb->prefix}vsecrets_secrets`.

## Commands

```bash
# Triage: run first to learn what's available
node .agents/skills/wp-project-triage/scripts/detect_wp_project.mjs

# PHP lint for a plugin
find <plugin-dir> -name '*.php' -exec php -l {} \;

# e2e tests (v-secrets-manager only)
cd v-secrets-manager/tests/e2e
npm install && npx playwright install chromium
npm test                    # headless
npm run test:headed         # visible browser

# PHPCS (social-feed has config, v-secrets-manager does not)
cd social-feed && composer run phpcs

# PHPStan (social-feed has config, v-secrets-manager does not)
cd social-feed && composer run phpstan

# Quick Playground (any plugin dir)
cd <plugin-dir>
npx @wp-playground/cli@latest server --auto-mount --port=9400
```

## CI

Single workflow at `.github/workflows/build-vs-secrets-manager.yml`:
- `test-e2e` → `build` on push/PR to `main`/`develop` touching `v-secrets-manager/**`.
- On release tag: ZIP uploaded as Release asset.
- Build excludes `tests/`, `node_modules/`, `.git/`.

## Security

- `wp_unslash()` + specific `sanitize_*` on input; context-aware `esc_*` on output.
- Nonce + `current_user_can('manage_options')` on every state change.
- `$wpdb->prepare()` for all SQL — no string interpolation.

## Agent skills

Skills in `.agents/skills/` (locked by `skills-lock.json`). Load via `Skill({ skill: "..." })`. Key ones: `wordpress-router` (start), `wp-plugin-development`, `wp-rest-api`, `wp-playground`, `wp-abilities-api`, `blueprint`.
