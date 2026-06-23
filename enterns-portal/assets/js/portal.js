/**
 * Enterns Portal — front-end JS bootstrap.
 * Phase 1: partner form stub (processing wired in Phase 3).
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

			// Phase 3 will replace this stub with an AJAX call.
			$msg
				.addClass( 'enp-notice enp-notice--info' )
				.text( 'Application submission is being set up — please check back soon.' )
				.show();
		} );
	} );
} )( jQuery );
