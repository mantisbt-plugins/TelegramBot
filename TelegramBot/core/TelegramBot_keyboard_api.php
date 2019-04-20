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

function telegram_bot_keyboard_rows_default_filter() {

    $t_keyboard_rows   = array();
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_assigned' ), 'callback_data' => json_encode( array( 'add_comment' => array( 'get_bugs' => array( 'assigned' => array( 'page' => 1 ) ) ) ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_monitored' ), 'callback_data' => json_encode( array( 'add_comment' => array( 'get_bugs' => array( 'monitored' => array( 'page' => 1 ) ) ) ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_reported' ), 'callback_data' => json_encode( array( 'add_comment' => array( 'get_bugs' => array( 'reported' => array( 'page' => 1 ) ) ) ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'use_query' ), 'callback_data' => json_encode( array( 'add_comment' => array( 'get_bugs' => array( 'use_query' => array( 'page' => 1 ) ) ) ) ) ];

    return $t_keyboard_rows;
}

function telegram_bot_get_keyboard_default_filter() {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    foreach( telegram_bot_keyboard_rows_default_filter() as $t_row ) {
        $t_inline_keyboard->addRow( $t_row );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '>> ' . plugin_lang_get( 'keyboard_button_back' ) . ' <<',
                              'callback_data' => json_encode( array( 'action_select' => '' ) )
    ] );

    return $t_inline_keyboard;
}

function keyboard_bugs_get( $p_mantis_custom_filter, $p_page ) {
    $t_per_page   = null;
    $t_bug_count  = null;
    $t_page_count = null;


    $t_current_page = $p_page;

    $t_bugs = filter_get_bug_rows( $t_current_page, $t_per_page, $t_page_count, $t_bug_count, $p_mantis_custom_filter, 0, null, true );

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    if( $t_current_page > 1 && $t_current_page <= $t_page_count ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '<<',
                                  'callback_data' => json_encode( array( 'add_comment' => array( 'get_bugs' => array( 'use_query' => array( 'page' => $t_current_page - 1 ) ) ) ) )
        ] );
    }

    foreach( $t_bugs as $t_bug ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => $t_bug->id . ': ' . $t_bug->summary,
                                  'callback_data' => json_encode( array( 'add_comment' => array( 'set_bug' => $t_bug->id ) ) )
        ] );
    }

    if( $t_current_page > 1 && $t_current_page <= $t_page_count ) {
        $t_inline_keyboard->addRow( [ 'text'          => '<<',
                                  'callback_data' => json_encode( array( 'add_comment' => array( 'get_bugs' => array( 'use_query' => array( 'page' => $t_current_page - 1 ) ) ) ) )
        ] );
    }

    if( $t_current_page >= 1 && $t_current_page < $t_page_count ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '>>',
                                  'callback_data' => json_encode( array( 'add_comment' => array( 'get_bugs' => array( 'use_query' => array( 'page' => $t_current_page + 1 ) ) ) ) )
        ] );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '>>' . plugin_lang_get( 'keyboard_button_list_of_sections' ) . '<<',
                              'callback_data' => json_encode( array( 'add_comment' => array( 'get_default_category' => '' ) ) )
    ] );


    return $t_inline_keyboard;
}

function keyboard_get_menu_operations() {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    if( access_has_any_project_level( 'report_bug_threshold' ) ) {

//        $t_inline_keyboard->addRow( [
//                                  'text'          => lang_get( 'report_bug_link' ),
//                                  'callback_data' => json_encode( array( 'report_bug' => array( 'get_fields' => '' ) ) )
//        ] );
        $t_inline_keyboard->addRow( [
                                  'text'          => lang_get( 'report_bug_link' ),
                                  'callback_data' => json_encode( array( 'rb' => array( 'gp' => array(
                                                                                                                'id' => 0,
                                                                                                                'p'  => 1,
                                                                                                                'fp' => 1
                                                                                      )
                                                    ) ) )
        ] );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => lang_get( 'add_bugnote_title' ),
                              'callback_data' => json_encode( array( 'add_comment' => array( 'get_default_category' => '' ) ) )
    ] );

    return $t_inline_keyboard;
}

