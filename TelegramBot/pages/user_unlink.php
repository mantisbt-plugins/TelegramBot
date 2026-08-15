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

$t_confirm_message = auth_get_current_user_id() == $f_user_id
                          ? plugin_lang_get( 'user_unlink_confirm_self' )
                          : sprintf( plugin_lang_get( 'user_unlink_confirm' ), user_get_field( $f_user_id, 'username' ) );

helper_ensure_confirmed( $t_confirm_message, plugin_lang_get( 'user_unlink_button' ) );

# The same cleanup the /stop command does, only for another user: the chat keeps
# working as an anonymous one and asks for a binding on the next message
$t_telegram_user_id = telegram_user_get_id_by_user_id( $f_user_id );

if( $t_telegram_user_id != 0 ) {
    telegram_message_realatationship_delete( $t_telegram_user_id );
    telegram_bot_user_mapping_delete( $f_user_id );
    telegram_registration_complete( $t_telegram_user_id );

    # the draft of an unfinished issue belongs to the user, not to the chat
    plugin_config_delete( 'bug_data_draft', $f_user_id );
    plugin_config_delete( 'bug_data_draft_chat_id', $f_user_id );
    plugin_config_delete( 'bug_data_draft_message_id', $f_user_id );
    plugin_config_delete( 'bug_data_draft_text_msg', $f_user_id );
    plugin_config_delete( 'bug_data_draft_current_field_to_save', $f_user_id );

    plugin_log_event( 'Telegram user id#' . $t_telegram_user_id . ' is unlinked from mantisbt user '
            . user_get_username( $f_user_id ) . ' by ' . user_get_username( auth_get_current_user_id() ) );

    telegram_session_send_message( $t_telegram_user_id, array( 'text' => plugin_lang_get( 'end_message' ) ) );
}

form_security_purge( 'telegram_user_unlink' );

# Back where the button was pressed, no url is taken from the request for that
$t_redirect_url = auth_get_current_user_id() == $f_user_id
                          ? plugin_page( 'account_telegram_prefs_page', TRUE )
                          : plugin_page( 'monitor_page', TRUE );

layout_page_header( null, $t_redirect_url );

layout_page_begin();

html_operation_successful( $t_redirect_url );

layout_page_end();
