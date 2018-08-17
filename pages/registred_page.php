<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

auth_reauthenticate();

$f_telegram_user_id = gpc_get_int( 'telegram_user_id' );
$f_bot_name         = gpc_get_string( 'bot_name' );
$f_is_confirmed     = gpc_get_bool( '_confirmed', FALSE );

helper_ensure_telegram_bot_registred_confirmed( 'Связать ваш аккаунт с ботом: ' . $f_bot_name );

layout_page_header_begin();
layout_page_header_end();
layout_page_begin( 'account_page' );

if( $f_is_confirmed ) {
    $t_current_user_id = auth_get_current_user_id();
    plugin_config_set( 'telegram_user_id', $f_telegram_user_id, $t_current_user_id );

    html_operation_successful( 'account_page.php', 'Аккаунт привязан' );
    html_meta_redirect( 'account_page.php' );
}

layout_page_end();
