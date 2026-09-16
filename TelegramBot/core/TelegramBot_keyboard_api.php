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

function telegram_bot_keyboard_rows_default_filter( $p_action_tag = TelegrambotActions::ADD_COMMENT_TAG ) {

    $t_keyboard_rows   = array();
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_assigned' ), 'callback_data' => json_encode( array( $p_action_tag => array( 'get_bugs' => array( 'assigned' => array( 'page' => 1 ) ) ) ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_monitored' ), 'callback_data' => json_encode( array( $p_action_tag => array( 'get_bugs' => array( 'monitored' => array( 'page' => 1 ) ) ) ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_reported' ), 'callback_data' => json_encode( array( $p_action_tag => array( 'get_bugs' => array( 'reported' => array( 'page' => 1 ) ) ) ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'use_query' ), 'callback_data' => json_encode( array( $p_action_tag => array( 'get_bugs' => array( 'use_query' => array( 'page' => 1 ) ) ) ) ) ];

    return $t_keyboard_rows;
}

function telegram_bot_get_keyboard_default_filter( $p_action_tag = TelegrambotActions::ADD_COMMENT_TAG ) {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    foreach( telegram_bot_keyboard_rows_default_filter( $p_action_tag ) as $t_row ) {
        $t_inline_keyboard->addRow( $t_row );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '>> ' . plugin_lang_get( 'keyboard_button_list_of_projects' ) . ' <<',
                              'callback_data' => json_encode( array( $p_action_tag => array( 'get_projects' => array( 'page' => 1 ) ) ) )
    ] );

    $t_inline_keyboard->addRow( [
                              'text'          => '>> ' . plugin_lang_get( 'keyboard_button_back' ) . ' <<',
                              'callback_data' => json_encode( array( 'action_select' => '' ) )
    ] );

    return $t_inline_keyboard;
}

