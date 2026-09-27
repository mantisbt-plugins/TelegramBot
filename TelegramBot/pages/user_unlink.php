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

form_security_validate( 'telegram_user_unlink' );

auth_ensure_user_authenticated();

$f_user_id = gpc_get_int( 'user_id' );

# Which button was pressed, not whose binding it is: an administrator unlinking himself
# from the plugin pages is still doing an administrative action and belongs back there
$f_source = gpc_get_string( 'source', TELEGRAM_UNLINK_SOURCE_ADMIN );

if( $f_source != TELEGRAM_UNLINK_SOURCE_ACCOUNT ) {
    $f_source = TELEGRAM_UNLINK_SOURCE_ADMIN;
}

user_ensure_exists( $f_user_id );

# Everybody may release his own binding from his account page - the /stop command needs
# access to the chat, which a user who lost the telegram account does not have anymore
if( $f_source == TELEGRAM_UNLINK_SOURCE_ACCOUNT && auth_get_current_user_id() == $f_user_id ) {
    current_user_ensure_unprotected();
} else {
    auth_reauthenticate();
    access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );
}

$t_confirm_message = auth_get_current_user_id() == $f_user_id
                          ? plugin_lang_get( 'user_unlink_confirm_self' )
                          : sprintf( plugin_lang_get( 'user_unlink_confirm' ), user_get_field( $f_user_id, 'username' ) );

# From his own account page the user gets the confirmation in the chat as he does for
# /stop; an administrative unlink asks whether to tell the chat, the setting being the
# state the checkbox starts in
if( $f_source == TELEGRAM_UNLINK_SOURCE_ACCOUNT ) {
    helper_ensure_confirmed( $t_confirm_message, plugin_lang_get( 'user_unlink_button' ) );

    $t_notify = true;
} else {
    telegram_ensure_unlink_confirmed(
            $t_confirm_message,
            plugin_lang_get( 'user_unlink_button' ),
            ON == plugin_config_get( 'admin_unlink_notify' ),
            plugin_page( 'monitor_page', TRUE )
    );

    $t_notify = gpc_get_bool( 'notify_user' );
}

# The chat keeps working as an anonymous one and asks for a binding on the next message
telegram_bot_user_unlink( $f_user_id, $t_notify );

form_security_purge( 'telegram_user_unlink' );

# Back to the page the button was pressed on, no url is taken from the request for that
$t_redirect_url = $f_source == TELEGRAM_UNLINK_SOURCE_ACCOUNT
                          ? plugin_page( 'account_telegram_prefs_page', TRUE )
                          : plugin_page( 'monitor_page', TRUE );

layout_page_header( null, $t_redirect_url );

layout_page_begin();

html_operation_successful( $t_redirect_url );

layout_page_end();
