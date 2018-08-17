<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function helper_ensure_telegram_bot_registred_confirmed( $p_message ) {
    if( true == gpc_get_string( '_confirmed', FALSE ) ) {
        return gpc_get_string( '_confirmed' );
    }

    layout_page_header();
    layout_page_begin();

    echo '<div class="col-md-12 col-xs-12">';
    echo '<div class="space-10"></div>';
    echo '<div class="alert alert-warning center">';
    echo '<p class="bigger-110">';
    echo "\n" . $p_message . "\n";
    echo '</p>';
    echo '<div class="space-10"></div>';

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );
    echo '<input type="hidden" name="_confirmed" value="1" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="ДА" />';
    echo "\n</form>";

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );
    echo '<input type="hidden" name="_confirmed" value="0" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="НЕТ" />';
    echo "\n</form>";


    echo '<div class="space-10"></div>';
    echo '</div></div>';

    layout_page_end();
    exit;
}