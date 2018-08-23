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

$f_token = gpc_get_string( 'token' );

if( plugin_config_get( 'api_key' ) && plugin_config_get( 'bot_name' ) && $f_token == plugin_config_get( 'api_key' ) ) {

    $t_tg = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );

    $t_tg->setDownloadPath( '/tmp/' );

    $post = json_decode( Longman\TelegramBot\Request::getInput(), true );

    $t_update = new Longman\TelegramBot\Entities\Update( $post, $botname );

    $t_update_type = $t_update->getUpdateType();


    switch( $t_update_type ) {

//MESSAGE
        case 'message':
            $t_message = $t_update->getMessage();

            if( !auth_ensure_telegram_user_authenticated( $t_message->getFrom()->getId() ) ) {
                break;
            }

            $t_message_type = $t_message->getType();
            $t_file         = NULL;

            switch( $t_message_type ) {
                case 'command':
                    $t_command = $t_message->getCommand();

                    switch( $t_command ) {
                        case 'start':
                            $t_tg     = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );
                            $data     = [
                                                      'chat_id' => $t_message->getFrom()->getId(),
                                                      'text'    => plugin_lang_get( 'first_message' )
                            ];
                            $t_result = Longman\TelegramBot\Request::sendMessage( $data );

                            break;
                        case 'stop':
                            break;
                    }
                    break;

                case 'video':
                    if( $t_file == NULL ) {
                        $t_file = $t_message->getVideo();
                    }
                case 'photo':
                    if( $t_file == NULL ) {
                        $t_content_photo = $t_message->getPhoto();
                        $t_file          = $t_content_photo[3];
                    }
                case 'document':
                    if( $t_file == NULL ) {
                        $t_file = $t_message->getDocument();
                    }

                    if( $t_file->getFileSize() > 20971520 ) {
                        $data = [
                                                  'chat_id'             => $t_message->getChat()->getId(),
                                                  'text'                => plugin_lang_get( 'error_file_size' ),
                                                  'reply_to_message_id' => $t_message->getMessageId()
                        ];

                        Longman\TelegramBot\Request::sendMessage( $data );
                        break;
                    }
                case 'text':

                    $t_inline_keyboard = telegram_bot_get_keyboard_default_filter();
                    $data              = [
                                              'chat_id'             => $t_message->getChat()->getId(),
                                              'text'                => plugin_lang_get( 'bug_section_select' ),
                                              'reply_markup'        => $t_inline_keyboard,
                                              'reply_to_message_id' => $t_message->getMessageId()
                    ];

                    Longman\TelegramBot\Request::sendMessage( $data );

                    break;

                default :
                    $data = [
                                              'chat_id' => $t_message->getChat()->getId(),
                                              'text'    => plugin_lang_get( 'error_content_type' )
                    ];

                    Longman\TelegramBot\Request::sendMessage( $data );
                    break;
            }
            break;
//END MESSAGE
//CALLBACK
        case 'callback_query':
            $t_callback_query = $t_update->getCallbackQuery();

            if( !auth_ensure_telegram_user_authenticated( $t_callback_query->getFrom()->getId() ) ) {
                break;
            }

            $t_data = json_decode( $t_callback_query->getData(), TRUE );

            $t_orgl_message = $t_callback_query->getMessage()->getReplyToMessage();

            $t_command = array_keys( $t_data );

            switch( $t_command[0] ) {

                case 'get_default_category':

                    $t_inline_keyboard = telegram_bot_get_keyboard_default_filter();
                    $data              = [
                                              'chat_id'      => $t_orgl_message->getChat()->getId(),
                                              'message_id'   => $t_callback_query->getMessage()->getMessageId(),
                                              'text'         => plugin_lang_get( 'bug_section_select' ),
                                              'reply_markup' => $t_inline_keyboard,
                    ];

                    Longman\TelegramBot\Request::editMessageText( $data );
                    break;

                case 'get_bugs':
                    switch( $t_data['get_bugs'] ) {
                        case 'assigned':
                            $t_custom_filter = filter_create_assigned_to_unresolved( 0, auth_get_current_user_id() );
                            break;

                        case 'monitored':
                            $t_custom_filter = filter_create_monitored_by( 0, auth_get_current_user_id() );
                            break;

                        case 'reported':
                            $t_custom_filter = filter_create_reported_by( 0, auth_get_current_user_id() );
                            break;

                        case 'use_query':
                            $t_custom_filter = filter_get_default();
                            break;
                    }

                    $t_inline_keyboard = keyboard_bugs_get( $t_custom_filter, $t_data['page'] );

                    $data = [
                                              'chat_id'      => $t_orgl_message->getChat()->getId(),
                                              'message_id'   => $t_callback_query->getMessage()->getMessageId(),
                                              'text'         => plugin_lang_get( bug_select ),
                                              'reply_markup' => $t_inline_keyboard,
                    ];

                    Longman\TelegramBot\Request::editMessageText( $data );
                    break;

                case 'set_bug':

                    $t_content_type = $t_orgl_message->getType();

                    switch( $t_content_type ) {
                        case 'video':
                        case 'photo':
                        case 'document':

                            switch( $t_content_type ) {
                                case 'video':
                                    $t_file_orgl     = $t_orgl_message->getVideo();
                                    break;
                                case 'photo':
                                    $t_content_photo = $t_orgl_message->getPhoto();
                                    $t_file_orgl     = $t_content_photo[3];
                                    break;
                                case 'document':
                                    $t_file_orgl     = $t_orgl_message->getDocument();
                                    break;
                            }

                            $t_download = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_file_orgl->getFileId() ] );

                            $t_file = $t_download->getResult();

                            Longman\TelegramBot\Request::downloadFile( $t_file );

                            $t_file_path = $t_tg->getDownloadPath() . $t_file->getFilePath();

                            $t_file_for_attach = [
                                                      'browser_upload' => FALSE,
                                                      'tmp_name'       => $t_file_path,
                                                      'name'           => $t_file_orgl->getFileName() == NULL ? $t_file->getFilePath() : $t_file_orgl->getFileName()
                            ];

                            $t_file_complite = file_add( $t_data['set_bug'], $t_file_for_attach );

                            $inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

                            $t_data = [
                                                      'chat_id'    => $t_callback_query->getMessage()->getChat()->getId(),
                                                      'message_id' => $t_callback_query->getMessage()->getMessageId(),
                                                      'text'       => plugin_lang_get( 'file_upload_complete' ) . $t_data['set_bug']
                            ];

                            Longman\TelegramBot\Request::editMessageText( $t_data );
                            break;

                        case 'text':
                            $t_text       = $t_orgl_message->getText();
                            $t_bugnote_id = bugnote_add( $t_data['set_bug'], $t_text );

                            $inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

                            $t_data = [
                                                      'chat_id'    => $t_callback_query->getMessage()->getChat()->getId(),
                                                      'message_id' => $t_callback_query->getMessage()->getMessageId(),
                                                      'text'       => plugin_lang_get( 'comment_add_complete' ) . $t_data['set_bug']
                            ];

                            Longman\TelegramBot\Request::editMessageText( $t_data );
                            break;
                    }
                    break;
            }

            break;
//END CALLBACK
    }
}