function keyboard_projects_get( $p_selected_project = ALL_PROJECTS, $p_page = 1, $p_from_page = 1 ) {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    $t_user_id = auth_get_current_user_id();

    if( $p_selected_project == ALL_PROJECTS ) {
        $t_project_ids = user_get_accessible_projects( $t_user_id );
    } else {
        $t_project_ids = user_get_accessible_subprojects( $t_user_id, $p_selected_project );
    }

    project_cache_array_rows( $t_project_ids );

    for( $i = ($p_page * 10) - 10; $i < ($p_page * 10) && $i < count( $t_project_ids ); $i++ ) {

        $t_child_project_ids = user_get_accessible_subprojects( $t_user_id, $t_project_ids[$i] );
        $t_inline_keyboard->addRow( [
                                  'text'          => project_get_field( $t_project_ids[$i], 'name' ),
                                  'callback_data' => json_encode( array(
                                                            'rb' => array( 'sp' => array( 'id' => $t_project_ids[$i] ) )
                                  ) )
                ], count( $t_child_project_ids ) > 0 ? [
                                          'text'          => '>>',
                                          'callback_data' => json_encode( array( 'rb' => array( 'gp' => array(
                                                                                                                        'id' => $t_project_ids[$i],
                                                                                                                        'p'  => 1,
                                                                                                                        'fp' => $p_page
                                                                                              ) )
                                          ) )
                        ] : []
        );
    }

    if( $p_page > 1 ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '<<',
                                  'callback_data' => json_encode( array( 'rb' => array( 'gp' => array(
                                                                                                                'id' => $p_selected_project,
                                                                                                                'p'  => $p_page - 1,
                                                                                                                'fp' => $p_from_page
                                                                                      ) )
                                  ) )
        ] );
    }

    if( $p_page <= 1 ) {
        $t_parent_project_id = project_hierarchy_get_parent( $p_selected_project, TRUE );
        if( $t_parent_project_id != $p_selected_project ) {
            $t_inline_keyboard->addRow( [
                                      'text'          => '<<',
                                      'callback_data' => json_encode( array( 'rb' => array( 'gp' => array(
                                                                                                                    'id' => $t_parent_project_id,
                                                                                                                    'p'  => $p_from_page,
                                                                                                                    'fp' => $p_page
                                                                                          ) )
                                      ) )
            ] );
        }
    }

    if( (count( $t_project_ids ) / 10) > $p_page ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '>>',
                                  'callback_data' => json_encode( array( 'rb' => array( 'gp' => array(
                                                                                                                'id' => $p_selected_project,
                                                                                                                'p'  => $p_page + 1,
                                                                                                                'fp' => $p_from_page
                                                                                      ) )
                                  ) )
        ] );
    }

    return $t_inline_keyboard;
}

function keyboard_category_get( $p_project_id ) {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    $t_category_rows = category_get_all_rows( $p_project_id, null, true );

    foreach( $t_category_rows as $t_category ) {

        $t_inline_keyboard->addRow( [
                                  'text'          => $p_project_id == $t_category['project_id'] ? $t_category['name'] : '[' .
                                          ($t_category['project_name'] == NULL ? lang_get( 'all_projects' ) : $t_category['project_name'])
                                          . '] ' . $t_category['name'],
                                  'callback_data' => json_encode( array(
                                                            'rb' => array( 'sc' => array( 'id' => $t_category['id'] ) )
                                  ) )
        ] );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '<<',
                              'callback_data' => json_encode( array( 'rb' => array( 'gp' => array(
                                                                                                            'id' => 0,
                                                                                                            'p'  => 1,
                                                                                                            'fp' => 1
                                                                                  ) )
                              ) )
    ] );

    return $t_inline_keyboard;
}

function keyboard_enum_string_get( $p_enum_string ) {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    $t_config_reproducibility_name = $p_enum_string . '_enum_string';
    $t_config_var_value            = config_get( $t_config_reproducibility_name );


    $t_enum_values = MantisEnum::getValues( $t_config_var_value );

    foreach( $t_enum_values as $t_key ) {
        $t_elem2 = get_enum_element( $p_enum_string, $t_key );

        $t_inline_keyboard->addRow( [
                                  'text'          => $t_elem2,
                                  'callback_data' => json_encode( array(
                                                            'rb' => array( 's' . $p_enum_string => array( 'id' => $t_key ) )
                                  ) )
        ] );
    }

    return $t_inline_keyboard;
}

