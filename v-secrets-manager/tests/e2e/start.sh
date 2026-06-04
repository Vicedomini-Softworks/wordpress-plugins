#!/bin/bash
exec npx @wp-playground/cli@latest server \
  --port=9400 \
  --php=8.0 \
  --workers=1 \
  --mount-before-install="/Users/sofiavicedomini/development/wp-plugins/v-secrets-manager:/wordpress/wp-content/plugins/v-secrets-manager" \
  --blueprint="/Users/sofiavicedomini/development/wp-plugins/v-secrets-manager/tests/e2e/blueprint.json"
