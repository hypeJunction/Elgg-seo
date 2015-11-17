define(function (require) {

	var elgg = require('elgg');
	var $ = require('jquery');
	var spinner = require('elgg/spinner');

	$(document).on('submit', '.elgg-form-seo-edit', function (e) {
		var $form = $(this);
		if ($form.closest('#colorbox').length === 0) {
			return;
		}

		e.preventDefault();
		elgg.action($form.attr('action'), {
			data: $form.serialize(),
			beforeSend: spinner.start,
			complete: spinner.stop,
			success: function() {
				if (typeof $.fn.colorbox !== 'undefined') {
					$.colorbox.close();
				}
			}
		});
	});
});

