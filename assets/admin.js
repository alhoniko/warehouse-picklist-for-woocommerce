/**
 * Warehouse Picklist admin: logo media picker + category order sortable.
 */
jQuery(function ($) {
	'use strict';

	// --- Logo media picker (Settings tab) ---
	var frame;

	$('#whpl-logo-select').on('click', function (e) {
		e.preventDefault();

		if (frame) {
			frame.open();
			return;
		}

		frame = wp.media({
			title: whplAdmin.i18n.selectLogo,
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

			$('#whpl-logo-id').val(attachment.id);
			$('#whpl-logo-preview').html(
				$('<img>', { src: url, css: { maxHeight: '60px', maxWidth: '240px' } })
			);
			$('#whpl-logo-remove').show();
		});

		frame.open();
	});

	$('#whpl-logo-remove').on('click', function (e) {
		e.preventDefault();
		$('#whpl-logo-id').val(0);
		$('#whpl-logo-preview').empty();
		$(this).hide();
	});

	// --- Category order sortable (Category order tab) ---
	var $sortable = $('#whpl-category-sortable');

	if ($sortable.length) {
		$sortable.sortable();

		$('#whpl-category-save').on('click', function () {
			var order = $sortable.find('li').map(function () {
				return $(this).data('term-id');
			}).get();

			$('#whpl-category-status').text(whplAdmin.i18n.saving);

			$.post(ajaxurl, {
				action: 'whpl_save_category_order',
				order: order,
				nonce: whplAdmin.nonce
			}, function (response) {
				$('#whpl-category-status').text(
					response && response.success ? whplAdmin.i18n.saved : whplAdmin.i18n.error
				);
			});
		});

		$('#whpl-category-reset').on('click', function () {
			$('#whpl-category-status').text(whplAdmin.i18n.saving);

			$.post(ajaxurl, {
				action: 'whpl_reset_category_order',
				nonce: whplAdmin.nonce
			}, function (response) {
				if (response && response.success) {
					window.location.reload();
				} else {
					$('#whpl-category-status').text(whplAdmin.i18n.error);
				}
			});
		});
	}
});
