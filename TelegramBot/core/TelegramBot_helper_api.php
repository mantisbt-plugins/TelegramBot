<?php

# Copyright (c) 2024 Grigoriy Ermolaev (igflocal@gmail.com)
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

function helper_ensure_telegram_bot_registred_confirmed( $p_message ) {
    if( true == gpc_get_string( '_confirmed', FALSE ) ) {
        return gpc_get_string( '_confirmed' );
    }

    layout_page_header();
    layout_page_begin();

    echo '<div class="col-md-12 col-xs-12">';
    echo '<div class="space-10"></div>';
    echo '<div class="alert alert-warning center">';
    echo '<p class="bigger-110">';
    echo "\n" . $p_message . "\n";
    echo '</p>';
    echo '<div class="space-10"></div>';

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );
    echo '<input type="hidden" name="_confirmed" value="1" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'user_relationship_yes' ) . '" />';
    echo "\n</form>";

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );
    echo '<input type="hidden" name="_confirmed" value="0" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'user_relationship_no' ) . '" />';
    echo "\n</form>";


    echo '<div class="space-10"></div>';
    echo '</div></div>';

    layout_page_end();
    exit;
}

function bugnote_add_from_telegram( $p_bug_id, $p_text = '', $p_files = array(), $p_duration = '0:00' ) {

    $t_query = array( 'issue_id' => $p_bug_id );

    if( count( $p_files ) > 0 && is_blank( $p_text ) && helper_duration_to_minutes( $p_duration ) == 0 ) {
        $t_payload = array(
                                  'files' => helper_array_transpose( $p_files )
        );

        $t_data = array(
                                  'query'   => $t_query,
                                  'payload' => $t_payload,
        );

        $t_command = new IssueFileAddCommand( $t_data );
        $t_command->execute();

        plugin_history_log( $p_bug_id, lang_get("download_attachments"), "", lang_get("file_added") );

    } else {
        $t_payload = array(
                                  'text'          => $p_text,
                                  'view_state'    => array(
                                                            'id' => VS_PUBLIC
                                  ),
                                  'time_tracking' => array(
                                                            'duration' => $p_duration
                                  ),
                                  'files'         => helper_array_transpose( $p_files )
        );

        $t_data = array(
                                  'query'   => $t_query,
                                  'payload' => $t_payload,
        );

        $t_command = new IssueNoteAddCommand( $t_data );
        $t_noteId = $t_command->execute();
        
        plugin_history_log( $p_bug_id, lang_get( "add_notes" ), "", lang_get( "bugnote_added" ) . ": " . $t_noteId['id']);
       
    }
}