/**
 * Build the page of the projects narrowing the issue lists of the flows picking
 * an issue first, ten projects a page. The buttons carry the action tag of the
 * flow the step belongs to.
 *
 * @param string  $p_action_tag TelegrambotActions tag of the flow.
 * @param integer $p_page       Page of the list.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_bug_select_projects_get( $p_action_tag, $p_page = 1 ) {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    $t_project_ids = user_get_all_accessible_projects( auth_get_current_user_id() );
    $t_project_ids = array_values( $t_project_ids );

    project_cache_array_rows( $t_project_ids );

    if( $p_page == 1 ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => lang_get( 'all_projects' ),
                                  'callback_data' => json_encode( array( $p_action_tag => array( 'sprj' => array( 'id' => ALL_PROJECTS ) ) ) )
        ] );
    }

    for( $i = ($p_page * 10) - 10; $i < ($p_page * 10) && $i < count( $t_project_ids ); $i++ ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => project_get_field( $t_project_ids[$i], 'name' ),
                                  'callback_data' => json_encode( array( $p_action_tag => array( 'sprj' => array( 'id' => (int)$t_project_ids[$i] ) ) ) )
        ] );
    }

    if( $p_page > 1 ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '<<',
                                  'callback_data' => json_encode( array( $p_action_tag => array( 'get_projects' => array( 'page' => $p_page - 1 ) ) ) )
        ] );
    }

    if( count( $t_project_ids ) > $p_page * 10 ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '>>',
                                  'callback_data' => json_encode( array( $p_action_tag => array( 'get_projects' => array( 'page' => $p_page + 1 ) ) ) )
        ] );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '>> ' . plugin_lang_get( 'keyboard_button_back' ) . ' <<',
                              'callback_data' => json_encode( array( 'action_select' => '' ) )
    ] );

    return $t_inline_keyboard;
}

function keyboard_bugs_get( $p_mantis_custom_filter, $p_page, $p_action_tag = TelegrambotActions::ADD_COMMENT_TAG, $p_section = 'use_query' ) {
    $t_per_page   = null;
    $t_bug_count  = null;
    $t_page_count = null;


    $t_current_page = $p_page;

    $t_bugs = filter_get_bug_rows( $t_current_page, $t_per_page, $t_page_count, $t_bug_count, $p_mantis_custom_filter, 0, null, true );

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    foreach( $t_bugs as $t_bug ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => $t_bug->id . ': ' . $t_bug->summary,
                                  'callback_data' => json_encode( array( $p_action_tag => array( 'set_bug' => $t_bug->id ) ) )
        ] );
    }

    if( $t_current_page > 1 && $t_current_page <= $t_page_count ) {
        $t_inline_keyboard->addRow( [ 'text'          => '<<',
                                  'callback_data' => json_encode( array( $p_action_tag => array( 'get_bugs' => array( $p_section => array( 'page' => $t_current_page - 1 ) ) ) ) )
        ] );
    }

    if( $t_current_page >= 1 && $t_current_page < $t_page_count ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '>>',
                                  'callback_data' => json_encode( array( $p_action_tag => array( 'get_bugs' => array( $p_section => array( 'page' => $t_current_page + 1 ) ) ) ) )
        ] );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '>>' . plugin_lang_get( 'keyboard_button_list_of_sections' ) . '<<',
                              'callback_data' => json_encode( array( $p_action_tag => array( 'get_default_category' => '' ) ) )
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
                                  'callback_data' => json_encode( array( TelegrambotActions::REPORT_BUG_TAG => array( 'gp' => array(
                                                                                                                'id' => 0,
                                                                                                                'p'  => 1,
                                                                                                                'fp' => 1
                                                                                      )
                                                    ) ) )
        ] );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => lang_get( 'add_bugnote_title' ),
                              'callback_data' => json_encode( array( TelegrambotActions::ADD_COMMENT_TAG => array( 'start' => '' ) ) )
    ] );

    if( access_has_any_project_level( 'update_bug_status_threshold' ) ) {

        $t_inline_keyboard->addRow( [
                                  'text'          => plugin_lang_get( 'menu_update_bug' ),
                                  'callback_data' => json_encode( array( TelegrambotActions::UPDATE_BUG_TAG => array( 'start' => '' ) ) )
        ] );
    }

    # the calendar events are only offered while the Calendar plugin is around,
    # this one does not depend on it
    if( telegram_calendar_available() ) {

        $t_inline_keyboard->addRow( [
                                  'text'          => plugin_lang_get( 'menu_create_event' ),
                                  'callback_data' => json_encode( array( TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::GET_PROJECT => array(
                                                                                                                'id' => 0,
                                                                                                                'p'  => 1,
                                                                                                                'fp' => 1
                                                                                      ) )
                                  ) )
        ] );
    }

    return $t_inline_keyboard;
}

/**
 * Build the keyboard of the operations available for the picked issue,
 * for now the only operation is moving the issue to another status.
 *
 * @param BugData $p_bug A valid bug object.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_bug_actions_get( BugData $p_bug ) {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    if( access_has_bug_level( config_get( 'update_bug_status_threshold' ), $p_bug->id ) || telegram_bug_reporter_can_close( $p_bug ) ) {

        $t_inline_keyboard->addRow( [
                                  'text'          => lang_get( 'bug_status_to_button' ),
                                  'callback_data' => json_encode( array( TelegrambotActions::CHANGE_STATUS_TAG => array( 'set_bug' => $p_bug->id ) ) )
        ] );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '>>' . plugin_lang_get( 'keyboard_button_list_of_sections' ) . '<<',
                              'callback_data' => json_encode( array( TelegrambotActions::UPDATE_BUG_TAG => array( 'get_default_category' => '' ) ) )
    ] );

    return $t_inline_keyboard;
}

/**
 * Build the paginated keyboard of the projects available to the current user.
 *
 * The same list drives the issue wizard and the calendar event one, so the tag
 * of the flow the buttons belong to is told apart by the caller.
 *
 * @param integer $p_selected_project Project the subprojects are listed for.
 * @param integer $p_page             Page of the list, ten projects per page.
 * @param integer $p_from_page        Page of the parent list, returned to by the back button.
 * @param string  $p_action_tag       TelegrambotActions tag of the flow.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_projects_get( $p_selected_project = ALL_PROJECTS, $p_page = 1, $p_from_page = 1, $p_action_tag = TelegrambotActions::REPORT_BUG_TAG ) {

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
                                                            $p_action_tag => array( TelegrambotActions::SET_PROJECT => array( 'id' => $t_project_ids[$i] ) )
                                  ) )
                ], count( $t_child_project_ids ) > 0 ? [
                                          'text'          => '>>',
                                          'callback_data' => json_encode( array( $p_action_tag => array( TelegrambotActions::GET_PROJECT => array(
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
                                  'callback_data' => json_encode( array( $p_action_tag => array( TelegrambotActions::GET_PROJECT => array(
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
                                      'callback_data' => json_encode( array( $p_action_tag => array( TelegrambotActions::GET_PROJECT => array(
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
                                  'callback_data' => json_encode( array( $p_action_tag => array( TelegrambotActions::GET_PROJECT => array(
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

    # Enabled categories only, the way the web report form does it since 2.28.0;
    # the parameter is simply ignored by the earlier versions
    $t_category_rows = category_get_all_rows( $p_project_id, null, true, true );

    # The core allows an issue without a category only with this option on,
    # the button plays the role of the skip one then
    if( config_get( 'allow_no_category' ) ) {
        $t_inline_keyboard -> addRow( [
                                        'text' => lang_get( 'no_category' ),
                                        'callback_data' => json_encode( array(
                                                                                TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::SET_CATEGORY => array('id' => 0))
                                                           ) )
        ] );
    }

    foreach( $t_category_rows as $t_category ) {

        $t_inline_keyboard->addRow( [
                                  'text'          => $p_project_id == $t_category['project_id'] ? $t_category['name'] : '[' .
                                          ($t_category['project_name'] == NULL ? lang_get( 'all_projects' ) : $t_category['project_name'])
                                          . '] ' . $t_category['name'],
                                  'callback_data' => json_encode( array(
                                                            TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::SET_CATEGORY => array( 'id' => (int)$t_category['id'] ) )
                                  ) )
        ] );
    }

    return $t_inline_keyboard;
}

function keyboard_enum_string_get( $p_enum_string, $p_default_val = 0, $p_action_tag = TelegrambotActions::REPORT_BUG_TAG, $p_action = NULL ) {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    $t_config_reproducibility_name = $p_enum_string . '_enum_string';
    $t_config_var_value            = config_get( $t_config_reproducibility_name );

    $t_action = $p_action === NULL ? 's' . $p_enum_string : $p_action;

    if( is_array( $p_default_val ) ) {
            $t_val = $p_default_val;
    } else {
            $t_val = (int)$p_default_val;
    }

    $t_enum_values = MantisEnum::getValues( $t_config_var_value );

    foreach( $t_enum_values as $t_key ) {
        $t_elem2 = get_enum_element( $p_enum_string, $t_key );

        $t_inline_keyboard->addRow( [
                                  'text'          => $t_elem2 . ( telegrambot_check_default( $t_val, $t_key ) ? ' (Default)' : '' ),
                                  'callback_data' => json_encode( array(
                                                            $p_action_tag => array( $t_action => array( 'id' => $t_key ) )
                                  ) )
        ] );
    }

    return $t_inline_keyboard;
}

function keyboard_duedate_get() {
    $keyboard = new TelegramBotInlineKeyboardCalendar();

    $keyboard->setConfigDate("2023-04");

    return $keyboard->getKeyboard();
}

/**
 * Build the first half of the inline time picker: the twenty four hours.
 *
 * The buttons are tagged the way the inline calendar tags its own, 's' + action
 * with the bare hour as the value; the minutes are asked next.
 *
 * @param string $p_action Action the time picker is shown for.
 * @param mixed  $p_id     Identifier passed back along with the action.
 * @param string $p_tag    Root tag of the callback data.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_time_hours_get( $p_action, $p_id = 0, $p_tag = TelegrambotActions::REPORT_BUG_TAG ) {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    for( $t_row_start = 0; $t_row_start < 24; $t_row_start += 6 ) {
        $t_row = array();

        for( $t_hour = $t_row_start; $t_hour < $t_row_start + 6; $t_hour++ ) {
            $t_row[] = array(
                                      'text'          => sprintf( '%02d', $t_hour ),
                                      'callback_data' => json_encode( array(
                                                                $p_tag => array( 's' . $p_action => array( $p_id => '' . $t_hour ) )
                                      ) )
            );
        }

        call_user_func_array( array( $t_inline_keyboard, 'addRow' ), $t_row );
    }

    return $t_inline_keyboard;
}

/**
 * Build the second half of the inline time picker: the minutes of the hour picked.
 *
 * The header shows the hour and switches the picker back to the hours, 'g' +
 * action; a minute completes the answer, 's' + action with "H:MM" as the value,
 * so nothing has to be remembered between the two halves.
 *
 * @param integer $p_hour   Hour picked with the first half.
 * @param integer $p_step   Minutes between the buttons.
 * @param string  $p_action Action the time picker is shown for.
 * @param mixed   $p_id     Identifier passed back along with the action.
 * @param string  $p_tag    Root tag of the callback data.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_time_minutes_get( $p_hour, $p_step, $p_action, $p_id = 0, $p_tag = TelegrambotActions::REPORT_BUG_TAG ) {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    $t_inline_keyboard->addRow( array(
                              'text'          => sprintf( '%02d:__', $p_hour ),
                              'callback_data' => json_encode( array(
                                                        $p_tag => array( 'g' . $p_action => array( $p_id => '' ) )
                              ) )
    ) );

    $t_row = array();

    for( $t_minute = 0; $t_minute < 60; $t_minute += $p_step ) {
        $t_row[] = array(
                                  'text'          => sprintf( '%02d:%02d', $p_hour, $t_minute ),
                                  'callback_data' => json_encode( array(
                                                            $p_tag => array( 's' . $p_action => array( $p_id => $p_hour . ':' . sprintf( '%02d', $t_minute ) ) )
                                  ) )
        );

        if( count( $t_row ) == 6 ) {
            call_user_func_array( array( $t_inline_keyboard, 'addRow' ), $t_row );
            $t_row = array();
        }
    }

    if( count( $t_row ) > 0 ) {
        call_user_func_array( array( $t_inline_keyboard, 'addRow' ), $t_row );
    }

    return $t_inline_keyboard;
}

function keyboard_profile_option_list( $p_user_id, $p_select_id = 0, array $p_profiles = array() ) {
	if( 0 == $p_select_id ) {
		$p_select_id = profile_get_default( $p_user_id );
	}
	if( $p_profiles ) {
		$t_profiles = $p_profiles;
	} else {
		$t_profiles = profile_get_all_for_user( $p_user_id );
	}
	return keyboard_profile_option_list_from_profiles( $t_profiles, $p_select_id );
}

function keyboard_profile_option_list_from_profiles( array $p_profiles, $p_select_id ) {
        $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
	
	foreach( $p_profiles as $t_profile ) {
		extract( $t_profile, EXTR_PREFIX_ALL, 'v' );

		$t_platform = string_attribute( $t_profile['platform'] );
		$t_os = string_attribute( $t_profile['os'] );
		$t_os_build = string_attribute( $t_profile['os_build'] );

                if( $p_select_id == (int)$t_profile['id'] ) {
                        $t_inline_keyboard->addRow( [
                                  'text'          => $t_platform . ' ' . $t_os . ' ' . $t_os_build . ' (Default)',
                                  'callback_data' => json_encode( array(
                                                            'rb' => array( 'splatform' => array( 'id' => $t_profile['id'] ) )
                                  ) )
                        ] );
		} else {
                        $t_inline_keyboard->addRow( [
                                          'text'          => $t_platform . ' ' . $t_os . ' ' . $t_os_build,
                                          'callback_data' => json_encode( array(
                                                                    'rb' => array( 'splatform' => array( 'id' => $t_profile['id'] ) )
                                          ) )
                        ] );
                }
	}
        return $t_inline_keyboard;
}

function keyboard_version_option_list( $p_version, $p_project_ids, $p_released, $p_action, $p_action_tag = TelegrambotActions::REPORT_BUG_TAG ) {
        $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
        
	if( null === $p_project_ids ) {
		$p_project_ids = helper_get_current_project();
	}
	$t_project_ids = is_array( $p_project_ids ) ? $p_project_ids : array( $p_project_ids );

	$t_versions = version_get_all_rows( $t_project_ids, $p_released, true );

	# Ensure the selected version (if specified) is included in the list
	# Note: Filter API specifies selected versions as an array
	if( !is_array( $p_version ) ) {
		if( !empty( $p_version ) ) {
			foreach( $t_project_ids as $t_project_id ) {
				$t_version_id = version_get_id( $p_version, $t_project_id );
				if( $t_version_id !== false ) {
					$t_versions[] = version_cache_row( $t_version_id );
					break;
				}
			}
		}
	}

	$t_listed = array();
	$t_max_length = config_get( 'max_dropdown_length' );

	$t_show_project_name = count( $t_project_ids ) > 1;

	foreach( $t_versions as $t_version ) {
		# If the current version is obsolete, and current version not equal to $p_version,
		# then skip it.
		if( ( (int)$t_version['obsolete'] ) == 1 ) {
			if( $t_version['version'] != $p_version ) {
				continue;
			}
		}

		$t_version_version = string_attribute( $t_version['version'] );

		if( !in_array( $t_version_version, $t_listed, true ) ) {
			$t_listed[] = $t_version_version;
//			check_selected( $p_version, $t_version['version'] );
			$t_version_string = string_attribute( prepare_version_string( $t_version['project_id'], $t_version['id'], $t_show_project_name ) );

                        $t_inline_keyboard->addRow( [
                                          'text'          => string_shorten( $t_version_string, $t_max_length ),
                                          'callback_data' => json_encode( array(
                                                                    $p_action_tag => array( $p_action => array( 'version' => $t_version_version ) )
                                          ) )
                        ] );
		}
	}
        return $t_inline_keyboard;
}

function keyboard_handler_get( $p_project_id, $p_action_tag = TelegrambotActions::REPORT_BUG_TAG, $p_action = TelegrambotActions::SET_HANDLER ) {
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
                                                            $p_action_tag => array( $p_action => array( 'id' => $t_row['id'] ) )
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

function keyboard_summary_get() {
    $t_inline_keyboard = Longman\TelegramBot\Entities\InlineKeyboard::forceReply();


    return $t_inline_keyboard;
}

/**
 * Add the navigation buttons of the status change dialog to the keyboard of one
 * of its questions.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the buttons to.
 * @param array   $p_draft Draft of the dialog.
 * @param BugData $p_bug   A valid bug object.
 * @return void
 */
