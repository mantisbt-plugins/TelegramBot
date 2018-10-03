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

    $f_bug_id   = $p_bug_id;
    $f_text     = $p_text;
    $f_duration = $p_duration;
    $f_files    = $p_files;

    $t_query = array( 'issue_id' => $f_bug_id );

    if( count( $f_files ) > 0 && is_blank( $f_text ) && helper_duration_to_minutes( $f_duration ) == 0 ) {
        $t_payload = array(
                                  'files' => helper_array_transpose( $f_files )
        );

        $t_data = array(
                                  'query'   => $t_query,
                                  'payload' => $t_payload,
        );

        $t_command = new IssueFileAddCommand( $t_data );
        $t_command->execute();
    } else {
        $t_payload = array(
                                  'text'          => $f_text,
                                  'view_state'    => array(
                                                            'id' => VS_PUBLIC
                                  ),
                                  'time_tracking' => array(
                                                            'duration' => $f_duration
                                  ),
                                  'files'         => helper_array_transpose( $f_files )
        );

        $t_data = array(
                                  'query'   => $t_query,
                                  'payload' => $t_payload,
        );

        $t_command = new IssueNoteAddCommand( $t_data );
        $t_command->execute();
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

        plugin_config_set( 'bug_data_draft', json_encode( $t_final_fields ), auth_get_current_user_id() );
        $t_bug_data_draft = $t_final_fields;
    }

    switch( $t_content_type ) {
        case 'video':
        case 'photo':
        case 'document':
            if( $t_bug_data_draft['ufile'] == NULL && empty( $t_bug_data_draft['ufile'] ) ) {

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

                $t_bug_data_draft['ufile'] = [
                                          'browser_upload' => [ 0 => FALSE ],
                                          'tmp_name'       => [ 0 => $t_file_path ],
                                          'name'           => $t_file_orgl->getFileName() == NULL ? [ 0 => $t_file->getFilePath() ] : [ 0 => $t_file_orgl->getFileName() ]
                ];
            }

        case 'text':

            switch( array_keys( $p_current_action )[0] ) {

                case 'gp':
                    $t_current_project = 0;
                    $t_project_id      = $t_current_project;

                    # If all projects, use default project if set
                    $t_default_project = user_pref_get_pref( auth_get_current_user_id(), 'default_project' );
                    if( ALL_PROJECTS == $t_project_id && ALL_PROJECTS != $t_default_project ) {
                        $p_current_action             = array();
                        $p_current_action['sp']['id'] = $t_default_project;

                        $t_callback_msg_text = lang_get( 'email_project' ) . ': ';
                    } else {
                        $t_inline_keyboard   = keyboard_projects_get( $p_current_action['gp']['id'], $p_current_action['gp']['p'], $p_current_action['gp']['fp'] );
                        $t_callback_msg_text = $p_callback_query->getMessage()->getText();
                        $t_text              = lang_get( 'email_project' ) . ': ';
                        break;
                    }

                case 'sp':
                    $t_bug_data_draft['project'] = $p_current_action['sp']['id'];
                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    $t_text = $t_callback_msg_text . ' ' . project_get_field( $t_bug_data_draft['project'], 'name' );

//CATEGORY
                case 'gc':
                    if( key_exists( 'category_id', $t_bug_data_draft ) ) {
                        $t_inline_keyboard = keyboard_category_get( $t_bug_data_draft['project'] );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'category' ) . ': ';

                        break;
                    }

                case 'sc':
                    if( key_exists( 'category_id', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['category'] = $p_current_action['sc']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . category_get_name( $t_bug_data_draft['category'] );
                    }

//REPRODUCIBILITY
                case 'greproducibility':
                    if( key_exists( 'reproducibility', $t_bug_data_draft ) ) {
                        $t_inline_keyboard = keyboard_enum_string_get( 'reproducibility' );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'reproducibility' ) . ': ';

                        break;
                    }
                case 'sreproducibility':
                    if( key_exists( 'reproducibility', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['reproducibility'] = $p_current_action['sreproducibility']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . get_enum_element( 'reproducibility', $t_bug_data_draft['reproducibility'] );
                    }

//ETA
                case 'geta':
                    if( key_exists( 'eta', $t_bug_data_draft ) ) {
                        $t_inline_keyboard = keyboard_enum_string_get( 'eta' );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'eta' ) . ': ';

                        break;
                    }
                case 'seta':
                    if( key_exists( 'eta', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['eta'] = $p_current_action['seta']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . get_enum_element( 'eta', $t_bug_data_draft['eta'] );
                    }

//SEVERITY
                case 'gseverity':
                    if( key_exists( 'severity', $t_bug_data_draft ) ) {
                        $t_inline_keyboard = keyboard_enum_string_get( 'severity' );

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
                        $t_inline_keyboard = keyboard_enum_string_get( 'priority' );

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

////DUE_DATE
//        case 'gduedate':
//            if( key_exists( 'due_date', $t_bug_data_draft ) && access_has_project_level( config_get( 'due_date_update_threshold' ), $t_bug_data_draft['project']['id'], auth_get_current_user_id() ) ) {
//                $t_inline_keyboard = keyboard_duedate_get();
//
//                $t_text .= PHP_EOL;
//                $t_text .= lang_get( 'priority' ) . ': ';
//
//                break;
//            }
//        case 'sduedate':
//            if( key_exists( 'due_date', $t_bug_data_draft ) && access_has_project_level( config_get( 'due_date_update_threshold' ), $t_bug_data_draft['project']['id'], auth_get_current_user_id() ) ) {
//                $t_bug_data_draft['due_date'] = $p_current_action['priority']['id'];
//
//                plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
//
//                $t_text = $p_callback_msg_text . ' ' . get_enum_element( 'priority', $t_bug_data_draft['priority'] );
//            }
//$t_show_platform || $t_show_os || $t_show_os_version
//$t_show_product_version
//$t_show_product_build
//HANDLER
                case 'ghandler':
                    if( key_exists( 'handler', $t_bug_data_draft ) && access_has_project_level( config_get( 'update_bug_assign_threshold' ) ) ) {
                        $t_inline_keyboard = keyboard_handler_get( $t_bug_data_draft['project'] );

                        $t_text .= PHP_EOL;
                        $t_text .= lang_get( 'issue_handler' ) . ': ';

                        break;
                    }
                case 'shandler':
                    if( key_exists( 'handler', $t_bug_data_draft ) && access_has_project_level( config_get( 'update_bug_assign_threshold' ) ) ) {
                        $t_bug_data_draft['handler'] = $p_current_action['shandler']['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                        $t_text = $t_callback_msg_text . ' ' . user_get_name( $t_bug_data_draft['handler'] );
                    }

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

////TARGET_VERSION
//        case 'gtargetv':
//            if( version_should_show_product_version( $t_bug_data_draft['project']['id'] ) && key_exists( 'target_version', $t_bug_data_draft ) && access_has_project_level( config_get( 'roadmap_update_threshold' ) ) ) {
//                $t_inline_keyboard = keyboard_target_version_get( '', $t_bug_data_draft['project']['id'], VERSION_FUTURE );
//
//                $t_text .= PHP_EOL;
//                $t_text .= lang_get( 'target_version' ) . ': ';
//
//                break;
//            }
//        case 'stargetv':
//            if( version_should_show_product_version( $t_bug_data_draft['project']['id'] ) && key_exists( 'target_version', $t_bug_data_draft ) && access_has_project_level( config_get( 'roadmap_update_threshold' ) ) ) {
//                $t_bug_data_draft['target_version'] = $p_current_action['stargetv']['id'];
//
//                plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
//
//                $t_text = $p_callback_msg_text . ' ' . ;
//            }
//SUMMARY            
                case 'gsummary':

                    $t_text .= PHP_EOL;
                    $t_text .= lang_get( 'summary' ) . ': ';

                    if( !is_blank( $p_callback_query->getMessage()->getReplyToMessage()->getText() ) ) {
                        $t_summary = $p_callback_query->getMessage()->getReplyToMessage()->getText();
                    } else if( !is_blank( $t_orgl_message->getCaption() ) ) {
                        $t_summary = $t_orgl_message->getCaption();
                    } else {

                        $t_text .= PHP_EOL;
                        $t_text .= '----------------------------';
                        $t_text .= PHP_EOL;
                        $t_text .= plugin_lang_get( 'get_summary' );

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
                    $t_text .= PHP_EOL;
                    $t_text .= '----------------------------';
                    $t_text .= PHP_EOL;
                    $t_text .= plugin_lang_get( 'get_description' );
                    break;

                case 'sdescription':
                    $t_bug_data_draft['summary'] = $p_callback_query->getMessage()->getReplyToMessage()->getText();

                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    $t_text .= $t_bug_data_draft['summary'];
            }

            $t_data_send = [
                                      'chat_id'      => $t_orgl_chat_id,
                                      'message_id'   => $t_callback_msg_id,
                                      'text'         => $t_text,
                                      'reply_markup' => $t_inline_keyboard,
            ];

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
                    $t_upload_is_error = FALSE;
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
                $t_file_is_deleted = unlink( $t_file_path );

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
