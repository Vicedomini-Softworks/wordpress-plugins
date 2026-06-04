import { defineConfig } from '@playwright/test';
import path from 'path';

const PLAYGROUND_URL = process.env.PLAYGROUND_URL || 'http://localhost:9400';
const PLUGIN_DIR   = path.resolve( __dirname, '../../' );
const BLUEPRINT    = path.resolve( __dirname, 'blueprint.json' );

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
		command: `npx --yes @wp-playground/cli@latest start --path="${ PLUGIN_DIR }" --port=9400 --php=8.0 --blueprint="${ BLUEPRINT }" --skip-browser`,
		url: PLAYGROUND_URL,
		reuseExistingServer: true,
		timeout: 120000,
	},
} );
