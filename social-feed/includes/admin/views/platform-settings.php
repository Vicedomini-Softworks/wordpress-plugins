<?php
/**
 * Platform settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $platform and $connected are injected by class-admin.php before including this view.
$platform  = $platform ?? '';  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$connected = $connected ?? false;  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$platforms = array(
	'instagram' => array(
		'name' => 'Instagram',
		'note' => 'Requires Meta Business/Creator account + App Review.',
	),
	'facebook'  => array(
		'name' => 'Facebook',
		'note' => 'Requires Facebook Page + App Review.',
	),
	'tiktok'    => array(
		'name' => 'TikTok',
		'note' => 'Requires TikTok Developer account + sandbox/production audit.',
	),
	'x'         => array(
		'name' => 'X',
		'note' => '',
	),
	'threads'   => array(
		'name' => 'Threads',
		'note' => 'Uses same Meta app as Instagram/Facebook.',
	),
	'bluesky'   => array(
		'name' => 'Bluesky',
		'note' => 'Free, no App Review required.',
	),
	'youtube'   => array(
		'name' => 'YouTube',
		'note' => 'Free. 10,000 API units/day quota.',
	),
);

$active = $platform && isset( $platforms[ $platform ] ) ? $platform : key( $platforms );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Platform Settings', 'social-feed' ); ?></h1>

	<?php if ( $connected ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Account connected successfully!', 'social-feed' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Credentials saved successfully!', 'social-feed' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Cache cleared successfully!', 'social-feed' ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $platforms as $slug => $info ) : ?>
			<a
				href="<?php echo esc_url( admin_url( 'admin.php?page=social-feed-platform-settings&platform=' . $slug ) ); ?>"
				class="nav-tab <?php echo $slug === $active ? 'nav-tab-active' : ''; ?>"
			>
				<?php echo esc_html( $info['name'] ); ?>
				<?php
				$_tab_meta  = get_option( 'social_feed_meta_' . $slug, array() );
				$_tab_token = vs_secrets_manager_get( 'social_feed_' . $slug . '_access_token' );
				if ( ! empty( $_tab_token ) && ( $_tab_meta['token_expiry'] ?? 0 ) > time() ) {
					echo ' <span class="social-feed-connected" style="color:#46b450;">&#10003;</span>';
				}
				?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php
	if ( $active ) :
		$meta       = get_option( 'social_feed_meta_' . $active, array() );
		$info       = $platforms[ $active ];
		$token      = vs_secrets_manager_get( 'social_feed_' . $active . '_access_token' );
		$connected  = ! empty( $token ) && ( $meta['token_expiry'] ?? 0 ) > time();
		$reset_time = $meta['cache_reset_at'] ?? 0;
		// Mask stored client_id for display (show last 4 chars only)
		$stored_client_id  = vs_secrets_manager_get( 'social_feed_' . $active . '_client_id' ) ?? '';
		$display_client_id = $stored_client_id ? str_repeat( '•', max( 0, strlen( $stored_client_id ) - 4 ) ) . substr( $stored_client_id, -4 ) : '';
		?>
		<div class="tab-content" style="margin-top:20px;">

			<?php if ( ! empty( $info['note'] ) ) : ?>
				<div class="notice notice-info inline" style="margin-bottom:16px;">
					<p><?php echo esc_html( $info['note'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( 'x' === $active ) : ?>
				<div class="notice notice-warning inline" style="margin-bottom:16px;">
					<p><strong><?php esc_html_e( 'X API Costs:', 'social-feed' ); ?></strong> <?php esc_html_e( '$0.001 per owned read, $0.005 per third-party read. OAuth mode will incur API costs on every cache miss. Consider using Embed mode to avoid costs.', 'social-feed' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'social_feed_save_platform' ); ?>
				<input type="hidden" name="action" value="social_feed_save_platform">
				<input type="hidden" name="platform" value="<?php echo esc_attr( $active ); ?>">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="client_id"><?php esc_html_e( 'Client ID / App ID', 'social-feed' ); ?></label>
						</th>
						<td>
							<input type="text" id="client_id" name="client_id" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( $display_client_id ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to keep existing value.', 'social-feed' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="client_secret"><?php esc_html_e( 'Client Secret / App Secret', 'social-feed' ); ?></label>
						</th>
						<td>
							<input type="password" id="client_secret" name="client_secret" value="" class="regular-text" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Stored via VSecrets Manager. Leave blank to keep existing value.', 'social-feed' ); ?></p>
						</td>
					</tr>
				</table>

				<p>
					<button type="submit" name="social_feed_save_platform" class="button button-primary"><?php esc_html_e( 'Save Credentials', 'social-feed' ); ?></button>
				</p>
			</form>

			<hr>

			<h3><?php esc_html_e( 'OAuth Connection', 'social-feed' ); ?></h3>

			<?php if ( $connected ) : ?>
				<p>
					<span style="color:#46b450;font-weight:bold;">&#10003; <?php esc_html_e( 'Connected', 'social-feed' ); ?></span>
					<?php if ( ! empty( $meta['connected_at'] ) ) : ?>
						&mdash; 
						<?php
						echo esc_html(
							sprintf(
							/* translators: %s: date */
								__( 'Since %s', 'social-feed' ),
								date_i18n( get_option( 'date_format' ), $meta['connected_at'] )
							)
						);
						?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php
			$auth_url = rest_url( 'social-feed/v1/oauth/' . $active . '/authorize' );
			?>
			<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-secondary">
				<?php
				echo $connected
					? esc_html__( 'Reconnect Account', 'social-feed' )
					: esc_html__( 'Connect Account', 'social-feed' );
				?>
			</a>

			<hr>

			<h3><?php esc_html_e( 'Cache', 'social-feed' ); ?></h3>

			<?php if ( $reset_time > 0 ) : ?>
				<p>
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: date */
						__( 'Last cleared: %s', 'social-feed' ),
						human_time_diff( $reset_time ) . ' ' . __( 'ago', 'social-feed' )
					)
				);
				?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'social_feed_reset_cache' ); ?>
				<input type="hidden" name="action" value="social_feed_reset_cache">
				<input type="hidden" name="platform" value="<?php echo esc_attr( $active ); ?>">
				<button type="submit" name="social_feed_reset_cache" class="button button-secondary">
					<?php esc_html_e( 'Clear Platform Cache', 'social-feed' ); ?>
				</button>
			</form>

			<hr>

			<h3><?php esc_html_e( 'OAuth Redirect URI', 'social-feed' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Add this URL to your app\'s allowed redirect URIs:', 'social-feed' ); ?></p>
			<code><?php echo esc_url( rest_url( 'social-feed/v1/oauth/' . $active . '/callback' ) ); ?></code>

		</div>
	<?php endif; ?>
</div>
