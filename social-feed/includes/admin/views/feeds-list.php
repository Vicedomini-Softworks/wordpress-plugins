<?php
/**
 * Feeds list page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$feeds = Social_Feed_Feed_Repository::get_all();
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ): ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Feed saved successfully!', 'social-feed' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['deleted'] ) ): ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Feed deleted successfully!', 'social-feed' ); ?></p>
		</div>
	<?php endif; ?>

	<p class="description">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=social-feed-add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Feed', 'social-feed' ); ?></a>
	</p>

	<?php if ( empty( $feeds ) ): ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No feeds configured yet. Create your first feed to get started!', 'social-feed' ); ?></p>
		</div>
	<?php else: ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Feed ID', 'social-feed' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Platform', 'social-feed' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Mode', 'social-feed' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Layout', 'social-feed' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Limit', 'social-feed' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Cache', 'social-feed' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Shortcode', 'social-feed' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'social-feed' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $feeds as $slug => $feed ): ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $slug ); ?></strong>
						</td>
						<td>
							<?php echo esc_html( ucfirst( $feed['platform'] ) ); ?>
						</td>
						<td>
							<?php echo 'oauth' === $feed['mode'] ? esc_html__( 'OAuth', 'social-feed' ) : esc_html__( 'Embed', 'social-feed' ); ?>
						</td>
						<td>
							<?php echo esc_html( ucfirst( $feed['display']['type'] ) ); ?>
						</td>
						<td>
							<?php echo intval( $feed['display']['limit'] ); ?>
						</td>
						<td>
							<?php
							$age = Social_Feed_Cache_Manager::get_cache_age( $slug );
							if ( 0 === $age ) {
								esc_html_e( 'Not cached', 'social-feed' );
							} else {
								printf(
									/* translators: %d: hours ago */
									esc_html__( '%d hours ago', 'social-feed' ),
									$age
								);
							}
							?>
						</td>
						<td>
							<code>[social_feed id="<?php echo esc_attr( $slug ); ?>" type="<?php echo esc_attr( $feed['display']['type'] ); ?>" limit="<?php echo intval( $feed['display']['limit'] ); ?>"]</code>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=social-feed-add&edit=' . urlencode( $slug ) ) ); ?>"><?php esc_html_e( 'Edit', 'social-feed' ); ?></a>
							|
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_html_e( 'Are you sure?', 'social-feed' ); ?>')">
								<?php wp_nonce_field( 'social_feed_delete_feed' ); ?>
								<input type="hidden" name="action" value="social_feed_delete_feed">
								<input type="hidden" name="feed_slug" value="<?php echo esc_attr( $slug ); ?>">
								<button type="submit" name="social_feed_delete_feed" class="button-link delete"><?php esc_html_e( 'Delete', 'social-feed' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
