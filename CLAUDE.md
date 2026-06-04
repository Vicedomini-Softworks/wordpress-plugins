# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repo Overview

GPL v3 monorepo of WordPress plugins by Vicedomini Softworks. Each plugin lives in its own top-level directory (e.g. `social-feed/`). Target: WordPress 6.9+, PHP 7.2.24+.

## Development Commands

All commands assume a plugin directory with its own tooling. Run triage first to discover what's available:

```bash
node .agents/skills/wp-project-triage/scripts/detect_wp_project.mjs
```

Common commands after triage (use whatever the triage output reports as present):

```bash
# PHP static analysis
composer run phpstan
# or
vendor/bin/phpstan analyse

# PHP code style
composer run phpcs

# JS/block build
npm run build
npm run start       # watch mode

# Tests
composer run test   # PHPUnit
npx wp-env start    # spin up local WP test env

# Lint a single PHP file
vendor/bin/phpcs path/to/file.php
```

## Local Testing with WordPress Playground

```bash
# Quick spin-up — auto-detects and mounts the plugin
cd <plugin-dir>
npx @wp-playground/cli@latest server --auto-mount

# Run a blueprint headlessly (e.g. CI)
npx @wp-playground/cli@latest run-blueprint --blueprint=./blueprint.json
```

Requires Node ≥ 20.18. Instances are ephemeral (SQLite-backed); never point at production data.

## Agent Skills

Skills live in `.agents/skills/` and are versioned by `skills-lock.json`. Before any significant WordPress task, route through the relevant skill:

| Skill | When to use |
|-------|-------------|
| `wordpress-router` | Start here — classifies repo and routes to correct skill |
| `wp-plugin-development` | Plugin architecture, hooks, Settings API, security |
| `wp-project-triage` | Deterministic repo inspection; produces JSON report |
| `wp-block-development` | Gutenberg blocks, `block.json`, `@wordpress/scripts` |
| `wp-interactivity-api` | Interactivity API directives and state |
| `wp-rest-api` | REST endpoint registration, auth, schema |
| `wp-abilities-api` | `wp_register_ability`, REST/JS client exposure |
| `wp-phpstan` | PHPStan setup/config, baselines, WP-specific typing |
| `wp-playground` | Local Playground server, blueprints, version switching |
| `blueprint` | Blueprint JSON authoring and validation |
| `wp-wpcli-and-ops` | WP-CLI operations, migrations, cron, search-replace |
| `wp-plugin-directory-guidelines` | WP.org submission requirements |
| `wp-performance` | Caching, queries, autoload options, HTTP API |
| `wp-block-themes` | Block themes, `theme.json`, template parts |

Run skills via the Skill tool, e.g. `Skill({ skill: "wordpress-router" })`.

## Plugin Architecture Conventions

- Single bootstrap file per plugin (the main `.php` file with WP plugin header).
- No heavy side effects at file load time — load via hooks.
- Use a loader class to register all hooks.
- Admin-only code behind `is_admin()` or admin hooks.
- Activation/deactivation hooks registered at top-level file scope (not inside other hooks).
- `uninstall.php` or `register_uninstall_hook` for cleanup.

## Security Baseline

Every plugin change must satisfy:
- Sanitize input early (`wp_unslash()` + specific `sanitize_*` functions).
- Escape output late (context-specific: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Nonce check + capability check on every state-changing request.
- `$wpdb->prepare()` for all SQL — no string concatenation.

## License

All plugins in this monorepo are GPL v3. New plugins must include `LICENSE` and declare `License: GPL v3` in the plugin header.
