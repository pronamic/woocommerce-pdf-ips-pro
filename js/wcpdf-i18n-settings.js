jQuery( function( $ ) {
	// extend attr() function http://stackoverflow.com/a/14645827/1446634
	(function(old) {
		$.fn.attr = function() {
			if(arguments.length === 0) {
				if(this.length === 0) {
					return null;
				}

				var obj = {};
				$.each(this[0].attributes, function() {
					if(this.specified) {
						obj[this.name] = this.value;
					}
				});
				return obj;
			}

			return old.apply(this, arguments);
		};
	})($.fn.attr);

	// var button = '<img src="'+wpo_wcpdf_i18n.icon+'" style="vertical-align: top; height: 21px; padding: 4px; cursor: pointer; cursor: hand;" class="wpo-wcpdf-i18n-translations-toggle"/>'
	var button = '<button class="wpo-wcpdf-i18n-translations-toggle button button-secondary">'+wpo_wcpdf_i18n.translate_text+'</button>';
	$('#wpo-wcpdf-settings .translatable').after( '<div class="wpo-wcpdf-i18n-translations">'+button+'</div>' );

	// get translations
	$('#wpo-wcpdf-settings').on( 'click', '.wpo-wcpdf-i18n-translations-toggle', function(event) {
		event.preventDefault();
		var settings_input = $( this ).parent().prev();

		var translations_id = $( settings_input ).attr('id')+'-translations';
		if ($('#'+translations_id).length != 0) {
			return;
		};

		var data = {
			security:          wpo_wcpdf_i18n.nonce,
			input_type:        $( settings_input ).prop('nodeName'),
			input_attributes:  $( settings_input ).attr(),
		};

		xhr = $.ajax({
			type:		'POST',
			url:		wpo_wcpdf_i18n.ajaxurl+'?action=wcpdf_i18n_get_translations',
			data:		data,
			success:	function( data ) {
				// console.log( data );
				if ( !( $.isEmptyObject( data ) ) ) {
					var translations = $( settings_input ).nextAll('.wpo-wcpdf-i18n-translations').html( data );
					$( translations ).tabs();					
				}
			}
		});
	});

	// save translations
	$('#wpo-wcpdf-settings').on( 'click', '.wpo-wcpdf-i18n-translations-save', function(event) {
		event.preventDefault();
		var spinner = $( this ).next('.spinner');
		$( spinner ).show().addClass('is-active');

		var inputs = $( this ).closest('.translations').find( ':input' );
		var setting = $(this).closest('.wpo-wcpdf-i18n-translations').parent().find('.translatable').attr('name');
		// console.log( inputs );
		// console.log( setting );
		// console.log( $( this ).next('.spinner') );

		var translations = {};
		$( inputs ).each(function() {
			var lang = $(this).data("language");
			if ( typeof lang !== "undefined" ) {
				translations[lang] = $(this).val();
			}
		});
		// console.log( translations );
		
		var data = {
			security: wpo_wcpdf_i18n.nonce,
			strings:  translations,
			setting:  setting,
		};

		xhr = $.ajax({
			type:		'POST',
			url:		wpo_wcpdf_i18n.ajaxurl+'?action=wcpdf_i18n_save_translations',
			data:		data,
			success:	function( data ) {
				// console.log( data );
				$( spinner ).removeClass('is-active');

			}
		});
	});
});