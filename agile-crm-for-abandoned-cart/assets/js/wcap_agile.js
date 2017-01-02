jQuery(function( $ ) {

	var wcap_agile_connection_established = '';

	$ ( '#wcap_agile_test' ).on( 'click', function( e ) {
		e.preventDefault();
		var wcap_agile_user_name = $("#wcap_agile_user_name").val();
		var wcap_agile_domain    = $("#wcap_agile_domain").val();

		var wcap_agile_token     = $("#wcap_agile_security_token").val();
        var data = {
            wcap_agile_user_name: wcap_agile_user_name,
            wcap_agile_domain: wcap_agile_domain,
            wcap_agile_token: wcap_agile_token,
            action: 'wcap_agile_check_connection'
        };
        $( '#wcap_agile_test_connection_ajax_loader' ).show();
        $.post( wcap_agile_params.ajax_url, data, function( response ) {
        	wcap_check_string = response.indexOf("successfuly established");
        	if ( wcap_check_string !== -1 ){
        		wcap_agile_connection_established = 'yes';
        	}
    		$( '#wcap_agile_test_connection_message' ).html( response );
	        $( '#wcap_agile_test_connection_ajax_loader' ).hide();
        });
	});

	$ ( '.button-primary' ).on( 'click', function( e ) {
		if ( $(this).val() == 'Save Agile CRM Settings' ) {			
			
			var wcap_agile_user_name = $("#wcap_agile_user_name").val();
			var wcap_agile_domain    = $("#wcap_agile_domain").val();
			var wcap_agile_token     = $("#wcap_agile_security_token").val();
			
			if ( '' == wcap_agile_token ) {
				$( "#wcap_agile_security_token_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_agile_security_token_label_error" ).fadeOut();
		        },4000);
		        e.preventDefault();
			}
			if ( '' == wcap_agile_domain ) {
				$( "#wcap_agile_domain_label_error" ).fadeIn();
	            setTimeout( function() {
		            $( "#wcap_agile_domain_label_error" ).fadeOut();
		        },4000);
			    e.preventDefault();
			}
			if ( '' == wcap_agile_user_name ) {
				$( "#wcap_agile_user_name_label_error" ).fadeIn();
	            setTimeout( function(){
		            $( "#wcap_agile_user_name_label_error" ).fadeOut();
		        },4000);
	            e.preventDefault();
			}

			if ( ( wcap_agile_params.wcap_agile_user_name   != wcap_agile_user_name
				|| wcap_agile_params.wcap_agile_domain_name != wcap_agile_domain 
				|| wcap_agile_params.wcap_agile_api_key     != wcap_agile_token ) &&
				 ( wcap_agile_params.wcap_agile_connection_established != 'yes' || wcap_agile_connection_established != 'yes') )  {
				e.preventDefault();
				var data = {
		            wcap_agile_user_name: wcap_agile_user_name,
		            wcap_agile_domain: wcap_agile_domain,
		            wcap_agile_token: wcap_agile_token,
		            action: 'wcap_agile_check_connection'
		        };
		        $( '#wcap_agile_test_connection_ajax_loader' ).show();
		        $.post( wcap_agile_params.ajax_url, data, function( response ) {
		    		wcap_check_string = response.indexOf("successfuly established");
		    		$( '#wcap_agile_test_connection_ajax_loader' ).hide();
		    		
			    	if ( wcap_check_string !== -1 ){
			    		
			        	$('#wcap_agile_crm_form').submit();
			        }else{
			    		$( '#wcap_agile_test_connection_message' ).html( response );
					}
			    });
			}
		}
	});

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