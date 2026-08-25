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

function telegram_bug_add( $p_bug_data_draft, $p_orgl_chat_id, $p_callback_msg_id ) {

    $t_issue = array(
                              'project'     => array( 'id' => $p_bug_data_draft['project'] ),
                              'reporter'    => array( 'id' => auth_get_current_user_id() ),
                              'summary'     => $p_bug_data_draft['summary'],
                              'description' => $p_bug_data_draft['description'],
    );

    $t_tag_string = '';
    $f_tag_select = array_key_exists('tag_select', $p_bug_data_draft ) ? $p_bug_data_draft['tag_select'] : 0 ;
    if( $f_tag_select != 0 ) {
        $t_tag_string = tag_get_name( $f_tag_select );
    }

    $f_tag_string = array_key_exists( 'tag_string', $p_bug_data_draft ) ? $p_bug_data_draft['tag_string'] : '';
    if( !is_blank( $f_tag_string ) ) {
        $t_tag_string = is_blank( $t_tag_string ) ? $f_tag_string : ',' . $f_tag_string;
    }

    $t_tags = tag_parse_string( $t_tag_string );
    if( !empty( $t_tags ) ) {
        $t_issue['tags'] = array();
        foreach( $t_tags as $t_tag ) {
            $t_issue['tags'][] = array( 'id' => $t_tag['id'] );
        }
    }

    $f_files = array_key_exists( 'attachments', $p_bug_data_draft ) ? $p_bug_data_draft['attachments'] : null;
    if( $f_files !== null && !empty( $f_files ) ) {
        $t_issue['files'] = helper_array_transpose( $f_files );
    }

    $t_build = array_key_exists( 'build', $p_bug_data_draft ) ? $p_bug_data_draft['build'] : '';
    if( !is_blank( $t_build ) ) {
        $t_issue['build'] = $t_build;
    }

    $t_platform = array_key_exists( 'platform', $p_bug_data_draft ) ? $p_bug_data_draft['platform'] : '';
    if( !is_blank( $t_platform ) ) {
        $t_issue['platform'] = $t_platform;
    }

    $t_os = array_key_exists( 'os', $p_bug_data_draft ) ? $p_bug_data_draft['os'] : '';
    if( !is_blank( $t_os ) ) {
        $t_issue['os'] = $t_os;
    }

    $t_os_build = array_key_exists( 'os_build',$p_bug_data_draft ) ? $p_bug_data_draft['os_build'] : '';
    if( !is_blank( $t_os_build ) ) {
        $t_issue['os_build'] = $t_os_build;
    }

    $t_version = array_key_exists( 'product_version', $p_bug_data_draft ) ? $p_bug_data_draft['product_version'] : '';
    if( !is_blank( $t_version ) ) {
        $t_issue['version'] = array( 'name' => $t_version );
    }

    $t_target_version = array_key_exists( 'target_version', $p_bug_data_draft ) ? $p_bug_data_draft['target_version'] : '';
    if( !is_blank( $t_target_version ) ) {
        $t_issue['target_version'] = array( 'name' => $t_target_version );
    }

    $t_profile_id = array_key_exists( 'profile', $p_bug_data_draft ) ? $p_bug_data_draft['profile'] : 0;
    if( (int)$t_profile_id != 0 ) {
        $t_issue['profile'] = array( 'id' => $t_profile_id );
    }

    $t_handler_id = array_key_exists( 'handler', $p_bug_data_draft ) ? $p_bug_data_draft['handler'] : NO_USER;
    if( (int)$t_handler_id != NO_USER ) {
        $t_issue['handler'] = array( 'id' => $t_handler_id );
    }

    $t_view_state = array_key_exists( 'view_state', $p_bug_data_draft ) ? $p_bug_data_draft['view_state'] : 0;
    if( (int)$t_view_state != 0 ) {
        $t_issue['view_state'] = array( 'id' => $t_view_state );
    }

    # The id has to be an integer: since 2.28.0 the core refuses a category given
    # as a string, and the draft keeps whatever the keyboard has put into it
    $t_category_id = array_key_exists( 'category', $p_bug_data_draft ) ? (int)$p_bug_data_draft['category'] : 0;
    if( $t_category_id != 0 ) {
        $t_issue['category'] = array( 'id' => $t_category_id );
    }

    $t_reproducibility = array_key_exists( 'reproducibility', $p_bug_data_draft ) ? $p_bug_data_draft['reproducibility'] : 0;
    if( (int)$t_reproducibility != 0 ) {
        $t_issue['reproducibility'] = array( 'id' => $t_reproducibility );
    }

    $t_severity = array_key_exists( 'severity', $p_bug_data_draft ) ? $p_bug_data_draft['severity'] : 0;
    if( (int)$t_severity != 0 ) {
        $t_issue['severity'] = array( 'id' => $t_severity );
    }

    $t_priority = array_key_exists( 'priority', $p_bug_data_draft ) ? $p_bug_data_draft['priority'] : 0;
    if( (int)$t_priority != 0 ) {
        $t_issue['priority'] = array( 'id' => $t_priority );
    }

    $t_projection = array_key_exists( 'projection', $p_bug_data_draft ) ? $p_bug_data_draft['projection'] : 0;
    if( (int)$t_projection != 0 ) {
        $t_issue['projection'] = array( 'id' => $t_projection );
    }

    $t_eta = array_key_exists( 'eta', $p_bug_data_draft ) ? $p_bug_data_draft['eta'] : 0;
    if( (int)$t_eta != 0 ) {
        $t_issue['eta'] = array( 'id' => $t_eta );
    }

    $t_resolution = array_key_exists( 'resolution', $p_bug_data_draft ) ? $p_bug_data_draft['resolution'] : 0;
    if( (int)$t_resolution != 0 ) {
        $t_issue['resolution'] = array( 'id' => $t_resolution );
    }

    $t_status = array_key_exists( 'status', $p_bug_data_draft ) ? $p_bug_data_draft['status'] : 0;
    if( (int)$t_status != 0 ) {
        $t_issue['status'] = array( 'id' => $t_status );
    }

    $t_steps_to_reproduce = array_key_exists( 'steps_to_reproduce', $p_bug_data_draft ) ? $p_bug_data_draft['steps_to_reproduce'] : null;
    if( $t_steps_to_reproduce !== null ) {
        $t_issue['steps_to_reproduce'] = $t_steps_to_reproduce;
    }

    $t_additional_info = array_key_exists( 'additional_info', $p_bug_data_draft ) ? $p_bug_data_draft['additional_info'] : null;
    if( $t_additional_info !== null ) {
        $t_issue['additional_information'] = $t_additional_info;
    }

    # A field which is neither answered nor skipped holds an empty string
    $t_due_date = array_key_exists( 'due_date', $p_bug_data_draft ) ? $p_bug_data_draft['due_date'] : null;
    if( $t_due_date !== null && !is_blank( (string)$t_due_date ) ) {
        $t_issue['due_date'] = $t_due_date;
    }
    
    # Collect the custom fields of the draft, the values are validated by the command.
    # Drafts started before the custom fields support have no such key at all.
    $t_draft_custom_fields = array_key_exists( 'custom_fields', $p_bug_data_draft ) && is_array( $p_bug_data_draft['custom_fields'] )
                              ? $p_bug_data_draft['custom_fields']
                              : array();

    $t_related_custom_field_ids = custom_field_get_linked_ids( $p_bug_data_draft['project'] );
    $t_custom_fields = array();
    foreach( $t_related_custom_field_ids as $t_id ) {
                if( !array_key_exists( (int)$t_id, $t_draft_custom_fields ) ) {
                        continue;
                }

                $t_value = $t_draft_custom_fields[(int)$t_id];

                # A field that was not answered or was skipped is not sent at all
                if( $t_value === null || is_array( $t_value ) || is_blank( $t_value ) ) {
                        continue;
                }

                $t_custom_fields[] = array(
                    'field' => array( 'id' => $t_id ),
                    'value' => $t_value
                );
    }

    if( !empty( $t_custom_fields ) ) {
                $t_issue['custom_fields'] = $t_custom_fields;
    }
        
    $t_data = array(
                              'payload' => array( 'issue' => $t_issue ),
    );

    $t_command = new IssueAddCommand( $t_data );
    $t_result = $t_command -> execute();
    $t_issue_id = (int) $t_result['issue_id'];

    # The key of the entry is localized by the core when the history is shown,
    # so the values may carry language neutral data only
    plugin_history_log( $t_issue_id, 'history_issue_created', '' );

    return $t_issue_id;

}

