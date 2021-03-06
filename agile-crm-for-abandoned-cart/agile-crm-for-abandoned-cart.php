<?php
/*
Plugin Name: Agile CRM Sync for WooCommerce Abandoned Cart Plugin
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-abandoned-cart-pro
Description: This plugin allow you to export the abandoned cart data to your Agile CRM.
Version: 1.0
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/

global $AgileCRMpdateChecker;
$AgileCRMpdateChecker = '1.0';

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'EDD_SL_STORE_URL_AGILE_WOO', 'http://www.tychesoftwares.com/' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

// the name of your product. This is the title of your product in EDD and should match the download title in EDD exactly
define( 'EDD_SL_ITEM_NAME_AGILE_WOO', 'Agile CRM Sync for WooCommerce Abandoned Cart Plugin' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

if( ! class_exists( 'EDD_AGILE_WOO_Plugin_Updater' ) ) {
    // load our custom updater if it doesn't already exist
    include( dirname( __FILE__ ) . '/plugin-updates/EDD_AGILE_WOO_Plugin_Updater.php' );
}

// retrieve our license key from the DB
$license_key = trim( get_option( 'edd_sample_license_key_agile_woo' ) );
// setup the updater
$edd_updater = new EDD_AGILE_WOO_Plugin_Updater( EDD_SL_STORE_URL_AGILE_WOO, __FILE__, array(
        'version'   => '1.0',                     // current version number
        'license'   => $license_key,                // license key (used get_option above to retrieve from DB)
        'item_name' => EDD_SL_ITEM_NAME_AGILE_WOO,     // name of this plugin
        'author'    => 'Ashok Rane'                 // author of this plugin
        )
);


require_once ( "cron/wcap_agile_add_abandoned_data.php" );
require_once ( "includes/class_add_to_agile_crm.php" );

// Add a new interval of 1 Day
add_filter( 'cron_schedules', 'wcap_agile_add_data_schedule' );

function wcap_agile_add_data_schedule( $schedules ) {

    $hour_seconds     = 3600; // 60 * 60
    $day_seconds      = 86400; // 24 * 60 * 60
    
    $duration         = get_option( 'wcap_add_automatically_add_after_email_frequency' );
    $wcap_day_or_hour = get_option( 'wcap_add_automatically_add_after_time_day_or_hour' );
    
    if ( $wcap_day_or_hour == 'Days' ) {
        $duration_in_seconds = $duration * $day_seconds;
    } elseif ( $wcap_day_or_hour == 'Hours' ) {
        $duration_in_seconds = $duration * $hour_seconds;
    } else {
        $duration_in_seconds = $day_seconds;
    }

    $schedules['1_day'] = array(
                'interval' => $duration_in_seconds,  
                'display'  => __( 'Once in a day.' ),
    );
    return $schedules;
}
// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'wcap_agile_add_abandoned_data_schedule' ) ) {
    wp_schedule_event( time(), '1_day', 'wcap_agile_add_abandoned_data_schedule' );
}

register_uninstall_hook( __FILE__, 'wcap_agile_crm_uninstall' );

function wcap_agile_crm_uninstall (){
    global $wpdb;
    
    $wcap_agile_table_name = $wpdb->prefix . "wcap_agile_abandoned_cart";
    $sql_wcap_agile_table_name = "DROP TABLE " . $wcap_agile_table_name ;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $wpdb->get_results( $sql_wcap_agile_table_name );

    delete_option( 'wcap_enable_agile_crm' );
    delete_option( 'wcap_add_automatically_to_agile_crm' );
    delete_option( 'wcap_add_automatically_add_after_email_frequency' );
    delete_option( 'wcap_add_automatically_add_after_time_day_or_hour' );
    delete_option( 'wcap_agile_last_id_checked' );

    delete_option( 'wcap_agile_domain ');
    delete_option( 'wcap_agile_user_name ');
    delete_option( 'wcap_agile_security_token ');
    delete_option ( 'wcap_agile_connection_established' );
    
    wp_clear_scheduled_hook( 'wcap_agile_add_abandoned_data_schedule' );
}

if ( ! class_exists( 'Wcap_Agile_CRM' ) ) {

    class Wcap_Agile_CRM {

        public function __construct( ) {
            register_activation_hook( __FILE__,                    array( &$this, 'wcap_agile_crm_create_table' ) );
            add_action( 'admin_init',                              array( &$this, 'wcap_agile_crm_check_compatibility' ) );
            if ( ! has_action ('wcap_add_tabs' ) ){
                add_action ( 'wcap_add_tabs',                          array( &$this, 'wcap_agile_crm_add_tab' ));
            }
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'wcap_agile_plugin_action_links' ) );
            add_action ( 'admin_init',                             array( &$this, 'wcap_agile_crm_initialize_settings_options' ) );
            add_action ( 'wcap_display_message',                   array( &$this, 'wcap_agile_crm_display_message' ) );
            add_action ( 'wcap_crm_data',                          array( &$this, 'wcap_agile_crm_display_data' ) );
            add_action ( 'wcap_add_buttons_on_abandoned_orders',   array( &$this, 'wcap_add_export_all_data_to_agile_crm' ) );
            add_filter ( 'wcap_abandoned_orders_single_column' ,   array( &$this, 'wcap_add_individual_record_to_agile_crm' ), 10 , 2 );
            add_filter ( 'wcap_abandoned_order_add_bulk_action',   array( &$this, 'wcap_add_bulk_record_to_agile_crm' ), 10 , 1 );
            add_action ( 'wp_ajax_wcap_add_to_agile_crm',          array( &$this, 'wcap_add_to_agile_crm_callback' ));
            add_action ( 'admin_enqueue_scripts',                  array( &$this, 'wcap_agile_enqueue_scripts_js' ) );
            add_action ( 'admin_enqueue_scripts',                  array( &$this, 'wcap_agile_enqueue_scripts_css' ) );
            add_action ( 'wcap_agile_add_abandoned_data_schedule', array( 'Wcap_Agile_CRM_Add_Cron_Data', 'wcap_add_agile_abandoned_cart_data' ) );
            /*
             * When cron job time changed this function will be called.
             * It is used to reset the cron time again.
             */
            add_action ( 'update_option_wcap_add_automatically_add_after_email_frequency',  array( &$this,'wcap_agile_reset_cron_time_duration' ) );
            add_action ( 'update_option_wcap_add_automatically_add_after_time_day_or_hour', array( &$this,'wcap_agile_reset_cron_time_duration' ) );

            /*
            Test Connection for saved settings
            */
            add_action ( 'wp_ajax_wcap_agile_check_connection',                             array( &$this, 'wcap_agile_check_connection_callback' ));

            /* License */

            add_action( 'admin_init',                                                  		array( &$this, 'wcap_agile_edd_ac_register_option' ) );
            add_action( 'admin_init',                                                  		array( &$this, 'wcap_agile_edd_ac_deactivate_license' ) );
            add_action( 'admin_init',                                                  		array( &$this, 'wcap_agile_edd_ac_activate_license' ) );

        }

        /**
         * Check if Abandoned cart is active or not.
         */
        public static function wcap_agile_crm_check_ac_installed() {
        
            if ( class_exists( 'woocommerce_abandon_cart' ) ) {
                return true;
            } else {
                return false;
            }
        }
            
        /**
         * Ensure that the Agile crm addon is deactivated when Abandoned cart 
         * is deactivated.
         */
        public static function wcap_agile_crm_check_compatibility() {
                
            if ( ! self::wcap_agile_crm_check_ac_installed() ) {
                    
                if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
                    deactivate_plugins( plugin_basename( __FILE__ ) );
                        
                    add_action( 'admin_notices', array( 'Wcap_Agile_CRM', 'wcap_agile_crm_disabled_notice' ) );
                    if ( isset( $_GET['activate'] ) ) {
                        unset( $_GET['activate'] );
                    }
                        
                }
                    
            }
        }
        /**
         * Display a notice in the admin Plugins page if the Agile crm addon is
         * activated while Abandoned cart is deactivated.
         */
        public static function wcap_agile_crm_disabled_notice() {
                
            $class = 'notice notice-error is-dismissible';
            $message = __( 'Agile CRM Addon for Abandoned Cart Pro for WooCommerce requires Abandoned Cart Pro for WooCommerce installed and activate.', 'woocommerce-ac' );
                
            printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
        }

        function wcap_agile_edd_ac_activate_license() {               
            // listen for our activate button to be clicked
            if ( isset( $_POST['edd_agile_license_activate'] ) ) {        
                // run a quick security check
                if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
                    return; // get out if we didn't click the Activate button        
                // retrieve the license from the database
                $license = trim( get_option( 'edd_sample_license_key_agile_woo' ) );                               
                // data to send in our API request
                $api_params = array(
                        'edd_action'=> 'activate_license',
                        'license'   => $license,
                        'item_name' => urlencode( EDD_SL_ITEM_NAME_AGILE_WOO ) // the name of our product in EDD
                );         
                // Call the custom API.
                $response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_AGILE_WOO ), array( 'timeout' => 15, 'sslverify' => false ) );        
                // make sure the response came back okay
                if ( is_wp_error( $response ) )
                    return false;        
                // decode the license data
                $license_data = json_decode( wp_remote_retrieve_body( $response ) );        
                // $license_data->license will be either "active" or "inactive"        
                update_option( 'edd_sample_license_status_agile_woo', $license_data->license );           
            }
        }
                    
        /***********************************************
         * Illustrates how to deactivate a license key.
         * This will descrease the site count
         ***********************************************/           
        function wcap_agile_edd_ac_deactivate_license() {               
            // listen for our activate button to be clicked
            if ( isset( $_POST['edd_agile_license_deactivate'] ) ) {        
                // run a quick security check
                if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
                    return; // get out if we didn't click the Activate button        
                // retrieve the license from the database
                $license = trim( get_option( 'edd_sample_license_key_agile_woo' ) );                              
                // data to send in our API request
                $api_params = array(
                        'edd_action'=> 'deactivate_license',
                        'license'   => $license,
                        'item_name' => urlencode( EDD_SL_ITEM_NAME_AGILE_WOO ) // the name of our product in EDD
                );        
                // Call the custom API.
                $response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_AGILE_WOO ), array( 'timeout' => 15, 'sslverify' => false ) );        
                // make sure the response came back okay
                if ( is_wp_error( $response ) )
                    return false;        
                // decode the license data
                $license_data = json_decode( wp_remote_retrieve_body( $response ) );        
                // $license_data->license will be either "deactivated" or "failed"
                if ( $license_data->license == 'deactivated' )
                    delete_option( 'edd_sample_license_status_agile_woo' );
            }
        }
                  
        /************************************
         * this illustrates how to check if
         * a license key is still valid
         * the updater does this for you,
         * so this is only needed if you
         * want to do something custom
        *************************************/           
        function edd_sample_check_license() {                
            global $wp_version;
            $license = trim( get_option( 'edd_sample_license_key_agile_woo' ) );
            $api_params = array(
                    'edd_action' => 'check_license',
                    'license'    => $license,
                    'item_name'  => urlencode( EDD_SL_ITEM_NAME_AGILE_WOO )
            );                
            // Call the custom API.
            $response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_AGILE_WOO ), array( 'timeout' => 15, 'sslverify' => false ) );               
            if ( is_wp_error( $response ) )
                return false;               
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );              
            if ( $license_data->license == 'valid' ) {
                echo 'valid'; 
                exit;
                // this license is still valid
            } else {
                echo 'invalid'; 
                exit;
                // this license is no longer valid
            }
        }
        
        function wcap_agile_edd_ac_register_option() {
            // creates our settings in the options table
            register_setting( 'edd_sample_license', 'edd_sample_license_key_agile_woo', array( &$this, 'wcap_agile_edd_sanitize_license' ) );
        }
         
        function wcap_agile_edd_sanitize_license( $new ) {
            $old = get_option( 'edd_sample_license_key_agile_woo' );
            if ( $old && $old != $new ) {
                delete_option( 'edd_sample_license_key_agile_woo' ); // new license has been entered, so must reactivate
            }
            return $new;
        }

        function wcap_agile_check_connection_callback(){

            $wcap_domain   = $_POST['wcap_agile_domain'];
            $wcap_email    = $_POST['wcap_agile_user_name'];
            $wcap_rest_api = $_POST['wcap_agile_token'];

            $content_type  = "application/json";
            $entity        = "api-key";
            $agile_url     = "https://" . $wcap_domain . ".agilecrm.com/dev/api/" . $entity;
            
            $url = $agile_url;
            $ch  = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $wcap_email . ':' . $wcap_rest_api);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-type : $content_type;", 'Accept : application/json'
            ));
            $curlresult = curl_exec ($ch);
            curl_close ($ch);
            if ( preg_match( "/api_key/i", $curlresult ) ) {
                $result = "The Agile CRM connection successfuly established!";
                update_option ( 'wcap_agile_connection_established', 'yes' );
            } else {
                $result = "The Agile CRM connection has FAILED! Please check your credentials!";
                update_option ( 'wcap_agile_connection_established', 'no' );
            }
            echo $result;
            wp_die();
        }

        function wcap_agile_crm_create_table() {
            global $wpdb;
            $wcap_collate = '';
            if ( $wpdb->has_cap( 'collation' ) ) {
                $wcap_collate = $wpdb->get_charset_collate();
            }
            $table_name = $wpdb->prefix . "wcap_agile_abandoned_cart";

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `abandoned_cart_id` int(11) COLLATE utf8_unicode_ci NOT NULL,
                    `date_time` TIMESTAMP on update CURRENT_TIMESTAMP COLLATE utf8_unicode_ci NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                    ) $wcap_collate AUTO_INCREMENT=1 ";           
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            $wpdb->query( $sql );        
        }

        /**
         * Show action links on the plugin screen.
         *
         * @param   mixed $links Plugin Action links
         * @return  array
         */
        
        public static function wcap_agile_plugin_action_links( $links ) {
            $action_links = array(
                'settings' => '<a href="' . admin_url( 'admin.php?page=woocommerce_ac_page&action=wcap_crm' ) . '" title="' . esc_attr( __( 'View Agile CRM Settings', 'woocommerce-ac' ) ) . '">' . __( 'Settings', 'woocommerce-ac' ) . '</a>',
            );
            return array_merge( $action_links, $links );
        }

        function wcap_agile_enqueue_scripts_js( $hook ) {
            if ( $hook != 'woocommerce_page_woocommerce_ac_page' ) {
                return;
            } else {
                wp_register_script( 'wcap-agile', plugins_url()  . '/agile-crm-for-abandoned-cart/assets/js/wcap_agile.js', array( 'jquery' ) );
                wp_enqueue_script ( 'wcap-agile' );
                $wcap_agile_connection_established = get_option ('wcap_agile_connection_established');
                $wcap_agile_domain                 = get_option( 'wcap_agile_domain ');
                $wcap_agile_user_name              = get_option( 'wcap_agile_user_name ');
                $wcap_agile_security_token         = get_option( 'wcap_agile_security_token ');
                wp_localize_script( 'wcap-agile', 'wcap_agile_params', array(
                                    'ajax_url'                          => admin_url( 'admin-ajax.php' ),
                                    'wcap_agile_user_name'              => $wcap_agile_user_name,
                                    'wcap_agile_domain_name'            => $wcap_agile_domain,
                                    'wcap_agile_api_key'                => $wcap_agile_security_token,
                                    'wcap_agile_connection_established' => $wcap_agile_connection_established
                                    
                ) );
            }
        }

        function wcap_agile_enqueue_scripts_css( $hook ) {
            
            if ( $hook != 'woocommerce_page_woocommerce_ac_page' ) {
                return;
            } else {
                wp_enqueue_style( 'wcap-agile',  plugins_url() . '/agile-crm-for-abandoned-cart/assets/css/wcap_agile_style.css' );
            }
        }

        function wcap_agile_reset_cron_time_duration (){
            wp_clear_scheduled_hook( 'wcap_agile_add_abandoned_data_schedule' );
        }

        function wcap_agile_crm_add_tab () {

            $wcap_action           = "";
            if ( isset( $_GET['action'] ) ) {
                $wcap_action = $_GET['action'];
            }

            $wcap_agile_crm_active = "";
            if (  'wcap_crm' == $wcap_action ) {
                $wcap_agile_crm_active = "nav-tab-active";
            }
            ?>
            <a href="admin.php?page=woocommerce_ac_page&action=wcap_crm" class="nav-tab <?php if( isset( $wcap_agile_crm_active ) ) echo $wcap_agile_crm_active; ?>"> <?php _e( 'Addon Settings', 'woocommerce-ac' );?> </a>
            <?php
        }

        function wcap_agile_crm_display_data () {
            ?>
            <div id="wcap_manual_email_data_loading" >
                <img  id="wcap_manual_email_data_loading_image" src="<?php echo plugins_url(); ?>/woocommerce-abandon-cart-pro/assets/images/loading.gif" alt="Loading...">
            </div>
            <div id = "wcap_manual_email_data_loading_text_agile" > Please wait while we are exporting Abandoned Cart Data to Agile CRM.</div>
            <?php
            /*
                When we use the bulk action it will allot the action and mode.
            */
            $wcap_action = "";
            /*
            When we click on the hover link it will take the action.
            */

            if ( '' == $wcap_action && isset( $_GET['action'] )) { 
                $wcap_action = $_GET['action'];
            }

            /*
             *  It will add the settings in the New tab.
             */
            if ( 'wcap_crm' == $wcap_action ){
                ?>
                <p><?php _e( 'Change settings for exporting the abandoned cart data to the Agile CRM.', 'woocommerce-ac' ); ?></p>

                <form method="post" action="options.php" id="wcap_agile_crm_form">
                    <?php settings_fields     ( 'wcap_agile_crm_setting' ); ?>
                    <?php do_settings_sections( 'wcap_agile_crm_section' ); ?>
                    <?php submit_button( 'Save Agile CRM Settings', 'primary', 'wcap-save-agile-settings' ); ?>
                </form>
                <?php
            }
        }

        function wcap_agile_crm_initialize_settings_options () {

            // First, we register a section. This is necessary since all future options must belong to a
            add_settings_section(
                'wcap_agile_crm_general_settings_section',         // ID used to identify this section and with which to register options
                __( 'Agile CRM Settings', 'woocommerce-ac' ),                  // Title to be displayed on the administration page
                array($this, 'wcap_agile_crm_general_settings_section_callback' ), // Callback used to render the description of the section
                'wcap_agile_crm_section'     // Page on which to add this section of options
            );
             
            add_settings_field(
                'wcap_enable_agile_crm',
                __( 'Export abandoned cart data to Agile CRM', 'woocommerce-ac' ),
                array( $this, 'wcap_enable_agile_crm_callback' ),
                'wcap_agile_crm_section',
                'wcap_agile_crm_general_settings_section',
                array( __( 'Enable to export the abandoned carts data to the Agile CRM.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_add_automatically_to_agile_crm',
                __( 'Automatically add abandoned cart data to Agile CRM', 'woocommerce-ac' ),
                array( $this, 'wcap_add_automatically_to_agile_crm_callback' ),
                'wcap_agile_crm_section',
                'wcap_agile_crm_general_settings_section',
                array( __( 'When any abandoned cart record is captured on the Abandoned Orders tab, it will be automatically exported to the Agile CRM after the set time.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                '',
                __( 'Automatically add abandoned cart data to Agile CRM after set time', 'woocommerce-ac' ),
                array( $this, 'wcap_add_automatically_add_after_time_callback' ),
                'wcap_agile_crm_section',
                'wcap_agile_crm_general_settings_section',
                array( __( 'Set the time after which the abandoned records will be exported automatically to the Agile CRM.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_agile_user_name',
                __( 'Agile CRM Username', 'woocommerce-ac' ),
                array( $this, 'wcap_agile_user_name_callback' ),
                'wcap_agile_crm_section',
                'wcap_agile_crm_general_settings_section',
                array( __( 'Please provide your Agile CRM username.', 'woocommerce-ac' ) )
            );
            
            add_settings_field(
                'wcap_agile_domain',
                __( 'Agile CRM Domain', 'woocommerce-ac' ),
                array( $this, 'wcap_agile_domain_callback' ),
                'wcap_agile_crm_section',
                'wcap_agile_crm_general_settings_section',
                array( __( ".agilecrm.com. Please provide your Agile CRM domain name. <br/>The domain is which you have given while creating the agile CRM account.", 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_agile_security_token',
                __( 'Agile CRM REST API Key', 'woocommerce-ac' ),
                array( $this, 'wcap_agile_security_token_callback' ),
                'wcap_agile_crm_section',
                'wcap_agile_crm_general_settings_section',
                array( __( 'Please provide your Agile CRM REST API key. <br/>Kindly, login to your Agile CRM account. On the top right of your Agile CRM dashboard page, click on your profile, then click on Admin Settings. On this page, click on API. Within the API, you will see your REST API key.', 'woocommerce-ac' ) )
            );

            add_settings_field(
               'wcap_agile_test_connection',
               '',
               array( $this, 'wcap_agile_test_connection_callback' ),
               'wcap_agile_crm_section',
               'wcap_agile_crm_general_settings_section'
            );
            
            /******************************************/
            
            //Setting section and field for license options
            add_settings_section(
            'agile_general_license_key_section',
            __( 'Plugin License Options', 'woocommerce-ac' ),
            array( $this, 'wcap_agile_general_license_key_section_callback' ),
            'wcap_agile_crm_section'
                );
            
            add_settings_field(
            'edd_sample_license_key_agile_woo',
            __( 'License Key', 'woocommerce-ac' ),
            array( $this, 'wcap_edd_sample_license_key_agile_woo_callback' ),
            'wcap_agile_crm_section',
            'agile_general_license_key_section',
            array( __( 'Enter your license key.', 'woocommerce-ac' ) )
            );
             
            add_settings_field(
            'activate_license_key_ac_woo',
            __( 'Activate License', 'woocommerce-ac' ),
            array( $this, 'wcap_activate_license_key_agile_woo_callback' ),
            'wcap_agile_crm_section',
            'agile_general_license_key_section',
            array( __( 'Enter your license key.', 'woocommerce-ac' ) )
            );

            // Finally, we register the fields with WordPress
            register_setting(
                'wcap_agile_crm_setting',
                'wcap_enable_agile_crm'
            );
            register_setting(
                'wcap_agile_crm_setting',
                'wcap_add_automatically_to_agile_crm'
            );
            register_setting(
                'wcap_agile_crm_setting',
                'wcap_add_automatically_add_after_email_frequency'
            );
            register_setting(
                'wcap_agile_crm_setting',
                'wcap_add_automatically_add_after_time_day_or_hour'
            );

            register_setting(
                'wcap_agile_crm_setting',
                'wcap_agile_user_name'
            );
            register_setting(
                'wcap_agile_crm_setting',
                'wcap_agile_domain'
            );
            register_setting(
                'wcap_agile_crm_setting',
                'wcap_agile_security_token'
            );
            
            register_setting(
                'wcap_agile_crm_setting',
                'edd_sample_license_key_agile_woo'
            );
        }

        /***************************************************************
         * WP Settings API callback for section
        **************************************************************/
        function wcap_agile_crm_general_settings_section_callback() {
             
        }

        /***************************************************************
         * WP Settings API callback for enable exporting the abandoned data to agile crm
        **************************************************************/
        function wcap_enable_agile_crm_callback( $args ) {
            // First, we read the option
            $wcap_enable_agile_crm = get_option( 'wcap_enable_agile_crm' );
            // This condition added to avoid the notie displyed while Check box is unchecked.
            if (isset( $wcap_enable_agile_crm ) &&  $wcap_enable_agile_crm == "") {
                $wcap_enable_agile_crm = 'off';
            }
        
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function 
            $html = '<input type="checkbox" id="wcap_enable_agile_crm" name="wcap_enable_agile_crm" value="on" ' . checked( 'on', $wcap_enable_agile_crm, false ) . '/>';
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html .= '<label for="wcap_enable_agile_crm_lable"> '  . $args[0] . '</label>';
            echo $html;
        }
        /***************************************************************
         * WP Settings API callback for automatically exporting the abandoned data to agile crm
        **************************************************************/
        function wcap_add_automatically_to_agile_crm_callback( $args ) {
            // First, we read the option
            $wcap_add_automatically_to_agile_crm = get_option( 'wcap_add_automatically_to_agile_crm' );
            // This condition added to avoid the notie displyed while Check box is unchecked.
            if (isset( $wcap_add_automatically_to_agile_crm ) &&  $wcap_add_automatically_to_agile_crm == "") {
                $wcap_add_automatically_to_agile_crm = 'off';
            }
        
            $html  = '<input type="checkbox" id="wcap_add_automatically_to_agile_crm" name="wcap_add_automatically_to_agile_crm" value="on" ' . checked( 'on', $wcap_add_automatically_to_agile_crm, false ) . '/>';
            $html .= '<label for="wcap_add_automatically_to_agile_crm_lable"> '  . $args[0] . '</label>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for automatically exporting the abandoned data to agile crm
        **************************************************************/
        function wcap_add_automatically_add_after_time_callback( $args ) {
            // First, we read the option
            $wcap_add_automatically_add_after_time = get_option( 'wcap_add_automatically_add_after_time' );
            // This condition added to avoid the notie displyed while Check box is unchecked.
            if (isset( $wcap_add_automatically_add_after_time ) &&  $wcap_add_automatically_add_after_time == "") {
                $wcap_add_automatically_add_after_time = 'off';
            }
            ?>
            <select name="wcap_add_automatically_add_after_email_frequency" id="wcap_add_automatically_add_after_email_frequency">
            <?php
            $frequency_edit = '';
            $frequency_edit = get_option( 'wcap_add_automatically_add_after_email_frequency' );
            for ( $i=1;$i<60;$i++ ) {
                printf( "<option %s value='%s'>%s</option>\n",
                    selected( $i, $frequency_edit, false ),
                    esc_attr( $i ),
                    $i
                );
            }
            ?>
            </select>
            <select name="wcap_add_automatically_add_after_time_day_or_hour" id="wcap_add_automatically_add_after_time_day_or_hour">
                <?php
                    
                    $days_or_hours_edit = get_option( 'wcap_add_automatically_add_after_time_day_or_hour' );
                    $days_or_hours = array(
                       'Days'      => 'Day(s)',
                       'Hours'     => 'Hour(s)'
                    );

                    foreach( $days_or_hours as $k => $v ) {
                        printf( "<option %s value='%s'>%s</option>\n",
                            selected( $k, $days_or_hours_edit, false ),
                            esc_attr( $k ),
                            $v
                        );
                    }
                ?>
                </select>
            <?
            $html = '<label for="wcap_add_automatically_add_after_time_lable"> '  . $args[0] . '</label>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for Agie crm user name
        **************************************************************/
        function wcap_agile_user_name_callback($args) {
            
            // First, we read the option
            $wcap_agile_user_name = get_option( 'wcap_agile_user_name' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_agile_user_name" name="wcap_agile_user_name" value="%s" />',
                isset( $wcap_agile_user_name ) ? esc_attr( $wcap_agile_user_name ) : ''
            );
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_agile_user_name_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_agile_user_name_label_error" > Please enter your Agile CRM username. </span>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for agile crm password
        **************************************************************/
        function wcap_agile_domain_callback($args) {            
            // First, we read the option
            $wcap_agile_domain = get_option( 'wcap_agile_domain' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_agile_domain" name="wcap_agile_domain" value="%s" />',
                isset( $wcap_agile_domain ) ? esc_attr( $wcap_agile_domain ) : ''
            );            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_agile_domain_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_agile_domain_label_error" > Please enter your Agile CRM Domain. </span>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce REST API
        **************************************************************/
        function wcap_agile_security_token_callback($args) {           
            // First, we read the option
            $wcap_agile_security_token = get_option( 'wcap_agile_security_token' );            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_agile_security_token" name="wcap_agile_security_token" value="%s" />',
                isset( $wcap_agile_security_token ) ? esc_attr( $wcap_agile_security_token ) : ''
            );            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_agile_security_token_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_agile_security_token_label_error"> Please enter your Agile CRM REST API key. </span>';
            echo $html;
        }
        
        /***************************************************************
         * WP Settings API callback for License plugin option
         **************************************************************/
        function wcap_agile_general_license_key_section_callback(){
        
        }
        
        /***************************************************************
         * WP Settings API callback for License key
         **************************************************************/
        function wcap_edd_sample_license_key_agile_woo_callback( $args ){
            $edd_sample_license_key_ac_woo_field = get_option( 'edd_sample_license_key_agile_woo' );
            printf(
            '<input type="text" id="edd_sample_license_key_agile_woo" name="edd_sample_license_key_agile_woo" class="regular-text" value="%s" />',
            isset( $edd_sample_license_key_ac_woo_field ) ? esc_attr( $edd_sample_license_key_ac_woo_field ) : ''
                );
                // Here, we'll take the first argument of the array and add it to a label next to the checkbox
                $html = '<label for="edd_sample_license_key_agile_woo"> '  . $args[0] . '</label>';
                echo $html;
        }
        /***************************************************************
         * WP Settings API callback for to Activate License key
         **************************************************************/
        function wcap_activate_license_key_agile_woo_callback() {
            
            $license = get_option( 'edd_sample_license_key_agile_woo' );
            $status  = get_option( 'edd_sample_license_status_agile_woo' );
            ?>
            <form method="post" action="options.php">
            <?php if ( false !== $license ) { ?>
                <?php if( $status !== false && $status == 'valid' ) { ?>
                    <span style="color:green;"><?php _e( 'active' ); ?></span>
                    <?php wp_nonce_field( 'edd_sample_nonce' , 'edd_sample_nonce' ); ?>
                    <input type="submit" class="button-secondary" name="edd_agile_license_deactivate" value="<?php _e( 'Deactivate License' ); ?>"/>
                 <?php } else {
                      
                        wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
                        <input type="submit" class="button-secondary" name="edd_agile_license_activate" value="Activate License"/>
                    <?php } ?>
            <?php } ?>
            
            
            </form>
            <?php 
        }

        
        public static function wcap_agile_test_connection_callback() {
            
            print "<a href='' id='wcap_agile_test' class= 'wcap_agile_test' >" . __( 'Test Connection', 'woocommerce-ac' ) . "</a> &nbsp &nbsp
                <img src='" . plugins_url() . "/woocommerce-abandon-cart-pro/assets/images/loading.gif' alt='Loading...' id='wcap_agile_test_connection_ajax_loader' class = 'wcap_agile_test_connection_ajax_loader' >";
            print "<div id='wcap_agile_test_connection_message'></div>";
        }

        function wcap_agile_crm_display_message (){

            $wcap_action           = "";
            if ( isset( $_GET['action'] ) ){
                $wcap_action = $_GET['action'];
            }
            /*
                It will display the message when abandoned cart data successfuly added to agile crm.
            */
            ?>
            <div id="wcap_agile_message" class="updated fade notice is-dismissible">
                <p class="wcap_agile_message_p">
                    <strong>
                        <?php _e( "" ); ?>
                    </strong>
                </p>
            </div>

            <div id="wcap_agile_message_error" class="error fade notice is-dismissible">
                <p class="wcap_agile_message_p_error">
                    <strong>
                        <?php _e( "" ); ?>
                    </strong>
                </p>
            </div>
            <?php 
        }

        

        function wcap_add_to_agile_crm_callback (){

            global $wpdb, $woocommerce;
            $ids = array();
            
            if ( $_POST [ 'wcap_all' ] == 'yes' ) {

                $blank_cart_info         = '{"cart":[]}';
                $blank_cart_info_guest   = '[]';
                $wcap_get_all_abandoned_carts = "SELECT id FROM `".$wpdb->prefix."ac_abandoned_cart_history` WHERE `id` NOT IN ( SELECT abandoned_cart_id FROM `".$wpdb->prefix."wcap_agile_abandoned_cart`) AND user_id > 0 AND recovered_cart = 0 AND abandoned_cart_info NOT LIKE '$blank_cart_info_guest' AND abandoned_cart_info NOT LIKE '%$blank_cart_info%'";
                
                $abandoned_cart_results  = $wpdb->get_results( $wcap_get_all_abandoned_carts );

                if ( empty ( $abandoned_cart_results ) ){
                    echo 'no_record';
                    wp_die();
                } 

                foreach ( $abandoned_cart_results as $abandoned_cart_results_key => $abandoned_cart_results_value ) {
                    $ids [] = $abandoned_cart_results_value->id;
                }
            } else {
                $ids = $_POST ['wcap_abandoned_cart_ids'];
                
                $wcap_check_duplicate_record = $wpdb->get_var ( "SELECT abandoned_cart_id FROM `".$wpdb->prefix."wcap_agile_abandoned_cart` WHERE `abandoned_cart_id` = $ids[0]" ); 
                if ( $wcap_check_duplicate_record > 0 ){
                    echo 'duplicate_record';
                    wp_die();
                }
                
            }
            
            $abandoned_order_count      = count ( $ids );
            
            foreach ( $ids as $id ) {

                $get_abandoned_cart     = "SELECT * FROM `".$wpdb->prefix."ac_abandoned_cart_history` WHERE id = $id";
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
                        
                        if ( count ($results_guest) > 0 ) {
                            $wcap_contact_email   = $results_guest[0]->email_id;
                            $wcap_user_first_name = $results_guest[0]->billing_first_name;
                            $wcap_user_last_name  = $results_guest[0]->billing_last_name;
                            $wcap_user_phone      = $results_guest[0]->phone;
                        }       
                    } else {                   
                        
                       $wcap_contact_email = get_user_meta( $wcap_user_id, 'billing_email', true );
                            
                        if( $wcap_contact_email == ""){  
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
                          "subject"     =>     "Abandoned Cart Details",
                          "description" => $wcap_product_details,
                          "contact_ids" => array( $created_customer_id ),
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
                          "subject"     =>     "Abandoned Cart Details",
                          "description" => $wcap_product_details,
                          "contact_ids" => array( $created_customer_id ),
                        );

                        $wcap_note_json    = json_encode( $wcap_note_json );
                        $wcap_contact_note = Wcap_Add_To_Agile_CRM::wcap_add_data_to_agile_crm ( "notes", $wcap_note_json, "POST", "application/json" );
                    }
                }
                
                $wcap_insert_abandoned_id = "INSERT INTO `" . $wpdb->prefix . "wcap_agile_abandoned_cart` ( abandoned_cart_id, date_time )
                                          VALUES ( '" . $id . "', '" . current_time( 'mysql' ) . "' )";      
                $wpdb->query( $wcap_insert_abandoned_id );
            }

            echo $abandoned_order_count;
            wp_die();
        }

        function wcap_add_export_all_data_to_agile_crm (){
            $wcap_agile_crm_check = get_option( 'wcap_enable_agile_crm' );
            $wcap_agile_crm_check_connection = get_option ( 'wcap_agile_connection_established' );
            if ( 'on' == $wcap_agile_crm_check && 'yes' == $wcap_agile_crm_check_connection ) {
            ?>
                <a href="javascript:void(0);"  id = "add_all_carts" class="button-secondary"><?php _e( 'Export to Agile CRM', 'woocommerce-ac' ); ?></a>
            <?php
            }
        }

        function wcap_add_individual_record_to_agile_crm ( $actions, $abandoned_row_info ){

            $wcap_agile_crm_check            = get_option ( 'wcap_enable_agile_crm' );
            $wcap_agile_crm_check_connection = get_option ( 'wcap_agile_connection_established' );

            if ( 'on' == $wcap_agile_crm_check && 'yes' == $wcap_agile_crm_check_connection ) { 

                if ( $abandoned_row_info->user_id != 0 ){
                    $abandoned_order_id         = $abandoned_row_info->id ;
                    $class_abandoned_orders     = new WCAP_Abandoned_Orders_Table();
                    $abandoned_orders_base_url  = $class_abandoned_orders->base_url;
                    
                    $inserted['wcap_add_agile'] = '<a href="javascript:void(0);" class="add_single_cart" data-id="' . $abandoned_order_id . '">' . __( 'Add to Agile CRM', 'woocommerce-ac' ) . '</a>';

                    $count = count ( $actions ) - 1 ;

                    array_splice( $actions, $count, 0, $inserted ); // it will add the new data just before the Trash link.
                }
            }

            return $actions;
        }

        function wcap_add_bulk_record_to_agile_crm ( $wcap_abandoned_bulk_actions ){
            $wcap_agile_crm_check = get_option ( 'wcap_enable_agile_crm' );
            $wcap_agile_crm_check_connection = get_option ( 'wcap_agile_connection_established' );
            if ( 'on' == $wcap_agile_crm_check && 'yes' == $wcap_agile_crm_check_connection ) {
                $inserted = array(
                    'wcap_add_agile' => __( 'Add to Agile CRM', 'woocommerce-ac' )
                );
                
                $wcap_abandoned_bulk_actions =  $wcap_abandoned_bulk_actions + $inserted ;
            }
            return $wcap_abandoned_bulk_actions;
        }

    }
}
$wcap_agile_crm_call = new Wcap_Agile_CRM();