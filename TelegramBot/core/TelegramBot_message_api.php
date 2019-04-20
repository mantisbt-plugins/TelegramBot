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

/**
 * Send notices when a bug handler is changed.
 * @param int $p_bug_id
 * @param int $p_prev_handler_id
 * @param int $p_new_handler_id
 * @return null
 */
function telegram_message_owner_changed( $p_bug_id, $p_prev_handler_id, $p_new_handler_id ) {
    if( $p_prev_handler_id == 0 && $p_new_handler_id != 0 ) {
        plugin_log_event( sprintf( 'Issue #%d assigned to user @U%d.', $p_bug_id, $p_new_handler_id ) );
    } else if( $p_prev_handler_id != 0 && $p_new_handler_id == 0 ) {
        plugin_log_event( sprintf( 'Issue #%d is no longer assigned to @U%d.', $p_bug_id, $p_prev_handler_id ) );
    } else {
        plugin_log_event(
                sprintf(
                        'Issue #%d is assigned to @U%d instead of @U%d.', $p_bug_id, $p_new_handler_id, $p_prev_handler_id )
        );
    }

    $t_message_id = $p_new_handler_id == NO_USER ?
            'telegram_message_notification_title_for_action_bug_unassigned' :
            'telegram_message_notification_title_for_action_bug_assigned';

    $t_extra_user_ids_to_telegram = array();
    if( $p_prev_handler_id !== NO_USER && $p_prev_handler_id != $p_new_handler_id ) {
        if( telegram_message_notify_flag( 'owner', 'handler' ) == ON ) {
            $t_extra_user_ids_to_telegram[] = $p_prev_handler_id;
        }
    }

    telegram_message_generic( $p_bug_id, 'owner', $t_message_id, /* headers */ null, $t_extra_user_ids_to_telegram );
}

/**
 * Send a notification to user or set of users that were mentioned in an issue
 * or an issue note.
 *
 * @param integer       $p_bug_id     Issue for which the reminder is sent.
 * @param array         $p_mention_user_ids User id or list of user ids array.
 * @param string        $p_message    Optional message to add to the telegram message.
 * @param array         $p_removed_mention_user_ids  The users that were removed due to lack of access.
 * @return array        List of users ids to whom the mentioned telegram message were actually sent
 */