function telegram_bug_report( $p_current_action, Longman\TelegramBot\Entities\CallbackQuery $p_callback_query ) {

    $t_bug_data_draft = json_decode( plugin_config_get( 'bug_data_draft', NULL, FALSE, auth_get_current_user_id() ), TRUE );

    $t_callback_msg_id   = $p_callback_query->getMessage()->getMessageId();
    $t_orgl_chat_id      = $p_callback_query->getMessage()->getChat()->getId();
    $t_callback_msg_text = $p_callback_query->getMessage()->getText();

    $t_orgl_message = $p_callback_query->getMessage()->getReplyToMessage();
    $t_content_type = $t_orgl_message->getType();

    if( $t_bug_data_draft == NULL ) {

        $t_issue = array(
                                  'project'     => '',
                                  'reporter'    => '',
                                  'summary'     => '',
                                  'description' => '',
        );

        $t_fields = config_get( 'bug_report_page_fields' );
        $t_fields = columns_filter_disabled( $t_fields );

        $t_fields_temp = array_fill_keys( $t_fields, '' );

        $t_final_fields = array_merge( $t_issue, $t_fields_temp );
        
        $t_final_fields['custom_fields'] = array();

        plugin_config_set( 'bug_data_draft', json_encode( $t_final_fields ), auth_get_current_user_id() );
        plugin_config_set( 'bug_data_draft_chat_id', $t_orgl_chat_id, auth_get_current_user_id() );
        plugin_config_set( 'bug_data_draft_message_id', $t_callback_msg_id, auth_get_current_user_id() );
        
        $t_bug_data_draft = $t_final_fields;
    }
    
    $t_inline_keyboard = null;

    switch( $t_content_type ) {
        case 'video':
        case 'photo':
        case 'document':
            if( array_key_exists( 'attachments', $t_bug_data_draft ) ) {

                switch( $t_content_type ) {
                    case 'video':
                        $t_file_orgl     = $t_orgl_message->getVideo();
                        break;
                    case 'photo':
                        $t_content_photo = $t_orgl_message->getPhoto();
                        $t_file_orgl     = $t_content_photo[count( $t_content_photo ) - 1];

                        break;
                    case 'document':
                        $t_file_orgl = $t_orgl_message->getDocument();
                        break;
                }

                $t_download = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_file_orgl->getFileId() ] );

                $t_file = $t_download->getResult();

                $t_data_send_action = [
                                          'chat_id' => $t_orgl_chat_id,
                                          'action'  => 'upload_document'
                ];
                $t_rttt             = Longman\TelegramBot\Request::sendChatAction( $t_data_send_action );
                $t_upload_is_error  = FALSE;
                try {
                    Longman\TelegramBot\Request::downloadFile( $t_file );
                } catch( Longman\TelegramBot\Exception\TelegramException $e ) {
                    $t_data_send       = [
                                              'chat_id'    => $t_orgl_chat_id,
                                              'message_id' => $t_callback_msg_id,
                                              'text'       => $e->getMessage()
                    ];
                    $t_upload_is_error = TRUE;
                    break;
                }
                $t_file_path = plugin_config_get( 'download_path' ) . $t_file->getFilePath();

                $t_bug_data_draft['attachments'] = [
                                          'browser_upload' => [ 0 => FALSE ],
                                          'tmp_name'       => [ 0 => $t_file_path ],
                                          'name'           => $t_file_orgl->getFileName() == NULL ? [ 0 => $t_file->getFilePath() ] : [ 0 => $t_file_orgl->getFileName() ]
                ];
            }

        case 'text':

            switch( array_keys( $p_current_action )[0] ) {
//PROJECT
                case TelegrambotActions::GET_PROJECT:
                    $t_current_project = 0;
                    $t_project_id      = $t_current_project;

                    # If all projects, use default project if set
                    $t_default_project = user_pref_get_pref( auth_get_current_user_id(), 'default_project' );
                    if( ALL_PROJECTS == $t_project_id && ALL_PROJECTS != $t_default_project ) {
                        $p_current_action             = array();
                        $p_current_action['sp']['id'] = $t_default_project;

                        $t_callback_msg_text = lang_get( 'email_project' ) . ': ';
                    } else {
                        $t_inline_keyboard   = keyboard_projects_get(
                                                        $p_current_action[TelegrambotActions::GET_PROJECT]['id'],
                                                        $p_current_action[TelegrambotActions::GET_PROJECT]['p'],
                                                        $p_current_action[TelegrambotActions::GET_PROJECT]['fp']
                                                        );
                        $t_callback_msg_text = $p_callback_query->getMessage()->getText();
                        $t_text              = lang_get( 'email_project' ) . ': ';
                        break;
                    }

                case TelegrambotActions::SET_PROJECT:
                    $t_bug_data_draft['project'] = $p_current_action[TelegrambotActions::SET_PROJECT]['id'];
                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    $t_text = $t_callback_msg_text . ' ' . project_get_field( $t_bug_data_draft['project'], 'name' );

//CATEGORY
                case TelegrambotActions::GET_CATEGORY:
                    if( key_exists( 'category_id', $t_bug_data_draft ) ) {
                        $t_inline_keyboard = keyboard_category_get( $t_bug_data_draft['project'] );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'category' ) . ': ';

                        break;
                    }

                case TelegrambotActions::SET_CATEGORY:
                    if( key_exists( 'category_id', $t_bug_data_draft ) ) {
                            $t_bug_data_draft['category'] = $p_current_action[TelegrambotActions::SET_CATEGORY]['id'];

                            plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                            $t_text = $t_callback_msg_text . ' ' . ( $t_bug_data_draft['category'] != 0 ? category_get_name( $t_bug_data_draft['category'] ) : lang_get( 'no_category' ) );
                    }
                
