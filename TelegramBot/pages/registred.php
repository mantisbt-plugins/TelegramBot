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

# A protected account (a shared or anonymous one) must not get a chat of its own
current_user_ensure_unprotected();

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
$f_token            = gpc_get_string( 'token', '' );
$f_is_confirmed     = gpc_get_bool( '_confirmed', FALSE );

# The telegram user id is public, the one-time token issued with the invitation is not:
# only the owner of the chat has the link, so nobody else can get his chat bound to the
# account that opens this page
$t_telegram_name = telegram_registration_link_token_check( $f_telegram_user_id, $f_token );

if( $t_telegram_name === false ) {
    plugin_log_event( 'Registration Error! Invalid or expired link token for telegram user id#' . $f_telegram_user_id . ', opened by user ' . user_get_username( auth_get_current_user_id() ) );
    plugin_error( 'ERROR_TG_REGISTRATION_LINK_INVALID', ERROR );
}

# A refusal kills the link: it may have come from somebody else, and a link left
# alive could still be confirmed by a careless second click
if( '0' === gpc_get_string( '_confirmed', '' ) && 'POST' == $_SERVER['REQUEST_METHOD'] ) {
    telegram_registration_link_token_burn( $f_telegram_user_id );

    plugin_log_event( 'Binding of telegram user id#' . $f_telegram_user_id . ' is declined by mantisbt user ' . user_get_username( auth_get_current_user_id() ) );

    layout_page_header( plugin_lang_get( 'account_telegram_register_page_header' ) );
    layout_page_begin( 'account_page' );

    html_operation_warning( helper_mantis_url( config_get( 'default_home_page' ) ), plugin_lang_get( 'user_relationship_declined' ) );

    layout_page_end();

    return;
}

# Neither the account nor the chat is relinked silently. Checked before the question,
# asking to confirm a binding that is going to be refused would only mislead
telegram_bot_user_mapping_ensure_allowed( auth_get_current_user_id(), $f_telegram_user_id );

# Both sides of the binding are shown apart and the warning stands out: the page is
# the last chance to notice a link sent by somebody else
$t_telegram_account = is_blank( $t_telegram_name ) ? '' : '<strong>' . string_html_specialchars( $t_telegram_name ) . '</strong><br>';
$t_telegram_account .= '<span class="grey">ID ' . (int)$f_telegram_user_id . '</span>';

helper_ensure_telegram_bot_registred_confirmed(
                          '<h4 class="bold">' . plugin_lang_get( 'user_relationship_confirm_title' ) . '</h4>'
                          . '<table class="table table-bordered table-condensed" style="width: auto; margin: 10px auto;">'
                          . '<tr><th class="category">' . plugin_lang_get( 'user_relationship_confirm_telegram' ) . '</th>'
                          . '<td class="left">' . $t_telegram_account . '</td></tr>'
                          . '<tr><th class="category">' . plugin_lang_get( 'user_relationship_confirm_mantis' ) . '</th>'
                          . '<td class="left"><strong>' . string_html_specialchars( user_get_name( auth_get_current_user_id() ) ) . '</strong></td></tr>'
                          . '</table>'
                          . '<p>' . plugin_lang_get( 'user_relationship_confirm_effect' ) . '</p>'
                          . '<p class="red bold">' . plugin_lang_get( 'user_relationship_confirm_warning' ) . '</p>'
);

if( $f_is_confirmed ) {
    # The token is printed by the confirmation form: a cross-site request has no way
    # to obtain it, so a forged confirmation cannot bind a foreign chat to the session
    form_security_validate( 'plugin_TelegramBot_registred' );

    if( 'POST' != $_SERVER['REQUEST_METHOD'] ) {
        access_denied();
    }

    # One use only, whatever comes next: a link that has been confirmed once must not
    # work again, even when the binding is refused below
    telegram_registration_link_token_burn( $f_telegram_user_id );
}

layout_page_header_begin();
layout_page_header_end();
layout_page_begin( 'account_page' );

if( $f_is_confirmed ) {

    form_security_purge( 'plugin_TelegramBot_registred' );

    $t_current_user_id = auth_get_current_user_id();

    telegram_bot_user_mapping_add( $t_current_user_id, $f_telegram_user_id );

    plugin_log_event( 'Telegram user id#' . $f_telegram_user_id . ' is mapped to mantisbt user ' . user_get_username( $t_current_user_id ) . ' by link' );

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