function telegram_message_user_mention( $p_bug_id, $p_mention_user_ids, $p_message, $p_removed_mention_user_ids = array() ) {
    if( OFF == plugin_config_get( 'enable_telegram_message_notification' ) || plugin_config_get( 'api_key' ) == NULL ) {
        plugin_log_event( 'telegram notifications disabled.' );
        return array();
    }

    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
    $t_sender_id  = auth_get_current_user_id();
    $t_sender     = user_get_name( $t_sender_id );

    $t_project_name = project_get_field( bug_get_field( $p_bug_id, 'project_id' ), 'name' );

    $t_bug_summary = bug_get_field( $p_bug_id, 'summary' );

    $t_formatted_bug_id = bug_format_id( $p_bug_id );

//    $t_subject         = email_build_subject( $p_bug_id );
//    $t_date            = date( config_get( 'normal_date_format' ) );
    $t_user_id         = auth_get_current_user_id();
    $t_users_processed = array();

    foreach( $p_removed_mention_user_ids as $t_removed_mention_user_id ) {
        plugin_log_event( 'skipped mention telegram for U' . $t_removed_mention_user_id . ' (no access to issue or note).' );
    }

    $t_result = array();
    foreach( $p_mention_user_ids as $t_mention_user_id ) {
        # Don't trigger mention etelegramss for self mentions
        if( $t_mention_user_id == $t_user_id ) {
            plugin_log_event( 'skipped mention telegram for U' . $t_mention_user_id . ' (self-mention).' );
            continue;
        }

        $t_telegram_user_id = telegram_user_get_id_by_user_id( $t_mention_user_id );
        if( $t_telegram_user_id == NULL ) {
            continue;
        }

        # Don't process a user more than once
        if( isset( $t_users_processed[$t_mention_user_id] ) ) {
            continue;
        }

        $t_users_processed[$t_mention_user_id] = true;

        # Don't telegram mention notifications to disabled users.
        if( !user_is_enabled( $t_mention_user_id ) ) {
            continue;
        }

        lang_push( user_pref_get_language( $t_mention_user_id, $t_project_id ) );

//        $t_telegram_user_id = telegram_user_get_id_by_user_id( $t_mention_user_id );

        if( access_has_project_level( config_get( 'show_user_email_threshold' ), $t_project_id, $t_mention_user_id ) ) {
            $t_sender_email = ' <' . user_get_email( $t_sender_id ) . '> ';
        } else {
            $t_sender_email = '';
        }

        $t_second_message = plugin_config_get( 'telegram_message_separator2' ) . "\n";
        $t_second_message .= '[ ' . $t_project_name . ' ]' . "\n";
        $t_second_message .= $t_formatted_bug_id . ': ' . $t_bug_summary . "\n";

//        $t_complete_subject = sprintf( lang_get( 'mentioned_in' ), $t_subject );
//        $t_header           = "\n" . lang_get( 'on_date' ) . ' ' . $t_date . ', ' . $t_sender . ' ' . $t_sender_email . lang_get( 'mentioned_you' ) . "\n\n";
        $t_header   = $t_sender . ' ' . $t_sender_email . lang_get( 'mentioned_you' ) . "\n";
        $t_contents = $t_header . $t_second_message . string_get_bug_view_url_with_fqdn( $p_bug_id ) . " \n\n" . $p_message;

        $t_data = [
                                  'text' => $t_contents
        ];


        $t_results_send = telegram_session_send_message( $t_telegram_user_id, $t_data );
        foreach( $t_results_send as $t_result_count ) {
            if( $t_result_count->isOk() ) {
                $t_result[] = $t_mention_user_id;
                telegram_message_realatationship_add( $p_bug_id, $t_telegram_user_id, $t_result_count->getResult()->getMessageId() );
                break;
            }
        }

        lang_pop();
    }

    return $t_result;
}

/**
 * send notices when a new bugnote
 * @param int $p_bugnote_id  The bugnote id.
 * @param array $p_files The array of file information (keys: name, size)
 * @param array $p_exclude_user_ids The id of users to exclude.
 * @return void
 */
