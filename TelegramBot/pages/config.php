<?php
# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
# TelegramBot for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 2 of the License, or (at your option) any later version.
#
# TelegramBot plugin for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with TelegramBot plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.

use Mantis\Exceptions\ClientException;
use Longman\TelegramBot\Request;

form_security_validate( 'config' );

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_bot_name			= gpc_get_string   ( 'bot_username' );
$f_api_key			= gpc_get_string   ( 'api_key' );
$f_reinstall_webhook            = gpc_get_bool     ( 'reinstall_webhook' );
$f_registration_method          = gpc_get_int      ( 'registration_method', plugin_config_get( 'registration_method' ) );
$f_pin_code_attempts_max        = gpc_get_int      ( 'pin_code_attempts_max', plugin_config_get( 'pin_code_attempts_max' ) );
$f_pin_code_attempts_window     = gpc_get_int      ( 'pin_code_attempts_window', plugin_config_get( 'pin_code_attempts_window' ) );
$f_admin_unlink_notify          = gpc_get_bool     ( 'admin_unlink_notify' );
# the use_cert radio is only rendered in Webhook mode: fall back to the stored
# value so that saving the form in Script mode does not wipe the certificate
$f_use_cert                     = gpc_get_bool     ( 'use_cert', plugin_config_get( 'use_cert' ) == ON );
$f_bot_cert_file                = gpc_get_file     ( 'bot_cert_file', null );
$f_proxy_address		= gpc_get_string   ( 'proxy_address', '' );
$f_time_out_server_response	= gpc_get_int      ( 'time_out_server_response' );
$f_get_updates_timeout		= gpc_get_int      ( 'get_updates_timeout', plugin_config_get( 'get_updates_timeout' ) );
$f_get_updates_run_time		= gpc_get_int      ( 'get_updates_run_time', plugin_config_get( 'get_updates_run_time' ) );
$f_debug_connection_log_path    = gpc_get_string   ( 'debug_connection_log_path', '' );
$f_debug_connection_enabled	= gpc_get_bool     ( 'debug_connection_enabled', FALSE );
$f_cli_g_path                   = gpc_get_string   ( 'cli_g_path', plugin_config_get( 'cli_g_path' ) );

$t_cert_uploaded = $f_bot_cert_file !== null && $f_bot_cert_file['error'] !== UPLOAD_ERR_NO_FILE;

if( $t_cert_uploaded ) {
        $t_tmp_file = $f_bot_cert_file['tmp_name'];

	file_ensure_uploaded( $f_bot_cert_file );

	$t_file_name = $f_bot_cert_file['name'];

	if(
                strcasecmp( pathinfo( $t_file_name, PATHINFO_EXTENSION ), 'crt' ) != 0
                && strcasecmp( pathinfo( $t_file_name, PATHINFO_EXTENSION ), 'pem' ) != 0
                && strcasecmp( pathinfo( $t_file_name, PATHINFO_EXTENSION ), 'cer' ) != 0
        ) {
		throw new ClientException(
			sprintf( "File '%s' type not allowed", $t_file_name ),
			ERROR_FILE_NOT_ALLOWED
		);
	}

	$t_file_size = filesize( $t_tmp_file );
	if( 0 == $t_file_size ) {
		throw new ClientException(
			sprintf( "File '%s' not uploaded", $t_file_name ),
			ERROR_FILE_NO_UPLOAD_FAILURE );
	}

	$t_max_file_size = (int)min( ini_get_number( 'upload_max_filesize' ), ini_get_number( 'post_max_size' ), config_get( 'max_file_size' ) );
	if( $t_file_size > $t_max_file_size ) {
		throw new ClientException(
			sprintf( "File '%s' too big", $t_file_name ),
			ERROR_FILE_TOO_BIG );
	}

        # store the raw file content: plugin_config_set() does its own escaping,
        # db_prepare_binary_string() would corrupt the value on pgsql/mssql
        $t_content = file_get_contents( $t_tmp_file );
        if( $t_content === false ) {
		throw new ClientException(
			sprintf( "File '%s' not uploaded", $t_file_name ),
			ERROR_FILE_NO_UPLOAD_FAILURE );
        }

        plugin_config_set( 'bot_cert', $t_content );
        plugin_config_set( 'use_cert', ON );

        unlink($t_tmp_file);
} else if( $f_use_cert ) {
        # validate before any config writes to avoid the inconsistent
        # "use_cert is ON but no certificate is stored" state
        if( plugin_config_get( 'bot_cert' ) == '' ) {
                error_parameters( plugin_lang_get( 'bot_cert' ) );
                plugin_error( 'ERROR_CERT_FILE_NOT_FOUND', ERROR );
        }

        if( plugin_config_get( 'use_cert' ) != ON ) {
                plugin_config_set( 'use_cert', ON );
        }
} else {
        plugin_config_delete( 'bot_cert' );
        plugin_config_delete( 'use_cert' );
}

if( plugin_config_get( 'bot_name' ) != $f_bot_name ) {
	plugin_config_set( 'bot_name', $f_bot_name );
}

