jQuery(function( $ ) {

	var wcap_all = '';
	$ ( '.add_single_cart' ).on( 'click', function( e ) {
		var wcap_selected_id = [];
		wcap_selected_id.push ( $( this ).attr( 'data-id' ) );
		$( '#wcap_manual_email_data_loading' ).show();
		var data = {
			action                  : 'wcap_add_to_agile_crm',
			wcap_abandoned_cart_ids : wcap_selected_id,
			wcap_all                : wcap_all
		};

		$.post( wcap_agile_params.ajax_url, data, function( response ) {
			$( '#wcap_manual_email_data_loading' ).hide();

			var abadoned_order_count = response;
			var order                = 'order';
			if ( abadoned_order_count > 1 ){
				order 				 = 'orders';
			}
			
			var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
			$( ".wcap_agile_message_p" ).html( display_message );
            $( "#wcap_agile_message" ).fadeIn();
            setTimeout( function(){
            	$( "#wcap_agile_message" ).fadeOut();
            },4000);
		});
	});

	$ ( '#add_all_carts' ).on( 'click', function( e ) {
		
		wcap_all = 'yes';
		var wcap_selected_id = [];
		$( '#wcap_manual_email_data_loading' ).show();
		var data = {
			action                  : 'wcap_add_to_agile_crm',
			wcap_abandoned_cart_ids : wcap_selected_id,
			wcap_all                : wcap_all
		};

		$.post( wcap_agile_params.ajax_url, data, function( response ) {
			$( '#wcap_manual_email_data_loading' ).hide();
			var abadoned_order_count = response;
			var order                = 'order';
			if ( abadoned_order_count > 1 ){
				order 				 = 'orders';
			}
			
			var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
			$( ".wcap_agile_message_p" ).html( display_message );
            $( "#wcap_agile_message" ).fadeIn();
            setTimeout( function(){
            	$( "#wcap_agile_message" ).fadeOut();
            },4000);
		});
	});

	$ ( '#doaction' ).on( 'click', function( e ) {
		if ( $( '#bulk-action-selector-top' ).val() == 'wcap_add_agile' ) {
			
			var checkboxes = document.getElementsByName('abandoned_order_id[]');
			var wcap_selected_id = [];
		  	for (var i = 0; i < checkboxes.length; i++) {
		     
		     	if ( checkboxes[i].checked ) {
		        	wcap_selected_id.push( checkboxes[i].value );
		    	}
		  	}
		  	
		  	$( '#wcap_manual_email_data_loading' ).show();
			var data = {
				action                  : 'wcap_add_to_agile_crm',
				wcap_abandoned_cart_ids : wcap_selected_id,
				wcap_all                : wcap_all
			};
			
			$.post( wcap_agile_params.ajax_url, data, function( response ) {
				$( '#wcap_manual_email_data_loading' ).hide();
				var abadoned_order_count = response;
				var order                = 'order';
				if ( abadoned_order_count > 1 ){
					order 				 = 'orders';
				}
				
				var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
				$( ".wcap_agile_message_p" ).html( display_message );
	            $( "#wcap_agile_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_agile_message" ).fadeOut();
	            },4000);
			});
			e.preventDefault();
		}
	});

	$ ( '#doaction2' ).on( 'click', function( e ) {
		if ( $( '#bulk-action-selector-bottom' ).val() == 'wcap_add_agile' ) {
			
			var checkboxes = document.getElementsByName('abandoned_order_id[]');
			var wcap_selected_id = [];
		  	for (var i = 0; i < checkboxes.length; i++) {
		     
		     	if ( checkboxes[i].checked ) {
		        	wcap_selected_id.push( checkboxes[i].value );
		    	}
		  	}
		  	
		  	$( '#wcap_manual_email_data_loading' ).show();
			var data = {
				action                  : 'wcap_add_to_agile_crm',
				wcap_abandoned_cart_ids : wcap_selected_id,
				wcap_all                : wcap_all
			};
			
			$.post( wcap_agile_params.ajax_url, data, function( response ) {
				$( '#wcap_manual_email_data_loading' ).hide();
				var abadoned_order_count = response;
				var order                = 'order';
				if ( abadoned_order_count > 1 ){
					order 				 = 'orders';
				}
				
				var display_message       = abadoned_order_count  + ' Abandoned ' +  order + ' has been successfully added to Agile CRM.'
				$( ".wcap_agile_message_p" ).html( display_message );
	            $( "#wcap_agile_message" ).fadeIn();
	            setTimeout( function(){
	            	$( "#wcap_agile_message" ).fadeOut();
	            },4000);
			});
			e.preventDefault();
		}
	});
});