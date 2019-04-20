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

function telegram_bug_add( $p_bug_data_draft, $p_orgl_chat_id, $p_callback_msg_id ) {

    $t_issue = array(
                              'project'     => array( 'id' => $p_bug_data_draft['project'] ),
                              'reporter'    => array( 'id' => auth_get_current_user_id() ),
                              'summary'     => $p_bug_data_draft['summary'],
                              'description' => $p_bug_data_draft['description'],
    );

    $t_tag_string = '';
    $f_tag_select = $p_bug_data_draft['tag_select'] == NULL ? 0 : $p_bug_data_draft['tag_select'];
    if( $f_tag_select != 0 ) {
        $t_tag_string = tag_get_name( $f_tag_select );
    }

    $f_tag_string = $p_bug_data_draft['tag_string'] == NULL ? '' : $p_bug_data_draft['tag_string'];
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

    $f_files = $p_bug_data_draft['ufile'] == NULL ? null : $p_bug_data_draft['ufile'];
    if( $f_files !== null && !empty( $f_files ) ) {
        $t_issue['files'] = helper_array_transpose( $f_files );
    }

    $t_build = $p_bug_data_draft['build'] == NULL ? '' : $p_bug_data_draft['build'];
    if( !is_blank( $t_build ) ) {
        $t_issue['build'] = $t_build;
    }

    $t_platform = $p_bug_data_draft['platform'] == NULL ? '' : $p_bug_data_draft['platform'];
    if( !is_blank( $t_platform ) ) {
        $t_issue['platform'] = $t_platform;
    }

    $t_os = $p_bug_data_draft['os'] == NULL ? '' : $p_bug_data_draft['os'];
    if( !is_blank( $t_os ) ) {
        $t_issue['os'] = $t_os;
    }

    $t_os_build = $p_bug_data_draft['os_build'] == NULL ? '' : $p_bug_data_draft['os_build'];
    if( !is_blank( $t_os_build ) ) {
        $t_issue['os_build'] = $t_os_build;
    }

    $t_version = $p_bug_data_draft['product_version'] == NULL ? '' : $p_bug_data_draft['product_version'];
    if( !is_blank( $t_version ) ) {
        $t_issue['version'] = array( 'name' => $t_version );
    }

    $t_target_version = $p_bug_data_draft['target_version'] == NULL ? '' : $p_bug_data_draft['target_version'];
    if( !is_blank( $t_target_version ) ) {
        $t_issue['target_version'] = array( 'name' => $t_target_version );
    }

    $t_profile_id = $p_bug_data_draft['profile_id'] == NULL ? 0 : $p_bug_data_draft['profile_id'];
    if( $t_profile_id != 0 ) {
        $t_issue['profile'] = array( 'id' => $t_profile_id );
    }

    $t_handler_id = $p_bug_data_draft['handler'] == NULL ? NO_USER : $p_bug_data_draft['handler'];
    if( $t_handler_id != NO_USER ) {
        $t_issue['handler'] = array( 'id' => $t_handler_id );
    }

    $t_view_state = $p_bug_data_draft['view_state'] == NULL ? 0 : $p_bug_data_draft['view_state'];
    if( $t_view_state != 0 ) {
        $t_issue['view_state'] = array( 'id' => $t_view_state );
    }

    $t_category_id = $p_bug_data_draft['category'] == NULL ? 0 : $p_bug_data_draft['category'];
    if( $t_category_id != 0 ) {
        $t_issue['category'] = array( 'id' => $t_category_id );
    }

    $t_reproducibility = $p_bug_data_draft['reproducibility'] == NULL ? 0 : $p_bug_data_draft['reproducibility'];
    if( $t_reproducibility != 0 ) {
        $t_issue['reproducibility'] = array( 'id' => $t_reproducibility );
    }

    $t_severity = $p_bug_data_draft['severity'] == NULL ? 0 : $p_bug_data_draft['severity'];
    if( $t_severity != 0 ) {
        $t_issue['severity'] = array( 'id' => $t_severity );
    }

    $t_priority = $p_bug_data_draft['priority'] == NULL ? 0 : $p_bug_data_draft['priority'];
    if( $t_priority != 0 ) {
        $t_issue['priority'] = array( 'id' => $t_priority );
    }

    $t_projection = $p_bug_data_draft['projection'] == NULL ? 0 : $p_bug_data_draft['projection'];
    if( $t_projection != 0 ) {
        $t_issue['projection'] = array( 'id' => $t_projection );
    }

    $t_eta = $p_bug_data_draft['eta'] == NULL ? 0 : $p_bug_data_draft['eta'];
    if( $t_eta != 0 ) {
        $t_issue['eta'] = array( 'id' => $t_eta );
    }

    $t_resolution = $p_bug_data_draft['resolution'] == NULL ? 0 : $p_bug_data_draft['resolution'];
    if( $t_resolution != 0 ) {
        $t_issue['resolution'] = array( 'id' => $t_resolution );
    }

    $t_status = $p_bug_data_draft['status'] == NULL ? 0 : $p_bug_data_draft['status'];
    if( $t_status != 0 ) {
        $t_issue['status'] = array( 'id' => $t_status );
    }

    $t_steps_to_reproduce = $p_bug_data_draft['steps_to_reproduce'] == NULL ? null : $p_bug_data_draft['steps_to_reproduce'];
    if( $t_steps_to_reproduce !== null ) {
        $t_issue['steps_to_reproduce'] = $t_steps_to_reproduce;
    }

    $t_additional_info = $p_bug_data_draft['additional_info'] == NULL ? null : $p_bug_data_draft['additional_info'];
    if( $t_additional_info !== null ) {
        $t_issue['additional_information'] = $t_additional_info;
    }

    $t_due_date = $p_bug_data_draft['due_date'] == NULL ? null : $p_bug_data_draft['due_date'];
    if( $t_due_date !== null ) {
        $t_issue['due_date'] = $t_due_date;
    }

    $t_data = array(
                              'payload' => array( 'issue' => $t_issue ),
    );

    try {
        $t_command  = new IssueAddCommand( $t_data );
        $t_result   = $t_command->execute();
        $t_issue_id = (int) $t_result['issue_id'];

        $t_data_send = [
                                  'chat_id'             => $p_orgl_chat_id,
                                  'reply_to_message_id' => $p_callback_msg_id,
                                  'message_id'          => $p_callback_msg_id,
                                  'text'                => sprintf( plugin_lang_get( 'bug_creation_complete' ), lang_get( 'bug' ) ) . $t_issue_id
        ];
    } catch( Mantis\Exceptions\MantisException $t_error ) {

        $t_params = $t_error->getParams();
        if( !empty( $t_params ) ) {
            call_user_func_array( 'error_parameters', $t_params );
        }

        $t_error_text = error_string( $t_error->getCode() );
        $t_data_send  = [
                                  'chat_id'    => $p_orgl_chat_id,
                                  'message_id' => $p_callback_msg_id,
                                  'text'       => $t_error_text
        ];
    }

    plugin_config_delete( 'bug_data_draft', auth_get_current_user_id() );

    return $t_data_send;
}
