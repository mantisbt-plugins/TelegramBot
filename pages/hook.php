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

    $t_tg->setDownloadPath( plugin_config_get( 'download_path' ) );

//    $dwdds = $t_tg->addCommandsPath( realpath( __DIR__ . '/../core/Commands/' ));
//    $efvef = $t_tg->handle();

    $post = json_decode( Longman\TelegramBot\Request::getInput(), true );

    $t_update      = new Longman\TelegramBot\Entities\Update( $post, $botname );
    $t_update_type = $t_update->getUpdateType();

    if( $t_update_type == 'message' ) {
        $t_message          = $t_update->getMessage();
        $t_reply_to_message = $t_message->getReplyToMessage();

        if( $t_reply_to_message == NULL ) {
            $t_acction_handler = 'message';
        } else {
            $t_acction_handler = 'reply_to_message';
        }
    } else {
        $t_acction_handler = 'callback_query';
    }

    switch( $t_acction_handler ) {
//MESSAGE
        case 'message':

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
                            $data = [
                                                      'chat_id' => $t_message->getFrom()->getId(),
                                                      'text'    => sprintf( plugin_lang_get( 'first_message' ), config_get( 'window_title' ) . ' ( ' . config_get( 'path' ) . ' )', ' ( ' . config_get( 'path' ) . plugin_page( 'account_telegram_prefs_page', TRUE ) . ' )'
                                                      ),
                            ];

                            break;
                        case 'stop':
                            $t_user_id = user_get_id_by_telegram_user_id( $t_message->getFrom()->getId() );

                            telegram_message_realatationship_delete( $t_message->getFrom()->getId() );
                            telegram_bot_user_mapping_delete( $t_user_id );

                            $data = [
                                                      'chat_id' => $t_message->getFrom()->getId(),
                                                      'text'    => plugin_lang_get( 'end_message' )
                            ];

                            break;
                    }
                    break;

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
                        break;
                    }

                case 'video':
//                    if( $t_file == NULL ) {
//                        $t_file = $t_message->getVideo();
//                    }

                case 'photo':
//                    if( $t_file == NULL ) {
//                        $t_content_photo = $t_message->getPhoto();
//                        $t_file          = $t_content_photo[3];
//                    }

                case 'text':
                    $t_bug_data_draft = json_decode( plugin_config_get( 'bug_data_draft', NULL, FALSE, auth_get_current_user_id() ), TRUE );

                    if( $t_bug_data_draft == NULL ) {
                        $data = telegram_action_select( $t_message->getChat()->getId(), $t_message->getMessageId() );
                        break;
                    }

                    if( is_blank( $t_bug_data_draft['summary'] ) ) {

                        $t_bug_data_draft['summary'] = $t_message->getText();
                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $data = [
                                                  'chat_id' => $t_message->getChat()->getId(),
                                                  'text'    => plugin_lang_get( 'get_description' )
                        ];
                        break;
                    }
                    if( is_blank( $t_bug_data_draft['description'] ) ) {

                        $t_bug_data_draft['description'] = $t_message->getText();

                        $data = telegram_bug_add( $t_bug_data_draft, $t_message->getChat()->getId(), $t_message->getMessageId() );

                        break;
                    }

                default :
                    $data = [
                                              'chat_id' => $t_message->getChat()->getId(),
                                              'text'    => plugin_lang_get( 'error_content_type' )
                    ];
                    break;
            }
            $t_result         = Longman\TelegramBot\Request::sendMessage( $data );
            break;
//END MESSAGE
//CALLBACK
        case 'callback_query':
            $t_callback_query = $t_update->getCallbackQuery();

            if( !auth_ensure_telegram_user_authenticated( $t_callback_query->getFrom()->getId() ) ) {
                break;
            }

            $t_data = json_decode( $t_callback_query->getData(), TRUE );

            $t_result_callback = $t_callback_query->answer();
            $t_command         = array_keys( $t_data );
            $t_message         = $t_callback_query->getMessage();

            switch( $t_command[0] ) {
                case 'rb':
                    $t_data_send = telegram_bug_report( $t_data['rb'], $t_callback_query );
                    break;

                case 'add_comment':
                    $t_data_send = telegram_add_comment( $t_data['add_comment'], $t_message, $t_message->getReplyToMessage() );

                    $t_message_id   = $t_message->getMessageId();
                    $t_orgl_chat_id = $t_message->getReplyToMessage()->getChat()->getId();

                    $t_data_send['chat_id']    = $t_orgl_chat_id;
                    $t_data_send['message_id'] = $t_message_id;
                    break;

                case 'action_select':
                    $t_orgl_message = $t_callback_query->getMessage();
                    $t_data_send    = telegram_action_select( $t_orgl_message->getChat()->getId(), $t_orgl_message->getMessageId() );
                    break;
//                case 'get_status':
//                    $t_bug                     = bug_get( $t_data['get_status']['bug_id'] );
//                    $t_data_send               = [
//                                              'reply_markup' => keyboard_buttons_bug_change_status( $t_bug ),
//                    ];
//                    $t_data_send['chat_id']    = $t_message->getReplyToMessage()->getChat()->getId();
//                    $t_data_send['message_id'] = $t_message->getMessageId();
//                    
//                    $t_visible_bug_data = email_build_visible_bug_data( user_get_id_by_telegram_user_id( $t_message->getReplyToMessage()->getChat()->getId() ), $t_bug->id );
//                    $t_message = telegram_message_format_bug_message( $t_visible_bug_data, FALSE );
//                    $t_message .= lang_get( 'bug_status_to_button' );
//                    $t_data_send['text']       = $t_message;
//                    break;
//                case 'set_status':
//                    
//
//                    break;
            }


            $t_result = Longman\TelegramBot\Request::editMessageText( $t_data_send );

            break;
//END CALLBACK
//REPLY_TO_MESSAGE
        case 'reply_to_message':
            if( !auth_ensure_telegram_user_authenticated( $t_message->getFrom()->getId() ) ) {
                break;
            }

            $t_bug_id = bug_get_id_from_message_id( $t_reply_to_message->getChat()->getId(), $t_reply_to_message->getMessageId() );
            $t_data   = [ 'add_comment' => [ 'set_bug' => $t_bug_id ] ];

            $t_data_send = telegram_add_comment( $t_data['add_comment'], $t_reply_to_message, $t_message );

            $t_message_id                       = $t_message->getMessageId();
            $t_orgl_chat_id                     = $t_message->getChat()->getId();
            $t_data_send['chat_id']             = $t_orgl_chat_id;
            $t_data_send['reply_to_message_id'] = $t_message_id;

            $t_result = Longman\TelegramBot\Request::sendMessage( $t_data_send );
            break;
    }
}