import { spawn } from 'child_process';
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const PLUGIN_DIR = path.resolve( __dirname, '../../' );
const BLUEPRINT  = path.resolve( __dirname, 'blueprint.json' );

const args = [
	'@wp-playground/cli@latest',
	'server',
	'--port=9400',
	'--php=8.0',
	'--workers=1',
	`--mount-before-install=${ PLUGIN_DIR }:/wordpress/wp-content/plugins/v-secrets-manager`,
	`--blueprint=${ BLUEPRINT }`,
];

const cp = spawn( 'npx', args, {
	stdio: [ 'ignore', 'pipe', 'pipe' ],
	cwd: __dirname,
} );

cp.stdout.on( 'data', ( d ) => process.stdout.write( d ) );
cp.stderr.on( 'data', ( d ) => process.stderr.write( d ) );
cp.on( 'exit', ( code ) => process.exit( code ?? 1 ) );
