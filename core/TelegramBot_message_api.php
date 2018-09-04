<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Send a notification to user or set of users that were mentioned in an issue
 * or an issue note.
 *
 * @param integer       $p_bug_id     Issue for which the reminder is sent.
 * @param array         $p_mention_user_ids User id or list of user ids array.
 * @param string        $p_message    Optional message to add to the e-mail.
 * @param array         $p_removed_mention_user_ids  The users that were removed due to lack of access.
 * @return array        List of users ids to whom the mentioned e-mail were actually sent
 */
function telegram_message_user_mention( $p_bug_id, $p_mention_user_ids, $p_message, $p_removed_mention_user_ids = array() ) {
    if( OFF == plugin_config_get( 'enable_telegram_message_notification' ) || plugin_config_get( 'api_key' ) == NULL ) {
        plugin_log_event( 'telegram notifications disabled.' );
        return array();
    }

    $t_tg = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );

    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
    $t_sender_id  = auth_get_current_user_id();
    $t_sender     = user_get_name( $t_sender_id );

    $t_project_name = project_get_field( bug_get_field( $p_bug_id, 'project_id' ), 'name' );

    $t_bug_summary = bug_get_field( $p_bug_id, 'summary' );

    $t_formatted_bug_id = bug_format_id( $p_bug_id );

    $t_subject         = email_build_subject( $p_bug_id );
    $t_date            = date( config_get( 'normal_date_format' ) );
    $t_user_id         = auth_get_current_user_id();
    $t_users_processed = array();

    foreach( $p_removed_mention_user_ids as $t_removed_mention_user_id ) {
        plugin_log_event( 'skipped mention telegram for U' . $t_removed_mention_user_id . ' (no access to issue or note).' );
    }

    $t_result = array();
    foreach( $p_mention_user_ids as $t_mention_user_id ) {
        # Don't trigger mention emails for self mentions
        if( $t_mention_user_id == $t_user_id ) {
            plugin_log_event( 'skipped mention telegram for U' . $t_mention_user_id . ' (self-mention).' );
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

        $t_telegram_user_id = telegram_user_get_id_by_user_id( $t_mention_user_id );

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

        $data = [
                                  'chat_id' => $t_telegram_user_id,
                                  'text'    => $t_contents
        ];

        $t_result_send = Longman\TelegramBot\Request::sendMessage( $data );
        if( $t_result_send->getOk() ) {
            $t_result[] = $t_mention_user_id;
        }

        lang_pop();
    }

    return $t_result;
}

/**
 * Generates a formatted note to be used in email notifications.
 *
 * @param BugnoteData $p_bugnote The bugnote object.
 * @param integer $p_project_id  The project id
 * @param boolean $p_show_time_tracking true: show time tracking, false otherwise.
 * @param string $p_horizontal_separator The horizontal line separator to use.
 * @param string $p_date_format The date format to use.
 * @return string The formatted note.
 */
