<?php

# Copyright (c) 2018 Grigoriy Ermolaev (igflocal@gmail.com)
# TelegramBot for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 2 of the License, or (at your option) any later version.
#
# TelegramBot plugin for for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Customer management plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.

form_security_validate( 'config' );

$f_bot_name = gpc_get_string( 'bot_name' );
$f_api_key  = gpc_get_string( 'api_key' );

if( plugin_config_get( 'bot_name' ) != $f_bot_name ) {
    plugin_config_set( 'bot_name', $f_bot_name );
}

if( plugin_config_get( 'api_key' ) != $f_api_key ) {
    plugin_config_set( 'api_key', $f_api_key );
}

form_security_purge( plugin_page( 'config', true ) );

$t_redirect_url = plugin_page( 'config_page', true );
layout_page_header( null, $t_redirect_url );
layout_page_begin( $t_redirect_url );

try {
    $t_tg     = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );
    $t_result = $t_tg->setWebhook( config_get_global( 'path' ) . plugin_page( 'hook', TRUE ) . '&token=' . plugin_config_get( 'api_key' ) );

//$t_logo_path = config_get( 'absolute_path' ) . config_get( 'logo_image' );
//if( file_exists( $t_logo_path ) ) {
//    $t_file_content = file_get_contents( $t_logo_path );
//
////    $t_chat_photo = new \Longman\TelegramBot\Entities\InputMedia\InputMediaPhoto( $t_file_content );
//    $t_data            = [];
//    $t_data['chat_id'] = get_telegram_user_id_from_mantis_user_id( auth_get_current_user_id() );
//
//    $t_result = Longman\TelegramBot\Request::setChatPhoto( $t_data, $t_logo_path );
////$t_result_icon = $t_tg->
//}

    html_operation_successful( $t_redirect_url, plugin_lang_get( 'response_from_telegram' ) . $t_result->getDescription() );
} catch( Longman\TelegramBot\Exception\TelegramException $e ) {
    html_operation_failure( $t_redirect_url, $e->getMessage() );
}
layout_page_end();
