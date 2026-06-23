/**
 * Enterns Portal — front-end JS.
 * Phase 3: live AJAX submission for the partner / mentor application form.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $form   = $( '#enp-partner-form' );
		var $msg    = $( '#enp-partner-msg' );
		var $submit = $( '#enp-partner-submit' );

		if ( ! $form.length ) {
			return;
		}

		$form.on( 'submit', function ( e ) {
			e.preventDefault();
			$msg.hide().removeClass( 'enp-notice--success enp-notice--error enp-notice--info' );

			// FormData captures text fields AND the photo file automatically.
			var data = new FormData( this );
			data.append( 'action', 'enp_partner_apply' );

			$submit.prop( 'disabled', true );
			$submit.find( '.enp-btn__text' ).hide();
			$submit.find( '.enp-btn__spinner' ).show();

			$.ajax( {
				url:         ENP.ajaxUrl,
				type:        'POST',
				data:        data,
				processData: false,
				contentType: false,
				success: function ( res ) {
					if ( res.success ) {
						$msg.addClass( 'enp-notice enp-notice--success' )
						    .text( res.data )
						    .show();
						$form[ 0 ].reset();
					} else {
						$msg.addClass( 'enp-notice enp-notice--error' )
						    .text( res.data || 'Submission failed. Please try again.' )
						    .show();
					}
				},
				error: function () {
					$msg.addClass( 'enp-notice enp-notice--error' )
					    .text( 'Network error — please check your connection and try again.' )
					    .show();
				},
				complete: function () {
					$submit.prop( 'disabled', false );
					$submit.find( '.enp-btn__text' ).show();
					$submit.find( '.enp-btn__spinner' ).hide();
				},
			} );
		} );
	} );
} )( jQuery );
