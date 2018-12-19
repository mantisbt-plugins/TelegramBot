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
            $response[]   = self::send( 'sendMessage', $data );

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
		
		$g_tg_options = [
					  'base_uri'	 => 'https://api.telegram.org',
					  'timeout'	 => plugin_config_get( 'time_out_server_response' ),
		];
		
		if( !empty( plugin_config_get( 'proxy_address' ) ) ) {
			$g_tg_options['proxy'] = 'socks5://' . plugin_config_get( 'proxy_address' );
		}
		\Longman\TelegramBot\Request::setClient( new \GuzzleHttp\Client( $g_tg_options ) );
		
		$g_tg->setDownloadPath( plugin_config_get( 'download_path' ) );
	}
}

function telegram_session_send_message( $p_telegram_user_id, $p_data ) {
//    telegram_session_start();

    $p_data['chat_id'] = $p_telegram_user_id;

    $t_results_send = RequestMantis::sendMessage( $p_data );

    return $t_results_send;
}

function auth_ensure_telegram_user_authenticated( $p_telegram_user_id, $p_message_id = 0 ) {

    global $g_cache_cookie_valid;

    $t_mantis_user_id = user_get_id_by_telegram_user_id( $p_telegram_user_id );

    if( $t_mantis_user_id == NULL ) {
        user_telegram_signup( $p_telegram_user_id, $p_message_id );
        return FALSE;
    } else if( !user_is_enabled( $t_mantis_user_id ) || !user_exists( $t_mantis_user_id ) ) {
        user_telegram_signup( $p_telegram_user_id, $p_message_id );
        return FALSE;
    } else {
        current_user_set( $t_mantis_user_id );
        $g_cache_cookie_valid = TRUE;

        lang_push( lang_get_default() );
        return TRUE;
    }
}

function user_telegram_signup( $p_telegram_user_id ) {

    $t_signup_keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard( array() );
    $t_signup_keyboard->addRow( [
                              'text' => plugin_lang_get( 'registration_button_text' ),
                              'url'  => config_get_global( 'path' ) . plugin_page( 'registred', TRUE ) . '&telegram_user_id=' . $p_telegram_user_id
    ] );
    $data_signup       = [
                              'chat_id'      => $p_telegram_user_id,
                              'text'         => sprintf( plugin_lang_get( 'registration_message_text' ), config_get( 'window_title' ) . ' ( ' . config_get( 'path' ) . ' )' ),
                              'reply_markup' => $t_signup_keyboard,
    ];

    RequestMantis::sendMessage( $data_signup );
}
