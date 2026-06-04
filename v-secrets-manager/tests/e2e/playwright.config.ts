import { defineConfig } from '@playwright/test';
import path from 'path';

const PLAYGROUND_URL = process.env.PLAYGROUND_URL || 'http://localhost:9400';
const PLUGIN_DIR = path.resolve( __dirname, '../../' );

export default defineConfig( {
	testDir: './tests',
	fullyParallel: false,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: 'list',
	timeout: 60000,
	expect: {
		timeout: 15000,
	},
	use: {
		baseURL: PLAYGROUND_URL,
		headless: true,
	},
	webServer: {
		command: `npx @wp-playground/cli@latest server \
			--port=9400 \
			--mount="${ PLUGIN_DIR }:/wordpress/wp-content/plugins/v-secrets-manager" \
			--mount-before-install \
			--blueprint=./tests/e2e/blueprint.json`,
		url: PLAYGROUND_URL,
		reuseExistingServer: ! process.env.CI,
		timeout: 120000,
		cwd: PLUGIN_DIR,
	},
} );
