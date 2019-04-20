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

form_security_validate( 'account_telegram_prefs_update' );

auth_ensure_user_authenticated();

$f_user_id      = gpc_get_int( 'user_id' );
$f_redirect_url = gpc_get_string( 'redirect_url' );

user_ensure_exists( $f_user_id );

$t_user = user_get_row( $f_user_id );

# This page is currently called from the manage_* namespace and thus we
# have to allow authorised users to update the accounts of other users.
# TODO: split this functionality into manage_user_prefs_update.php
if( auth_get_current_user_id() != $f_user_id ) {
    access_ensure_global_level( config_get( 'manage_user_threshold' ) );
    access_ensure_global_level( $t_user['access_level'] );
} else {
    # Protected users should not be able to update the preferences of their
    # user account. The anonymous user is always considered a protected
    # user and hence will also not be allowed to update preferences.
    user_ensure_unprotected( $f_user_id );
}

plugin_config_set( 'telegram_message_on_new', gpc_get_bool( 'telegram_message_on_new' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_assigned', gpc_get_bool( 'telegram_message_on_assigned' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_feedback', gpc_get_bool( 'telegram_message_on_feedback' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_resolved', gpc_get_bool( 'telegram_message_on_resolved' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_closed', gpc_get_bool( 'telegram_message_on_closed' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_reopened', gpc_get_bool( 'telegram_message_on_reopened' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_bugnote', gpc_get_bool( 'telegram_message_on_bugnote' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_status', gpc_get_bool( 'telegram_message_on_status' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_priority', gpc_get_bool( 'telegram_message_on_priority' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_new_min_severity', gpc_get_int( 'telegram_message_on_new_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_assigned_min_severity', gpc_get_int( 'telegram_message_on_assigned_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_feedback_min_severity', gpc_get_int( 'telegram_message_on_feedback_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_resolved_min_severity', gpc_get_int( 'telegram_message_on_resolved_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_closed_min_severity', gpc_get_int( 'telegram_message_on_closed_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_reopened_min_severity', gpc_get_int( 'telegram_message_on_reopened_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_bugnote_min_severity', gpc_get_int( 'telegram_message_on_bugnote_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_status_min_severity', gpc_get_int( 'telegram_message_on_status_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_on_priority_min_severity', gpc_get_int( 'telegram_message_on_priority_min_severity' ), $f_user_id, ALL_PROJECTS );
plugin_config_set( 'telegram_message_included_all_bugnote_is', gpc_get_bool( 'telegram_message_included_all_bugnote_is' ) ? 1 : 0, $f_user_id, ALL_PROJECTS );

# Save user preference with regards to getting full issue details in notifications or not.
$t_telegram_message_full_issue         = gpc_get_bool( 'telegram_message_full_issue' ) ? 1 : 0;
$t_telegram_message_full_config_option = 'telegram_message_notifications_verbose';
if( plugin_config_get( $t_telegram_message_full_config_option, /* default */ NULL, FALSE, $f_user_id, ALL_PROJECTS ) != $t_telegram_message_full_issue ) {
    plugin_config_set( $t_telegram_message_full_config_option, $t_telegram_message_full_issue, $f_user_id, ALL_PROJECTS );
}

form_security_purge( 'account_telegram_prefs_update' );

layout_page_header( null, $f_redirect_url );

layout_page_begin();

html_operation_successful( $f_redirect_url );

layout_page_end();
