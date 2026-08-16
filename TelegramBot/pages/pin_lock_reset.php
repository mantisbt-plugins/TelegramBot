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

form_security_validate( 'plugin_TelegramBot_pin_lock_reset' );

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_user_id = gpc_get_int( 'user_id' );

# No user_ensure_exists(): the row may be left behind by an account that is gone,
# and cleaning it up is exactly what this page is for
telegram_pin_code_attempts_reset( $f_user_id );

plugin_log_event( 'PIN code lock of user #' . $f_user_id . ' reset by ' . user_get_username( auth_get_current_user_id() ) );

form_security_purge( 'plugin_TelegramBot_pin_lock_reset' );

$t_redirect_url = plugin_page( 'monitor_page', TRUE );

layout_page_header( null, $t_redirect_url );

layout_page_begin();

html_operation_successful( $t_redirect_url );

layout_page_end();