function keyboard_status_change_buttons_add( $p_inline_keyboard, array $p_draft, BugData $p_bug ) {

    keyboard_wizard_buttons_add( $p_inline_keyboard, telegram_status_change_wizard_descriptor( $p_draft, $p_bug ) );
}

/**
 * Build the keyboard of the statuses an issue can be moved to.
 * This code is similar to print_status_option_list except
 * there is no masking, except for the current state
 *
 * @param BugData $p_bug A valid bug object.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_buttons_bug_change_status( BugData $p_bug ) {
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
    $t_current_access  = access_get_project_level( $p_bug->project_id );

    $t_allow_close = telegram_bug_reporter_can_close( $p_bug );

    # User must have rights to change status to use these buttons
    if( access_has_bug_level( config_get( 'update_bug_status_threshold' ), $p_bug->id ) || $t_allow_close ) {

        $t_enum_list = get_status_option_list( $t_current_access, $p_bug->status, false, $t_allow_close, $p_bug->project_id );

        # resort the list into ascending order after noting the key from the first element (the default)
        ksort( $t_enum_list );

        foreach( $t_enum_list as $t_key => $t_val ) {
            $t_inline_keyboard->addRow( [
                                      'text'          => $t_val,
                                      'callback_data' => json_encode( array(
                                                                TelegrambotActions::CHANGE_STATUS_TAG => array( TelegrambotActions::SET_BUG_STATUS => array(
                                                                                          'id' => $p_bug->id,
                                                                                          's'  => $t_key
                                                                ) )
                                      ) )
            ] );
        }
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '>> ' . plugin_lang_get( 'keyboard_button_back' ) . ' <<',
                              'callback_data' => json_encode( array( TelegrambotActions::UPDATE_BUG_TAG => array( 'set_bug' => $p_bug->id ) ) )
    ] );

    return $t_inline_keyboard;
}

/**
 * Describe a step by step dialog for the navigation buttons of its cards.
 *
 * The dialogs of the bot ( the issue wizard, the calendar event wizard, the status
 * change dialog ) keep their own state and ask their own questions, but the way out
 * of a question is the same everywhere: give the answer given last up, drop the whole
 * dialog, or go back to the list the dialog has been entered from. The descriptor is
 * what tells these dialogs apart for keyboard_wizard_buttons_add().
 *
 * @param string $p_tag            Root TelegrambotActions tag of the callbacks of the dialog.
 * @param string $p_cancel_tag     TelegrambotActions tag dropping the dialog.
 * @param array  $p_answered       Steps answered so far, in the canonical order of the dialog.
 * @param string $p_label_callback Name of the function naming a step of the dialog.
 * @param array  $p_options        Anything the dialog differs from the defaults in:
 *                                 'replace'      => array( step => plugin lang key ) of the
 *                                                   step back button labels written by hand,
 *                                 'project_step' => name of the step picking the project when
 *                                                   the dialog starts with it, '' otherwise,
 *                                 'cancel_deep'  => plugin lang key of the button dropping the
 *                                                   dialog once it holds answers, '' to keep
 *                                                   the button leading back to the action list.
 * @return array Descriptor of the dialog.
 */