function telegram_message_bugnote_add_generic( $p_bugnote_id, $p_files = array(), $p_exclude_user_ids = array() ) {
    if( OFF == plugin_config_get( 'enable_telegram_message_notification' ) || plugin_config_get( 'api_key' ) == NULL ) {
        plugin_log_event( 'telegram notifications disabled.' );
        return;
    }

    telegram_session_start();

    ignore_user_abort( true );

    $t_bugnote = bugnote_get( $p_bugnote_id );

    plugin_log_event( sprintf( 'Note ~%d added to issue #%d', $p_bugnote_id, $t_bugnote->bug_id ) );

    $t_project_id                     = bug_get_field( $t_bugnote->bug_id, 'project_id' );
    $t_separator                      = plugin_config_get( 'telegram_message_separator2' );
    $t_time_tracking_access_threshold = config_get( 'time_tracking_view_threshold' );
    $t_view_attachments_threshold     = config_get( 'view_attachments_threshold' );
    $t_message_id                     = 'telegram_message_notification_title_for_action_bugnote_submitted';

//	$t_subject = email_build_subject( $t_bugnote->bug_id );
    $t_recipients         = telegram_message_collect_recipients( $t_bugnote->bug_id, 'bugnote', /* extra_user_ids */ array(), $p_bugnote_id );
    $t_recipients_verbose = array();

    # send telegram message to every recipient
    foreach( $t_recipients as $t_user_id => $t_telegram_user_id ) {
        if( in_array( $t_user_id, $p_exclude_user_ids ) ) {
            plugin_log_event( sprintf( 'Issue = #%d, Note = ~%d, Type = %s, Msg = \'%s\', User = @U%d excluded, Telegram User = \'%s\'.', $t_bugnote->bug_id, $p_bugnote_id, 'bugnote', 'telegram_message_notification_title_for_action_bugnote_submitted', $t_user_id, $t_telegram_user_id ) );
            continue;
        }

        # Load this here per user to allow overriding this per user, or even per user per project
        if( plugin_config_get( 'telegram_message_notifications_verbose', /* default */ null, FALSE, $t_user_id, $t_project_id ) == ON ) {
            $t_recipients_verbose[$t_user_id] = $t_telegram_user_id;
            continue;
        }

        plugin_log_event( sprintf( 'Issue = #%d, Note = ~%d, Type = %s, Msg = \'%s\', User = @U%d, Telegram User = \'%s\'.', $t_bugnote->bug_id, $p_bugnote_id, 'bugnote', $t_message_id, $t_user_id, $t_telegram_user_id ) );

        # load (push) user language
        lang_push( user_pref_get_language( $t_user_id, $t_project_id ) );

//        $t_message = plugin_lang_get( 'telegram_message_notification_title_for_action_bugnote_submitted' ) . "\n";

        $t_show_time_tracking = access_has_bug_level( $t_time_tracking_access_threshold, $t_bugnote->bug_id, $t_user_id );
        $t_formatted_note     = telegram_message_format_bugnote( $t_bugnote, $t_project_id, $t_show_time_tracking, $t_separator );
        $t_message            = trim( $t_formatted_note ) . "\n";
//        $t_message            .= $t_separator . "\n";
        # Files attached
        if( count( $p_files ) > 0 &&
                access_has_bug_level( $t_view_attachments_threshold, $t_bugnote->bug_id, $t_user_id ) ) {
            $t_message .= lang_get( 'bugnote_attached_files' ) . "\n";

            foreach( $p_files as $t_file ) {
                $t_message .= '- ' . $t_file['name'] . ' (' . number_format( $t_file['size'] ) .
                        ' ' . lang_get( 'bytes' ) . ")\n";
            }

            $t_message .= $t_separator . "\n";
        }

        $t_contents = $t_message . "\n";

        $data = [
                                  'text' => $t_contents
        ];

        $t_results = telegram_session_send_message( $t_telegram_user_id, $data );

        foreach( $t_results as $t_result ) {
            if( $t_result->isOk() ) {
                telegram_message_realatationship_add( $t_bugnote->bug_id, $t_telegram_user_id, $t_result->getResult()->getMessageId() );
            }
        }

        lang_pop();
    }

    # Send telegram messages out for users that select verbose notifications
    telegram_message_generic_to_recipients(
            $t_bugnote->bug_id, 'bugnote', $t_recipients_verbose, $t_message_id );
}

/**
 * Send bug info to given user
 * return true on success
 * @param array   $p_visible_bug_data       Array of bug data information.
 * @param string  $p_message_id             A message identifier.
 * @param integer $p_user_id                A valid user identifier.
 * @param array   $p_header_optional_params Array of additional telegram message headers.
 * @return void
 */
function telegram_message_bug_info_to_one_user( array $p_visible_bug_data, $p_message_id, $p_user_id, array $p_header_optional_params = null ) {
    $t_telegram_user_id = telegram_user_get_id_by_user_id( $p_user_id );

    # check whether email should be sent
    # @@@ can be email field empty? if yes - then it should be handled here
    if( ON !== plugin_config_get( 'enable_telegram_message_notification' ) || $t_telegram_user_id == NULL || plugin_config_get( 'api_key' ) == NULL ) {
        return;
    }

    # build subject
//    $t_subject = email_build_subject( $p_visible_bug_data['email_bug'] );
//    $t_message = $t_subject . PHP_EOL;
    # build message
    $t_message = plugin_lang_get( $p_message_id );

    if( is_array( $p_header_optional_params ) ) {
        $t_message = vsprintf( $t_message, $p_header_optional_params );
    }

    if( ( $t_message !== null ) && (!is_blank( $t_message ) ) ) {
        $t_message .= " \n";
    }

    $t_message .= telegram_message_format_bug_message( $p_visible_bug_data, TRUE, $p_user_id );

    $data = [
                              'text' => $t_message
    ];

    $t_results = telegram_session_send_message( $t_telegram_user_id, $data );

    foreach( $t_results as $t_result ) {
        if( $t_result->isOk() ) {
            telegram_message_realatationship_add( $p_visible_bug_data['email_bug'], $t_telegram_user_id, $t_result->getResult()->getMessageId() );
        }
    }

    return;
}

