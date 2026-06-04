import { defineConfig } from '@playwright/test';
import path from 'path';

const PLAYGROUND_URL = process.env.PLAYGROUND_URL || 'http://localhost:9401';
const PLUGIN_DIR     = path.resolve( __dirname, '../../' );
const VSM_DIR        = path.resolve( __dirname, '../../../v-secrets-manager' );
const BLUEPRINT      = path.resolve( __dirname, 'blueprint.json' );

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
		command: `npx @wp-playground/cli@latest server --port=9401 --mount-before-install="${ VSM_DIR }:/wordpress/wp-content/plugins/v-secrets-manager" --mount-before-install="${ PLUGIN_DIR }:/wordpress/wp-content/plugins/social-feed" --blueprint="${ BLUEPRINT }"`,
		url: PLAYGROUND_URL,
		reuseExistingServer: ! process.env.CI,
		timeout: 120000,
	},
} );