function keyboard_handler_get( $p_project_id ) {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    $p_user_id      = 0;
    $p_access       = config_get( 'handle_bug_threshold' );
    $t_current_user = auth_get_current_user_id();

    if( null === $p_project_id ) {
        $p_project_id = helper_get_current_project();
    }

    if( $p_project_id === ALL_PROJECTS ) {
        $t_projects = user_get_accessible_projects( $t_current_user );

        # Get list of users having access level for all accessible projects
        $t_users = array();
        foreach( $t_projects as $t_project_id ) {
            $t_project_users_list = project_get_all_user_rows( $t_project_id, $p_access );
            # Do a 'smart' merge of the project's user list, into an
            # associative array (to remove duplicates)
            foreach( $t_project_users_list as $t_id => $t_user ) {
                $t_users[$t_id] = $t_user;
            }
            # Clear the array to release memory
            unset( $t_project_users_list );
        }
        unset( $t_projects );
    } else {
        $t_users = project_get_all_user_rows( $p_project_id, $p_access );
    }

    # Add the specified user ID to the list
    # If we have an array of user IDs, then we've been called from a filter
    # so don't add anything
    if( !is_array( $p_user_id ) &&
            $p_user_id != NO_USER &&
            !array_key_exists( $p_user_id, $t_users )
    ) {
        $t_row = user_cache_row( $p_user_id, /* trigger_error */ false );
        if( $t_row === false ) {
            # User doesn't exist - create a dummy record for display purposes
            $t_name = user_get_name( $p_user_id );
            $t_row  = array(
                                      'id'       => $p_user_id,
                                      'username' => $t_name,
                                      'realname' => $t_name,
            );
        }
        $t_users[$p_user_id] = $t_row;
    }

    $t_display = array();
    $t_sort    = array();

    foreach( $t_users as $t_key => $t_user ) {
        $t_display[] = user_get_expanded_name_from_row( $t_user );
        $t_sort[]    = user_get_name_for_sorting_from_row( $t_user );
    }

    array_multisort( $t_sort, SORT_ASC, SORT_STRING, $t_users, $t_display );
    unset( $t_sort );

    $t_count = count( $t_users );
    for( $i = 0; $i < $t_count; $i++ ) {
        $t_row = $t_users[$i];

        $t_inline_keyboard->addRow( [
                                  'text'          => $t_display[$i],
                                  'callback_data' => json_encode( array(
                                                            'rb' => array( 'shandler' => array( 'id' => $t_row['id'] ) )
                                  ) )
        ] );
    }

    return $t_inline_keyboard;
}

function keyboard_status_get( $p_project_id ) {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    $t_resolution_options = get_status_option_list( access_get_project_level( $p_project_id ), config_get( 'bug_submit_status' ), true, ON == config_get( 'allow_reporter_close' ), $p_project_id );

    foreach( $t_resolution_options as $t_key => $t_value ) {

        $t_inline_keyboard->addRow( [
                                  'text'          => $t_value,
                                  'callback_data' => json_encode( array(
                                                            'rb' => array( 'sstatus' => array( 'id' => $t_key ) )
                                  ) )
        ] );
    }

    return $t_inline_keyboard;
}

/**
 * Print the option list for versions
 * @param string  $p_version       The currently selected version.
 * @param integer $p_project_id    Project id, otherwise current project will be used.
 * @param integer $p_released      Null to get all, 1: only released, 0: only future versions.
 * @param boolean $p_leading_blank Allow selection of no version.
 * @param boolean $p_with_subs     Whether to include sub-projects.
 * @return void
 */
