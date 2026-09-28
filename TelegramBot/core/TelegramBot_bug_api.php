<?php

# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
# TelegramBot for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 2 of the License, or (at your option) any later version.
#
# TelegramBot plugin for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with TelegramBot plugin for MantisBT.  
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

    # The draft keeps the id of the file only, the file itself is downloaded now
    # and removed right after the command has stored it
    $f_files = array_key_exists( 'attachments', $p_bug_data_draft ) ? $p_bug_data_draft['attachments'] : null;
    if( is_array( $f_files ) && !empty( $f_files['file_id'] ) ) {
        try {
            $t_response = Longman\TelegramBot\Request::getFile( [ 'file_id' => $f_files['file_id'] ] );

            if( !$t_response->isOk() ) {
                throw new Longman\TelegramBot\Exception\TelegramException( (string)$t_response->getDescription() );
            }

            $t_issue['files'] = helper_array_transpose( telegram_file_download( $t_response->getResult(), (string)$f_files['name'] ) );
        } catch( Longman\TelegramBot\Exception\TelegramException $t_exception ) {
            # The issue is still worth creating, the dialog cannot be replayed;
            # the failure is left in the log of MantisBT
            telegram_file_download_error( $t_exception );
        }
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
        # The draft keeps the timestamp the calendar has picked, while the command
        # of the core runs strtotime() over the value: a bare timestamp is not a
        # date to it and the due date would be dropped without a word
        $t_issue['due_date'] = date( 'c', (int)$t_due_date );
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
    try {
        $t_result = $t_command -> execute();
    } finally {
        telegram_file_download_remove();
    }
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
 * The draft outlives the rights it was filled in with, so the issue is checked
 * the way view.php checks it and the configuration is read for its project, the
 * way bug_update.php overrides the current project.
 *
 * @param array $p_draft Draft of the status change dialog.
 * @return array 'ok' flag, 'error' and 'warning' texts, the final 'new_status'.
 */
function telegram_bug_status_change( $p_draft ) {

    $t_error = telegram_bug_view_error( $p_draft['bug_id'] );

    if( $t_error != '' ) {
        return array(
                                  'ok'         => FALSE,
                                  'error'      => $t_error,
                                  'warning'    => '',
                                  'old_status' => 0,
                                  'new_status' => (int)$p_draft['new_status'],
        );
    }

    $t_project_override = telegram_project_override_set( bug_get_field( (int)$p_draft['bug_id'], 'project_id' ) );

    try {
        return telegram_bug_status_change_apply( $p_draft );
    } finally {
        telegram_project_override_restore( $t_project_override );
    }
}

/**
 * Apply the status change of a draft to an issue the current user may view,
 * see telegram_bug_status_change().
 *
 * @param array $p_draft Draft of the status change dialog.
 * @return array 'ok' flag, 'error' and 'warning' texts, the final 'new_status'.
 */
function telegram_bug_status_change_apply( $p_draft ) {
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

    # The issue or the rights may have changed since the status was picked, the
    # checks of the status list, bug_change_status_page.php and bug_update.php
    # are run once more on the current state
    $t_warning = '';
    $t_error   = telegram_status_change_entry_check( $t_existing_bug, $t_new_status, $t_warning );

    if( $t_error != '' ) {
        $t_result['error'] = $t_error;
        return $t_result;
    }

    # An answer is taken only for a question the dialog would ask now, the way
    # bug_change_status_page.php shows the fields
    foreach( array( 'resolution', 'duplicate_id', 'handler', 'fixed_in_version' ) as $t_step ) {
        if( $p_draft[$t_step] !== '' && $p_draft[$t_step] !== null
                && !telegram_status_change_step_is_applicable( $t_step, $p_draft, $t_existing_bug ) ) {
            $t_result['error'] = error_string( ERROR_ACCESS_DENIED );
            return $t_result;
        }
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

    # Don't allow the resolution which contradicts the new status, the rule of
    # bug_update.php ( #15653 of the core )
    $t_resolution_fixed_threshold = config_get( 'bug_resolution_fixed_threshold', null, null, $t_project_id );
    $t_reopen_resolution          = config_get( 'bug_reopen_resolution', null, null, $t_project_id );

    if( $t_existing_bug->resolution != $t_updated_bug->resolution && (
            ( $t_updated_bug->resolution >= $t_resolution_fixed_threshold
                && $t_updated_bug->resolution != $t_reopen_resolution
                && $t_updated_bug->status < $t_resolved_status )
            || ( $t_updated_bug->resolution == $t_reopen_resolution
                && ( $t_existing_bug->status < $t_resolved_status
                    || $t_updated_bug->status >= $t_resolved_status ) )
            || ( $t_updated_bug->resolution < $t_resolution_fixed_threshold
                && $t_updated_bug->status >= $t_resolved_status )
    ) ) {
        error_parameters(
                get_enum_element( 'resolution', $t_updated_bug->resolution ),
                get_enum_element( 'status', $t_updated_bug->status )
        );
        $t_result['error'] = error_string( ERROR_INVALID_RESOLUTION );
        return $t_result;
    }

    if( $p_draft['fixed_in_version'] !== '' && $p_draft['fixed_in_version'] !== null ) {
        # The version has to exist in the project, get_valid_version() of bug_update.php
        if( $p_draft['fixed_in_version'] != $t_existing_bug->fixed_in_version
                && version_get_id( (string)$p_draft['fixed_in_version'], $t_project_id ) === false ) {
            error_parameters( (string)$p_draft['fixed_in_version'] );
            $t_result['error'] = error_string( ERROR_VERSION_NOT_FOUND );
            return $t_result;
        }

        $t_updated_bug->fixed_in_version = (string)$p_draft['fixed_in_version'];
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

    # The note carried by the dialog, the file of the message the dialog was
    # started from is picked up from Telegram now
    $t_content   = isset( $p_draft['content'] ) && is_array( $p_draft['content'] )
            ? $p_draft['content']
            : array( 'file_id' => '', 'file_name' => '', 'file_size' => 0 );
    $t_note_text = trim( (string)$p_draft['bugnote'] );
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
                    $t_files = telegram_file_download( $t_file, $t_file_name );
                }
            } catch( Longman\TelegramBot\Exception\TelegramException $t_exception ) {
                $t_file_error = telegram_file_download_error( $t_exception );
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
                $t_note_id = bugnote_add( $t_bug_id, $t_note_text, '0:00', telegram_bugnote_view_state_get( $t_bug_id ) == VS_PRIVATE, 0, '', null, FALSE );
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

    # The core has its own copy of the file by now, or the file is refused; an
    # exception thrown before this point leaves it to the shutdown cleanup
    telegram_file_download_remove();

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

/**
 * Download a file of Telegram to be attached to an issue.
 *
 * The file lands in a directory of its own, created with 0700 inside the
 * download_path ( the temp directory of the system when it is blank ), and is
 * readable by its owner only. The caller removes it with
 * telegram_file_download_remove() once the attachment is stored by the core;
 * whatever an error leaves behind is removed at the end of the script.
 *
 * @param Longman\TelegramBot\Entities\File $p_file      File returned by getFile.
 * @param string                            $p_file_name Name the file is attached under.
 * @return array Attachment in the shape of $_FILES the commands of the core take.
 * @throws Longman\TelegramBot\Exception\TelegramException The file is not downloaded.
 */
function telegram_file_download( Longman\TelegramBot\Entities\File $p_file, $p_file_name ) {
    global $g_tg;

    $t_dir           = telegram_file_download_dir_create();
    $t_download_path = $g_tg->getDownloadPath();

    $g_tg->setDownloadPath( $t_dir );

    try {
        $t_downloaded = Longman\TelegramBot\Request::downloadFile( $p_file );
    } finally {
        $g_tg->setDownloadPath( $t_download_path );
    }

    $t_path = $t_dir . '/' . $p_file->getFilePath();

    if( !$t_downloaded || !is_file( $t_path ) ) {
        telegram_file_download_remove();
        throw new Longman\TelegramBot\Exception\TelegramException( 'The file "' . $p_file->getFilePath() . '" is not downloaded' );
    }

    @chmod( $t_path, 0600 );

    return array(
                              'browser_upload' => array( 0 => FALSE ),
                              'tmp_name'       => array( 0 => $t_path ),
                              'name'           => array( 0 => $p_file_name ),
    );
}

/**
 * Create a directory with a random name, accessible to its owner only, for a
 * single download.
 *
 * @return string Path of the directory.
 * @throws Longman\TelegramBot\Exception\TelegramException The directory is not created.
 */
function telegram_file_download_dir_create() {
    global $g_telegram_file_download_dirs;

    $t_base = plugin_config_get( 'download_path' );
    if( is_blank( $t_base ) ) {
        $t_base = sys_get_temp_dir();
    }

    $t_dir = rtrim( $t_base, '/\\' ) . '/TelegramBot_' . bin2hex( random_bytes( 16 ) );

    # mkdir() fails on an existing path, so the directory cannot be one planted in advance
    if( !@mkdir( $t_dir, 0700 ) ) {
        throw new Longman\TelegramBot\Exception\TelegramException( 'The download directory "' . $t_dir . '" is not created' );
    }

    # The mode given to mkdir() is narrowed by the umask only, never widened
    @chmod( $t_dir, 0700 );

    if( !is_array( $g_telegram_file_download_dirs ) ) {
        $g_telegram_file_download_dirs = array();
        register_shutdown_function( 'telegram_file_download_remove' );
    }

    $g_telegram_file_download_dirs[] = $t_dir;

    return $t_dir;
}

/**
 * Remove the files downloaded by telegram_file_download() along with their
 * directories.
 *
 * @return void
 */
function telegram_file_download_remove() {
    global $g_telegram_file_download_dirs;

    if( empty( $g_telegram_file_download_dirs ) ) {
        return;
    }

    foreach( $g_telegram_file_download_dirs as $t_dir ) {
        telegram_directory_delete( $t_dir );
    }

    $g_telegram_file_download_dirs = array();
}

/**
 * Delete a directory with all of its content.
 *
 * @param string $p_dir Path of the directory.
 * @return void
 */
function telegram_directory_delete( $p_dir ) {

    $t_entries = @scandir( $p_dir );

    if( $t_entries === FALSE ) {
        return;
    }

    foreach( $t_entries as $t_entry ) {
        if( $t_entry == '.' || $t_entry == '..' ) {
            continue;
        }

        $t_path = $p_dir . '/' . $t_entry;

        if( is_dir( $t_path ) && !is_link( $t_path ) ) {
            telegram_directory_delete( $t_path );
        } else {
            @unlink( $t_path );
        }
    }

    @rmdir( $p_dir );
}
