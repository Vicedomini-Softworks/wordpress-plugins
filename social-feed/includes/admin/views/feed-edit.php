<?php
/**
 * Feed add/edit page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $feed may be null (new) or array (edit)
$is_edit    = ! empty( $feed );
$slug       = $feed['slug'] ?? '';
$platform   = $feed['platform'] ?? 'instagram';
$mode       = $feed['mode'] ?? 'embed';
$account    = $feed['account'] ?? '';
$cache_h    = $feed['cache_hours'] ?? 8;
$display    = $feed['display'] ?? array();

$platforms  = array(
	'instagram' => 'Instagram',
	'facebook'  => 'Facebook',
	'tiktok'    => 'TikTok',
	'x'         => 'X',
	'threads'   => 'Threads',
	'bluesky'   => 'Bluesky',
	'youtube'   => 'YouTube',
);

$layouts    = array(
	'grid'     => 'Grid',
	'masonry'  => 'Masonry',
	'carousel' => 'Carousel',
	'column'   => 'Column',
);

$themes     = array(
	'light' => 'Light',
	'dark'  => 'Dark',
);
?>

<div class="wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Edit Feed', 'social-feed' ) : esc_html__( 'Add New Feed', 'social-feed' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'social_feed_save_feed' ); ?>
		<input type="hidden" name="action" value="social_feed_save_feed">

		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="feed_slug"><?php esc_html_e( 'Feed ID', 'social-feed' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="feed_slug"
						name="feed_slug"
						value="<?php echo esc_attr( $slug ); ?>"
						class="regular-text"
						pattern="[a-z0-9\-]+"
						required
						<?php echo $is_edit ? 'readonly' : ''; ?>
					>
					<p class="description"><?php esc_html_e( 'Lowercase letters, numbers, hyphens only. Used in shortcode.', 'social-feed' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="platform"><?php esc_html_e( 'Platform', 'social-feed' ); ?></label>
				</th>
				<td>
					<select id="platform" name="platform">
						<?php foreach ( $platforms as $value => $label ): ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $platform, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="mode"><?php esc_html_e( 'Mode', 'social-feed' ); ?></label>
				</th>
				<td>
					<select id="mode" name="mode">
						<option value="embed" <?php selected( $mode, 'embed' ); ?>><?php esc_html_e( 'Embed (default)', 'social-feed' ); ?></option>
						<option value="oauth" <?php selected( $mode, 'oauth' ); ?>><?php esc_html_e( 'OAuth (API)', 'social-feed' ); ?></option>
					</select>
					<p class="description" id="mode-embed-desc"><?php esc_html_e( 'Embed: paste a post or profile URL below. No API required.', 'social-feed' ); ?></p>
					<p class="description" id="mode-oauth-desc" style="display:none;"><?php esc_html_e( 'OAuth: connect account via Platform Settings. Fetches a feed of posts.', 'social-feed' ); ?></p>
					<div id="x-cost-warning" style="display:none;">
						<div class="notice notice-warning inline">
							<p><?php esc_html_e( 'X API charges $0.001/owned read and $0.005/third-party read. OAuth mode will incur costs on every cache miss.', 'social-feed' ); ?></p>
						</div>
					</div>
					<div id="tiktok-approval-notice" style="display:none;">
						<div class="notice notice-info inline">
							<p><?php esc_html_e( 'TikTok OAuth requires sandbox approval then a production audit. Until approved, only sandbox data is available.', 'social-feed' ); ?></p>
						</div>
					</div>
				</td>
			</tr>

			<tr id="account-row">
				<th scope="row">
					<label for="account"><span id="account-label"><?php esc_html_e( 'Post or Profile URL', 'social-feed' ); ?></span></label>
				</th>
				<td>
					<input type="url" id="account" name="account" value="<?php echo esc_attr( $account ); ?>" class="regular-text">
					<p class="description" id="account-desc-embed"><?php esc_html_e( 'URL of the post or profile to embed.', 'social-feed' ); ?></p>
					<p class="description" id="account-desc-oauth" style="display:none;"><?php esc_html_e( 'Account/page/channel identifier from Platform Settings.', 'social-feed' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="display_type"><?php esc_html_e( 'Layout', 'social-feed' ); ?></label>
				</th>
				<td>
					<select id="display_type" name="display_type">
						<?php foreach ( $layouts as $value => $label ): ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $display['type'] ?? 'grid', $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="display_theme"><?php esc_html_e( 'Theme', 'social-feed' ); ?></label>
				</th>
				<td>
					<select id="display_theme" name="display_theme">
						<?php foreach ( $themes as $value => $label ): ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $display['theme'] ?? 'light', $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="display_limit"><?php esc_html_e( 'Post Limit', 'social-feed' ); ?></label>
				</th>
				<td>
					<input type="number" id="display_limit" name="display_limit" value="<?php echo intval( $display['limit'] ?? 8 ); ?>" min="1" max="48" class="small-text">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cache_hours"><?php esc_html_e( 'Cache Duration (hours)', 'social-feed' ); ?></label>
				</th>
				<td>
					<input type="number" id="cache_hours" name="cache_hours" value="<?php echo intval( $cache_h ); ?>" min="4" max="48" class="small-text">
					<p class="description"><?php esc_html_e( 'Min 4, max 48 hours.', 'social-feed' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Show Fields', 'social-feed' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="show_media" value="1" <?php checked( $display['show_media'] ?? true ); ?>>
							<?php esc_html_e( 'Media (image/video)', 'social-feed' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="show_caption" value="1" <?php checked( $display['show_caption'] ?? true ); ?>>
							<?php esc_html_e( 'Caption', 'social-feed' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="show_username" value="1" <?php checked( $display['show_username'] ?? true ); ?>>
							<?php esc_html_e( 'Username', 'social-feed' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="show_timestamp" value="1" <?php checked( $display['show_timestamp'] ?? true ); ?>>
							<?php esc_html_e( 'Timestamp', 'social-feed' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="link_posts" value="1" <?php checked( $display['link_posts'] ?? true ); ?>>
							<?php esc_html_e( 'Link to original post', 'social-feed' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>

		</table>

		<p class="submit">
			<button type="submit" name="social_feed_save_feed" class="button button-primary">
				<?php echo $is_edit ? esc_html__( 'Update Feed', 'social-feed' ) : esc_html__( 'Create Feed', 'social-feed' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=social-feed' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Cancel', 'social-feed' ); ?></a>
		</p>

	</form>
</div>

<script>
(function() {
	var platformSelect = document.getElementById('platform');
	var modeSelect     = document.getElementById('mode');
	var accountInput   = document.getElementById('account');

	function update() {
		var platform = platformSelect.value;
		var mode     = modeSelect.value;

		document.getElementById('mode-embed-desc').style.display = 'embed' === mode ? '' : 'none';
		document.getElementById('mode-oauth-desc').style.display = 'oauth' === mode ? '' : 'none';
		document.getElementById('x-cost-warning').style.display  = ('x' === platform && 'oauth' === mode) ? '' : 'none';
		document.getElementById('tiktok-approval-notice').style.display = ('tiktok' === platform && 'oauth' === mode) ? '' : 'none';

		var accountLabel = document.getElementById('account-label');
		accountLabel.textContent = 'embed' === mode ? '<?php esc_html_e( 'Post or Profile URL', 'social-feed' ); ?>' : '<?php esc_html_e( 'Account / Channel ID', 'social-feed' ); ?>';

		document.getElementById('account-desc-embed').style.display = 'embed' === mode ? '' : 'none';
		document.getElementById('account-desc-oauth').style.display = 'oauth' === mode ? '' : 'none';

		// account field type
		accountInput.type = 'embed' === mode ? 'url' : 'text';
	}

	platformSelect.addEventListener('change', update);
	modeSelect.addEventListener('change', update);
	update();
}());
</script>