/**
 * Sends a generic telegram message to the specific set of recipients.
 *
 * @param integer $p_bug_id                  A bug identifier
 * @param string  $p_notify_type             Notification type
 * @param array   $p_recipients              Array of recipients (key: user id, value: telegram id)
 * @param integer $p_message_id              Message identifier
 * @param array   $p_header_optional_params  Optional Parameters (default null)
 * @return void
 */
function telegram_message_generic_to_recipients( $p_bug_id, $p_notify_type, array $p_recipients, $p_message_id = null, array $p_header_optional_params = null ) {
    if( empty( $p_recipients ) ) {
        return;
    }

    if( OFF == plugin_config_get( 'enable_telegram_message_notification' ) ) {
        return;
    }

    ignore_user_abort( true );

    bugnote_get_all_bugnotes( $p_bug_id );

    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );

    if( is_array( $p_recipients ) ) {
        # send email to every recipient
        foreach( $p_recipients as $t_user_id => $t_telegram_user_id ) {
            plugin_log_event( sprintf( 'Issue = #%d, Type = %s, Msg = \'%s\', User = @U%d, telegram user id = \'%s\'.', $p_bug_id, $p_notify_type, $p_message_id, $t_user_id, $t_telegram_user_id ) );

            # load (push) user language here as build_visible_bug_data assumes current language
            lang_push( user_pref_get_language( $t_user_id, $t_project_id ) );

            $t_visible_bug_data = email_build_visible_bug_data( $t_user_id, $p_bug_id, $p_message_id );
            telegram_message_bug_info_to_one_user( $t_visible_bug_data, $p_message_id, $t_user_id, $p_header_optional_params );

            lang_pop();
        }
    }
}

/**
 * send a generic telegram message
 * $p_notify_type: use check who she get notified of such event.
 * $p_message_id: message id to be translated and included at the top of the email message.
 * Return false if it were problems sending email
 * @param integer $p_bug_id                  A bug identifier.
 * @param string  $p_notify_type             Notification type.
 * @param integer $p_message_id              Message identifier.
 * @param array   $p_header_optional_params  Optional Parameters (default null).
 * @param array   $p_extra_user_ids_to_telegram_message Array of additional users to telegram message.
 * @return void
 */
function telegram_message_generic( $p_bug_id, $p_notify_type, $p_message_id = null, array $p_header_optional_params = null, array $p_extra_user_ids_to_telegram_message = array() ) {
    # @todo yarick123: email_collect_recipients(...) will be completely rewritten to provide additional information such as language, user access,..
    # @todo yarick123:sort recipients list by language to reduce switches between different languages
    $t_recipients = telegram_message_collect_recipients( $p_bug_id, $p_notify_type, $p_extra_user_ids_to_telegram_message );
    telegram_message_generic_to_recipients( $p_bug_id, $p_notify_type, $t_recipients, $p_message_id, $p_header_optional_params );
}

/**
 * Collect valid telegram recipients for telegram messgae notification
 * @todo yarick123: email_collect_recipients(...) will be completely rewritten to provide additional information such as language, user access,..
 * @todo yarick123:sort recipients list by language to reduce switches between different languages
 * @param integer $p_bug_id                  A bug identifier.
 * @param string  $p_notify_type             Notification type.
 * @param array   $p_extra_user_ids_to_telegram_message Array of additional email addresses to notify.
 * @param integer $p_bugnote_id The bugnote id in case of bugnote, otherwise null.
 * @return array
 */
