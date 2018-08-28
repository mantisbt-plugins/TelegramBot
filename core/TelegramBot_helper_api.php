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
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="ДА" />';
    echo "\n</form>";

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );
    echo '<input type="hidden" name="_confirmed" value="0" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="НЕТ" />';
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

function telegram_report_bug( $p_current_action, $p_orgl_chat_id, $p_callback_msg_id ) {

    $t_command = array_keys( $p_current_action );

    switch( $t_command[0] ) {
        case '' :
        case 'get_project':
//            $t_default_project = user_pref_get_pref( auth_get_current_user_id(), 'default_project' );

            $t_inline_keyboard = keyboard_projects_get( $p_current_action['get_project'], $p_current_action['page'], $p_current_action['from_page'] );
            $t_text            = lang_get( 'project_selection_title' );
            break;
        case 'set_project':
            
            $t_inline_keyboard = keyboard_projects_get( $p_current_action['set_project'], $p_current_action['page'] );
//            $t_text            = lang_get( 'project_selection_title' );
    }


    $t_data_send = [
                              'chat_id'      => $p_orgl_chat_id,
                              'message_id'   => $p_callback_msg_id,
                              'text'         => $t_text,
                              'reply_markup' => $t_inline_keyboard,
    ];
    return $t_data_send;
}