//REPRODUCIBILITY
                case TelegrambotActions::GET_REPRODUCIBILITY:
                    if( key_exists( 'reproducibility', $t_bug_data_draft ) ) {
                        $t_default_reproducibility = (int)config_get( 'default_bug_reproducibility' );
                            
                        $t_inline_keyboard = keyboard_enum_string_get( 'reproducibility', $t_default_reproducibility );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'reproducibility' ) . ': ';

                        break;
                    }
                case TelegrambotActions::SET_REPRODUCIBILITY:
                    if( key_exists( 'reproducibility', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['reproducibility'] = $p_current_action[TelegrambotActions::SET_REPRODUCIBILITY]['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . get_enum_element( 'reproducibility', $t_bug_data_draft['reproducibility'] );
                    }

//ETA
                case TelegrambotActions::GET_ETA:
                    if( key_exists( 'eta', $t_bug_data_draft ) ) {
                        $t_default_eta = (int)config_get( 'default_bug_eta' );
                            
                        $t_inline_keyboard = keyboard_enum_string_get( 'eta', $t_default_eta );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'eta' ) . ': ';

                        break;
                    }
                case TelegrambotActions::SET_ETA:
                    if( key_exists( 'eta', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['eta'] = $p_current_action['seta']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . get_enum_element( 'eta', $t_bug_data_draft['eta'] );
                    }

//SEVERITY
                case 'gseverity':
                    if( key_exists( 'severity', $t_bug_data_draft ) ) {
                        $t_default_severity = (int)config_get( 'default_bug_severity' );
                        $t_inline_keyboard = keyboard_enum_string_get( 'severity', $t_default_severity );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'severity' ) . ': ';

                        break;
                    }
                case 'sseverity':
                    if( key_exists( 'severity', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['severity'] = $p_current_action['sseverity']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . get_enum_element( 'severity', $t_bug_data_draft['severity'] );
                    }
//PRIORITY
                case 'gpriority':
                    if( key_exists( 'priority', $t_bug_data_draft ) ) {
                        $t_default_priority = (int)config_get( 'default_bug_priority' );
                        $t_inline_keyboard = keyboard_enum_string_get( 'priority', $t_default_priority );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'priority' ) . ': ';

                        break;
                    }
                case 'spriority':
                    if( key_exists( 'priority', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['priority'] = $p_current_action['spriority']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . get_enum_element( 'priority', $t_bug_data_draft['priority'] );
                    }

//DUE_DATE
                case 'gduedate':
                    if( key_exists( 'due_date', $t_bug_data_draft ) && access_has_project_level( config_get( 'due_date_update_threshold' ), $t_bug_data_draft['project'], auth_get_current_user_id() ) ) {
                        if( array_key_exists( 'gduedate', $p_current_action ) && array_key_exists( 'date', $p_current_action['gduedate'] ) ) {
                            $t_calendar = new TelegramBotInlineKeyboardCalendar( $p_current_action['gduedate']['date'] );
                            $t_inline_keyboard = $t_calendar->getKeyboard('duedate');

                            $t_text = $t_callback_msg_text;

                            break;
                        } else {
                            $t_calendar = new TelegramBotInlineKeyboardCalendar( date( "Y-n", time() ) );
                            $t_inline_keyboard = $t_calendar->getKeyboard('duedate');

                            $t_text .= PHP_EOL;
                            $t_text .= lang_get( 'due_date' ) . ': ';

                            break;
                        }
                    }
                case 'sduedate':
                    if( key_exists( 'due_date', $t_bug_data_draft ) && access_has_project_level( config_get( 'due_date_update_threshold' ), $t_bug_data_draft['project'], auth_get_current_user_id() ) ) {
                        
                        $t_bug_data_draft['due_date'] = date_strtotime( $p_current_action['sduedate'][0] );

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . date( config_get( 'normal_date_format' ), $t_bug_data_draft['due_date'] );
                    }

//$t_show_platform || $t_show_os || $t_show_os_version
//Implemented only the choice of platform from the available list. 
//TODO: Implement the ability to select options by severity and/or manually fill in with arbitrary data ( config_get( 'allow_freetext_in_profile_fields' ) == OFF )
                case 'gplatform':
                    if( 
                            ( config_get( 'enable_profiles' ) && key_exists( 'platform', $t_bug_data_draft ) ) 
                            || ( config_get( 'enable_profiles' ) && key_exists( 'os', $t_bug_data_draft ) )
                            || ( config_get( 'enable_profiles' ) && key_exists( 'os_build', $t_bug_data_draft ) ) 
                       ) 
                       {
                            if( count( profile_get_all_for_user( auth_get_current_user_id() ) ) > 0 ) {
                                $t_profile_id = 0;
                                $t_inline_keyboard = keyboard_profile_option_list( auth_get_current_user_id(), $t_profile_id );
                                
                                keyboard_skip_button_add( $t_inline_keyboard, array( 'rb' => array( 'splatform' => array( 'id' => 'skip' ) ) ) );
                                
                                $t_text .= PHP_EOL;
                                $t_text .= lang_get( 'select_profile' ) . ': ';
                                break;
                            }
                       }
                case 'splatform':
                    if( 
                            ( config_get( 'enable_profiles' ) && key_exists( 'platform', $t_bug_data_draft ) ) 
                            || ( config_get( 'enable_profiles' ) && key_exists( 'os', $t_bug_data_draft ) )
                            || ( config_get( 'enable_profiles' ) && key_exists( 'os_build', $t_bug_data_draft ) ) 
                       ) 
                       {
                            if( count( profile_get_all_for_user( auth_get_current_user_id() ) ) > 0 ) {
                                if( $p_current_action['splatform']['id'] == 'skip' ) {
                                        $t_text = $t_callback_msg_text;
                                } else {
                                        $t_bug_data_draft['profile'] = $p_current_action['splatform']['id'];

                                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                                        $t_text = $t_callback_msg_text . ' ' . profile_get_name( $p_current_action['splatform']['id'] );
                                }
                            }
                       }
//$t_show_product_version
                case 'gpversion':
                    if( version_should_show_product_version( $t_bug_data_draft['project'] ) && key_exists( 'product_version', $t_bug_data_draft ) ) {
                            $t_product_version_released_mask = VERSION_RELEASED;

                            if( access_has_project_level( config_get( 'report_issues_for_unreleased_versions_threshold' ) ) ) {
                                    $t_product_version_released_mask = VERSION_ALL;
                            }
                            
                            $t_inline_keyboard = keyboard_version_option_list( '', $t_bug_data_draft['project'], $t_product_version_released_mask, 'spversion' );
                            
                            keyboard_skip_button_add( $t_inline_keyboard, array( 'rb' => array( 'spversion' => array( 'version' => 'skip' ) ) ) );
                            
                            $t_text .= PHP_EOL;
                            $t_text .= lang_get( 'product_version' ) . ': ';
                            break;
                    }
                case 'spversion':
                    if( version_should_show_product_version( $t_bug_data_draft['project'] ) && key_exists( 'product_version', $t_bug_data_draft ) ) {
                            if( $p_current_action['spversion']['version'] == 'skip' ) {
                                    $t_text = $t_callback_msg_text;
                            } else {
                                    $t_bug_data_draft['product_version'] = $p_current_action['spversion']['version'];

                                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                                    $t_text = $t_callback_msg_text . ' ' . $t_bug_data_draft['product_version'];
                            }
                    }
//TODO: $t_show_product_build Text area
//HANDLER
                case 'ghandler':
                    if( key_exists( 'handler', $t_bug_data_draft ) && access_has_project_level( config_get( 'update_bug_assign_threshold' ) ) ) {
                        $t_inline_keyboard = keyboard_handler_get( $t_bug_data_draft['project'] );

                        keyboard_skip_button_add( $t_inline_keyboard, array( 'rb' => array( 'shandler' => array( 'id' => 0 ) ) ) );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'issue_handler' ) . ': ';

                        break;
                    }
                case 'shandler':
                    if( key_exists( 'handler', $t_bug_data_draft ) && access_has_project_level( config_get( 'update_bug_assign_threshold' ) ) ) {
                            if( $p_current_action['shandler']['id'] === 0 ) {
                                    $t_text = $t_callback_msg_text;
                            } else {
                                    $t_bug_data_draft['handler'] = $p_current_action['shandler']['id'];

                                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                                    $t_text = $t_callback_msg_text . ' ' . user_get_name( $t_bug_data_draft['handler'] );
                            }
                    }
//TODO: $t_show_monitors (new element)
//STATUS
                case 'gstatus':
                    if( key_exists( 'status', $t_bug_data_draft ) ) {
                        $t_inline_keyboard = keyboard_status_get( $t_bug_data_draft['project'] );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'status' ) . ': ';

                        break;
                    }
                case 'sstatus':
                    if( key_exists( 'status', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['status'] = $p_current_action['sstatus']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . get_enum_element( 'status', $t_bug_data_draft['status'] );
                    }

//RESOLUTION
                case 'gresolution':
                    if( key_exists( 'resolution', $t_bug_data_draft ) ) {
                        $t_inline_keyboard = keyboard_enum_string_get( 'resolution' );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'resolution' ) . ': ';

                        break;
                    }
                case 'sresolution':
                    if( key_exists( 'resolution', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['resolution'] = $p_current_action['sresolution']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . get_enum_element( 'resolution', $t_bug_data_draft['resolution'] );
                    }

//TARGET_VERSION
                case 'gtargetv':
                    if(
                            version_should_show_product_version( $t_bug_data_draft['project'] ) 
                            && key_exists( 'target_version', $t_bug_data_draft ) 
                            && access_has_project_level( config_get( 'roadmap_update_threshold' ) ) 
                            ) {
                        $t_inline_keyboard = keyboard_version_option_list( '', $t_bug_data_draft['project'], VERSION_FUTURE, 'stargetv' );

                        keyboard_skip_button_add( $t_inline_keyboard, array( 'rb' => array( 'stargetv' => array( 'version' => 'skip' ) ) ) );
                        
                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'target_version' ) . ': ';

                        break;
                    }
                case 'stargetv':
                    if(
                            version_should_show_product_version( $t_bug_data_draft['project'] ) 
                            && key_exists( 'target_version', $t_bug_data_draft ) 
                            && access_has_project_level( config_get( 'roadmap_update_threshold' ) ) 
                            ) {
                            if( $p_current_action['stargetv']['version'] == 'skip' ) {
                                        $t_text = $t_callback_msg_text;
                            } else {
                                        $t_bug_data_draft['target_version'] = $p_current_action['stargetv']['version'];

                                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                                        $t_text = $t_callback_msg_text . ' ' . $t_bug_data_draft['target_version'];
                            }
                    }
