<?php 
/*
* Class:  Wcap_Abandoned_Cart_Cron_Job_Class 
* 
* This class is responsible for adding the abandoned cart to the agile CRM.
*/

class Wcap_Agile_CRM_Add_Cron_Data {         
    
    /**
     * Function to send emails
     */
    public static function wcap_add_agile_abandoned_cart_data() {
        
        global $wpdb;
        global $woocommerce;

        $wcap_automatic_add_to_agile  = get_option ( 'wcap_add_automatically_to_agile_crm' );

        
        if ( isset( $wcap_automatic_add_to_agile ) && 'on' == $wcap_automatic_add_to_agile ){

            $get_last_agile_add_abandoned_cart_id = Wcap_Agile_CRM_Add_Cron_Data::wcap_get_last_abandoned_cart_id ();
            $get_last_checked_id  = get_option ( 'wcap_agile_last_id_checked' );

            if ( '' === $get_last_checked_id || false === $get_last_checked_id || $get_last_checked_id < $get_last_agile_add_abandoned_cart_id ) {
                $abandoned_cart_ids = array();
                
                $abandoned_cart_ids = Wcap_Agile_CRM_Add_Cron_Data::wcap_get_carts_for_agile ( $get_last_checked_id );
                foreach ( $abandoned_cart_ids as $abandoned_cart_ids_key => $abandoned_cart_ids_value ) {
                
                    Wcap_Agile_CRM_Add_Cron_Data::wcap_add_data_to_agile ( $abandoned_cart_ids_value );
                }
                update_option ( 'wcap_agile_last_id_checked' , $get_last_agile_add_abandoned_cart_id ); 
            }
        }
    }

   public static function wcap_get_last_abandoned_cart_id(){
        
        global $wpdb;
        $last_abandoned_id = 0 ;
        $blank_cart_info         = '{"cart":[]}';
        $blank_cart_info_guest   = '[]';
        $query_records     = "SELECT id FROM `" . $wpdb->prefix . "ac_abandoned_cart_history` WHERE `id` NOT IN ( SELECT abandoned_cart_id FROM `".$wpdb->prefix."wcap_agile_abandoned_cart`) AND recovered_cart = 0 AND `user_id` > 0 AND abandoned_cart_info NOT LIKE '$blank_cart_info_guest' AND abandoned_cart_info NOT LIKE '%$blank_cart_info%' ORDER BY id DESC LIMIT 1";
        $results_list      = $wpdb->get_results( $query_records );

        if ( isset( $results_list ) && count ( $results_list ) > 0 ) {
            $last_abandoned_id = $results_list[0]->id;
        }
        return $last_abandoned_id;
   }

   public static function wcap_get_carts_for_agile ( $get_last_checked_id ){
        global $wpdb;
        global $woocommerce;

        $blank_cart_info         = '{"cart":[]}';
        $blank_cart_info_guest   = '[]';
        if ( '' == $get_last_checked_id ){
            $get_last_checked_id = 0;
        }
        $wcap_get_all_abandoned_carts = "SELECT id FROM `".$wpdb->prefix."ac_abandoned_cart_history` WHERE `id` NOT IN ( SELECT abandoned_cart_id FROM `".$wpdb->prefix."wcap_agile_abandoned_cart`) AND `id` > $get_last_checked_id AND `user_id` > 0 and `recovered_cart` = 0";
        
        $abandoned_cart_results = $wpdb->get_results( $wcap_get_all_abandoned_carts );
        
        foreach ( $abandoned_cart_results as $abandoned_cart_results_key => $abandoned_cart_results_value ) {
            $ids [] = $abandoned_cart_results_value->id;
        }
        return $ids;
    }

