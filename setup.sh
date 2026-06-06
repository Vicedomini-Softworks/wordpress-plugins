#!/usr/bin/env bash
# One-time repo setup: install git hooks.
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"

git -C "$REPO_ROOT" config core.hooksPath .githooks
chmod +x "$REPO_ROOT/.githooks/pre-commit"

echo "Git hooks installed (.githooks/pre-commit active)."
echo "Every commit will run phpcs + phpstan on all three plugins."