if( plugin_config_get( 'api_key' ) != $f_api_key ) {
	plugin_config_set( 'api_key', $f_api_key );
}

if( plugin_config_get( 'reinstall_webhook' ) != $f_reinstall_webhook ) {
	# ON/OFF, not a PHP boolean: plugin_config_set() would store false as an empty string
	plugin_config_set( 'reinstall_webhook', $f_reinstall_webhook ? ON : OFF );
}

if( !in_array( $f_registration_method, array( TELEGRAM_REGISTRATION_LINK, TELEGRAM_REGISTRATION_PIN, TELEGRAM_REGISTRATION_BOTH ), true ) ) {
	$f_registration_method = TELEGRAM_REGISTRATION_LINK;
}

if( plugin_config_get( 'registration_method' ) != $f_registration_method ) {
	plugin_config_set( 'registration_method', $f_registration_method );
}

# Zero or a negative value would turn the brute force protection off entirely
$f_pin_code_attempts_max    = max( 1, $f_pin_code_attempts_max );
$f_pin_code_attempts_window = max( 1, $f_pin_code_attempts_window );

if( plugin_config_get( 'pin_code_attempts_max' ) != $f_pin_code_attempts_max ) {
	plugin_config_set( 'pin_code_attempts_max', $f_pin_code_attempts_max );
}

if( plugin_config_get( 'pin_code_attempts_window' ) != $f_pin_code_attempts_window ) {
	plugin_config_set( 'pin_code_attempts_window', $f_pin_code_attempts_window );
}

if( plugin_config_get( 'admin_unlink_notify' ) != $f_admin_unlink_notify ) {
	# ON/OFF, not a PHP boolean: plugin_config_set() would store false as an empty string
	plugin_config_set( 'admin_unlink_notify', $f_admin_unlink_notify ? ON : OFF );
}

if( plugin_config_get( 'proxy_address' ) != $f_proxy_address ) {
	plugin_config_set( 'proxy_address', $f_proxy_address );
}

if( plugin_config_get( 'cli_g_path' ) != $f_cli_g_path ) {
	plugin_config_set( 'cli_g_path', $f_cli_g_path );
}

if( plugin_config_get( 'time_out_server_response' ) != $f_time_out_server_response ) {
	plugin_config_set( 'time_out_server_response', $f_time_out_server_response );
}

if( plugin_config_get( 'get_updates_timeout' ) != $f_get_updates_timeout ) {
	plugin_config_set( 'get_updates_timeout', $f_get_updates_timeout );
}

if( plugin_config_get( 'get_updates_run_time' ) != $f_get_updates_run_time ) {
	plugin_config_set( 'get_updates_run_time', $f_get_updates_run_time );
}

if( $f_debug_connection_enabled == ON ) {
	$t_log_handle = @fopen( $f_debug_connection_log_path, 'a' );
	if( $t_log_handle !== false ) {
		fclose( $t_log_handle );
		plugin_config_set( 'debug_connection_enabled', $f_debug_connection_enabled ? ON : OFF );
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

form_security_purge( 'config' );

$t_redirect_url = plugin_page( 'config_page', true );
layout_page_header();
layout_page_begin();

if( $f_reinstall_webhook == ON ) {
        $t_data = array();

        if( plugin_config_get( 'use_cert' ) == ON ) {
                # the handle must stay referenced until setWebhook(): closing it deletes the file;
                # Request turns a local path in 'certificate' into a multipart upload by itself
                $t_cert_file = tmpfile();
                fwrite( $t_cert_file, plugin_config_get( 'bot_cert' ) );

                $t_data['certificate'] = stream_get_meta_data( $t_cert_file )['uri'];
        }

        $t_data['url'] = config_get_global( 'path' ) . plugin_page( 'hook', TRUE ) . '&token=' . plugin_config_get( 'api_key' );

        try {
                telegram_session_start();
                html_operation_successful( $t_redirect_url, plugin_lang_get( 'response_from_telegram' ) . Request::setWebhook( $t_data )->getDescription() );
        } catch( Longman\TelegramBot\Exception\TelegramException $t_errors ) {
                //plugin_config_set( 'reinstall_webhook', OFF );
                html_operation_failure( $t_redirect_url, plugin_lang_get( 'response_from_telegram' ) . $t_errors->getMessage() );
        } catch( GuzzleHttp\Exception\GuzzleException $t_errors ) {
                html_operation_failure( $t_redirect_url, plugin_lang_get( 'response_from_telegram' ) . $t_errors->getMessage() );
        }
} else {
//    html_operation_successful( $t_redirect_url );
    try {
            html_operation_successful( $t_redirect_url, plugin_lang_get( 'response_from_telegram' ) . Request::deleteWebhook()->getDescription() );
    } catch( Longman\TelegramBot\Exception\TelegramException $t_errors ) {
            html_operation_failure( $t_redirect_url, plugin_lang_get( 'response_from_telegram' ) . $t_errors->getMessage() );
    } catch( GuzzleHttp\Exception\GuzzleException $t_errors ) {
            html_operation_failure( $t_redirect_url, plugin_lang_get( 'response_from_telegram' ) . $t_errors->getMessage() );
    }
}
layout_page_end();