/**
 * Apply the collected status change to the issue the way bug_update.php of the
 * core does: the workflow, the access and the answers of the dialog are
 * validated, the update events are signalled, the note carried by the dialog is
 * added within the update and the email matching the transition is sent.
 *
 * @param array $p_draft Draft of the status change dialog.
 * @return array 'ok' flag, 'error' and 'warning' texts, the final 'new_status'.
 */
function telegram_bug_status_change( $p_draft ) {
    global $g_skip_sending_bugnote;

    $t_bug_id     = (int)$p_draft['bug_id'];
    $t_new_status = (int)$p_draft['new_status'];

    $t_existing_bug = bug_get( $t_bug_id, true );
    $t_user_id      = auth_get_current_user_id();

    $t_result = array(
                              'ok'         => FALSE,
                              'error'      => '',
                              'warning'    => isset( $p_draft['warning'] ) ? $p_draft['warning'] : '',
                              'old_status' => $t_existing_bug->status,
                              'new_status' => $t_new_status,
    );

    if( $t_new_status == $t_existing_bug->status ) {
        $t_result['error'] = error_string( ERROR_ACCESS_DENIED );
        return $t_result;
    }

    $t_project_id      = $t_existing_bug->project_id;
    $t_resolved_status = config_get( 'bug_resolved_status_threshold', null, null, $t_project_id );
    $t_closed_status   = config_get( 'bug_closed_status_threshold', null, null, $t_project_id );

    # Determine whether the new status will reopen, resolve or close the issue.
    # Note that multiple resolved or closed states can exist and thus we need to
    # look at a range of statuses when performing this check.
    $t_resolve_issue = $t_existing_bug->status < $t_resolved_status && $t_new_status >= $t_resolved_status && $t_new_status < $t_closed_status;
    $t_close_issue   = $t_existing_bug->status < $t_closed_status && $t_new_status >= $t_closed_status;
    $t_reopen_issue  = $t_existing_bug->status >= $t_resolved_status && $t_new_status <= config_get( 'bug_reopen_status', null, null, $t_project_id );

    if( !bug_check_workflow( $t_existing_bug->status, $t_new_status ) ) {
        error_parameters( lang_get( 'status' ) );
        $t_result['error'] = error_string( ERROR_CUSTOM_FIELD_INVALID_VALUE );
        return $t_result;
    }

    if( !access_has_bug_level( access_get_status_threshold( $t_new_status, $t_project_id ), $t_bug_id ) ) {
        # The reporter may be allowed to close or reopen the issue regardless
        $t_can_bypass_status_access_thresholds =
                ( $t_close_issue && $t_existing_bug->status >= $t_resolved_status
                        && access_can_close_bug( $t_existing_bug, $t_user_id ) )
                || ( $t_reopen_issue && $t_existing_bug->status >= $t_resolved_status
                        && $t_existing_bug->status <= $t_closed_status
                        && access_can_reopen_bug( $t_existing_bug, $t_user_id ) );

        if( !$t_can_bypass_status_access_thresholds ) {
            $t_result['error'] = error_string( ERROR_ACCESS_DENIED );
            return $t_result;
        }
    }

    $t_updated_bug         = clone $t_existing_bug;
    $t_updated_bug->status = $t_new_status;

    if( $t_reopen_issue ) {
        # for everyone allowed to reopen an issue, set the reopen resolution
        $t_updated_bug->resolution = config_get( 'bug_reopen_resolution', null, null, $t_project_id );
    }

    if( $p_draft['resolution'] !== '' && $p_draft['resolution'] !== null ) {
        $t_updated_bug->resolution = (int)$p_draft['resolution'];
    }

    if( $p_draft['fixed_in_version'] !== '' && $p_draft['fixed_in_version'] !== null ) {
        $t_updated_bug->fixed_in_version = $p_draft['fixed_in_version'];
    }

    if( $p_draft['handler'] !== '' && $p_draft['handler'] !== null ) {
        $t_updated_bug->handler_id = (int)$p_draft['handler'];
    }

    # Perform validation of the duplicate ID of the bug the way bug_update.php does
    if( $p_draft['duplicate_id'] !== '' && $p_draft['duplicate_id'] !== null && (int)$p_draft['duplicate_id'] != 0 ) {
        $t_duplicate_id = (int)$p_draft['duplicate_id'];

        if( $t_duplicate_id == $t_bug_id ) {
            $t_result['error'] = error_string( ERROR_BUG_DUPLICATE_SELF );
            return $t_result;
        }

        if( !bug_exists( $t_duplicate_id ) ) {
            error_parameters( $t_duplicate_id );
            $t_result['error'] = error_string( ERROR_BUG_NOT_FOUND );
            return $t_result;
        }

        if( !access_has_bug_level( config_get( 'update_bug_threshold' ), $t_duplicate_id ) ) {
            $t_result['error'] = error_string( ERROR_RELATIONSHIP_ACCESS_LEVEL_TO_DEST_BUG_TOO_LOW );
            return $t_result;
        }

        $t_updated_bug->duplicate_id = $t_duplicate_id;
    }

    # Validate any change to the handler of the issue, the new handler is
    # checked at project level
    if( $t_existing_bug->handler_id != $t_updated_bug->handler_id ) {
        if( !access_has_bug_level( config_get( 'update_bug_assign_threshold', config_get( 'update_bug_threshold' ) ), $t_bug_id ) ) {
            $t_result['error'] = error_string( ERROR_ACCESS_DENIED );
            return $t_result;
        }

        $t_issue_is_sponsored = config_get( 'enable_sponsorship' )
                && sponsorship_get_amount( sponsorship_get_all_ids( $t_bug_id ) ) > 0;

        if( $t_issue_is_sponsored && !access_has_bug_level( config_get( 'assign_sponsored_bugs_threshold' ), $t_bug_id ) ) {
            $t_result['error'] = error_string( ERROR_SPONSORSHIP_ASSIGNER_ACCESS_LEVEL_TOO_LOW );
            return $t_result;
        }

        if( $t_updated_bug->handler_id != NO_USER ) {
            if( !access_has_project_level( config_get( 'handle_bug_threshold' ), $t_project_id, $t_updated_bug->handler_id ) ) {
                $t_result['error'] = error_string( ERROR_HANDLER_ACCESS_TOO_LOW );
                return $t_result;
            }

            if( $t_issue_is_sponsored && !access_has_project_level( config_get( 'handle_sponsored_bugs_threshold' ), $t_project_id, $t_updated_bug->handler_id ) ) {
                $t_result['error'] = error_string( ERROR_SPONSORSHIP_HANDLER_ACCESS_LEVEL_TOO_LOW );
                return $t_result;
            }
        }
    }

    # Handle automatic assignment of issues
    $t_updated_bug->status  = bug_get_status_for_assign( $t_existing_bug->handler_id, $t_updated_bug->handler_id, $t_existing_bug->status, $t_updated_bug->status );
    $t_result['new_status'] = $t_updated_bug->status;

    # The note carried by the dialog: the text of the message the dialog was
    # started from, the file it carried is picked up from Telegram now
    $t_content   = isset( $p_draft['content'] ) && is_array( $p_draft['content'] )
            ? $p_draft['content']
            : array( 'text' => '', 'file_id' => '', 'file_name' => '', 'file_size' => 0 );
    $t_note_text = trim( (string)$t_content['text'] );
    $t_files     = array();

    if( $t_content['file_id'] != '' ) {
        $t_file_error = telegram_file_check( (string)$t_content['file_name'], (int)$t_content['file_size'] );

        if( $t_file_error == '' ) {
            try {
                $t_download  = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_content['file_id'] ] );
                $t_file      = $t_download->getResult();
                $t_file_name = $t_content['file_name'] != '' ? $t_content['file_name'] : $t_file->getFilePath();

                $t_file_error = $t_content['file_name'] != '' ? '' : telegram_file_check( $t_file_name, 0 );

                if( $t_file_error == '' ) {
                    Longman\TelegramBot\Request::downloadFile( $t_file );

                    $t_files = [
                                              'browser_upload' => [ 0 => FALSE ],
                                              'tmp_name'       => [ 0 => plugin_config_get( 'download_path' ) . $t_file->getFilePath() ],
                                              'name'           => [ 0 => $t_file_name ]
                    ];
                }
            } catch( Longman\TelegramBot\Exception\TelegramException $t_exception ) {
                $t_file_error = $t_exception->getMessage();
            }
        }

        # The status change goes on without the attachment, the failure is
        # reported back on the card
        if( $t_file_error != '' ) {
            $t_result['warning'] = trim( $t_result['warning'] . PHP_EOL . $t_file_error );
        }
    }

    # Allow plugins to validate/modify the update prior to it being committed
    $t_updated_bug = event_signal( 'EVENT_UPDATE_BUG_DATA', $t_updated_bug, $t_existing_bug );

    $t_updated_bug->update( false, true );

    # Add the note within the update the way bug_update.php does: no separate
    # note email is sent, the mail of the status change carries the note
    if( $t_note_text != '' || !empty( $t_files ) ) {
        if( access_has_bug_level( config_get( 'add_bugnote_threshold' ), $t_bug_id ) ) {
            if( $t_note_text != '' ) {
                $t_note_id = bugnote_add( $t_bug_id, $t_note_text, '0:00', config_get( 'default_bugnote_view_status' ) == VS_PRIVATE, 0, '', null, FALSE );
                bugnote_process_mentions( $t_bug_id, $t_note_id, $t_note_text );
                plugin_history_log( $t_bug_id, 'history_note_added', '', (string)$t_note_id );
            }

            if( !empty( $t_files ) ) {
                $t_command = new IssueFileAddCommand( array(
                                          'query'   => array( 'issue_id' => $t_bug_id ),
                                          'payload' => array( 'files' => helper_array_transpose( $t_files ) ),
                ) );
                $t_command->execute();

                plugin_history_log( $t_bug_id, 'history_file_added', '' );
            }
        } else {
            $t_result['warning'] = trim( $t_result['warning'] . PHP_EOL . error_string( ERROR_ACCESS_DENIED ) );
        }
    }

    # Add the duplicate relationship if requested
    if( $t_updated_bug->duplicate_id != 0 ) {
        relationship_upsert( $t_bug_id, $t_updated_bug->duplicate_id, BUG_DUPLICATE, /* email_for_source */ false );

        if( user_exists( $t_existing_bug->reporter_id ) ) {
            bug_monitor( $t_updated_bug->duplicate_id, $t_existing_bug->reporter_id );
        }
        if( user_exists( $t_existing_bug->handler_id ) ) {
            bug_monitor( $t_updated_bug->duplicate_id, $t_existing_bug->handler_id );
        }

        bug_monitor_copy( $t_bug_id, $t_updated_bug->duplicate_id );
    }

    event_signal( 'EVENT_UPDATE_BUG', array( $t_existing_bug, $t_updated_bug ) );

    # The own handler of EVENT_UPDATE_BUG_DATA armed the flag gagging the note
    # of the update, a dialog without a note leaves the flag armed and it would
    # leak into the next update of a long polling batch
    $g_skip_sending_bugnote = FALSE;

    # Send a notification of changes via email
    if( $t_resolve_issue ) {
        email_resolved( $t_bug_id );
        email_relationship_child_resolved( $t_bug_id );
    } else if( $t_close_issue ) {
        email_close( $t_bug_id );
        email_relationship_child_closed( $t_bug_id );
    } else if( $t_reopen_issue ) {
        email_bug_reopened( $t_bug_id );
    } else if( $t_existing_bug->handler_id != $t_updated_bug->handler_id ) {
        email_owner_changed( $t_bug_id, $t_existing_bug->handler_id, $t_updated_bug->handler_id );
    } else if( $t_existing_bug->status != $t_updated_bug->status ) {
        $t_new_status_label = MantisEnum::getLabel( config_get( 'status_enum_string' ), $t_updated_bug->status );
        $t_new_status_label = str_replace( ' ', '_', $t_new_status_label );
        email_bug_status_changed( $t_bug_id, $t_new_status_label );
    } else {
        email_bug_updated( $t_bug_id );
    }

    $t_result['ok'] = TRUE;
    return $t_result;
}
