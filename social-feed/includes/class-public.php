<?php
/**
 * Public-facing functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Feed_Public {

	/**
	 * Register scripts and styles
	 */
	public static function register_assets(): void {
		wp_register_style(
			'social-feed-public',
			SOCIAL_FEED_PLUGIN_URL . 'public/css/social-feed.css',
			array(),
			SOCIAL_FEED_VERSION
		);

		wp_register_style(
			'social-feed-theme-light',
			SOCIAL_FEED_PLUGIN_URL . 'public/css/theme-light.css',
			array( 'social-feed-public' ),
			SOCIAL_FEED_VERSION
		);

		wp_register_style(
			'social-feed-theme-dark',
			SOCIAL_FEED_PLUGIN_URL . 'public/css/theme-dark.css',
			array( 'social-feed-public' ),
			SOCIAL_FEED_VERSION
		);

		wp_register_script(
			'social-feed-public',
			SOCIAL_FEED_PLUGIN_URL . 'public/js/social-feed.js',
			array(),
			SOCIAL_FEED_VERSION,
			true
		);
	}

	/**
	 * Enqueue assets for a specific feed
	 */
	public static function enqueue_for_feed( string $theme, string $type ): void {
		wp_enqueue_style( 'social-feed-public' );

		if ( 'light' === $theme ) {
			wp_enqueue_style( 'social-feed-theme-light' );
		} else {
			wp_enqueue_style( 'social-feed-theme-dark' );
		}

		// JS needed for carousel and masonry
		wp_enqueue_script( 'social-feed-public' );

		// Print error console warnings once via wp_footer
		static $footer_hooked = false;
		if ( ! $footer_hooked ) {
			$footer_hooked = true;
			add_action( 'wp_footer', array( __CLASS__, 'print_error_script' ) );
		}
	}

	/**
	 * Print inline console warning script — only if feeds have errors
	 */
	public static function print_error_script(): void {
		?>
		<script>
		(function(){
			var els = document.querySelectorAll('.social-feed[data-feed-error]');
			els.forEach(function(el){
				var e = el.getAttribute('data-feed-error');
				if(e) console.warn('[Social Feed] Feed error:', e, el);
			});
		})();
		</script>
		<?php
	}
}
