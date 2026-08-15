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

    $t_category_id = array_key_exists( 'category', $p_bug_data_draft ) ? $p_bug_data_draft['category'] : 0;
    if( (int)$t_category_id != 0 ) {
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
    
    plugin_history_log( $t_issue_id, lang_get("new_bug_title"), "", lang_get("email_notification_title_for_action_bug_submitted") . " " . $t_issue_id );
    
    return $t_issue_id;
   
}