    public static function wcap_add_data_to_agile ( $abandoned_cart_ids_key ) {

        global $wpdb;
        global $woocommerce;

        $get_abandoned_cart     = "SELECT * FROM `".$wpdb->prefix."ac_abandoned_cart_history` WHERE id = $abandoned_cart_ids_key";
        $abandoned_cart_results = $wpdb->get_results( $get_abandoned_cart );
        $wcap_user_id           = 0;
        $wcap_contact_email     = '';
        $wcap_user_last_name    = '';
        $wcap_user_first_name   = '';
        $wcap_user_phone        = '';
        $wcap_user_address      = '';
        $wcap_user_city         = '';
        $wcap_user_state        = '';
        $wcap_user_country      = '';                                

        if ( !empty( $abandoned_cart_results ) ) {

            $wcap_user_id = $abandoned_cart_results[0]->user_id;

            if ( $abandoned_cart_results[0]->user_type == "GUEST" && $abandoned_cart_results[0]->user_id != '0' ) {
                $query_guest         = "SELECT billing_first_name, billing_last_name, email_id, phone FROM `" . $wpdb->prefix . "ac_guest_abandoned_cart_history` WHERE id = %d";
                $results_guest       = $wpdb->get_results( $wpdb->prepare( $query_guest, $wcap_user_id ) );
                
                if ( count ($results_guest) > 0 ){
                    $wcap_contact_email   = $results_guest[0]->email_id;
                    $wcap_user_first_name = $results_guest[0]->billing_first_name;
                    $wcap_user_last_name  = $results_guest[0]->billing_last_name;
                    $wcap_user_phone      = $results_guest[0]->phone;
                }       
            } else {                   
                
               $wcap_contact_email = get_user_meta( $wcap_user_id, 'billing_email', true );
                    
                if( $wcap_contact_email == "") {  
                    $user_data = get_userdata( $wcap_user_id ); 
                    $wcap_contact_email = $user_data->user_email;   
                }
                
                $user_first_name_temp = get_user_meta( $wcap_user_id, 'billing_first_name', true );
                if( isset( $user_first_name_temp ) && "" == $user_first_name_temp ) {
                    $user_data  = get_userdata( $wcap_user_id );
                    $wcap_user_first_name = $user_data->first_name;
                } else {
                    $wcap_user_first_name = $user_first_name_temp;
                }
                                
                $user_last_name_temp = get_user_meta( $wcap_user_id, 'billing_last_name', true );
                if( isset( $user_last_name_temp ) && "" == $user_last_name_temp ) {
                    $user_data  = get_userdata( $wcap_user_id );
                    $wcap_user_last_name = $user_data->last_name;
                } else {
                    $wcap_user_last_name = $user_last_name_temp;
                }

                $user_billing_phone_temp = get_user_meta( $wcap_user_id, 'billing_phone' );
                
                if ( isset( $user_billing_phone_temp[0] ) ){
                    $wcap_user_phone = $user_billing_phone_temp[0];
                }

                $user_billing_address_1_temp = get_user_meta( $wcap_user_id, 'billing_address_1' );
                $user_billing_address_1 = "";
                if ( isset( $user_billing_address_1_temp[0] ) ) {
                    $user_billing_address_1 = $user_billing_address_1_temp[0];
                }
                
                $user_billing_address_2_temp = get_user_meta( $wcap_user_id, 'billing_address_2' );
                $user_billing_address_2 = "";
                if ( isset( $user_billing_address_2_temp[0] ) ) {
                    $user_billing_address_2 = $user_billing_address_2_temp[0];
                }
                $wcap_user_address = $user_billing_address_1 . $user_billing_address_2;

                $user_billing_city_temp = get_user_meta( $wcap_user_id, 'billing_city' );
                
                if ( isset( $user_billing_city_temp[0] ) ) {
                    $wcap_user_city = $user_billing_city_temp[0];
                }

                $user_billing_country_temp = get_user_meta( $wcap_user_id, 'billing_country' );
                
                if ( isset( $user_billing_country_temp[0] ) ){
                    $user_billing_country = $user_billing_country_temp[0];
                    $wcap_user_country = $woocommerce->countries->countries[ $user_billing_country ];
                }

                $user_billing_state_temp = get_user_meta( $wcap_user_id, 'billing_state' );
                if ( isset( $user_billing_state_temp[0] ) ){
                    $user_billing_state = $user_billing_state_temp[0];
                    $wcap_user_state = $woocommerce->countries->states[ $user_billing_country_temp[0] ][ $user_billing_state ];
                }
            }

            $address = array(
              "address" => $wcap_user_address,
              "city"    => $wcap_user_city,
              "state"   => $wcap_user_state,
              "country" => $wcap_user_country
            );

            $wcap_contact_json = array(
              "tags"=> array("Abandoned Cart"),
              "properties"=>array(
                    array(
                      "name"=>  "first_name",
                      "value"=> $wcap_user_first_name,
                      "type"=>  "SYSTEM"
                    ),
                    array(
                      "name"=>  "last_name",
                      "value"=> $wcap_user_last_name,
                      "type"=>  "SYSTEM"
                    ),
                    array(
                      "name"=>  "email",
                      "value"=> $wcap_contact_email,
                      "type"=>  "SYSTEM"
                    ),
                    array(
                        "name"=>  "address",
                        "value"=> json_encode( $address ),
                        "type"=>  "SYSTEM"
                    ),
                    array(
                        "name"=>  "phone",
                        "value"=> $wcap_user_phone,
                        "type"=>  "SYSTEM"
                    ) 
                )
            );

            $wcap_contact_json  = json_encode( $wcap_contact_json );

            $cart_info_db_field = json_decode( $abandoned_cart_results[0]->abandoned_cart_info );

            if( !empty( $cart_info_db_field ) ) {
                $cart_details           = $cart_info_db_field->cart;
            }
            $product_name = '';
            $wcap_product_details = '';
            foreach( $cart_details as $cart_details_key => $cart_details_value ) {
                $quantity_total = $cart_details_value->quantity;
                $product_id     = $cart_details_value->product_id;
                $prod_name      = get_post( $product_id );
                $product_name   = $prod_name->post_title;
                if( isset( $cart_details_value->variation_id ) && '' != $cart_details_value->variation_id ){
                    $variation_id               = $cart_details_value->variation_id;
                    $variation                  = wc_get_product( $variation_id );
                    $name                       = $variation->get_formatted_name() ;
                    $explode_all                = explode( "&ndash;", $name );
                    $pro_name_variation         = array_slice( $explode_all, 1, -1 );
                    $product_name_with_variable = '';
                    $explode_many_varaition     = array();
                
                    foreach ( $pro_name_variation as $pro_name_variation_key => $pro_name_variation_value ){
                        $explode_many_varaition = explode ( ",", $pro_name_variation_value );
                        if ( !empty( $explode_many_varaition ) ) {
                            foreach( $explode_many_varaition as $explode_many_varaition_key => $explode_many_varaition_value ){
                                $product_name_with_variable = $product_name_with_variable . "\n". html_entity_decode ( $explode_many_varaition_value );
                            }
                        } else {
                            $product_name_with_variable = $product_name_with_variable . "\n". html_entity_decode ( $explode_many_varaition_value );
                        }
                    }
                    $product_name = $product_name_with_variable;
                }

               $wcap_product_details = html_entity_decode ( $wcap_product_details . "Product Name: " . $product_name . " , Quantity: " . $quantity_total ) . "\n";
            }
            $wcap_posted_result = Wcap_Add_To_Agile_CRM::wcap_add_data_to_agile_crm ( "contacts", $wcap_contact_json, "POST", "application/json" );
            
            /*
                If any user is existing then we just need to add the note of the abandoned cart.
                It will be the error message when any user is existing.
            */
            if ( 'Sorry, duplicate contact found with the same email address.' == $wcap_posted_result ) {
                /*
                    Any existing user update its note
                    1. Get the user id from his email address.
                    2. from user id, add the note 
                */
                $wcap_get_user_result = Wcap_Add_To_Agile_CRM::wcap_add_data_to_agile_crm("contacts/search/email/$wcap_contact_email", null, "GET", "application/json");

                $created_customer_result = json_decode( $wcap_get_user_result, true );

                $created_customer_id     = $created_customer_result[ 'id' ];

                /*
                    Here, we have the user id.
                    Add the note for the user id. We will add the product name in the notes, with the selected quanitity
                */

                $wcap_note_json = array(
                  "subject"=>     "Abandoned Cart Details",
                  "description"=> $wcap_product_details,
                  "contact_ids"=> array( $created_customer_id ),
                );

                $wcap_note_json    = json_encode( $wcap_note_json );
                $wcap_contact_note = Wcap_Add_To_Agile_CRM::wcap_add_data_to_agile_crm ( "notes", $wcap_note_json, "POST", "application/json" );

            } else if ( ! empty( $wcap_posted_result ) ) {

                $created_customer_result = json_decode( $wcap_posted_result, true );

                $created_customer_id     = $created_customer_result[ 'id' ];

                /*
                    Here, we have the user id.
                    Add the note for the user id. We will add the product name in the notes, with the selected quanitity
                */

                $wcap_note_json = array(
                  "subject"=>     "Abandoned Cart Details",
                  "description"=> $wcap_product_details,
                  "contact_ids"=> array( $created_customer_id ),
                );

                $wcap_note_json    = json_encode( $wcap_note_json );
                $wcap_contact_note = Wcap_Add_To_Agile_CRM::wcap_add_data_to_agile_crm ( "notes", $wcap_note_json, "POST", "application/json" );
            }
        }
        
        $wcap_insert_abandoned_id = "INSERT INTO `" . $wpdb->prefix . "wcap_agile_abandoned_cart` ( abandoned_cart_id, date_time )
                                  VALUES ( '" . $abandoned_cart_ids_key . "', '" . current_time( 'mysql' ) . "' )";      
        $wpdb->query( $wcap_insert_abandoned_id );
    }
   
}
?>