function keyboard_wizard_descriptor( $p_tag, $p_cancel_tag, array $p_answered, $p_label_callback, array $p_options = array() ) {

        $t_wizard = array(
                              'tag'          => $p_tag,
                              'cancel_tag'   => $p_cancel_tag,
                              'answered'     => array_values( $p_answered ),
                              'label'        => $p_label_callback,
                              'replace'      => array(),
                              'project_step' => '',
                              'cancel_deep'  => '',
        );

        return array_merge( $t_wizard, $p_options );
}

/**
 * The label of the step back button of a dialog.
 *
 * The step back replaces the answer given last, so the button carries the name of
 * that very action instead of a bare "back": the user is told which field the press
 * is going to give up. A step named by hand keeps the grammar of the language of the
 * user, the steps the dialog learns about at run time ( the custom fields of the
 * issue wizard ) are named after the label of the step, the way the card labels it.
 *
 * @param array  $p_wizard Descriptor of the dialog.
 * @param string $p_step   Step the press returns to.
 * @return string
 */
function keyboard_wizard_replace_label( array $p_wizard, $p_step ) {

        if( array_key_exists( $p_step, $p_wizard['replace'] ) ) {
                return plugin_lang_get( $p_wizard['replace'][$p_step] );
        }

        return sprintf( plugin_lang_get( 'wizard_replace_button' ), call_user_func( $p_wizard['label'], $p_step ) );
}