//custom_fields buttons type
//                case 'scf':
//                        if( key_exists( 'scf', $p_current_action ) ) {
//                                $t_value_id = $p_current_action['scf'];
//                                $t_definition = custom_field_get_definition( key( $t_value_id ) );
//                                $t_value = '';
//                                
//                                switch( $t_definition['type'] ) {
//                                        case CUSTOM_FIELD_TYPE_MULTILIST:
//                                        case CUSTOM_FIELD_TYPE_CHECKBOX:
//                                                # ensure that the default is an array, if set
//                                                if( ( $p_default !== null ) && !is_array( $p_default ) ) {
//                                                        $p_default = array( $p_default );
//                                                }
//                                                $t_values = gpc_get_string_array( $p_var_name, $p_default );
//                                                if( is_array( $t_values ) ) {
//                                                        return implode( '|', $t_values );
//                                                } else {
//                                                        return '';
//                                                }
//                                                
//                                                
//                                                
//                                                $t_inline_keyboard = telegrambot_print_custom_field_input( $t_def, null, $t_def['require_report'] );
//                                                
//                                                break;
//                                        case CUSTOM_FIELD_TYPE_DATE:
//                                                $t_value = strtotime( $t_value_id[key( $t_value_id )] );
//                                                break;
//                                        default:
//                                                $t_value = $t_value_id[key( $t_value_id )];
//                                }
//                                
////                                $t_values = explode( '|', custom_field_prepare_possible_values( $t_definition['possible_values'] ) );
//                                
//                                $t_bug_data_draft['custom_fields'][key( $t_value_id )] = $t_value;
//                                
//                                plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
//                                
//                                $t_text = $t_callback_msg_text . ' ' . $t_value_id[key( $t_value_id )];
//                                
//                        }
//                // Change custom field date 
//                case 'gcf':                        
//                case 'skipcf':
//                        //Change date request
//                        if( key_exists( 'gcf', $p_current_action ) ) {
//                                $t_request_cfid = key( $p_current_action['gcf'] );
//                                $t_request_date = $p_current_action['gcf'][$t_request_cfid];
//                                
//                                $t_calendar = new TelegramBotInlineKeyboardCalendar( $t_request_date );
//                                $t_inline_keyboard = $t_calendar->getKeyboard( 'cf', $t_request_cfid );
//                                
//                                $t_def = custom_field_get_definition( $t_request_cfid );
//                                
//                                if( $t_def['default_value'] != 0 ) {
//                                        $t_inline_keyboard->addRow( [
//                                              'text'          => $t_def['default_value'] . ' (' . lang_get( 'custom_field_default_value' ) . ')',
//                                              'callback_data' => json_encode( array( 'rb' => array( 'scf' => array( $t_request_cfid => $t_def['default_value'] ) ) ) )
//                                        ] );
//                                }
//        
//                                if( !$t_def['require_report'] ) {
//                                        keyboard_skip_button_add( $t_inline_keyboard, array( 'rb' => array( 'skipcf' => $t_request_cfid ) ) );
//                                }
//
//                                $t_text = $t_callback_msg_text;
//                                break;
//                        }
//                        if( key_exists( 'skipcf', $p_current_action ) ) {
//                               $t_skip_cf_id = $p_current_action['skipcf'];
//                        } else {
//                               $t_skip_cf_id = 0;
//                        }
//
//                        //Other request
//                        $t_related_custom_field_ids = custom_field_get_linked_ids( $t_bug_data_draft['project'] );
//                        
//                        $t_break_switch = false;
//                    
//                        foreach( $t_related_custom_field_ids as $t_id ) {
//                                if( $t_skip_cf_id == $t_id ) {
//                                        $t_text = $t_callback_msg_text;
//                                        continue; 
//                                }
//                                
//                                if( !key_exists( $t_id, $t_bug_data_draft['custom_fields'] ) ) {
//                                        $t_def = custom_field_get_definition( $t_id );
//                                        
//                                        if( ( $t_def['display_report'] || $t_def['require_report'] ) && custom_field_has_write_access_to_project( $t_id, $t_bug_data_draft['project'] ) ) {
//
//                                                $t_inline_keyboard = telegrambot_print_custom_field_input( $t_def, null, $t_def['require_report'] );
//  
//                                                $t_text .= PHP_EOL;
//                                                $t_text .= lang_get_defaulted( $t_def['name'] ) . ': ';
//
//                                                $t_bug_data_draft['custom_fields'][$t_id] = array();
//                                                plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
//
//                                                $t_break_switch = true;
//
//                                                break;
//                                        }
//                                } # end if( !key_exists( $t_id, $t_bug_data_draft['custom_fields'] ) )
//                        } # end foreach( $t_related_custom_field_ids as $t_id )
//
//                        if( $t_break_switch ) {
//                                break;
//                        }
                    
