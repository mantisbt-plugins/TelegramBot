<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once 'core/json_api.php';
//set_error_handler('json_error_handler');

//access_ensure_global_level( plugin_config_get( 'manage_calendar_threshold' ) );
//form_security_validate( 'event_create' );

$contents = 'error';
$_qwer = json_encode($_POST);
switch ( $_POST["ACTION"] ) {
	case "EVENT_CREATE":
            
            $t_event_id = calendar_helper::create_event();
                        
            echo json_output_response($t_event_id);
            break;
        case "EVENT_DELETE":

            calendar_helper::delete_event();
                        
            echo json_output_response(TRUE);
            break;
        case "EVENT_UPDATE":
                
            calendar_helper::update_event();
                        
            echo json_output_response(TRUE);
            break;
}

//echo json_encode($_POST);
//echo json_output_response( $contents );
?>