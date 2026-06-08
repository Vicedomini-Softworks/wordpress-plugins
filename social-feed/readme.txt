=== Social Feed ===
Contributors: vicedominisoftworks
Tags: social media, instagram, facebook, tiktok, twitter, x, bluesky, youtube, threads, feed, embed
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Display social media feeds from Instagram, Facebook, TikTok, X, Threads, Bluesky, and YouTube using a simple shortcode.

== Description ==

Social Feed lets you embed posts and feeds from 7 major social platforms on any WordPress page or post using a simple shortcode.

**Supported platforms:**
* Instagram
* Facebook
* TikTok
* X (Twitter)
* Threads
* Bluesky
* YouTube

**Features:**
* Two modes per feed: Embed (paste a URL, no API required) or OAuth (connect your account for a live feed)
* Admin panel to create and manage multiple feeds
* Configurable layouts: grid, masonry, carousel, column
* Light and dark themes
* Configurable caching (4–48 hours, default 8 hours)
* Per-platform cache age display and reset button
* Images cached locally for performance
* Graceful error handling — feeds hide on failure instead of showing errors to visitors
* Encrypted credential storage

**Shortcode:**
`[social_feed id="my-feed" type="grid" limit="8"]`

== Installation ==

1. Upload the `social-feed` folder to `/wp-content/plugins/`
2. Activate the plugin in *Plugins > Installed Plugins*
3. Go to *Social Feed > Add New Feed* to create your first feed
4. For OAuth mode, add API credentials in *Social Feed > Platform Settings*
5. Place `[social_feed id="your-feed-id"]` on any page or post

== Frequently Asked Questions ==

= Do I need API keys? =

No — Embed mode works without any API credentials. Just paste a post or profile URL. OAuth mode requires platform-specific API credentials.

= Does X (Twitter) cost money? =

Yes. The X API charges $0.001/owned read and $0.005/third-party read. The plugin defaults to Embed mode for X. OAuth mode is available but will incur API costs.

= Does TikTok require approval? =

TikTok's Display API requires sandbox approval then a production audit. The plugin includes oEmbed fallback (Embed mode) that works without approval.

== Changelog ==

= 1.0.0 =
* Initial release

== Development ==

* **e2e Tests:** Playwright tests in `tests/e2e/`
* **Static Analysis:** PHPCS (WordPress Coding Standards) and PHPStan (level 5)
* **CI:** GitHub Actions runs e2e tests, PHPCS, and PHPStan on push/PR

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
