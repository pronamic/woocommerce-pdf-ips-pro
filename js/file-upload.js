// Thanks to Mike Jolley!
// http://mikejolley.com/2012/12/using-the-new-wordpress-3-5-media-uploader-in-plugins/

jQuery(document).ready(function($) {
		
	// Uploading files
	var file_frame;
	 
	$('.upload_file_button').live('click', function( event ){

		// get input field id from data-input_id
		input_id = '#'+$( this ).data( 'input_id' );
		input_id_class = '.'+$( this ).data( 'input_id' );
		input_id_clean = $( this ).data( 'input_id' );

		// get remove button text
		remove_button_text = $( this ).data( 'remove_button_text' );
	 
		event.preventDefault();
	 
		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			file_frame.open();
			return;
		}

		 
		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: $( this ).data( 'uploader_title' ),
			button: {
				text: $( this ).data( 'uploader_button_text' ),
			},
			multiple: false	// Set to true to allow multiple files to be selected
		});
	 
		// When a file is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one file from the uploader
			attachment = file_frame.state().get('selection').first().toJSON();

			// console.log(attachment);
			
			// set the values of the input fields to the attachment id and filename
			$( input_id+'_id' ).val(attachment.id);
			$( input_id+'_filename' ).val(attachment.filename);
			
			// show remove button
			if ( $( '.remove_file_button[data-input_id='+input_id_clean+']' ).length == 0 ) {
				remove_button = '<span class="button remove_file_button" data-input_id="'+input_id_clean+'">'+remove_button_text+'</span>';
				$( input_id+'_filename' ).after(remove_button);
			}
		});
	 
		// Finally, open the modal
		file_frame.open();
	});
 
	$('.remove_file_button').live('click', function( event ){
		// get input field from data-input_id
		input_id = '#'+$( this ).data( 'input_id' );

		// clear inputs and remove remove button ;)
		$( input_id+'_filename' ).val('');
		$( input_id+'_id' ).val('');
		$( this ).remove();
	});		
});