/**
 * Add the step back button of a dialog to the keyboard of one of its questions.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the button to.
 * @param array  $p_wizard Descriptor of the dialog.
 * @param string $p_step   Step of the dialog the press returns to, '' for a bare "back".
 * @return void
 */
function keyboard_wizard_back_button_add( $p_inline_keyboard, array $p_wizard, $p_step = '' ) {

        $t_label = is_blank( (string)$p_step ) ? plugin_lang_get( 'back_button' ) : keyboard_wizard_replace_label( $p_wizard, $p_step );

        $p_inline_keyboard->addRow( [
                              'text'          => '(← ' . $t_label . ')',
                              'callback_data' => json_encode( array(
                                                        $p_wizard['tag'] => array( TelegrambotActions::BACK_FIELD => 1 )
                              ) )
        ] );
}

/**
 * Add the button leading out of a dialog back to the list of the actions.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the button to.
 * @param array $p_wizard Descriptor of the dialog.
 * @return void
 */
function keyboard_wizard_cancel_button_add( $p_inline_keyboard, array $p_wizard ) {

        $p_inline_keyboard->addRow( [
                              'text'          => '>> ' . plugin_lang_get( 'keyboard_button_back' ) . ' <<',
                              'callback_data' => json_encode( array( $p_wizard['cancel_tag'] => 1 ) )
        ] );
}

