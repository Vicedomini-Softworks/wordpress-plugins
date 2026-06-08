/**
 * Minimal mock OpenID Connect provider for V-Auth e2e tests.
 *
 * Implements just enough of the spec for a single serial authorization-code
 * flow: discovery, /authorize (auto-approves and redirects with a fixed
 * code), /token (returns a freshly RS256-signed ID token), and /jwks
 * (publishes the matching public key). Designed for `workers: 1` —
 * state is kept in memory and only one flow is in-flight at a time.
 */

const express = require( 'express' );
const crypto = require( 'crypto' );

const PORT = process.env.MOCK_IDP_PORT || 9402;
const ISSUER = `http://127.0.0.1:${ PORT }`;
const KID = 'mock-idp-key-1';
const FIXED_CODE = 'mock-auth-code';

const { publicKey, privateKey } = crypto.generateKeyPairSync( 'rsa', {
	modulusLength: 2048,
} );

const jwk = publicKey.export( { format: 'jwk' } );
jwk.kid = KID;
jwk.alg = 'RS256';
jwk.use = 'sig';

let pending = null; // { redirectUri, nonce }

function base64url( input ) {
	return Buffer.from( input )
		.toString( 'base64' )
		.replace( /\+/g, '-' )
		.replace( /\//g, '_' )
		.replace( /=+$/, '' );
}

function signIdToken( { clientId, nonce, email, name, sub } ) {
	const header = { alg: 'RS256', typ: 'JWT', kid: KID };
	const now = Math.floor( Date.now() / 1000 );
	const payload = {
		iss: ISSUER,
		aud: clientId,
		sub,
		email,
		name,
		nonce,
		iat: now,
		exp: now + 3600,
	};

	const data = `${ base64url( JSON.stringify( header ) ) }.${ base64url( JSON.stringify( payload ) ) }`;
	const signature = crypto.sign( 'RSA-SHA256', Buffer.from( data ), privateKey );
	const sigB64 = signature.toString( 'base64' ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );

	return `${ data }.${ sigB64 }`;
}

const app = express();
app.use( express.urlencoded( { extended: true } ) );
app.use( express.json() );

app.get( '/.well-known/openid-configuration', ( req, res ) => {
	res.json( {
		issuer: ISSUER,
		authorization_endpoint: `${ ISSUER }/authorize`,
		token_endpoint: `${ ISSUER }/token`,
		jwks_uri: `${ ISSUER }/jwks`,
		response_types_supported: [ 'code' ],
		subject_types_supported: [ 'public' ],
		id_token_signing_alg_values_supported: [ 'RS256' ],
	} );
} );

app.get( '/jwks', ( req, res ) => {
	res.json( { keys: [ jwk ] } );
} );

// Auto-"login": skip any UI, immediately redirect back with the fixed code.
app.get( '/authorize', ( req, res ) => {
	const { redirect_uri: redirectUri, state, nonce } = req.query;

	pending = { redirectUri, nonce };

	const callback = new URL( redirectUri );
	callback.searchParams.set( 'code', FIXED_CODE );
	callback.searchParams.set( 'state', state );

	res.redirect( callback.toString() );
} );

app.post( '/token', ( req, res ) => {
	const { code, client_id: clientId } = req.body;

	if ( code !== FIXED_CODE || ! pending ) {
		res.status( 400 ).json( { error: 'invalid_grant' } );
		return;
	}

	const idToken = signIdToken( {
		clientId,
		nonce: pending.nonce,
		email: 'sso-user@example.com',
		name: 'SSO Test User',
		sub: 'mock-subject-1',
	} );

	pending = null;

	res.json( {
		access_token: 'mock-access-token',
		token_type: 'Bearer',
		expires_in: 3600,
		id_token: idToken,
	} );
} );

app.listen( PORT, () => {
	// eslint-disable-next-line no-console
	console.log( `Mock OIDC provider listening on ${ ISSUER }` );
} );
