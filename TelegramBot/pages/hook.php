<?php
# Copyright (c) 2019 Grigoriy Ermolaev (igflocal@gmail.com)
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

use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\CallbackQuery;

$f_token = gpc_get_string( 'token', '' );

if( $f_token != plugin_config_get( 'api_key' ) ) {
    plugin_log_event( 'ERROR! Wrong api key.' );
    exit();
}

telegram_session_start();

$t_post = json_decode( Longman\TelegramBot\Request::getInput(), true );

$t_update         = new Longman\TelegramBot\Entities\Update( $t_post );
$t_update_content = $t_update->getUpdateContent();

if( $t_update_content == null ) {
    plugin_error( 'BAD_REQUEST' );
    exit();
}

auth_ensure_telegram_user_authenticated( $t_update_content->getFrom()->getId() );

switch( true ) {
//MESSAGE
    case $t_update_content instanceof Message:

        $t_orgl_message = $t_update_content->getReplyToMessage();

        if( $t_orgl_message == NULL ) {
//NEW MESSAGE
            $t_file = NULL;
            switch( $t_update_content->getType() ) {

                case 'command':
                    switch( $t_update_content->getCommand() ) {

                        case 'start':
                            $t_data = [
                                                      'chat_id' => $t_update_content->getFrom()->getId(),
                                                      'text'    => sprintf(
                                                              plugin_lang_get( 'first_message' ), config_get( 'window_title' ) . ' ( ' . config_get( 'path' ) . ' )', ' ( ' . config_get( 'path' ) . plugin_page( 'account_telegram_prefs_page', TRUE ) . ' )'
                                                      ),
                            ];
                            break;

                        case 'stop':
                            $t_user_id = user_get_id_by_telegram_user_id( $t_update_content->getFrom()->getId() );

                            telegram_message_realatationship_delete( $t_update_content->getFrom()->getId() );
                            telegram_bot_user_mapping_delete( $t_user_id );

                            $t_data = [
                                                      'chat_id' => $t_update_content->getFrom()->getId(),
                                                      'text'    => plugin_lang_get( 'end_message' )
                            ];

                            break;

                        default:
                            $t_data = [
                                                      'chat_id' => $t_update_content->getFrom()->getId(),
                                                      'text'    => plugin_lang_get( 'command_not_found' )
                            ];
                    }
                    break;

                case 'document':
                    if( $t_file == NULL ) {
                        $t_file = $t_update_content->getDocument();
                    }

                    if( $t_file->getFileSize() > 20971520 ) {
                        $t_data = [
                                                  'chat_id'             => $t_update_content->getChat()->getId(),
                                                  'text'                => plugin_lang_get( 'error_file_size' ),
                                                  'reply_to_message_id' => $t_update_content->getMessageId()
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
                        $t_data = telegram_action_select( $t_update_content->getChat()->getId(), $t_update_content->getMessageId() );
                        break;
                    }

                    if( is_blank( $t_bug_data_draft['summary'] ) ) {

                        $t_bug_data_draft['summary'] = $t_update_content->getText();
                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_data = [
                                                  'chat_id' => $t_update_content->getChat()->getId(),
                                                  'text'    => plugin_lang_get( 'get_description' )
                        ];
                        break;
                    }
                    if( is_blank( $t_bug_data_draft['description'] ) ) {

                        $t_bug_data_draft['description'] = $t_update_content->getText();

                        $t_data = telegram_bug_add( $t_bug_data_draft, $t_update_content->getChat()->getId(), $t_update_content->getMessageId() );

                        break;
                    }

                default :
                    $t_data = [
                                              'chat_id' => $t_update_content->getChat()->getId(),
                                              'text'    => plugin_lang_get( 'error_content_type' )
                    ];
            }
//END NEW MESSAGE
        } else {
//REPLY TO MESSAGE
            $t_bug_id = bug_get_id_from_message_id( $t_orgl_message->getChat()->getId(), $t_orgl_message->getMessageId() );
            $t_data   = [ 'add_comment' => [ 'set_bug' => $t_bug_id ] ];

            $t_data = telegram_add_comment( $t_data['add_comment'], $t_orgl_message, $t_update_content );

            $t_message_id                  = $t_update_content->getMessageId();
            $t_orgl_chat_id                = $t_update_content->getChat()->getId();
            $t_data['chat_id']             = $t_orgl_chat_id;
            $t_data['reply_to_message_id'] = $t_message_id;
//END REPLY TO MESSAGE
        }

        $t_result = RequestMantis::sendMessage( $t_data );
        break;

//CALLBACK
    case $t_update_content instanceof CallbackQuery:

        $t_data = json_decode( $t_update_content->getData(), TRUE );

        $t_update_content->answer();

        $t_command = array_keys( $t_data );

        switch( $t_command[0] ) {
            case 'rb':
                $t_data = telegram_bug_report( $t_data['rb'], $t_update_content );
                break;

            case 'add_comment':
                $t_data = telegram_add_comment( $t_data['add_comment'], $t_update_content, $t_update_content->getMessage()->getReplyToMessage() );

                $t_message_id   = $t_update_content->getMessage()->getMessageId();
                $t_orgl_chat_id = $t_update_content->getMessage()->getReplyToMessage()->getChat()->getId();

                $t_data['chat_id']    = $t_orgl_chat_id;
                $t_data['message_id'] = $t_message_id;
                break;

            case 'action_select':
                $t_orgl_message = $t_update_content->getMessage();
                $t_data         = telegram_action_select( $t_orgl_message->getChat()->getId(), $t_orgl_message->getMessageId() );
                break;
        }

        $t_result = Longman\TelegramBot\Request::editMessageText( $t_data );

        break;
//END CALLBACK

    default:
        plugin_log_event( 'ERROR! Bad request. Update type "' . get_class( $t_update_content ) . '" is not implemented.' );

        $t_data = [
                                  'chat_id' => $t_update_content->getFrom()->getId(),
                                  'text'    => plugin_lang_get( 'error_content_type' ),
        ];

        RequestMantis::sendMessage( $t_data );
}