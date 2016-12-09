<?php
/*
* This class help to add / get / delete the data to the Agile CRM
*/
class Wcap_Add_To_Agile_CRM
{
    public static function wcap_add_data_to_agile_crm ( $entity, $data, $method, $content_type ) {

        $wcap_domain   = get_option( "agile_domain" );
        $wcap_email    = get_option( "agile_email" );
        $wcap_rest_api = get_option( "agile_rest_api" );

        if ($content_type == NULL) {
            $content_type = "application/json";
        }
        
        
        //$agile_url = "https://" . AGILE_DOMAIN . "-dot-sandbox-dot-agilecrmbeta.appspot.com/dev/api/" . $entity;
        $agile_url = "https://" . $wcap_domain . ".agilecrm.com/dev/api/" . $entity;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true);
        switch ($method) {
            case "POST":
                $url = $agile_url;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case "GET":
                $url = $agile_url;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                break;
            case "PUT":
                $url = $agile_url;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case "DELETE":
                $url = $agile_url;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default:
                break;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-type : $content_type;", 'Accept : application/json'
        ));


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $wcap_email . ':' . $wcap_rest_api);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }    
}