/**
 * Add the navigation buttons of a dialog to the keyboard of one of its cards.
 *
 * The entry steps of a dialog carry the same navigation buttons as the project lists
 * of the other flows; deeper in the dialog they turn into the step back and the way
 * of dropping the dialog, there are answers to lose then.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the buttons to.
 * @param array $p_wizard Descriptor of the dialog.
 * @return void
 */
function keyboard_wizard_buttons_add( $p_inline_keyboard, array $p_wizard ) {

        $t_answered = $p_wizard['answered'];

        if( empty( $t_answered ) ) {
                # The first question: nothing is answered yet, the only way back
                # is the action list
                keyboard_wizard_cancel_button_add( $p_inline_keyboard, $p_wizard );

                return;
        }

        if( !is_blank( $p_wizard['project_step'] ) && $t_answered == array( $p_wizard['project_step'] ) ) {
                # The question following the project: going back means picking the
                # project anew, which is a list of its own rather than a question
                $p_inline_keyboard->addRow( [
                                      'text'          => '>> ' . plugin_lang_get( 'keyboard_button_list_of_projects' ) . ' <<',
                                      'callback_data' => json_encode( array( $p_wizard['tag'] => array( TelegrambotActions::BACK_FIELD => 1 ) ) )
                ] );

                keyboard_wizard_cancel_button_add( $p_inline_keyboard, $p_wizard );

                return;
        }

        # The label names the step the BACK_FIELD handler is going to reset: both take
        # the step answered last, so the button never promises the wrong field
        keyboard_wizard_back_button_add( $p_inline_keyboard, $p_wizard, end( $t_answered ) );

        if( is_blank( $p_wizard['cancel_deep'] ) ) {
                keyboard_wizard_cancel_button_add( $p_inline_keyboard, $p_wizard );

                return;
        }

        $p_inline_keyboard->addRow( [
                              'text'          => '(' . plugin_lang_get( $p_wizard['cancel_deep'] ) . ')',
                              'callback_data' => json_encode( array( $p_wizard['cancel_tag'] => 1 ) )
        ] );
}