function telegram_message_collect_recipients( $p_bug_id, $p_notify_type, array $p_extra_user_ids_to_telegram_message = array(), $p_bugnote_id = null ) {
    $t_recipients = array();

    # add explicitly specified users
    $t_explicit_enabled = ( ON == telegram_message_notify_flag( $p_notify_type, 'explicit' ) );
    foreach( $p_extra_user_ids_to_telegram_message as $t_user_id ) {
        if( $t_explicit_enabled ) {
            $t_recipients[$t_user_id] = true;
            plugin_log_event( sprintf( 'Issue = #%d, add @U%d (explicitly specified)', $p_bug_id, $t_user_id ) );
        } else {
            plugin_log_event( sprintf( 'Issue = #%d, skip @U%d (explicit disabled)', $p_bug_id, $t_user_id ) );
        }
    }

    # add Reporter
    $t_reporter_id = bug_get_field( $p_bug_id, 'reporter_id' );
    if( ON == telegram_message_notify_flag( $p_notify_type, 'reporter' ) ) {
        $t_recipients[$t_reporter_id] = true;
        plugin_log_event( sprintf( 'Issue = #%d, add @U%d (reporter)', $p_bug_id, $t_reporter_id ) );
    } else {
        plugin_log_event( sprintf( 'Issue = #%d, skip @U%d (reporter disabled)', $p_bug_id, $t_reporter_id ) );
    }

    # add Handler
    $t_handler_id = bug_get_field( $p_bug_id, 'handler_id' );
    if( $t_handler_id > 0 ) {
        if( ON == telegram_message_notify_flag( $p_notify_type, 'handler' ) ) {
            $t_recipients[$t_handler_id] = true;
            plugin_log_event( sprintf( 'Issue = #%d, add @U%d (handler)', $p_bug_id, $t_handler_id ) );
        } else {
            plugin_log_event( sprintf( 'Issue = #%d, skip @U%d (handler disabled)', $p_bug_id, $t_handler_id ) );
        }
    }

    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );

    # add users monitoring the bug
    $t_monitoring_enabled = ON == telegram_message_notify_flag( $p_notify_type, 'monitor' );
    db_param_push();
    $t_query              = 'SELECT DISTINCT user_id FROM {bug_monitor} WHERE bug_id=' . db_param();
    $t_result             = db_query( $t_query, array( $p_bug_id ) );

    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_user_id = $t_row['user_id'];
        if( $t_monitoring_enabled ) {
            $t_recipients[$t_user_id] = true;
            plugin_log_event( sprintf( 'Issue = #%d, add @U%d (monitoring)', $p_bug_id, $t_user_id ) );
        } else {
            plugin_log_event( sprintf( 'Issue = #%d, skip @U%d (monitoring disabled)', $p_bug_id, $t_user_id ) );
        }
    }

    # add Category Owner
    if( ON == telegram_message_notify_flag( $p_notify_type, 'category' ) ) {
        $t_category_id = bug_get_field( $p_bug_id, 'category_id' );

        if( $t_category_id > 0 ) {
            $t_category_assigned_to = category_get_field( $t_category_id, 'user_id' );

            if( $t_category_assigned_to > 0 ) {
                $t_recipients[$t_category_assigned_to] = true;
                plugin_log_event( sprintf( 'Issue = #%d, add Category Owner = @U%d', $p_bug_id, $t_category_assigned_to ) );
            }
        }
    }

    # add users who contributed bugnotes
    $t_bugnote_id = ( $p_bugnote_id === null ) ? bugnote_get_latest_id( $p_bug_id ) : $p_bugnote_id;
    if( $t_bugnote_id !== 0 ) {
        $t_bugnote_date = bugnote_get_field( $t_bugnote_id, 'last_modified' );
    }
    $t_bug      = bug_get( $p_bug_id );
    $t_bug_date = $t_bug->last_updated;

    $t_notes_enabled = ( ON == telegram_message_notify_flag( $p_notify_type, 'bugnotes' ) );
    db_param_push();
    $t_query         = 'SELECT DISTINCT reporter_id FROM {bugnote} WHERE bug_id = ' . db_param();
    $t_result        = db_query( $t_query, array( $p_bug_id ) );
    while( $t_row           = db_fetch_array( $t_result ) ) {
        $t_user_id = $t_row['reporter_id'];
        if( $t_notes_enabled ) {
            $t_recipients[$t_user_id] = true;
            plugin_log_event( sprintf( 'Issue = #%d, add @U%d (note author)', $p_bug_id, $t_user_id ) );
        } else {
            plugin_log_event( sprintf( 'Issue = #%d, skip @U%d (note author disabled)', $p_bug_id, $t_user_id ) );
        }
    }

    # add project users who meet the thresholds
    $t_bug_is_private  = bug_get_field( $p_bug_id, 'view_state' ) == VS_PRIVATE;
    $t_threshold_min   = telegram_message_notify_flag( $p_notify_type, 'threshold_min' );
    $t_threshold_max   = telegram_message_notify_flag( $p_notify_type, 'threshold_max' );
    $t_threshold_users = project_get_all_user_rows( $t_project_id, $t_threshold_min );
    foreach( $t_threshold_users as $t_user ) {
        if( $t_user['access_level'] <= $t_threshold_max ) {
            if( !$t_bug_is_private || access_compare_level( $t_user['access_level'], config_get( 'private_bug_threshold' ) ) ) {
                $t_recipients[$t_user['id']] = true;
                plugin_log_event( sprintf( 'Issue = #%d, add @U%d (based on access level)', $p_bug_id, $t_user['id'] ) );
            }
        }
    }

    # add users as specified by plugins
    $t_recipients_include_data = event_signal( 'EVENT_NOTIFY_USER_INCLUDE', array( $p_bug_id, $p_notify_type ) );
    foreach( $t_recipients_include_data as $t_plugin => $t_recipients_include_data2 ) {
        foreach( $t_recipients_include_data2 as $t_callback => $t_recipients_included ) {
            # only handle if we get an array from the callback
            if( is_array( $t_recipients_included ) ) {
                foreach( $t_recipients_included as $t_user_id ) {
                    $t_recipients[$t_user_id] = true;
                    plugin_log_event( sprintf( 'Issue = #%d, add @U%d (by %s plugin)', $p_bug_id, $t_user_id, $t_plugin ) );
                }
            }
        }
    }

    # FIXME: the value of $p_notify_type could at this stage be either a status
    # or a built-in actions such as 'owner and 'sponsor'. We have absolutely no
    # idea whether 'new' is indicating a new bug has been filed, or if the
    # status of an existing bug has been changed to 'new'. Therefore it is best
    # to just assume built-in actions have precedence over status changes.
    switch( $p_notify_type ) {
        case 'new':
        case 'feedback': # This isn't really a built-in action (delete me!)
        case 'reopened':
        case 'resolved':
        case 'closed':
        case 'bugnote':
            $t_pref_field = 'telegram_message_on_' . $p_notify_type;
            break;
        case 'owner':
            # The telegram_message_on_assigned notification type is now effectively
            # telegram_message_on_change_of_handler.
            $t_pref_field = 'telegram_message_on_assigned';
            break;
        case 'deleted':
        case 'updated':
        case 'sponsor':
        case 'relation':
        case 'monitor':
        case 'priority': # This is never used, but exists in the database!
        # Issue #19459 these notification actions are not actually implemented
        # in the database and therefore aren't adjustable on a per-user
        # basis! The exception is 'monitor' that makes no sense being a
        # customisable per-user preference.
        default:
            # Anything not built-in is probably going to be a status
            $t_pref_field = 'telegram_message_on_status';
            break;
    }

    # @@@ we could optimize by modifiying user_cache() to take an array
    #  of user ids so we could pull them all in.  We'll see if it's necessary
    $t_final_recipients = array();

    $t_user_ids = array_keys( $t_recipients );
    user_cache_array_rows( $t_user_ids );
    user_pref_cache_array_rows( $t_user_ids );
    user_pref_cache_array_rows( $t_user_ids, $t_bug->project_id );

    # Check whether users should receive the telegram message
    # and put telegram user id to $t_recipients[user_id]
    foreach( $t_recipients as $t_id => $t_ignore ) {
        # Possibly eliminate the current user
        if( ( auth_get_current_user_id() == $t_id ) && ( OFF == plugin_config_get( 'telegram_message_receive_own' ) ) ) {
            plugin_log_event( sprintf( 'Issue = #%d, drop @U%d (own action)', $p_bug_id, $t_id ) );
            continue;
        }

        # Eliminate users who don't exist anymore or who are disabled
        if( !user_exists( $t_id ) || !user_is_enabled( $t_id ) ) {
            plugin_log_event( sprintf( 'Issue = #%d, drop @U%d (user disabled)', $p_bug_id, $t_id ) );
            continue;
        }

        # Exclude users who have this notification type turned off
        if( $t_pref_field ) {
            $t_notify = plugin_config_get( $t_pref_field, NULL, FALSE, $t_id );
            if( OFF == $t_notify ) {
                plugin_log_event( sprintf( 'Issue = #%d, drop @U%d (pref %s off)', $p_bug_id, $t_id, $t_pref_field ) );
                continue;
            } else {
                # Users can define the severity of an issue before they are emailed for
                # each type of notification
                $t_min_sev_pref_field = $t_pref_field . '_min_severity';
                $t_min_sev_notify     = plugin_config_get( $t_min_sev_pref_field, NULL, FALSE, $t_id );
                $t_bug_severity       = bug_get_field( $p_bug_id, 'severity' );

                if( $t_bug_severity < $t_min_sev_notify ) {
                    plugin_log_event( sprintf( 'Issue = #%d, drop @U%d (pref threshold)', $p_bug_id, $t_id ) );
                    continue;
                }
            }
        }

        # exclude users who don't have at least viewer access to the bug,
        # or who can't see bugnotes if the last update included a bugnote
        if( !access_has_bug_level( config_get( 'view_bug_threshold', null, $t_id, $t_bug->project_id ), $p_bug_id, $t_id ) || ( $t_bugnote_id !== 0 &&
                $t_bug_date == $t_bugnote_date && !access_has_bugnote_level( config_get( 'view_bug_threshold', null, $t_id, $t_bug->project_id ), $t_bugnote_id, $t_id ) )
        ) {
            plugin_log_event( sprintf( 'Issue = #%d, drop @U%d (access level)', $p_bug_id, $t_id ) );
            continue;
        }

        # check to exclude users as specified by plugins
        $t_recipient_exclude_data = event_signal( 'EVENT_NOTIFY_USER_EXCLUDE', array( $p_bug_id, $p_notify_type, $t_id ) );
        $t_exclude                = false;
        foreach( $t_recipient_exclude_data as $t_plugin => $t_recipient_exclude_data2 ) {
            foreach( $t_recipient_exclude_data2 as $t_callback => $t_recipient_excluded ) {
                # exclude if any plugin returns true (excludes the user)
                if( $t_recipient_excluded ) {
                    $t_exclude = true;
                    plugin_log_event( sprintf( 'Issue = #%d, drop @U%d (by %s plugin)', $p_bug_id, $t_id, $t_plugin ) );
                }
            }
        }

        # user was excluded by a plugin
        if( $t_exclude ) {
            continue;
        }

        # Finally, let's get their emails, if they've set one
        $t_telegram_user_id = telegram_user_get_id_by_user_id( $t_id );
        if( $t_telegram_user_id == NULL ) {
            plugin_log_event( sprintf( 'Issue = #%d, drop @U%d (no telegram user id)', $p_bug_id, $t_id ) );
        } else {
            # @@@ we could check the emails for validity again but I think
            #   it would be too slow
            $t_final_recipients[$t_id] = $t_telegram_user_id;
        }
    }

    return $t_final_recipients;
}