function keyboard_target_version_get( $p_version = '', $p_project_id = null, $p_released = null, $p_leading_blank = true, $p_with_subs = false ) {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    if( null === $p_project_id ) {
        $c_project_id = helper_get_current_project();
    } else {
        $c_project_id = (int) $p_project_id;
    }

    if( $p_with_subs ) {
        $t_versions = version_get_all_rows_with_subs( $c_project_id, $p_released, null );
    } else {
        $t_versions = version_get_all_rows( $c_project_id, $p_released, null );
    }

    # Ensure the selected version (if specified) is included in the list
    # Note: Filter API specifies selected versions as an array
    if( !is_array( $p_version ) ) {
        if( !empty( $p_version ) ) {
            $t_version_id = version_get_id( $p_version, $c_project_id );
            if( $t_version_id !== false ) {
                $t_versions[] = version_cache_row( $t_version_id );
            }
        }
    }

    if( $p_leading_blank ) {
        echo '<option value=""></option>';
    }

    $t_listed     = array();
    $t_max_length = config_get( 'max_dropdown_length' );

    foreach( $t_versions as $t_version ) {
        # If the current version is obsolete, and current version not equal to $p_version,
        # then skip it.
        if( ( (int) $t_version['obsolete'] ) == 1 ) {
            if( $t_version['version'] != $p_version ) {
                continue;
            }
        }

        $t_version_version = string_attribute( $t_version['version'] );

        if( !in_array( $t_version_version, $t_listed, true ) ) {
            $t_listed[]       = $t_version_version;
            $t_version_string = string_attribute( prepare_version_string( $c_project_id, $t_version['id'] ) );

            $t_inline_keyboard->addRow( [
                                      'text'          => string_shorten( $t_version_string, $t_max_length ),
                                      'callback_data' => json_encode( array(
                                                                'rb' => array( 'stargetv' => array( 'id' => $t_version_version ) )
                                      ) )
            ] );
        }
    }

    return $t_inline_keyboard;
}

function keyboard_summary_get() {
    $t_inline_keyboard = Longman\TelegramBot\Entities\InlineKeyboard::forceReply();


    return $t_inline_keyboard;
}

function keyboard_bug_status_change_is( $p_bug_id ) {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    # User must have rights to change status to use this button
    if( !access_has_bug_level( config_get( 'update_bug_status_threshold' ), $p_bug_id ) ) {
        return $t_inline_keyboard;
    }

    $t_inline_keyboard->addRow( [
                              'text'          => lang_get( 'status_group_bugs_button' ),
                              'callback_data' => json_encode( array(
                                                        'get_status' => array( 'bug_id' => $p_bug_id )
                              ) )
    ] );

    return $t_inline_keyboard;
}

/**
 * Print Change Status to: button
 * This code is similar to print_status_option_list except
 * there is no masking, except for the current state
 *
 * @param BugData $p_bug A valid bug object.
 * @return void
 */
function keyboard_buttons_bug_change_status( BugData $p_bug ) {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
    $t_current_access  = access_get_project_level( $p_bug->project_id );

    # User must have rights to change status to use this button
    if( !access_has_bug_level( config_get( 'update_bug_status_threshold' ), $p_bug->id ) ) {
        return;
    }

    $t_enum_list = get_status_option_list(
            $t_current_access, $p_bug->status, false,
            # Add close if user is bug's reporter, still has rights to report issues
            # (to prevent users downgraded to viewers from updating issues) and
            # reporters are allowed to close their own issues
            ( bug_is_user_reporter( $p_bug->id, auth_get_current_user_id() ) && access_has_bug_level( config_get( 'report_bug_threshold' ), $p_bug->id ) && ON == config_get( 'allow_reporter_close' )
            ), $p_bug->project_id );

    if( count( $t_enum_list ) > 0 ) {
        # resort the list into ascending order after noting the key from the first element (the default)

        ksort( $t_enum_list );

        # space at beginning of line is important
        foreach( $t_enum_list as $t_key => $t_val ) {
            $t_inline_keyboard->addRow( [
                                      'text'          => $t_val,
                                      'callback_data' => json_encode( array(
                                                                'set_status' => array(
                                                                                          'bug_id' => $p_bug->id,
                                                                                          'new_s'  => $t_key
                                                                )
                                      ) )
            ] );
        }
    }

    return $t_inline_keyboard;
}
