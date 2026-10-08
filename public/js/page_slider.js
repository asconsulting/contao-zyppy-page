/**
 * Zyppy Page - page preview slider
 *
 * Keeps the compact "header" index in sync with the scrolling "body" teaser strip
 * rendered by the pagepreview module. The two lists are matched item-for-item by the
 * `rel` attribute on each <li>, which carries the page alias.
 *
 * Requires jQuery. Contao ships it, but it is only put on the page when the page
 * layout has "Add jQuery" ticked - this file deliberately does NOT load its own copy,
 * because a second jQuery on the page breaks every other plugin already using the
 * first one. If jQuery is missing the slider says so in the console and stops.
 *
 * @copyright   Andrew Stevens
 * @author      Andrew Stevens
 * @license     AGPL-3.0-only
 * @package     Zyppy Page
 * @link        https://github.com/asconsulting/contao-zyppy-page
 */

(function (factory) {
	'use strict';

	if (typeof window.jQuery === 'undefined') {
		if (window.console && window.console.warn) {
			window.console.warn(
				'Zyppy Page: the page preview slider needs jQuery, which is not on this page. ' +
				'Tick "Add jQuery" in the page layout (Themes > Page layouts > JavaScript), ' +
				'or remove the slider by setting the module\'s "Slider items" field to 0.'
			);
		}

		return;
	}

	factory(window.jQuery);
}(function ($) {
	'use strict';

	// Inline SVG rather than Font Awesome classes: this bundle cannot ship Font Awesome
	// and cannot require it through Composer, so the original "fa fa-chevron-circle-*"
	// markup rendered as two invisible empty anchors on any site without it. The
	// arrow_left / arrow_right classes are kept so existing theme CSS still matches.
	var CHEVRON = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><polyline points="%POINTS%"></polyline></svg>';

	function chevron(direction) {
		// left = "<", right = ">" - the original had these two the wrong way round
		return CHEVRON.replace('%POINTS%', direction === 'left' ? '14 8 10 12 14 16' : '10 8 14 12 10 16');
	}

	/**
	 * Sync the header list's active/inactive classes to the body list's.
	 *
	 * @param {jQuery} header
	 * @param {jQuery} body
	 */
	function syncHeader(header, body) {
		var active = [];

		body.find('ul.level_1 > li.active_preview').each(function () {
			active.push($(this).attr('rel'));
		});

		header.find('ul.level_1 > li').each(function () {
			if (active.indexOf($(this).attr('rel')) >= 0) {
				$(this).addClass('active_preview').removeClass('inactive_preview');
			} else {
				$(this).removeClass('active_preview').addClass('inactive_preview');
			}
		});
	}

	$.fn.pagePreviewSlider = function () {
		return this.each(function () {
			var wrapper = $(this);

			// Re-initialising the same element would stack duplicate handlers
			if (wrapper.data('zyppyPageSlider')) {
				return;
			}

			var header = wrapper.find('div.header');
			var body = wrapper.find('div.body');

			header.find('ul.level_1 > li').addClass('inactive_preview').removeClass('active_preview');
			body.find('ul.level_1 > li').addClass('active_preview').removeClass('inactive_preview');

			// data-show, not show: "show" is not a valid HTML attribute, and the module
			// never assigned the value it used to read, so this was always empty.
			var showItems = parseInt(wrapper.attr('data-show'), 10);

			if (isNaN(showItems) || showItems < 0) {
				showItems = 0;
			}

			var left = $();
			var right = $();

			if (showItems > 0) {
				if (body.find('span.left').length < 1) {
					body.prepend(
						'<span class="left"><a href="#" class="arrow_left" role="button" aria-label="Previous">' + chevron('left') + '</a></span>' +
						'<span class="right"><a href="#" class="arrow_right" role="button" aria-label="Next">' + chevron('right') + '</a></span>'
					);
				}

				left = body.find('a.arrow_left');
				right = body.find('a.arrow_right');

				// Measured while every item is still visible (see the addClass above),
				// so a stylesheet that hides .inactive_preview cannot zero these out.
				var wrapperWidth = body.find('ul.level_1').outerWidth();
				var itemWidth = body.find('ul.level_1 > li:first-child').width();

				if (itemWidth > 0) {
					var calculatedItems = Math.floor(wrapperWidth / itemWidth);

					if (calculatedItems > 0 && calculatedItems < showItems) {
						showItems = calculatedItems;
					}
				}
			}

			body.find('ul.level_1 > li').addClass('inactive_preview').removeClass('active_preview');

			var slideLeft = function (el) {
				el.preventDefault();
				body.css('textAlign', 'right');

				var firstElement = body.find('ul.level_1 > li').first();

				body.find('li.active_preview').last().next('li').hide().removeClass('inactive_preview').addClass('active_preview').show('slow');
				firstElement.hide('slow').removeClass('active_preview').appendTo(body.find('ul.level_1'));
				firstElement.addClass('inactive_preview');

				syncHeader(header, body);
			};

			var slideRight = function (el) {
				el.preventDefault();
				body.css('textAlign', 'left');

				var lastElement = body.find('ul.level_1 > li').last();

				lastElement.hide().removeClass('inactive_preview').prependTo(body.find('ul.level_1'));
				body.find('li.active_preview').last().hide('slow').removeClass('active_preview').addClass('inactive_preview');
				lastElement.addClass('active_preview').show('slow');

				syncHeader(header, body);
			};

			if (showItems > 0) {
				var counter = 0;

				body.find('ul.level_1 > li').each(function () {
					counter = counter + 1;

					// Clear any inline display so the stylesheet's .inactive_preview rule wins
					$(this).css('display', '');

					if (counter <= showItems) {
						$(this).addClass('active_preview').removeClass('inactive_preview');
					} else {
						$(this).removeClass('active_preview').addClass('inactive_preview');
					}
				});

				syncHeader(header, body);

				// .bind()/.unbind()/.click() are all removed in jQuery 4. The original also
				// wrote right.click('click', slideRight), which only bound at all because of
				// jQuery's (eventData, handler) overload.
				left.off('click').on('click', slideLeft);
				right.off('click').on('click', slideRight);
			}

			wrapper.data('zyppyPageSlider', true);
		});
	};

	// Nothing in this bundle ever called the plugin, which is the other reason the
	// slider has never run. Every pagepreview module that asks for a slider starts here.
	$(function () {
		$('nav.mod_pagepreview[data-show]').pagePreviewSlider();
	});
}));
