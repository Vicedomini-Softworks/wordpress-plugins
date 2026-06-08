import { defineConfig } from '@playwright/test';
import path from 'path';

const PLAYGROUND_URL = process.env.PLAYGROUND_URL || 'http://127.0.0.1:9401';
const MOCK_IDP_PORT  = process.env.MOCK_IDP_PORT || '9402';
const PLUGIN_DIR     = path.resolve( __dirname, '../../' );
const VSM_DIR        = path.resolve( __dirname, '../../../v-secrets-manager' );
const BLUEPRINT      = path.resolve( __dirname, 'blueprint.json' );

export default defineConfig( {
	testDir: './tests',
	fullyParallel: false,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: 'list',
	timeout: 180000,
	expect: {
		timeout: 15000,
	},
	use: {
		baseURL: PLAYGROUND_URL,
		headless: true,
	},
	webServer: [
		{
			command: `node ./mock-idp/server.js`,
			url: `http://127.0.0.1:${ MOCK_IDP_PORT }/.well-known/openid-configuration`,
			reuseExistingServer: ! process.env.CI,
			timeout: 30000,
			env: { MOCK_IDP_PORT },
		},
		{
			command: `node ./node_modules/@wp-playground/cli/wp-playground.js server --port=9401 --mount="${ VSM_DIR }:/wordpress/wp-content/plugins/v-secrets-manager" --mount="${ PLUGIN_DIR }:/wordpress/wp-content/plugins/v-auth" --php=8.1 --wp=7.0 --blueprint="${ BLUEPRINT }"`,
			url: `${ PLAYGROUND_URL }/wp-includes/js/jquery/jquery.min.js`,
			reuseExistingServer: ! process.env.CI,
			timeout: 120000,
		},
	],
} );