//TODO: $t_show_tags
//SUMMARY            
                case 'gsummary':

                    $t_text .= PHP_EOL;
                    $t_text .= lang_get( 'summary' ) . ': ';

                    if( !is_blank( $p_callback_query->getMessage()->getReplyToMessage()->getText() ) ) {
                        $t_summary = $p_callback_query->getMessage()->getReplyToMessage()->getText();
                    } else if( !is_blank( $t_orgl_message->getCaption() ) ) {
                        $t_summary = $t_orgl_message->getCaption();
                    } else {

//                        $t_text .= PHP_EOL;
//                        $t_text .= '----------------------------';
//                        $t_text .= PHP_EOL;
//                        $t_text .= plugin_lang_get( 'get_summary' );

                        break;
                    }


                case 'ssummary':
                    $t_bug_data_draft['summary'] = $t_summary;

                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    $t_text .= $t_bug_data_draft['summary'];

//DESCRIPTION
                case 'gdescription':

                    $t_text .= PHP_EOL;
                    $t_text .= lang_get( 'description' ) . ': ';
//                    $t_text .= PHP_EOL;
//                    $t_text .= '----------------------------';
//                    $t_text .= PHP_EOL;
//                    $t_text .= plugin_lang_get( 'get_description' );
                    break;

                case 'sdescription':
                    $t_bug_data_draft['description'] = $p_callback_query->getMessage()->getReplyToMessage()->getText();

                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    $t_text .= $t_bug_data_draft['description'];
            }
            
            if( is_null( $t_inline_keyboard )) {
                $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
            }
            $t_inline_keyboard->addRow( [
                                  'text'          => '(Удалить черновик)',
                                  'callback_data' => json_encode( array( 'stop_report_issue' => 1 ) )
            ] );

            $t_data_send = [
                                      'chat_id'      => $t_orgl_chat_id,
                                      'message_id'   => $t_callback_msg_id,
                                      'text'         => $t_text,
                                      'reply_markup' => $t_inline_keyboard,
            ];
            plugin_config_set( 'bug_data_draft_text_msg', $t_text, auth_get_current_user_id() );
            break;

        default :
            $t_data_send = [
                                      'chat_id' => $t_orgl_chat_id,
                                      'text'    => plugin_lang_get( 'error_content_type' )
            ];
            break;
    }


    return $t_data_send;
}

