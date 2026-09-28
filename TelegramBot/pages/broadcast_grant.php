<?php
# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
# TelegramBot for MantisBT is free software:
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation,
# either version 2 of the License, or (at your option) any later version.
#
# TelegramBot plugin for MantisBT is distributed in the hope
# that it will be useful, but WITHOUT ANY WARRANTY; without even the
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with TelegramBot plugin for MantisBT.
# If not, see <http://www.gnu.org/licenses/>.

form_security_validate( 'broadcast_grant' );

auth_ensure_user_authenticated();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_grant_user_id  = gpc_get_int( 'grant_user_id', 0 );
$f_grant_projects = gpc_get_int_array( 'grant_projects', array() );

if( $f_grant_user_id == 0 || !user_exists( $f_grant_user_id ) ) {
	error_parameters( lang_get( 'username' ) );
	trigger_error( ERROR_EMPTY_FIELD, ERROR );
}

$t_all_project_ids = array();
foreach( project_get_all_rows() as $t_project ) {
	$t_all_project_ids[] = (int)$t_project['id'];
}

$t_projects = array_values( array_unique( array_intersect( array_map( 'intval', $f_grant_projects ), $t_all_project_ids ) ) );

# No projects checked means the permission is revoked.
$t_grants = telegram_broadcast_grants_get();
if( empty( $t_projects ) ) {
	unset( $t_grants[$f_grant_user_id] );
} else {
	$t_grants[$f_grant_user_id] = $t_projects;
}

plugin_config_set( 'broadcast_grants', $t_grants );

form_security_purge( 'broadcast_grant' );

$t_redirect_url = plugin_page( 'broadcast_config_page', true );
layout_page_header();
layout_page_begin();

html_operation_successful( $t_redirect_url );

layout_page_end();