function telegram_message_format_bugnote( $p_bugnote, $p_project_id, $p_show_time_tracking, $p_horizontal_separator, $p_date_format = null ) {
    $t_date_format = ( $p_date_format === null ) ? config_get( 'normal_date_format' ) : $p_date_format;

    # grab the project name
    $t_project_name     = project_get_field( bug_get_field( $p_bugnote->bug_id, 'project_id' ), 'name' );
    $t_bug_summary      = bug_get_field( $p_bugnote->bug_id, 'summary' );
    # pad the bug id with zeros
//    $t_bug_id       = bug_format_id( $p_bugnote->bug_id );
//
//    $t_last_modified = date( $t_date_format, $p_bugnote->last_modified );
    $t_formatted_bug_id = bug_format_id( $p_bugnote->bug_id );
    $t_bugnote_link     = string_process_bugnote_link( config_get( 'bugnote_link_tag' ) . $p_bugnote->id, false, false, true );

    if( $p_show_time_tracking && $p_bugnote->time_tracking > 0 ) {
        $t_time_tracking = "\n" . ' ' . lang_get( 'time_tracking' ) . ' ' . db_minutes_to_hhmm( $p_bugnote->time_tracking ) . "\n";
    } else {
        $t_time_tracking = '';
    }

    if( user_exists( $p_bugnote->reporter_id ) ) {
        $t_access_level        = access_get_project_level( $p_project_id, $p_bugnote->reporter_id );
        $t_access_level_string = ' (' . access_level_get_string( $t_access_level ) . ')';
    } else {
        $t_access_level_string = '';
    }

    $t_private = ( $p_bugnote->view_state == VS_PUBLIC ) ? '' : ' (' . lang_get( 'private' ) . ')';

//	$t_string = ' (' . $t_formatted_bugnote_id . ') ' . user_get_name( $p_bugnote->reporter_id ) .
//		$t_access_level_string . ' - ' . $t_last_modified . $t_private . "\n" .
//		$t_time_tracking . ' ' . $t_bugnote_link;

    $t_string  = user_get_name( $p_bugnote->reporter_id ) .
            $t_access_level_string . $t_private . $t_time_tracking;
    $t_message = plugin_lang_get( 'telegram_message_notification_title_for_action_bugnote_submitted' ) . "\n";
    $t_message .= $t_string . " \n";
    $t_message .= $p_horizontal_separator . " \n";
    $t_message .= $p_bugnote->note . " \n";
    $t_message .= $p_horizontal_separator . " \n";
//    $t_message .= lang_get( 'email_project' ) . ': ' . $t_project_name . "\n";
    $t_message .= '[ ' . $t_project_name . ' ]' . "\n";
//    $t_message .= lang_get( 'email_bug' ) . ': ' . $t_formatted_bugnote_id . "\n";
    $t_message .= $t_formatted_bug_id . ': ' . $t_bug_summary . "\n";
    $t_message .= $t_bugnote_link . "\n";

    return $t_message;
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

    $t_tg = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );

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

    # send email to every recipient
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
                                  'chat_id' => $t_telegram_user_id,
                                  'text'    => $t_contents
        ];

        $t_result = Longman\TelegramBot\Request::sendMessage( $data );

        lang_pop();
    }

    # Send emails out for users that select verbose notifications
    telegram_message_generic_to_recipients(
            $t_bugnote->bug_id, 'bugnote', $t_recipients_verbose, $t_message_id );
}

/**
 * if $p_visible_bug_data contains specified attribute the function
 * returns concatenated translated attribute name and original
 * attribute value. Else return empty string.
 * @param array  $p_visible_bug_data Visible Bug Data array.
 * @param string $p_attribute_id     Attribute ID.
 * @return string
 */
function telegraml_message_format_attribute( array $p_visible_bug_data, $p_attribute_id ) {
    if( array_key_exists( $p_attribute_id, $p_visible_bug_data ) ) {
        return utf8_str_pad( lang_get( $p_attribute_id ) . ': ', plugin_config_get( 'telegram_message_padding_length' ), ' ', STR_PAD_RIGHT ) . $p_visible_bug_data[$p_attribute_id] . "\n";
//        return lang_get( $p_attribute_id ) . ':' . PHP_EOL . plugin_config_get( 'telegram_message_separator2' ) . PHP_EOL . $p_visible_bug_data[$p_attribute_id] . PHP_EOL . plugin_config_get( 'telegram_message_separator2' ) . PHP_EOL;
    }
    return '';
}

/**
 * Build the bug info part of the message
 * @param array $p_visible_bug_data Bug data array to format.
 * @return string
 */
