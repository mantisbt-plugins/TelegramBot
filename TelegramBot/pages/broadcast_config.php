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

form_security_validate( 'broadcast_config' );

auth_ensure_user_authenticated();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_broadcast_enabled        = gpc_get_bool( 'broadcast_enabled', FALSE );
$f_broadcast_send_threshold = gpc_get_int( 'broadcast_send_threshold', plugin_config_get( 'broadcast_send_threshold' ) );

if( plugin_config_get( 'broadcast_enabled' ) != $f_broadcast_enabled ) {
	# ON/OFF, not a PHP boolean: plugin_config_set() would store false as an empty string
	plugin_config_set( 'broadcast_enabled', $f_broadcast_enabled ? ON : OFF );
}

if( plugin_config_get( 'broadcast_send_threshold' ) != $f_broadcast_send_threshold ) {
	plugin_config_set( 'broadcast_send_threshold', $f_broadcast_send_threshold );
}

form_security_purge( 'broadcast_config' );

$t_redirect_url = plugin_page( 'broadcast_config_page', true );
layout_page_header();
layout_page_begin();

html_operation_successful( $t_redirect_url );

layout_page_end();
