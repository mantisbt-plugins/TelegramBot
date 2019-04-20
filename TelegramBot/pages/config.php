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

use Mantis\Exceptions\ClientException;

form_security_validate( 'config' );

global $g_tg;

$f_bot_name			 = gpc_get_string( 'bot_name' );
$f_api_key			 = gpc_get_string( 'api_key' );
$f_proxy_address		 = gpc_get_string( 'proxy_address', '' );
$f_time_out_server_response	 = gpc_get_int( 'time_out_server_response' );
$f_debug_connection_log_path	 = gpc_get_string( 'debug_connection_log_path', '' );
$f_debug_connection_enabled	 = gpc_get_bool( 'debug_connection_enabled', FALSE );

if( plugin_config_get( 'bot_name' ) != $f_bot_name ) {
	plugin_config_set( 'bot_name', $f_bot_name );
}

if( plugin_config_get( 'api_key' ) != $f_api_key ) {
	plugin_config_set( 'api_key', $f_api_key );
}

if( plugin_config_get( 'proxy_address' ) != $f_proxy_address ) {
	plugin_config_set( 'proxy_address', $f_proxy_address );
}

if( plugin_config_get( 'time_out_server_response' ) != $f_time_out_server_response ) {
	plugin_config_set( 'time_out_server_response', $f_time_out_server_response );
}

if( $f_debug_connection_enabled == ON ) {
	if( fopen( $f_debug_connection_log_path, 'a' ) ) {
		plugin_config_set( 'debug_connection_enabled', $f_debug_connection_enabled );
		plugin_config_set( 'debug_connection_log_path', $f_debug_connection_log_path );
	} else {
		plugin_config_set( 'debug_connection_enabled', OFF );
		plugin_config_set( 'debug_connection_log_path', $f_debug_connection_log_path );
		throw new ClientException( 'Cannot access write file.', ERROR_FILE_INVALID_UPLOAD_PATH );
	}
} else {
	plugin_config_set( 'debug_connection_enabled', OFF );
	plugin_config_set( 'debug_connection_log_path', $f_debug_connection_log_path );
}

form_security_purge( plugin_page( 'config', true ) );

$t_redirect_url = plugin_page( 'config_page', true );
layout_page_header( null, $t_redirect_url );
layout_page_begin( $t_redirect_url );

try {
	html_operation_successful( $t_redirect_url, plugin_lang_get( 'response_from_telegram' ) . telegram_set_webhook()->getDescription() );
} catch( Longman\TelegramBot\Exception\TelegramException $t_errors ) {
	html_operation_failure( $t_redirect_url, $t_errors->getMessage() );
}
layout_page_end();
