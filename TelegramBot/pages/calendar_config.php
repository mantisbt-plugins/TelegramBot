<?php
# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
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

form_security_validate( 'calendar_config' );

auth_ensure_user_authenticated();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_calendar_integration_enabled = gpc_get_bool( 'calendar_integration_enabled' ) ? ON : OFF;

if( plugin_config_get( 'calendar_integration_enabled' ) != $f_calendar_integration_enabled ) {
	plugin_config_set( 'calendar_integration_enabled', $f_calendar_integration_enabled );
}

# the dependent settings are only on the form while the integration is on,
# and an absent checkbox reads as off - they are left alone when not shown
if( !gpc_get_bool( 'calendar_settings_shown' ) ) {
	form_security_purge( 'calendar_config' );
	print_header_redirect( plugin_page( 'calendar_config_page', TRUE ) );
}

$f_calendar_ics_mode = gpc_get_int( 'calendar_ics_mode', plugin_config_get( 'calendar_ics_mode' ) );

if( !in_array( $f_calendar_ics_mode, array( TELEGRAM_ICS_OFF, TELEGRAM_ICS_ON, TELEGRAM_ICS_OPT_IN ), true ) ) {
	$f_calendar_ics_mode = TELEGRAM_ICS_OFF;
}

if( plugin_config_get( 'calendar_ics_mode' ) != $f_calendar_ics_mode ) {
	plugin_config_set( 'calendar_ics_mode', $f_calendar_ics_mode );
}

$f_calendar_reminders_enabled = gpc_get_bool( 'calendar_reminders_enabled' ) ? ON : OFF;

if( plugin_config_get( 'calendar_reminders_enabled' ) != $f_calendar_reminders_enabled ) {
	plugin_config_set( 'calendar_reminders_enabled', $f_calendar_reminders_enabled );
}

# the matrix is kept per project and written as a whole, every cell of it, so
# that the page can tell a project copy from the global one cell by cell
$t_project_id = helper_get_current_project();

$f_calendar_flags = gpc_get_string_array( 'calendar_flag', array() );

$t_checked = array();
foreach( $f_calendar_flags as $t_flag_value ) {
	$t_checked[$t_flag_value] = TRUE;
}

$t_calendar_flags = array();
foreach( telegram_calendar_notify_actions() as $t_action ) {
	foreach( telegram_calendar_notify_targets() as $t_target ) {
		$t_calendar_flags[$t_action][$t_target] = isset( $t_checked[$t_action . ':' . $t_target] ) ? ON : OFF;
	}
}

if( telegram_calendar_notify_flags( $t_project_id ) != $t_calendar_flags ) {
	plugin_config_set( 'calendar_notify_flags', $t_calendar_flags, NO_USER, $t_project_id );
}

form_security_purge( 'calendar_config' );

$t_redirect_url = plugin_page( 'calendar_config_page', true );
layout_page_header();
layout_page_begin();

html_operation_successful( $t_redirect_url );

layout_page_end();