function telegram_add_comment( $p_current_action, $p_message, $p_reply_to_message ) {

    $t_command = array_keys( $p_current_action );

    switch( $t_command[0] ) {
        case 'get_default_category':
            $t_inline_keyboard = telegram_bot_get_keyboard_default_filter();
            $t_data_send       = [
                                      'text'         => plugin_lang_get( 'bug_section_select' ),
                                      'reply_markup' => $t_inline_keyboard,
            ];
            break;

        case 'get_bugs':
            $t_command_get_bugs = array_keys( $p_current_action['get_bugs'] );

            switch( $t_command_get_bugs[0] ) {
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

            $t_inline_keyboard = keyboard_bugs_get( $t_custom_filter, $p_current_action['get_bugs'][$t_command_get_bugs[0]]['page'] );
            $t_data_send       = [
                                      'text'         => plugin_lang_get( 'bug_select' ),
                                      'reply_markup' => $t_inline_keyboard,
            ];
            break;

        case 'set_bug':
            $t_bug_id = $p_current_action['set_bug'];
            $t_upload_is_error = FALSE;

            $t_orgl_message = $p_reply_to_message;
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
                            $t_file_orgl     = $t_content_photo[count( $t_content_photo ) - 1];
                            break;
                        case 'document':
                            $t_file_orgl     = $t_orgl_message->getDocument();
                            break;
                    }

                    $t_download = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_file_orgl->getFileId() ] );

                    $t_file = $t_download->getResult();

