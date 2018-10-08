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
//    $t_message .= $p_horizontal_separator . " \n";
    //    $t_message .= lang_get( 'email_project' ) . ': ' . $t_project_name . "\n";
    $t_message .= '[ ' . $t_project_name . ' ]' . "\n";
//    $t_message .= lang_get( 'email_bug' ) . ': ' . $t_formatted_bugnote_id . "\n";
    $t_message .= $t_formatted_bug_id . ': ' . $t_bug_summary . "\n";
    $t_message .= $t_bugnote_link . "\n";
    $t_message .= $p_horizontal_separator . " \n";
    $t_message .= $p_bugnote->note . " \n";



    return $t_message;
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
function telegram_message_format_bug_message( array $p_visible_bug_data, $p_include_bugnote = TRUE, $p_user_id = NULL ) {
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

    $t_message .= email_format_attribute( $p_visible_bug_data, 'email_bug' );
    $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_category' );

    if( isset( $p_visible_bug_data['email_tag'] ) ) {
        $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_tag' );
    }

    if( isset( $p_visible_bug_data['email_reproducibility'] ) ) {
        $p_visible_bug_data['email_reproducibility'] = get_enum_element( 'reproducibility', $p_visible_bug_data['email_reproducibility'] );
        $t_message                                   .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_reproducibility' );
    }

    if( isset( $p_visible_bug_data['email_severity'] ) ) {
        $p_visible_bug_data['email_severity'] = get_enum_element( 'severity', $p_visible_bug_data['email_severity'] );
        $t_message                            .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_severity' );
    }

    if( isset( $p_visible_bug_data['email_priority'] ) ) {
        $p_visible_bug_data['email_priority'] = get_enum_element( 'priority', $p_visible_bug_data['email_priority'] );
        $t_message                            .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_priority' );
    }

    if( isset( $p_visible_bug_data['email_status'] ) ) {
        $t_status                           = $p_visible_bug_data['email_status'];
        $p_visible_bug_data['email_status'] = get_enum_element( 'status', $t_status );
        $t_message                          .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_status' );
    }
    if( isset( $p_visible_bug_data['email_target_version'] ) ) {
        $t_message .= email_format_attribute( $p_visible_bug_data, 'email_target_version' );
    }

    # custom fields formatting
    foreach( $p_visible_bug_data['custom_fields'] as $t_custom_field_name => $t_custom_field_data ) {
        $t_message .= utf8_str_pad( lang_get_defaulted( $t_custom_field_name, null ) . ': ', $t_telegram_message_padding_length, ' ', STR_PAD_RIGHT );
        $t_message .= string_custom_field_value_for_email( $t_custom_field_data['value'], $t_custom_field_data['type'] );
        $t_message .= " \n";
    }
    # end foreach custom field
    if( isset( $t_status ) && config_get( 'bug_resolved_status_threshold' ) <= $t_status ) {

        if( isset( $p_visible_bug_data['email_resolution'] ) ) {
            $p_visible_bug_data['email_resolution'] = get_enum_element( 'resolution', $p_visible_bug_data['email_resolution'] );
            $t_message                              .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_resolution' );
        }

        $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_fixed_in_version' );
    }
    $t_message .= $t_telegram_message_separator1 . " \n";

    $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_date_submitted' );
    $t_message .= email_format_attribute( $p_visible_bug_data, 'email_last_modified' );
    if( isset( $p_visible_bug_data['email_due_date'] ) ) {
        $t_message .= telegraml_message_format_attribute( $p_visible_bug_data, 'email_due_date' );
    }
    $t_message .= $t_telegram_message_separator1 . " \n";

    if( isset( $p_visible_bug_data['email_bug_view_url'] ) ) {
        $t_message .= $p_visible_bug_data['email_bug_view_url'] . " \n";
        $t_message .= $t_telegram_message_separator1 . " \n";
    }


    if( isset( $p_visible_bug_data['email_steps_to_reproduce'] ) && !is_blank( $p_visible_bug_data['email_steps_to_reproduce'] ) ) {
        $t_message .= "\n" . lang_get( 'email_steps_to_reproduce' ) . ": \n" . $p_visible_bug_data['email_steps_to_reproduce'] . "\n";
    }

    if( isset( $p_visible_bug_data['email_additional_information'] ) && !is_blank( $p_visible_bug_data['email_additional_information'] ) ) {
        $t_message .= "\n" . lang_get( 'email_additional_information' ) . ": \n" . $p_visible_bug_data['email_additional_information'] . "\n";
    }

    if( isset( $p_visible_bug_data['relations'] ) ) {
        if( $p_visible_bug_data['relations'] != '' ) {
            $t_message .= $t_telegram_message_separator1 . "\n" . utf8_str_pad( lang_get( 'bug_relationships' ), 20 ) . utf8_str_pad( lang_get( 'id' ), 8 ) . lang_get( 'summary' ) . "\n" . $t_telegram_message_separator2 . "\n" . $p_visible_bug_data['relations'];
        }
    }
    # Sponsorship
    if( isset( $p_visible_bug_data['sponsorship_total'] ) && ( $p_visible_bug_data['sponsorship_total'] > 0 ) ) {
        $t_message .= $t_telegram_message_separator1 . " \n";
        $t_message .= sprintf( lang_get( 'total_sponsorship_amount' ), sponsorship_format_amount( $p_visible_bug_data['sponsorship_total'] ) ) . "\n\n";

        if( isset( $p_visible_bug_data['sponsorships'] ) ) {
            foreach( $p_visible_bug_data['sponsorships'] as $t_sponsorship ) {
                $t_date_added = date( config_get( 'normal_date_format' ), $t_sponsorship->date_submitted );

                $t_message .= $t_date_added . ': ';
                $t_message .= user_get_name( $t_sponsorship->user_id );
                $t_message .= ' (' . sponsorship_format_amount( $t_sponsorship->amount ) . ')' . " \n";
            }
        }
    }

    $t_message .= $t_telegram_message_separator1 . " \n\n";
    # format bugnote
    if( $p_include_bugnote ) {
        if( plugin_config_get( 'telegram_message_included_all_bugnote_is', NULL, FALSE, $p_user_id, NULL ) ) {
            foreach( $p_visible_bug_data['bugnotes'] as $t_bugnote ) {
                # Show time tracking is always true, since data has already been filtered out when creating the bug visible data.
                $t_message .= email_format_bugnote( $t_bugnote, $p_visible_bug_data['email_project_id'],
//        $t_message .= telegram_message_format_bugnote( $t_bugnote, $p_visible_bug_data['email_project_id'],
                                /* show_time_tracking */ true, $t_telegram_message_separator2, $t_normal_date_format ) . "\n";
            }
        } else {
            $t_bugnotes_last_id = bugnote_get_latest_id( $p_visible_bug_data['email_bug'] );
            if( $t_bugnotes_last_id != NULL ) {
                $t_bugnote = bugnote_get( $t_bugnotes_last_id );
                $t_message .= email_format_bugnote( $t_bugnote, $p_visible_bug_data['email_project_id'],
//        $t_message .= telegram_message_format_bugnote( $p_visible_bug_data['bugnotes'][$t_bugnotes_count - 1], $p_visible_bug_data['email_project_id'],
                                /* show_time_tracking */ true, $t_telegram_message_separator2, $t_normal_date_format ) . "\n";
            }
        }
    }
//    if( $p_include_bugnote ) {
//        foreach( $p_visible_bug_data['bugnotes'] as $t_bugnote ) {
//            # Show time tracking is always true, since data has already been filtered out when creating the bug visible data.
//            $t_message .= email_format_bugnote( $t_bugnote, $p_visible_bug_data['email_project_id'],
////        $t_message .= telegram_message_format_bugnote( $t_bugnote, $p_visible_bug_data['email_project_id'],
//                            /* show_time_tracking */ true, $t_telegram_message_separator2, $t_normal_date_format ) . "\n";
//        }
//    }
//    # format history
//    if( array_key_exists( 'history', $p_visible_bug_data ) ) {
//        $t_message .= lang_get( 'bug_history' ) . " \n";
//        $t_message .= utf8_str_pad( lang_get( 'date_modified' ), 17 ) . utf8_str_pad( lang_get( 'username' ), 15 ) . utf8_str_pad( lang_get( 'field' ), 25 ) . utf8_str_pad( lang_get( 'change' ), 20 ) . " \n";
//
//        $t_message .= $t_telegram_message_separator1 . " \n";
//
//        foreach( $p_visible_bug_data['history'] as $t_raw_history_item ) {
//            $t_localized_item = history_localize_item( $t_raw_history_item['field'], $t_raw_history_item['type'], $t_raw_history_item['old_value'], $t_raw_history_item['new_value'], false );
//
//            $t_message .= utf8_str_pad( date( $t_normal_date_format, $t_raw_history_item['date'] ), 17 ) . utf8_str_pad( $t_raw_history_item['username'], 15 ) . utf8_str_pad( $t_localized_item['note'], 25 ) . utf8_str_pad( $t_localized_item['change'], 20 ) . "\n";
//        }
//        $t_message .= $t_telegram_message_separator1 . " \n\n";
//    }

    return $t_message;
}