function keyboard_skip_button_add( &$p_inline_keyboard, $p_action ) {

//        $t_inline_keyboard = $p_inline_keyboard == null ? new Longman\TelegramBot\Entities\InlineKeyboard( array() ) : $p_inline_keyboard;

        $p_inline_keyboard->addRow( [
                              'text'          => '(' . plugin_lang_get( 'skip_button' ) . ')',
                              'callback_data' => json_encode( $p_action )
        ] );

//        return $t_inline_keyboard;
}

/**
 * Add the "create the issue" button to the keyboard of the issue draft wizard.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the button to.
 * @return void
 */
function keyboard_create_button_add( $p_inline_keyboard ) {

        $p_inline_keyboard->addRow( [
                              'text'          => '(' . plugin_lang_get( 'create_button' ) . ')',
                              'callback_data' => json_encode( array(
                                                        TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::CREATE_ISSUE => 1 )
                              ) )
        ] );
}

/**
 * Build the keyboard of the menu shown once every mandatory question of the issue
 * draft wizard is answered: the issue can be created right away or the optional
 * fields can be filled in first.
 *
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_draft_menu_get() {

        $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

        keyboard_create_button_add( $t_inline_keyboard );

        $t_inline_keyboard->addRow( [
                              'text'          => '(' . plugin_lang_get( 'fill_optional_button' ) . ')',
                              'callback_data' => json_encode( array(
                                                        TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::FILL_OPTIONAL => 1 )
                              ) )
        ] );

        return $t_inline_keyboard;
}

/**
 * Add the buttons available at every step of the issue draft wizard to the keyboard
 * of the current question.
 *
 * While the optional fields are being filled in the issue can be created at any
 * moment, so the create button is shown along with the question.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the buttons to.
 * @param array $p_bug_data_draft Issue draft.
 * @return void
 */
function keyboard_draft_buttons_add( $p_inline_keyboard, array $p_bug_data_draft ) {

        if( telegram_draft_optional_phase_is_on( $p_bug_data_draft ) ) {
                keyboard_create_button_add( $p_inline_keyboard );
        }

        keyboard_wizard_buttons_add( $p_inline_keyboard, telegram_draft_wizard_descriptor( $p_bug_data_draft ) );
}

/**
 * Add the button applying the status change to the keyboard of the menu of the
 * status change dialog, labelled the way bug_change_status_page.php of the core
 * labels its submit button.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the button to.
 * @param integer $p_new_status Status the issue is moved to.
 * @return void
 */
function keyboard_status_change_apply_button_add( $p_inline_keyboard, $p_new_status ) {

        $p_inline_keyboard->addRow( [
                              'text'          => '(' . telegram_status_change_process_string( $p_new_status, '_bug_button' ) . ')',
                              'callback_data' => json_encode( array(
                                                        TelegrambotActions::CHANGE_STATUS_TAG => array( TelegrambotActions::APPLY_STATUS => 1 )
                              ) )
        ] );
}

/**
 * Add the navigation buttons of the calendar event wizard to the keyboard of one
 * of its cards, the way keyboard_draft_buttons_add() serves the issue wizard.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the buttons to.
 * @param array $p_draft Event draft.
 * @return void
 */
function keyboard_event_buttons_add( $p_inline_keyboard, array $p_draft ) {

        keyboard_wizard_buttons_add( $p_inline_keyboard, telegram_event_wizard_descriptor( $p_draft ) );
}

/**
 * Add the "create the event" button to the keyboard of the calendar event wizard.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the button to.
 * @return void
 */
function keyboard_event_create_button_add( $p_inline_keyboard ) {

        $p_inline_keyboard->addRow( [
                              'text'          => '(' . plugin_lang_get( 'create_button' ) . ')',
                              'callback_data' => json_encode( array(
                                                        TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::CREATE_EVENT => 1 )
                              ) )
        ] );
}

