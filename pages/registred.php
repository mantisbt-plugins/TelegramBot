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

auth_ensure_user_authenticated();

$f_telegram_user_id = gpc_get_int( 'telegram_user_id' );
$f_is_confirmed     = gpc_get_bool( '_confirmed', FALSE );

helper_ensure_telegram_bot_registred_confirmed( plugin_lang_get( 'user_relationship_question' ) );

layout_page_header_begin();
layout_page_header_end();
layout_page_begin( 'account_page' );

if( $f_is_confirmed ) {

    $t_current_user_id = auth_get_current_user_id();

    telegram_bot_user_mapping_add( $t_current_user_id, $f_telegram_user_id );

    $data     = [
                              'chat_id' => $f_telegram_user_id,
                              'text'    => sprintf( plugin_lang_get( 'first_message' ), config_get( 'window_title' ) . ' ( ' . config_get( 'path' ) . ' )', ' ( ' . config_get( 'path' ) . plugin_page( 'account_telegram_prefs_page', TRUE ) . ' )'
                              ),
    ];
    $t_result = RequestMantis::sendMessage( $data );

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

    echo "\t" . '<meta http-equiv="Refresh" content="' . current_user_get_pref( 'redirect_delay' ) . '; URL=' . $t_redirect_url . '" />' . "\n";
}

layout_page_end();
