import { defineConfig } from '@playwright/test';
import path from 'path';

const PLAYGROUND_URL = process.env.PLAYGROUND_URL || 'http://localhost:9400';
const WORKSPACE_DIR  = path.resolve( __dirname, '../../../' );
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
		command: `npx @wp-playground/cli@latest server --port=9400 --mount-before-install="${ WORKSPACE_DIR }:/wordpress/wp-content/plugins" --blueprint="${ BLUEPRINT }"`,
		url: PLAYGROUND_URL,
		reuseExistingServer: ! process.env.CI,
		timeout: 120000,
	},
} );
