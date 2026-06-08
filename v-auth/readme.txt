=== V-Auth ===
Contributors: vicedominisoftworks
Tags: oidc, openid connect, sso, login, authentication
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

OpenID Connect single sign-on for WordPress. Configure login with external identity providers; SAML support planned.

== Description ==

V-Auth lets site administrators connect WordPress login to one or more external OpenID Connect identity providers (Azure AD / Entra ID, Google Workspace, Okta, Keycloak, Auth0, and any standards-compliant OIDC provider).

= Features =

* Configure multiple OIDC providers via OpenID discovery
* Standard authorization-code flow with state/nonce verification and signed ID token validation (JWKS)
* "Login with…" buttons alongside the normal WordPress login form, or full redirect-to-IdP mode
* Automatic WordPress account provisioning on first login, mapped by email, with a configurable default role
* Client secrets stored securely via VSecrets Manager (AES-256-CBC)
* SAML support planned for a future release

= Requirements =

This plugin requires the VSecrets Manager plugin to be installed and active.

== Installation ==

1. Ensure VSecrets Manager is installed and active.
2. Upload `v-auth` to `/wp-content/plugins/`.
3. Activate the plugin from the Plugins screen.
4. Go to Settings > V-Auth and add your identity provider(s).
5. Copy the displayed redirect URI into your identity provider's app registration.

== Frequently Asked Questions ==

= Why do I need VSecrets Manager? =

OIDC client secrets are stored via VSecrets Manager using AES-256-CBC encryption instead of plain text in wp_options.

= What happens to existing WordPress users? =

V-Auth matches the verified email from the identity provider to an existing WordPress user. If no match exists, a new account is created with the configured default role.

= Can I require SSO for all logins? =

Yes — set the login mode to "force redirect" in Settings > V-Auth. Use `?v_auth_bypass=1` on wp-login.php to reach the normal WordPress login form if needed.

== Changelog ==

= 1.0.0 =
* Initial release: OpenID Connect single sign-on.