function telegram_message_format_bug_message( array $p_visible_bug_data ) {
    $t_normal_date_format   = config_get( 'normal_date_format' );
    $t_complete_date_format = config_get( 'complete_date_format' );

    $t_telegram_message_separator1     = plugin_config_get( 'telegram_message_separator1' );
    $t_telegram_message_separator2     = plugin_config_get( 'telegram_message_separator2' );
    $t_telegram_message_padding_length = plugin_config_get( 'telegram_message_padding_length' );

    $p_visible_bug_data['email_date_submitted'] = date( $t_complete_date_format, $p_visible_bug_data['email_date_submitted'] );
    $p_visible_bug_data['email_last_modified']  = date( $t_complete_date_format, $p_visible_bug_data['email_last_modified'] );

    $t_message = $t_telegram_message_separator1 . " \n";

    $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_project' );
    $t_message .= $t_telegram_message_separator2 . " \n";
    $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_summary' );
    $t_message .= $t_telegram_message_separator2 . " \n";
    $t_message .= lang_get( 'email_description' ) . ": \n" . $p_visible_bug_data['email_description'] . "\n";

    $t_message .= $t_telegram_message_separator1 . " \n";

    $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_reporter' );
    $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_handler' );
    $t_message .= $t_telegram_message_separator1 . " \n";

//    $t_message .= email_format_attribute( $p_visible_bug_data, 'email_bug' );
//    $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_category' );
//
//    if( isset( $p_visible_bug_data['email_tag'] ) ) {
//        $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_tag' );
//    }
//
//    if( isset( $p_visible_bug_data['email_reproducibility'] ) ) {
//        $p_visible_bug_data['email_reproducibility'] = get_enum_element( 'reproducibility', $p_visible_bug_data['email_reproducibility'] );
//        $t_message                                   .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_reproducibility' );
//    }
//
//    if( isset( $p_visible_bug_data['email_severity'] ) ) {
//        $p_visible_bug_data['email_severity'] = get_enum_element( 'severity', $p_visible_bug_data['email_severity'] );
//        $t_message                            .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_severity' );
//    }
//
//    if( isset( $p_visible_bug_data['email_priority'] ) ) {
//        $p_visible_bug_data['email_priority'] = get_enum_element( 'priority', $p_visible_bug_data['email_priority'] );
//        $t_message                            .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_priority' );
//    }
//
//    if( isset( $p_visible_bug_data['email_status'] ) ) {
//        $t_status                           = $p_visible_bug_data['email_status'];
//        $p_visible_bug_data['email_status'] = get_enum_element( 'status', $t_status );
//        $t_message                          .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_status' );
//    }
//    if( isset( $p_visible_bug_data['email_target_version'] ) ) {
//        $t_message .= email_format_attribute( $p_visible_bug_data, 'email_target_version' );
//    }
//
//    # custom fields formatting
//    foreach( $p_visible_bug_data['custom_fields'] as $t_custom_field_name => $t_custom_field_data ) {
//        $t_message .= utf8_str_pad( lang_get_defaulted( $t_custom_field_name, null ) . ': ', $t_email_padding_length, ' ', STR_PAD_RIGHT );
//        $t_message .= string_custom_field_value_for_email( $t_custom_field_data['value'], $t_custom_field_data['type'] );
//        $t_message .= " \n";
//    }
    # end foreach custom field
//    if( isset( $t_status ) && config_get( 'bug_resolved_status_threshold' ) <= $t_status ) {
//
//        if( isset( $p_visible_bug_data['email_resolution'] ) ) {
//            $p_visible_bug_data['email_resolution'] = get_enum_element( 'resolution', $p_visible_bug_data['email_resolution'] );
//            $t_message                              .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_resolution' );
//        }
//
//        $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_fixed_in_version' );
//    }
//    $t_message .= $t_telegram_message_separator1 . " \n";
//
//    $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_date_submitted' );
//    $t_message .= email_format_attribute( $p_visible_bug_data, 'email_last_modified' );
//    if( isset( $p_visible_bug_data['email_due_date'] ) ) {
//        $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_due_date' );
//    }
//    $t_message .= $t_telegram_message_separator1 . " \n";

    if( isset( $p_visible_bug_data['email_bug_view_url'] ) ) {
        $t_message .= $p_visible_bug_data['email_bug_view_url'] . " \n";
        $t_message .= $t_telegram_message_separator1 . " \n";
    }


//    if( isset( $p_visible_bug_data['email_steps_to_reproduce'] ) && !is_blank( $p_visible_bug_data['email_steps_to_reproduce'] ) ) {
//        $t_message .= "\n" . lang_get( 'email_steps_to_reproduce' ) . ": \n" . $p_visible_bug_data['email_steps_to_reproduce'] . "\n";
//    }
//
//    if( isset( $p_visible_bug_data['email_additional_information'] ) && !is_blank( $p_visible_bug_data['email_additional_information'] ) ) {
//        $t_message .= "\n" . lang_get( 'email_additional_information' ) . ": \n" . $p_visible_bug_data['email_additional_information'] . "\n";
//    }
//
//    if( isset( $p_visible_bug_data['relations'] ) ) {
//        if( $p_visible_bug_data['relations'] != '' ) {
//            $t_message .= $t_email_separator1 . "\n" . utf8_str_pad( lang_get( 'bug_relationships' ), 20 ) . utf8_str_pad( lang_get( 'id' ), 8 ) . lang_get( 'summary' ) . "\n" . $t_email_separator2 . "\n" . $p_visible_bug_data['relations'];
//        }
//    }
//    # Sponsorship
//    if( isset( $p_visible_bug_data['sponsorship_total'] ) && ( $p_visible_bug_data['sponsorship_total'] > 0 ) ) {
//        $t_message .= $t_email_separator1 . " \n";
//        $t_message .= sprintf( lang_get( 'total_sponsorship_amount' ), sponsorship_format_amount( $p_visible_bug_data['sponsorship_total'] ) ) . "\n\n";
//
//        if( isset( $p_visible_bug_data['sponsorships'] ) ) {
//            foreach( $p_visible_bug_data['sponsorships'] as $t_sponsorship ) {
//                $t_date_added = date( config_get( 'normal_date_format' ), $t_sponsorship->date_submitted );
//
//                $t_message .= $t_date_added . ': ';
//                $t_message .= user_get_name( $t_sponsorship->user_id );
//                $t_message .= ' (' . sponsorship_format_amount( $t_sponsorship->amount ) . ')' . " \n";
//            }
//        }
//    }
//
//    $t_message .= $t_email_separator1 . " \n\n";
//    # format bugnotes
//    foreach( $p_visible_bug_data['bugnotes'] as $t_bugnote ) {
//        # Show time tracking is always true, since data has already been filtered out when creating the bug visible data.
//        $t_message .= email_format_bugnote( $t_bugnote, $p_visible_bug_data['email_project_id'],
//                        /* show_time_tracking */ true, $t_email_separator2, $t_normal_date_format ) . "\n";
//    }
//    # format history
//    if( array_key_exists( 'history', $p_visible_bug_data ) ) {
//        $t_message .= lang_get( 'bug_history' ) . " \n";
//        $t_message .= utf8_str_pad( lang_get( 'date_modified' ), 17 ) . utf8_str_pad( lang_get( 'username' ), 15 ) . utf8_str_pad( lang_get( 'field' ), 25 ) . utf8_str_pad( lang_get( 'change' ), 20 ) . " \n";
//
//        $t_message .= $t_email_separator1 . " \n";
//
//        foreach( $p_visible_bug_data['history'] as $t_raw_history_item ) {
//            $t_localized_item = history_localize_item( $t_raw_history_item['field'], $t_raw_history_item['type'], $t_raw_history_item['old_value'], $t_raw_history_item['new_value'], false );
//
//            $t_message .= utf8_str_pad( date( $t_normal_date_format, $t_raw_history_item['date'] ), 17 ) . utf8_str_pad( $t_raw_history_item['username'], 15 ) . utf8_str_pad( $t_localized_item['note'], 25 ) . utf8_str_pad( $t_localized_item['change'], 20 ) . "\n";
//        }
//        $t_message .= $t_email_separator1 . " \n\n";
//    }

    return $t_message;
}

/**
 * Send bug info to given user
 * return true on success
 * @param array   $p_visible_bug_data       Array of bug data information.
 * @param string  $p_message_id             A message identifier.
 * @param integer $p_user_id                A valid user identifier.
 * @param array   $p_header_optional_params Array of additional email headers.
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

//    if( is_array( $p_header_optional_params ) ) {
//        $t_message = vsprintf( $t_message, $p_header_optional_params );
//    }

    if( ( $t_message !== null ) && (!is_blank( $t_message ) ) ) {
        $t_message .= " \n";
    }

    $t_message .= telegram_message_format_bug_message( $p_visible_bug_data );

    $t_tg = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );

    $data = [
                              'chat_id' => $t_telegram_user_id,
                              'text'    => $t_message
    ];

    $t_result = Longman\TelegramBot\Request::sendMessage( $data );

    return;
}

/**
 * Sends a generic telegram message to the specific set of recipients.
 *
 * @param integer $p_bug_id                  A bug identifier
 * @param string  $p_notify_type             Notification type
 * @param array   $p_recipients              Array of recipients (key: user id, value: email address)
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
        if( ( auth_get_current_user_id() == $t_id ) && ( OFF == config_get( 'telegram_message_receive_own' ) ) ) {
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