/**
 * Get the value associated with the specific action and flag.
 * For example, you can get the value associated with notifying "admin"
 * on action "new", i.e. notify administrators on new bugs which can be
 * ON or OFF.
 * @param string $p_action Action.
 * @param string $p_flag   Flag.
 * @return integer 1 - enabled, 0 - disabled.
 */
function telegram_message_notify_flag( $p_action, $p_flag ) {
    # If flag is specified for the specific event, use that.
    $t_notify_flags = plugin_config_get( 'notify_flags' );
    if( isset( $t_notify_flags[$p_action][$p_flag] ) ) {
        return $t_notify_flags[$p_action][$p_flag];
    }

    # If not, then use the default if specified in database or global.
    # Note that web UI may not support or specify all flags (e.g. explicit),
    # hence, if config is retrieved from database it may not have the flag.
    $t_default_notify_flags = plugin_config_get( 'default_notify_flags' );
    if( isset( $t_default_notify_flags[$p_flag] ) ) {
        return $t_default_notify_flags[$p_flag];
    }

    # If the flag is not specified so far, then force using global config which
    # should have all flags specified.
    $t_global_default_notify_flags = plugin_config_get( 'default_notify_flags', NULL, TRUE );
    if( isset( $t_global_default_notify_flags[$p_flag] ) ) {
        return $t_global_default_notify_flags[$p_flag];
    }

    return OFF;
}

