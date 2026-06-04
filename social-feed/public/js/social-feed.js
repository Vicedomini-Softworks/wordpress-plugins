/* Social Feed - Frontend JS */
(function () {
	'use strict';

	// Console warnings for feed errors
	document.querySelectorAll('.social-feed[data-feed-error]').forEach(function (el) {
		var error = el.getAttribute('data-feed-error');
		if (error) {
			console.warn('[Social Feed] Feed error:', error, el);
		}
	});

	// Carousel: keyboard + touch navigation
	document.querySelectorAll('.social-feed-carousel').forEach(function (carousel) {
		var items   = carousel.querySelectorAll('.social-feed-item');
		var current = 0;

		if (!items.length) return;

		// Keyboard nav when focused inside carousel
		carousel.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') {
				current = Math.min(current + 1, items.length - 1);
				items[current].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
			}
			if (e.key === 'ArrowLeft') {
				current = Math.max(current - 1, 0);
				items[current].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
			}
		});
	});

	// Masonry: re-layout on image load (graceful)
	document.querySelectorAll('.social-feed-masonry').forEach(function (masonry) {
		masonry.querySelectorAll('img').forEach(function (img) {
			if (!img.complete) {
				img.addEventListener('load', function () {
					// CSS columns handle reflow automatically; nothing needed
				});
			}
		});
	});

})();
