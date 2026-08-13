<?php
# Copyright (c) 2023 Grigoriy Ermolaev (igflocal@gmail.com)
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

if( !defined( 'UPDATE_PROCESS_INC_ALLOW' ) ) {
	return;
}

use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Request;

foreach ( $t_results as $t_result ) {
    
    $t_update_content = $t_result->getUpdateContent();

    if( $t_update_content == null ) {
        plugin_error( 'BAD_REQUEST' );
        continue;
    }
    
    //We check the binding of the telegram account to the current user account mantisbt and if it is not linked, 
    //then we issue an invitation to bind and skip processing the current callback.
    if( !auth_ensure_telegram_user_authenticated( $t_update_content->getFrom()->getId(), $t_update_content->getFrom()->getLanguageCode() ) ) {
        continue;
    }

    switch( true ) {
    //MESSAGE
        case $t_update_content instanceof Message:

            $t_orgl_message = $t_update_content->getReplyToMessage();

            if( $t_orgl_message == NULL ) {
    //NEW MESSAGE
                $t_file = NULL;
                switch( $t_update_content->getType() ) {

                    case 'command':
                            $t_data     = telegramMsg_run_command( $t_update_content );
                            $t_result   = Request::sendMessage( $t_data );
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
                            $t_result = Request::sendMessage( $t_data );
                            break;
                        }

                    case 'video':
                    case 'photo':
                    case 'text':
                        $t_bug_data_draft_raw = plugin_config_get( 'bug_data_draft', '', FALSE, auth_get_current_user_id() );

                        //Create a new draft of the issue
                        if( is_blank( $t_bug_data_draft_raw ) ) {
                            $t_sendMessage_data = telegram_action_select( $t_update_content->getChat()->getId(), $t_update_content->getMessageId() );
                            $t_result = Request::sendMessage( $t_sendMessage_data );
                            break;
                        }
                        //Otherwise, continue to enter data into the current draft
                        $t_bug_data_draft_text_msg = plugin_config_get( 'bug_data_draft_text_msg', '', FALSE, auth_get_current_user_id() );
                        $t_bug_data_draft_current_field_to_save = plugin_config_get( 'bug_data_draft_current_field_to_save', '', FALSE, auth_get_current_user_id() );

                        $t_bug_data_draft = json_decode($t_bug_data_draft_raw, TRUE);

                        if (is_blank($t_bug_data_draft['summary'])) {

                            $t_bug_data_draft['summary'] = $t_update_content->getText();
                            
                            $t_bug_data_draft_text_msg .= $t_bug_data_draft['summary'];
                            $t_bug_data_draft_text_msg .= PHP_EOL;
                            $t_bug_data_draft_text_msg .= lang_get( 'description' ) . ': ';
                            
                            plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                            plugin_config_set( 'bug_data_draft_text_msg', $t_bug_data_draft_text_msg, auth_get_current_user_id() );
                            

                            $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
                            $t_inline_keyboard->addRow( [
                                                  'text'          => '('. plugin_lang_get( 'keyboard_button_delete_draft' ) . ')',
                                                  'callback_data' => json_encode( array( 'stop_report_issue' => 1 ) )
                            ] );
                            
                            $t_data = [
                                'chat_id' => plugin_config_get('bug_data_draft_chat_id', NULL, FALSE, auth_get_current_user_id()),
                                'message_id' => plugin_config_get('bug_data_draft_message_id', NULL, FALSE, auth_get_current_user_id()),
                                'text' => $t_bug_data_draft_text_msg,
                                'reply_markup' => $t_inline_keyboard,
                            ];
                            $t_data_del['chat_id'] = $t_update_content->getChat()->getId();
                            $t_data_del['message_id'] = $t_update_content->getMessageId();

                            $t_result = Longman\TelegramBot\Request::deleteMessage($t_data_del);
                            $t_result = Longman\TelegramBot\Request::editMessageText($t_data);
                            break;
                        }
                        
                        if( is_blank ( $t_bug_data_draft['description'] ) ) {
                            $t_bug_data_draft['description'] = $t_update_content->getText();
                            plugin_config_set('bug_data_draft', json_encode($t_bug_data_draft), auth_get_current_user_id());

                            $t_bug_data_draft_text_msg .= $t_bug_data_draft['description'];
                            
//                            plugin_config_set( 'bug_data_draft_text_msg', $t_text, auth_get_current_user_id() );
                        }

                        if( key_exists( 'steps_to_reproduce', $t_bug_data_draft ) && is_blank( $t_bug_data_draft['steps_to_reproduce'] ) ) {
                            if( $t_bug_data_draft_current_field_to_save == 'steps_to_reproduce' ) {
                                
                                $t_bug_data_draft['steps_to_reproduce'] = $t_update_content -> getText();
                                plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                                
                                $t_bug_data_draft_text_msg .= $t_bug_data_draft['steps_to_reproduce'];
//                                plugin_config_set( 'bug_data_draft_text_msg', $t_bug_data_draft_text_msg, auth_get_current_user_id() );
                                
                            } else {
//                                $t_data = [
//                                    'chat_id' => $t_update_content->getChat()->getId(),
//                                    'text' => lang_get('steps_to_reproduce')
//                                ];

                                $t_bug_data_draft_text_msg .= PHP_EOL;
                                $t_bug_data_draft_text_msg .= lang_get( 'steps_to_reproduce' ) . ': ';

                                plugin_config_set( 'bug_data_draft_text_msg', $t_bug_data_draft_text_msg, auth_get_current_user_id() );
                                plugin_config_set( 'bug_data_draft_current_field_to_save', 'steps_to_reproduce', auth_get_current_user_id() );

                                $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
                                $t_inline_keyboard->addRow( [
                                                  'text'          => '(Удалить черновик)',
                                                  'callback_data' => json_encode( array( 'stop_report_issue' => 1 ) )
                                ] );
                            
                                $t_data = [
                                    'chat_id' => plugin_config_get( 'bug_data_draft_chat_id', NULL, FALSE, auth_get_current_user_id() ),
                                    'message_id' => plugin_config_get( 'bug_data_draft_message_id', NULL, FALSE, auth_get_current_user_id() ),
                                    'text' => $t_bug_data_draft_text_msg,
                                    'reply_markup' => $t_inline_keyboard,
                                ];
                                $t_data_del['chat_id'] = $t_update_content -> getChat() -> getId();
                                $t_data_del['message_id'] = $t_update_content -> getMessageId();

                                $t_result = Longman\TelegramBot\Request::deleteMessage( $t_data_del );
                                $t_result = Longman\TelegramBot\Request::editMessageText( $t_data );
                                break;
                            }
                        }

                        if( key_exists( 'additional_info', $t_bug_data_draft ) && is_blank( $t_bug_data_draft['additional_info'] ) ) {
                            if( $t_bug_data_draft_current_field_to_save == 'additional_info' ) {
                                
                                $t_bug_data_draft['additional_info'] = $t_update_content -> getText();
                                plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                                
                                $t_bug_data_draft_text_msg .= $t_bug_data_draft['additional_info'];
//                                plugin_config_set( 'bug_data_draft_text_msg', $t_bug_data_draft_text_msg, auth_get_current_user_id() );
                                
                            } else {
                                
                                $t_bug_data_draft_text_msg .= PHP_EOL;
                                $t_bug_data_draft_text_msg .= lang_get( 'additional_information' ) . ': ';

                                plugin_config_set( 'bug_data_draft_text_msg', $t_bug_data_draft_text_msg, auth_get_current_user_id() );
                                plugin_config_set( 'bug_data_draft_current_field_to_save', 'additional_info', auth_get_current_user_id() );

                                $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
                                $t_inline_keyboard->addRow( [
                                                  'text'          => '(Удалить черновик)',
                                                  'callback_data' => json_encode( array( 'stop_report_issue' => 1 ) )
                                ] );
                                
                                $t_data = [
                                    'chat_id'       => plugin_config_get( 'bug_data_draft_chat_id', NULL, FALSE, auth_get_current_user_id() ),
                                    'message_id'    => plugin_config_get( 'bug_data_draft_message_id', NULL, FALSE, auth_get_current_user_id() ),
                                    'text'          => $t_bug_data_draft_text_msg,
                                    'reply_markup' => $t_inline_keyboard,
                                ];
                                $t_data_del['chat_id'] = $t_update_content -> getChat() -> getId();
                                $t_data_del['message_id'] = $t_update_content -> getMessageId();

                                $t_result = Longman\TelegramBot\Request::deleteMessage( $t_data_del );
                                $t_result = Longman\TelegramBot\Request::editMessageText( $t_data );
                                break;
                                
                            }
                        }
                        
                        try {
                            $t_issue_id = telegram_bug_add( $t_bug_data_draft, $t_update_content -> getChat() -> getId(), $t_update_content -> getMessageId() );

                            $t_bug_data_draft_text_msg .= PHP_EOL;
                            $t_bug_data_draft_text_msg .= '=======================================';
                            $t_bug_data_draft_text_msg .= PHP_EOL;
                            $t_bug_data_draft_text_msg .= sprintf( plugin_lang_get( 'bug_creation_complete' ), lang_get( 'bug' ) ) . $t_issue_id;

                            $t_data_send = [
                                'chat_id' => plugin_config_get( 'bug_data_draft_chat_id', NULL, FALSE, auth_get_current_user_id() ),
                                'message_id' => plugin_config_get( 'bug_data_draft_message_id', NULL, FALSE, auth_get_current_user_id() ),
                                'text' => $t_bug_data_draft_text_msg,
                            ];
                        } catch ( Mantis\Exceptions\MantisException $t_error ) {

                            $t_params = $t_error -> getParams();
                            if( !empty( $t_params ) ) {
                                call_user_func_array( 'error_parameters', $t_params );
                            }

                            $t_error_text = error_string( $t_error -> getCode() );

                            $t_data_send = [
                                'chat_id' => plugin_config_get( 'bug_data_draft_chat_id', NULL, FALSE, auth_get_current_user_id() ),
                                'message_id' => plugin_config_get( 'bug_data_draft_message_id', NULL, FALSE, auth_get_current_user_id() ),
                                'text' => $t_error_text,
                            ];
                        }

                        plugin_config_delete( 'bug_data_draft', auth_get_current_user_id() );
                        plugin_config_delete( 'bug_data_draft_chat_id', auth_get_current_user_id() );
                        plugin_config_delete( 'bug_data_draft_message_id', auth_get_current_user_id() );
                        plugin_config_delete( 'bug_data_draft_text_msg', auth_get_current_user_id() );
                        plugin_config_delete( 'bug_data_draft_current_field_to_save', auth_get_current_user_id() );

                        $t_data_del['chat_id'] = $t_update_content -> getChat() -> getId();
                        $t_data_del['message_id'] = $t_update_content -> getMessageId();

                        $t_result = Longman\TelegramBot\Request::deleteMessage( $t_data_del );
                        $t_result = Longman\TelegramBot\Request::editMessageText( $t_data_send );
                        break;

                    default :
                        $t_data = [
                                                  'chat_id' => $t_update_content->getChat()->getId(),
                                                  'text'    => plugin_lang_get( 'error_content_type' )
                        ];
                        $t_result = RequestMantis::sendMessage( $t_data );
                        break;
                }
                break;
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
                
                $t_result = Request::sendMessage( $t_data );
                break;
    //END REPLY TO MESSAGE
            }
    //END MESSAGE

    //CALLBACK
        case $t_update_content instanceof CallbackQuery:

            $t_data = json_decode( $t_update_content->getData(), TRUE );

            if ($t_data == null) {
                plugin_error('BAD_REQUEST');
                break;
            }
            
            $t_update_content->answer();

            $t_command = array_keys( $t_data );

            switch( $t_command[0] ) {
                case TelegrambotActions::REPORT_BUG_TAG:
                    $t_data = telegram_bug_report( 
                                                    $t_data[ TelegrambotActions::REPORT_BUG_TAG ], 
                                                    $t_update_content 
                            );
                    $t_result = Request::editMessageText( $t_data );
                    break;

                case TelegrambotActions::ADD_COMMENT_TAG:
                    $t_data = telegram_add_comment( 
                                                    $t_data[TelegrambotActions::ADD_COMMENT_TAG], 
                                                    $t_update_content, 
                                                    $t_update_content->getMessage()->getReplyToMessage() 
                            );

                    $t_message_id   = $t_update_content->getMessage()->getMessageId();
                    $t_orgl_chat_id = $t_update_content->getMessage()->getReplyToMessage()->getChat()->getId();

                    $t_data['chat_id']    = $t_orgl_chat_id;
                    $t_data['message_id'] = $t_message_id;
                    $t_result = Request::editMessageText( $t_data );
                    break;

                case TelegrambotActions::STOP_REPORT_ISSUE_TAG:
                    plugin_config_delete( 'bug_data_draft', auth_get_current_user_id() );
                    plugin_config_delete( 'bug_data_draft_chat_id', auth_get_current_user_id() );
                    plugin_config_delete( 'bug_data_draft_message_id', auth_get_current_user_id() );
                    plugin_config_delete( 'bug_data_draft_text_msg', auth_get_current_user_id() );
                    plugin_config_delete( 'bug_data_draft_current_field_to_save', auth_get_current_user_id() );
                    //And next, change the action selection keyboard 
                case TelegrambotActions::ACTION_SELECT_TAG:
                    $t_orgl_message = $t_update_content->getMessage();
                    $t_data         = telegram_action_select( $t_orgl_message->getChat()->getId(), $t_orgl_message->getMessageId() );
                    $t_result = Request::editMessageText( $t_data );
                    break;
            }

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
}