/**
 * Build the paginated keyboard of the issues a calendar event is attached to.
 *
 * The list is built the way keyboard_event_members_get() builds the member list,
 * with one difference: the issues of a project are read page by page from the
 * Calendar plugin, so the page shown arrives ready made and the caller tells
 * whether there is one more page behind it.
 *
 * @param array   $p_candidates Issues of the page shown, as calendar_api_candidate_issues() returns them.
 * @param array   $p_selected   Identifiers of the issues ticked off so far.
 * @param integer $p_page       Page of the list, ten issues per page.
 * @param boolean $p_has_next   Whether one more page follows the one shown.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_event_issues_get( array $p_candidates, array $p_selected, $p_page = 1, $p_has_next = FALSE ) {

        $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

        $t_page       = $p_page < 1 ? 1 : (int)$p_page;
        $t_max_length = config_get( 'max_dropdown_length' );

        foreach( $p_candidates as $t_candidate ) {
                $t_bug_id = (int)$t_candidate['id'];
                $t_mark   = in_array( $t_bug_id, $p_selected ) ? '☑ ' : '☐ ';

                # the summary of an issue is as long as the reporter wanted it to
                # be, the button carries as much of it as a list of values does
                $t_inline_keyboard->addRow( [
                                      'text'          => $t_mark . string_shorten( $t_bug_id . ': ' . $t_candidate['summary'], $t_max_length ),
                                      'callback_data' => json_encode( array( TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::TOGGLE_EVENT_ISSUE => array(
                                                                                                                    'id' => $t_bug_id,
                                                                                                                    'p'  => $t_page
                                                                                          ) )
                                      ) )
                ] );
        }

        if( $t_page > 1 ) {
                $t_inline_keyboard->addRow( [
                                      'text'          => '<<',
                                      'callback_data' => json_encode( array( TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::GET_EVENT_ISSUE => array(
                                                                                                                    'p' => $t_page - 1
                                                                                          ) )
                                      ) )
                ] );
        }

        if( $p_has_next ) {
                $t_inline_keyboard->addRow( [
                                      'text'          => '>>',
                                      'callback_data' => json_encode( array( TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::GET_EVENT_ISSUE => array(
                                                                                                                    'p' => $t_page + 1
                                                                                          ) )
                                      ) )
                ] );
        }

        $t_inline_keyboard->addRow( [
                              'text'          => '(' . plugin_lang_get( 'custom_field_done_button' ) . ')',
                              'callback_data' => json_encode( array(
                                                        TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::END_EVENT_ISSUE => 1 )
                              ) )
        ] );

        return $t_inline_keyboard;
}

/**
 * Build the paginated keyboard of the members of a calendar event.
 *
 * Every button toggles the membership of one user and the list is redrawn, the
 * ticked ones are marked; the list is closed with the button of its own, which
 * is the answer to the question.
 *
 * @param array   $p_candidates Users which may be signed up, as calendar_api_candidate_members() returns them.
 * @param array   $p_selected   Users ticked off so far.
 * @param integer $p_page       Page of the list, ten members per page.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function keyboard_event_members_get( array $p_candidates, array $p_selected, $p_page = 1 ) {

        $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

        $t_page  = $p_page < 1 ? 1 : (int)$p_page;
        $t_count = count( $p_candidates );

        user_cache_array_rows( $p_candidates );

        for( $i = ( $t_page * TELEGRAM_EVENT_MEMBERS_PER_PAGE ) - TELEGRAM_EVENT_MEMBERS_PER_PAGE;
                        $i < ( $t_page * TELEGRAM_EVENT_MEMBERS_PER_PAGE ) && $i < $t_count; $i++ ) {

                $t_member_id = (int)$p_candidates[$i];
                $t_mark      = in_array( $t_member_id, $p_selected ) ? '☑ ' : '☐ ';

                $t_inline_keyboard->addRow( [
                                      'text'          => $t_mark . user_get_name( $t_member_id ),
                                      'callback_data' => json_encode( array( TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::TOGGLE_EVENT_MEMBER => array(
                                                                                                                    'id' => $t_member_id,
                                                                                                                    'p'  => $t_page
                                                                                          ) )
                                      ) )
                ] );
        }

        if( $t_page > 1 ) {
                $t_inline_keyboard->addRow( [
                                      'text'          => '<<',
                                      'callback_data' => json_encode( array( TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::GET_EVENT_MEMBER => array(
                                                                                                                    'p' => $t_page - 1
                                                                                          ) )
                                      ) )
                ] );
        }

        if( ( $t_count / TELEGRAM_EVENT_MEMBERS_PER_PAGE ) > $t_page ) {
                $t_inline_keyboard->addRow( [
                                      'text'          => '>>',
                                      'callback_data' => json_encode( array( TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::GET_EVENT_MEMBER => array(
                                                                                                                    'p' => $t_page + 1
                                                                                          ) )
                                      ) )
                ] );
        }

        $t_inline_keyboard->addRow( [
                              'text'          => '(' . plugin_lang_get( 'custom_field_done_button' ) . ')',
                              'callback_data' => json_encode( array(
                                                        TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::END_EVENT_MEMBER => 1 )
                              ) )
        ] );

        return $t_inline_keyboard;
}