//                    $t_data_send_action = [
//                                              'chat_id' => $t_orgl_chat_id,
//                                              'action'  => 'upload_document'
//                    ];
//                    $t_rttt             = Longman\TelegramBot\Request::sendChatAction( $t_data_send_action );
                    
                    try {
                        Longman\TelegramBot\Request::downloadFile( $t_file );
                    } catch( Longman\TelegramBot\Exception\TelegramException $e ) {
                        $t_data_send       = [
                                                  'text' => $e->getMessage()
                        ];
                        $t_upload_is_error = TRUE;
                        break;
                    }
                    $t_file_path = plugin_config_get( 'download_path' ) . $t_file->getFilePath();

                    $t_file_for_attach = [
                                              'browser_upload' => [ 0 => FALSE ],
                                              'tmp_name'       => [ 0 => $t_file_path ],
                                              'name'           => $t_file_orgl->getFileName() == NULL ? [ 0 => $t_file->getFilePath() ] : [ 0 => $t_file_orgl->getFileName() ]
                    ];
                    $t_text            = $t_orgl_message->getCaption();
                    break;

                case 'text':
                    $t_text            = $t_orgl_message->getText();
                    $t_file_for_attach = array();
                    break;
            }
            //END SWITCH 'CONTENT TYPE'
//            $inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

            if( $t_upload_is_error ) {
                break;
            }

            try {
                bugnote_add_from_telegram( $t_bug_id, $t_text, $t_file_for_attach );

                $t_data_send = [
                                          'text'         => plugin_lang_get( 'content_upload_complete' ) . $p_current_action['set_bug'],
//                                          'reply_markup' => keyboard_bug_status_change_is( $t_bug_id )
                ];
            } catch( Mantis\Exceptions\MantisException $t_error ) {
                if( isset( $t_file_path ) ) {
                    $t_file_is_deleted = unlink( $t_file_path );
                }

                $t_params = $t_error->getParams();
                if( !empty( $t_params ) ) {
                    call_user_func_array( 'error_parameters', $t_params );
                }

                $t_error_text = error_string( $t_error->getCode() );
                $t_data_send  = [
                                          'text' => $t_error_text
                ];
            }
            break;
    }
    return $t_data_send;
}

function telegram_action_select( $p_orgl_chat_id, $p_callback_msg_id ) {

    $t_inline_keyboard = keyboard_get_menu_operations();
    $t_data_send       = [
                              'chat_id'             => $p_orgl_chat_id,
                              'reply_to_message_id' => $p_callback_msg_id,
                              'message_id'          => $p_callback_msg_id,
                              'text'                => plugin_lang_get( 'action_select' ),
                              'reply_markup'        => $t_inline_keyboard,
    ];
    return $t_data_send;
}

function telegram_lang_map_auto( $p_lang_code = null) {
	$t_lang = config_get_global( 'fallback_language' );

	if( isset( $p_lang_code ) ) {
		$t_auto_map = config_get_global( 'language_auto_map' );

		# Expand language map
		$t_auto_map_exp = array();
		foreach( $t_auto_map as $t_encs => $t_enc_lang ) {
			$t_encs_arr = explode( ',', $t_encs );

			foreach( $t_encs_arr as $t_enc ) {
				$t_auto_map_exp[trim( $t_enc )] = $t_enc_lang;
			}
		}

		# Find encoding
		if( isset( $t_auto_map_exp[$p_lang_code] ) ) {
                        $t_valid_langs = config_get( 'language_choices_arr' );
			$t_found_lang = $t_auto_map_exp[$p_lang_code];

                        if( in_array( $t_found_lang, $t_valid_langs, true ) ) {
				$t_lang = $t_found_lang;
			}
		}
	}

	return $t_lang;
}

function telegram_lang_get_default( $p_lang_code = null ) {
	global $g_active_language;

	$t_lang = false;

	# Confirm that the user's language can be determined
	if( function_exists( 'auth_is_user_authenticated' ) && auth_is_user_authenticated() ) {
		$t_lang = user_pref_get_language( auth_get_current_user_id() );
	}

	# Otherwise fall back to default
	if( !$t_lang ) {
		$t_lang = config_get_global( 'default_language' );
	}

	if( $t_lang == 'auto' ) {
		$t_lang = telegram_lang_map_auto( $p_lang_code );
	}

	# Remember the language
	$g_active_language = $t_lang;

	return $t_lang;
}

