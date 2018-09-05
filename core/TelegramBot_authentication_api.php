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

function telegram_session_send_message( $p_telegram_user_id, $p_data ) {
    global $g_tg;

    if( $g_tg == NULL ) {
        $g_tg = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );
    }

    
    $p_data['chat_id'] = $p_telegram_user_id;

    $t_result_send = Longman\TelegramBot\Request::sendMessage( $p_data );

    if( $t_result_send->getOk() ) {
        return TRUE;
    } else {
//        telegram_bot_user_mapping_delete( $p_user_id );
        return FALSE;
    }
}

function auth_ensure_telegram_user_authenticated( $p_telegram_user_id, $p_message_id = 0 ) {

    global $g_cache_cookie_valid;

    $t_mantis_user_id = user_get_id_by_telegram_user_id( $p_telegram_user_id );

    if( $t_mantis_user_id == NULL ) {
        user_telegram_signup( $p_telegram_user_id, $p_message_id );
        return FALSE;
    } else if( !user_is_enabled( $t_mantis_user_id ) || !user_exists( $t_mantis_user_id ) ) {

        $data_signup_break = [
                                  'chat_id' => $t_message->getChat()->getId(),
                                  'text'    => plugin_lang_get( 'error_user' )
        ];

        Longman\TelegramBot\Request::sendMessage( $data_signup_break );
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

    Longman\TelegramBot\Request::sendMessage( $data_signup );
}
