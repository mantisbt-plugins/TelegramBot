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

class TelegramBotPlugin extends MantisPlugin {

    function register() {

        $this->name        = 'TelegramBot';
        $this->description = plugin_lang_get( 'description' );

        $this->version  = '1.0.0-dev';
        $this->requires = array(
                                  'MantisCore' => '2.14.0',
        );

        $this->author  = 'Grigoriy Ermolaev';
        $this->contact = 'igflocal@gmail.com';
        $this->url     = 'http://github.com/mantisbt-plugins/TelegramBot';
        $this->page    = 'config_page';
    }

    function schema() {

        return array(
                                  // version 0.0.1
                                  array( "CreateTableSQL", array( plugin_table( "user_relationship" ), "
                                      mantis_user_id INT(10) NOTNULL PRIMARY,
                                      telegram_user_id INT(10) NOTNULL                                        
				" ) )
        );
    }

    function init() {
        require_once __DIR__ . '/api/vendor/autoload.php';
        require_once 'core/TelegramBot_authentication_api.php';
        require_once 'core/TelegramBot_user_api.php';
        require_once 'core/TelegramBot_helper_api.php';
        require_once 'core/TelegramBot_keyboard_api.php';
    }

    function config() {
        return array(
                                  'api_token'      => NULL,
                                  'bot_name'       => NULL,
                                  'bot_father_url' => 'https://t.me/BotFather',
                                  'telegram_url'   => 'https://telegram.me/'
        );
    }

//    public function hooks() {
//        return array(
//                                  'EVENT_BUGNOTE_ADD' => 'bugnote_telegram_send'
//        );
//    }
//
//    function bugnote_telegram_send( $p_type_event, $p_bug_id, $p_bugnote_id ) {
//
//
//        $t_tg = new \Longman\TelegramBot\Telegram( $token, $botname );
//
//        $t_bugnote = bugnote_get($p_bugnote_id);
//        $t_text       = bugnote_get_text($p_bugnote_id);
//
//        $data1 = [
//                                  'chat_id'    => get_telegram_user_id_from_mantis_user_id( $t_bugnote->reporter_id ),
////                                  'message_id' => $t_callback_query->getMessage()->getMessageId(),
//                                  'text'       => $t_text
//        ];
//
//        Longman\TelegramBot\Request::sendMessage( $data1 );
//    }
}