/**
 * Attach a "checked" attribute to a HTML element if $p_var === $p_val or
 * a {value within an array passed via $p_var} === $p_val.
 *
 * If the second parameter is not given, the first parameter is compared to
 * the boolean value true.
 *
 * @param mixed   $p_var    The variable to compare.
 * @param mixed   $p_val    The value to compare $p_var with.
 * @param boolean $p_strict Set to false to bypass strict type checking (defaults to true).
 * @return void
 */
function telegrambot_check_default( $p_var, $p_val = true, $p_strict = true ) {
	if( is_array( $p_var ) ) {
		foreach( $p_var as $t_this_var ) {
			if( helper_check_variables_equal( $t_this_var, $p_val, $p_strict ) ) {
				echo ' checked="checked"';
				return;
			}
		}
	} else {
		if( helper_check_variables_equal( $p_var, $p_val, $p_strict ) ) {
//			echo ' checked="checked"';
			return true;
		} else {
                        return false;
                }
	}
}

function telegramMsg_run_command( $t_command ) {
        switch( $t_command->getCommand() ) {
                case 'start':
                        $t_data = [
                            'chat_id' => $t_command->getFrom()->getId(),
                            'text' => sprintf(
                                                plugin_lang_get('first_message'), 
                                                config_get('window_title') . ' ( ' . config_get('path') . ' )', 
                                                ' ( ' . config_get('path') . plugin_page('account_telegram_prefs_page', TRUE) . ' )'
                            ),
                        ];
                        break;

                case 'stop':
                        $t_user_id = user_get_id_by_telegram_user_id($t_command->getFrom()->getId());

                        telegram_message_realatationship_delete($t_command->getFrom()->getId());
                        telegram_bot_user_mapping_delete($t_user_id);
                        plugin_config_delete('bug_data_draft', auth_get_current_user_id());
                        plugin_config_delete('bug_data_draft_chat_id', auth_get_current_user_id());
                        plugin_config_delete('bug_data_draft_message_id', auth_get_current_user_id());
                        plugin_config_delete('bug_data_draft_text_msg', auth_get_current_user_id());
                        plugin_config_delete('bug_data_draft_current_field_to_save', auth_get_current_user_id());

                        $t_data = [
                            'chat_id' => $t_command->getFrom()->getId(),
                            'text' => plugin_lang_get('end_message')
                        ];
                        break;

                default:
                        $t_data = [
                            'chat_id' => $t_command->getFrom()->getId(),
                            'text' => plugin_lang_get('command_not_found')
                        ];
        }
        return $t_data;
}

//function telegrambot_get_pin_code( $p_telegram_user_id ) {
//
//                telegrembot_clear_old_pins();
//
//        //        $t_pin_code = $p_telegram_user_id;
//
//                db_param_push();
//
//        //        $t_query = 'SELECT pin_code FROM {plugin_TelegramBot_pin_codes} WHERE telegram_user_id=' . db_param();
//                $t_query = 'SELECT * FROM {plugin_TelegramBot_pin_codes} WHERE 1';
//        //	$t_result = db_query( $t_query, array( $p_telegram_user_id ) );
//                $t_result = db_query( $t_query );
//
//                $t_rows = array();
//
//                while( $c_row = db_fetch_array( $t_result ) ) {
//                        $t_rows[] = $c_row;
//                }
//
//
//                foreach( $t_rows as $t_row) {
//                        if( $t_row['telegram_user_id'] == $p_telegram_user_id ) {
//                                return $t_row['pin_code'];
//                        }
//                }
//
//                while( true ) {
//                        $t_pin_code = mt_rand(1000, 9999);
//                        foreach( $t_rows as $t_row) {
//                                if( $t_row['pin_code'] == $t_pin_code ) {
//                                        continue;
//                                }
//
//                        }
//
//                        db_param_push();
//
//                        $t_query = 'INSERT INTO {plugin_TelegramBot_pin_codes} (telegram_user_id, pin_code, timestamp) VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ')';
//                        db_query( $t_query, array( $p_telegram_user_id, $t_pin_code, db_now() ) );
//
//                        return $t_pin_code;
//                }
//        }

//function telegrembot_clear_old_pins() {
//
//                $t_time_cut = db_now() - (15*60);
//                db_param_push();
//                $t_query = 'DELETE FROM {plugin_TelegramBot_pin_codes} WHERE timestamp <' . $t_time_cut;
//                db_query( $t_query );
//}