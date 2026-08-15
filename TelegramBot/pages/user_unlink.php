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

form_security_validate( 'telegram_user_unlink' );

auth_ensure_user_authenticated();

$f_user_id = gpc_get_int( 'user_id' );

user_ensure_exists( $f_user_id );

# Everybody may release his own binding - the /stop command needs access to the chat,
# which is exactly what a user who lost the telegram account does not have anymore
if( auth_get_current_user_id() == $f_user_id ) {
    current_user_ensure_unprotected();
} else {
    auth_reauthenticate();
    access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );
}

$t_own_binding = auth_get_current_user_id() == $f_user_id;

$t_confirm_message = $t_own_binding
                          ? plugin_lang_get( 'user_unlink_confirm_self' )
                          : sprintf( plugin_lang_get( 'user_unlink_confirm' ), user_get_field( $f_user_id, 'username' ) );

helper_ensure_confirmed( $t_confirm_message, plugin_lang_get( 'user_unlink_button' ) );

# Releasing his own binding, the user gets the confirmation in the chat as he does for
# /stop; for somebody else's binding the setting decides whether the chat is told at all
$t_notify = $t_own_binding || ON == plugin_config_get( 'admin_unlink_notify' );

# The chat keeps working as an anonymous one and asks for a binding on the next message
telegram_bot_user_unlink( $f_user_id, $t_notify );

form_security_purge( 'telegram_user_unlink' );

# Back where the button was pressed, no url is taken from the request for that
$t_redirect_url = auth_get_current_user_id() == $f_user_id
                          ? plugin_page( 'account_telegram_prefs_page', TRUE )
                          : plugin_page( 'monitor_page', TRUE );

layout_page_header( null, $t_redirect_url );

layout_page_begin();

html_operation_successful( $t_redirect_url );

layout_page_end();