/**
 * send notices to all the handlers of the parent bugs when a child bug is RESOLVED
 * @param integer $p_bug_id A bug identifier.
 * @return void
 */
function telegram_message_relationship_child_resolved( $p_bug_id ) {
    telegram_message_relationship_child_resolved_closed( $p_bug_id, 'telegram_message_notification_title_for_action_relationship_child_resolved' );
}

/**
 * send notices to all the handlers of the parent bugs when a child bug is CLOSED
 * @param integer $p_bug_id A bug identifier.
 * @return void
 */
function telegram_message_relationship_child_closed( $p_bug_id ) {
    telegram_message_relationship_child_resolved_closed( $p_bug_id, 'telegram_message_notification_title_for_action_relationship_child_closed' );
}

/**
 * send notices to all the handlers of the parent bugs still open when a child bug is resolved/closed
 *
 * @param integer $p_bug_id     A bug identifier.
 * @param integer $p_message_id A message identifier.
 * @return void
 */
function telegram_message_relationship_child_resolved_closed( $p_bug_id, $p_message_id ) {
    # retrieve all the relationships in which the bug is the destination bug
    $t_relationship       = relationship_get_all_dest( $p_bug_id );
    $t_relationship_count = count( $t_relationship );
    if( $t_relationship_count == 0 ) {
        # no parent bug found
        return;
    }

    if( $p_message_id == 'telegram_message_notification_title_for_action_relationship_child_closed' ) {
        plugin_log_event( sprintf( 'Issue #%d child issue closed', $p_bug_id ) );
    } else {
        plugin_log_event( sprintf( 'Issue #%d child issue resolved', $p_bug_id ) );
    }

    for( $i = 0; $i < $t_relationship_count; $i++ ) {
        if( $t_relationship[$i]->type == BUG_DEPENDANT ) {
            $t_src_bug_id = $t_relationship[$i]->src_bug_id;
            $t_status     = bug_get_field( $t_src_bug_id, 'status' );
            if( $t_status < config_get( 'bug_resolved_status_threshold' ) ) {

                # sent the notification just for parent bugs not resolved/closed
                $t_opt   = array();
                $t_opt[] = bug_format_id( $p_bug_id );
                telegram_message_generic( $t_src_bug_id, 'handler', $p_message_id, $t_opt );
            }
        }
    }
}

