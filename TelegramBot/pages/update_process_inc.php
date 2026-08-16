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

                    case 'video':
                    case 'photo':
                    case 'document':
                        if( $t_file == NULL ) {
                            switch( $t_update_content->getType() ) {
                                case 'video':
                                    $t_file = $t_update_content->getVideo();
                                    break;
                                case 'photo':
                                    $t_content_photo = $t_update_content->getPhoto();
                                    $t_file          = $t_content_photo[count( $t_content_photo ) - 1];
                                    break;
                                case 'document':
                                    $t_file = $t_update_content->getDocument();
                                    break;
                            }
                        }

                        #A photo has no name at this point, its name checks run on the
                        #file path when the draft or the note picks the file up
                        $t_error_text = telegram_file_check( (string)$t_file->getFileName(), (int)$t_file->getFileSize() );

                        if( $t_error_text != '' ) {
                            $t_data = [
                                                      'chat_id'             => $t_update_content->getChat()->getId(),
                                                      'text'                => $t_error_text,
                                                      'reply_to_message_id' => $t_update_content->getMessageId()
                            ];
                            $t_result = Request::sendMessage( $t_data );
                            break;
                        }

                    case 'text':
                        $t_user_id = auth_get_current_user_id();

                        # A pending text question of the status change dialog takes the
                        # message first; the card ids are read before the answer is
                        # processed, the submit step drops them along with the draft
                        $t_status_card_chat_id    = plugin_config_get( 'status_change_draft_chat_id', NULL, FALSE, $t_user_id );
                        $t_status_card_message_id = plugin_config_get( 'status_change_draft_message_id', NULL, FALSE, $t_user_id );

                        $t_status_answer = telegram_status_change_text_answer( $t_update_content->getText() );

                        if( $t_status_answer !== NULL ) {
                            $t_data_del = [
                                'chat_id'    => $t_update_content->getChat()->getId(),
                                'message_id' => $t_update_content->getMessageId(),
                            ];
                            Longman\TelegramBot\Request::deleteMessage( $t_data_del );

                            $t_status_answer['chat_id']    = $t_status_card_chat_id;
                            $t_status_answer['message_id'] = $t_status_card_message_id;
                            $t_result = Longman\TelegramBot\Request::editMessageText( $t_status_answer );
                            break;
                        }

                        $t_bug_data_draft_raw = plugin_config_get( 'bug_data_draft', '', FALSE, $t_user_id );

                        //Create a new draft of the issue
                        if( is_blank( $t_bug_data_draft_raw ) ) {
                            $t_sendMessage_data = telegram_action_select( $t_update_content->getChat()->getId(), $t_update_content->getMessageId() );
                            $t_result = Request::sendMessage( $t_sendMessage_data );
                            break;
                        }
                        //Otherwise, continue to enter data into the current draft
                        $t_bug_data_draft_current_field_to_save = plugin_config_get( 'bug_data_draft_current_field_to_save', '', FALSE, $t_user_id );

                        $t_bug_data_draft = json_decode( $t_bug_data_draft_raw, TRUE );

                        $t_message_text    = $t_update_content->getText();
                        $t_custom_field_id = telegram_custom_field_pending_id( $t_bug_data_draft_current_field_to_save );
                        $t_error_text      = '';
                        $t_answer_taken    = FALSE;

                        if( $t_custom_field_id > 0 ) {
                            //A rejected value leaves the state of the field untouched, so the same question is asked again
                            telegram_custom_field_text_set(
                                                      $t_bug_data_draft,
                                                      $t_custom_field_id,
                                                      $t_message_text,
                                                      $t_error_text
                                    );

                            $t_answer_taken = TRUE;
                        } else if( !is_blank( $t_message_text ) ) {
                            $t_step = telegram_draft_text_step_pending( $t_bug_data_draft, $t_bug_data_draft_current_field_to_save );

                            if( $t_step !== NULL ) {
                                $t_bug_data_draft[$t_step] = $t_message_text;
                                plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), $t_user_id );

                                $t_answer_taken = TRUE;
                            }
                        }

                        $t_data_del = [
                            'chat_id'    => $t_update_content->getChat()->getId(),
                            'message_id' => $t_update_content->getMessageId(),
                        ];
                        $t_result = Longman\TelegramBot\Request::deleteMessage( $t_data_del );

                        //A message answering no question of the wizard leaves the draft card as it is
                        if( !$t_answer_taken ) {
                            break;
                        }

                        $t_next = telegram_draft_ask_next_step( $t_bug_data_draft );

                        if( $t_next['state'] == TELEGRAM_DRAFT_NEXT_SUBMIT ) {
                            $t_data_send = telegram_draft_submit( $t_bug_data_draft );
                        } else {
                            $t_inline_keyboard = $t_next['keyboard'];

                            if( $t_inline_keyboard === NULL ) {
                                $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
                            }

                            keyboard_draft_buttons_add( $t_inline_keyboard, $t_bug_data_draft );

                            $t_data_send = [
                                'chat_id'      => plugin_config_get( 'bug_data_draft_chat_id', NULL, FALSE, $t_user_id ),
                                'message_id'   => plugin_config_get( 'bug_data_draft_message_id', NULL, FALSE, $t_user_id ),
                                'text'         => telegram_draft_card_compose( $t_bug_data_draft, $t_next['suffix'], $t_error_text ),
                                'reply_markup' => $t_inline_keyboard,
                            ];
                        }

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

            //The placeholder buttons (the header of the calendar, the days of the week)
            //carry no action, such a press is only acknowledged to drop the spinner
            if( !is_array( $t_data ) ) {
                $t_update_content->answer();
                break;
            }
            
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

                case TelegrambotActions::UPDATE_BUG_TAG:
                    $t_data = telegram_update_bug( $t_data[TelegrambotActions::UPDATE_BUG_TAG] );

                    $t_data['chat_id']    = $t_update_content->getMessage()->getChat()->getId();
                    $t_data['message_id'] = $t_update_content->getMessage()->getMessageId();
                    $t_result = Request::editMessageText( $t_data );
                    break;

                case TelegrambotActions::CHANGE_STATUS_TAG:
                    $t_data = telegram_change_status( $t_data[TelegrambotActions::CHANGE_STATUS_TAG], $t_update_content );

                    $t_data['chat_id']    = $t_update_content->getMessage()->getChat()->getId();
                    $t_data['message_id'] = $t_update_content->getMessage()->getMessageId();
                    $t_result = Request::editMessageText( $t_data );
                    break;

                case TelegrambotActions::STOP_CHANGE_STATUS_TAG:
                    telegram_status_change_draft_clear();
                    //And next, change the action selection keyboard
                case TelegrambotActions::STOP_REPORT_ISSUE_TAG:
                    //The draft is dropped by the card driving it only: a press on another
                    //message would take away the draft being filled in somewhere else
                    if( telegram_draft_belongs_to_message( $t_update_content->getMessage()->getMessageId() ) ) {
                        plugin_config_delete( 'bug_data_draft', auth_get_current_user_id() );
                        plugin_config_delete( 'bug_data_draft_chat_id', auth_get_current_user_id() );
                        plugin_config_delete( 'bug_data_draft_message_id', auth_get_current_user_id() );
                        plugin_config_delete( 'bug_data_draft_text_msg', auth_get_current_user_id() );
                        plugin_config_delete( 'bug_data_draft_current_field_to_save', auth_get_current_user_id() );
                    } else if( $t_command[0] == TelegrambotActions::STOP_REPORT_ISSUE_TAG ) {
                        //Cancelling a status change touches no draft of its own, only the
                        //user asking for the draft to be removed is told about the refusal
                        telegram_callback_alert_set( plugin_lang_get( 'draft_other_message' ) );
                    }
                    //And next, change the action selection keyboard
                case TelegrambotActions::ACTION_SELECT_TAG:
                    $t_orgl_message = $t_update_content->getMessage();
                    $t_data         = telegram_action_select( $t_orgl_message->getChat()->getId(), $t_orgl_message->getMessageId() );
                    $t_result = Request::editMessageText( $t_data );
                    break;
            }

            //A callback query can only be answered once, so it is done after the whole callback is processed
            $t_update_content->answer( telegram_callback_alert_get() );

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