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

class RequestMantis extends \Longman\TelegramBot\Request {

    public static function sendMessage( array $data ) {
        telegram_session_start();

        $text = $data['text'];

        $response = array();
        do {
            //Chop off and send the first message
            $data['text'] = mb_substr( $text, 0, 4096 );
            
            try {
                $response[] = self::send( 'sendMessage', $data );
            } catch( Exception $t_error ) {
                plugin_log_event( 'ERROR! "' . $t_error->getMessage() );
            }

            //Prepare the next message
            $text = mb_substr( $text, 4096 );
        } while( mb_strlen( $text, 'UTF-8' ) > 0 );

        return $response;
    }

    public static function __callStatic( $action, array $data ) {
        telegram_session_start();
        return parent::__callStatic( $action, $data );
    }

}

function telegram_set_webhook() {
	global $g_tg;
	telegram_session_start();

	return $g_tg->setWebhook( config_get_global( 'path' ) . plugin_page( 'hook', TRUE ) . '&token=' . plugin_config_get( 'api_key' ) );
}

function telegram_session_start() {
	global $g_tg;

	if( $g_tg == NULL ) {
		$g_tg = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );

		$t_proxy_address = plugin_config_get( 'proxy_address' );

		$t_client_prop = array();

		$t_client_prop['base_uri']	 = 'https://api.telegram.org';
		$t_client_prop['timeout']	 = plugin_config_get( 'time_out_server_response' );

		if( !is_blank( $t_proxy_address ) ) {
			$t_client_prop['proxy'] = 'socks5://' . $t_proxy_address;
		}

		\Longman\TelegramBot\Request::setClient( new \GuzzleHttp\Client( $t_client_prop ) );

		$g_tg->setDownloadPath( plugin_config_get( 'download_path' ) );

		if( plugin_config_get( 'debug_connection_enabled' ) == ON ) {
			Longman\TelegramBot\TelegramLog::initDebugLog( plugin_config_get( 'debug_connection_log_path' ) );
                        Longman\TelegramBot\TelegramLog::initErrorLog( plugin_config_get( 'debug_connection_log_path' ) );
                        Longman\TelegramBot\TelegramLog::initUpdateLog( plugin_config_get( 'debug_connection_log_path' ) );
		}
	}
}

function telegram_session_send_message( $p_telegram_user_id, $p_data ) {
//    telegram_session_start();

    $p_data['chat_id'] = $p_telegram_user_id;

    $t_results_send = RequestMantis::sendMessage( $p_data );

    return $t_results_send;
}

/**
* The function of checking the authorization of a telegram user and issuing an invitation for authorization
*
* @param int $p_telegram_user_id  Telegram user id.
* @param string $p_telegram_user_lang_code Telegram user language code.
* @return bool
*/
function auth_ensure_telegram_user_authenticated( $p_telegram_user_id, $p_telegram_user_lang_code = null ) {

    global $g_cache_cookie_valid;

    plugin_log_event( 'Telegram user ' . $p_telegram_user_id . ' request language: "'.$p_telegram_user_lang_code.'"' );
    
    $t_mantis_user_id = user_get_id_by_telegram_user_id( $p_telegram_user_id );

    if( $t_mantis_user_id == 0 ) {
        lang_push( telegram_lang_map_auto( $p_telegram_user_lang_code ) );
        $t_response = user_telegram_signup( $p_telegram_user_id );
        if( !$t_response[0]->getOk() ) {
            error_parameters( $t_response[0]->getDescription() );
            plugin_error( 'ERROR_TG_GET_UPDATE', WARNING );
        }
        plugin_log_event( 'Authorization Error! Telegram user id#' . $p_telegram_user_id . ' is not mapped to any mantisbt user. As a response, an authorization invitation was sent.' );
        return false;
    } else if( !user_is_enabled( $t_mantis_user_id ) || !user_exists( $t_mantis_user_id ) ) {
        lang_push( telegram_lang_map_auto( $p_telegram_user_lang_code ) );
        $t_response = user_telegram_signup( $p_telegram_user_id );
        if( !$t_response[0]->getOk() ) {
            error_parameters( $t_response[0]->getDescription() );
            plugin_error( 'ERROR_TG_GET_UPDATE', WARNING );
        }
        plugin_log_event( 'Authorization Error! User ' . user_get_username( $t_mantis_user_id ) . ' is disabled or deleted. As a response, an authorization invitation was sent.' );
        return false;
    } else {
        current_user_set( $t_mantis_user_id );
        plugin_log_event( 'Authorization success! Server telegrams successfully logged in as user: ' . user_get_username( $t_mantis_user_id ) );
        $g_cache_cookie_valid = TRUE;

        lang_push( telegram_lang_get_default( $p_telegram_user_lang_code ) );
        return true;
    }
}

function user_telegram_signup( $p_telegram_user_id ) {

//    $t_pin_code = telegrambot_get_pin_code( $p_telegram_user_id );
    
    //We correctly form the url, depending on which method of receiving updates from the telegram server is selected.
    if( php_sapi_name() == 'cli' ) {
            $t_url = plugin_config_get( 'cli_g_path' ) == '' ? config_get_global( 'path' ) : plugin_config_get( 'cli_g_path' );
    } else {
            $t_url = config_get_global( 'path' );
    }
 
    $t_signup_keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard( array() );
    $t_signup_keyboard->addRow( [
                              'text' => plugin_lang_get( 'registration_button_text' ),
                              'url'  => $t_url . plugin_page( 'registred', TRUE ) . '&telegram_user_id=' . $p_telegram_user_id
    ] );
    $data_signup       = [
                              'chat_id'      => $p_telegram_user_id,
                              'text'         => sprintf( 
                                                            plugin_lang_get( 'registration_message_text' ), 
                                                            config_get( 'window_title' ),
                                                            $t_url,
                                      ),
                              'reply_markup' => $t_signup_keyboard,
    ];

    return RequestMantis::sendMessage( $data_signup );
    
}