function telegram_message_realatationship_add( $p_bug_id, $p_chat_id, $p_msg_id ) {
    $t_message_relationship_table = plugin_table( 'message_relationship' );

    $query = "INSERT INTO $t_message_relationship_table
                                                ( bug_id, chat_id, msg_id )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ',' . db_param() . ')';

    db_query( $query, array( $p_bug_id, $p_chat_id, $p_msg_id ) );

    return TRUE;
}

function telegram_message_realatationship_delete( $p_chat_id ) {

    $t_message_relationship_table = plugin_table( 'message_relationship' );

    $query = "DELETE FROM $t_message_relationship_table";

    $query .= " WHERE chat_id=" . db_param();

    $t_fields[] = $p_chat_id;

    db_query( $query, $t_fields );

    return true;
}

function bug_get_id_from_message_id( $p_chat_id, $p_msg_id ) {

    $t_message_relationship_table = plugin_table( 'message_relationship' );

    db_param_push();

    $t_query = "SELECT bug_id
			FROM $t_message_relationship_table
			WHERE chat_id=" . db_param() . " AND msg_id=" . db_param();

    $t_result = db_query( $t_query, array( $p_chat_id, $p_msg_id ) );

    $t_row    = db_fetch_array( $t_result );
    $t_bug_id = $t_row['bug_id'];

    return (int) $t_bug_id;
}
