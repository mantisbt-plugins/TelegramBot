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

auth_ensure_user_authenticated();

# Links sent before the method was switched must not keep working: with PIN codes
# the binding is confirmed from the chat side only. An old invitation stays in the
# chat forever, so the dead end is explained instead of a bare "access denied"
if( TELEGRAM_REGISTRATION_PIN == (int) plugin_config_get( 'registration_method' ) ) {
    layout_page_header( plugin_lang_get( 'account_telegram_register_page_header' ) );
    layout_page_begin( 'account_page' );

    echo '<div class="col-md-12 col-xs-12">';
    echo '<div class="space-10"></div>';
    echo '<div class="alert alert-warning center">';
    echo '<p class="bigger-110">' . plugin_lang_get( 'registration_link_disabled' ) . '</p>';
    echo '<p class="bigger-110"><a href="' . plugin_page( 'account_telegram_register_page' ) . '">'
    . plugin_lang_get( 'account_telegram_register_page_header' ) . '</a></p>';
    echo '</div></div>';

    layout_page_end();

    return;
}

$f_telegram_user_id = gpc_get_int( 'telegram_user_id' );
$f_is_confirmed     = gpc_get_bool( '_confirmed', FALSE );

helper_ensure_telegram_bot_registred_confirmed( plugin_lang_get( 'user_relationship_question' ) );

# The telegram user id travels through the browser, so a chat already bound to somebody
# else must not be relinked: its owner would end up working in the bot on behalf of the
# account that opened this page. The chat is released by /stop sent from that chat.
if( $f_is_confirmed ) {
    # The token is printed by the confirmation form: a cross-site request has no way
    # to obtain it, so a forged confirmation cannot bind a foreign chat to the session
    form_security_validate( 'plugin_TelegramBot_registred' );

    $t_associated_user_id = user_get_id_by_telegram_user_id( $f_telegram_user_id );

    if( $t_associated_user_id != 0 && $t_associated_user_id != auth_get_current_user_id() ) {
        plugin_log_event( 'Registration Error! Telegram user id#' . $f_telegram_user_id . ' is already mapped to mantisbt user ' . user_get_username( $t_associated_user_id ) );
        plugin_error( 'ERROR_TG_USER_ALREADY_ASSOCIATED', ERROR );
    }
}

layout_page_header_begin();
layout_page_header_end();
layout_page_begin( 'account_page' );

if( $f_is_confirmed ) {

    form_security_purge( 'plugin_TelegramBot_registred' );

    $t_current_user_id = auth_get_current_user_id();

    telegram_bot_user_mapping_add( $t_current_user_id, $f_telegram_user_id );

    # The accounts are linked: the invitation leaves the chat and the PIN code is dropped
    telegram_registration_complete( $f_telegram_user_id );

    $data     = [
                              'chat_id' => $f_telegram_user_id,
                              'text'    => telegram_message_first_text(),
    ];
    $t_result = \Longman\TelegramBot\Request::sendMessage( $data );

    $t_redirect_url = plugin_config_get( 'telegram_url' ) . plugin_config_get( 'bot_name' );
    echo '<div class="col-md-12 col-xs-12">';
    echo '<div class="space-10"></div>';
    echo '<div class="alert alert-success center">';
    echo '<p class="bigger-110">';
    echo "\n" . plugin_lang_get( 'bot_successfully_attached' ) . "\n";
    echo '</p>';
    echo '<p class="bigger-110">';
    echo "\n" . plugin_lang_get( 'info_to_redirect_bot_page' ) . "\n";
    echo '</p>';

    echo '</div></div>';

    echo "\t" . '<meta http-equiv="Refresh" content="' . (int)current_user_get_pref( 'redirect_delay' ) . '; URL=' . string_attribute( $t_redirect_url ) . '" />' . "\n";
}

layout_page_end();
