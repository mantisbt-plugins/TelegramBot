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

function telegram_bot_keyboard_rows_default_filter() {

    $t_keyboard_rows   = array();
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_assigned' ), 'callback_data' => json_encode( array( 'get_bugs' => 'assigned' ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_monitored' ), 'callback_data' => json_encode( array( 'get_bugs' => 'monitored' ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'my_view_title_reported' ), 'callback_data' => json_encode( array( 'get_bugs' => 'reported' ) ) ];
    $t_keyboard_rows[] = [ 'text' => lang_get( 'use_query' ), 'callback_data' => json_encode( array( 'get_bugs' => 'use_query' ) ) ];

    return $t_keyboard_rows;
}

function telegram_bot_get_keyboard_default_filter() {

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    foreach( telegram_bot_keyboard_rows_default_filter() as $t_row ) {
        $t_inline_keyboard->addRow( $t_row );
    }

    return $t_inline_keyboard;
}

function keyboard_bugs_get( $p_mantis_custom_filter, $p_page ) {
    $t_per_page   = null;
    $t_bug_count  = null;
    $t_page_count = null;


    $t_current_page = $p_page;

    $t_bugs = filter_get_bug_rows( $t_current_page, $t_per_page, $t_page_count, $t_bug_count, $p_mantis_custom_filter, 0, null, true );

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    if( $t_current_page > 1 && $t_current_page <= $t_page_count ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '<<',
                                  'callback_data' => json_encode( array(
                                                            'get_bugs' => 'use_query',
                                                            'page'     => $t_current_page - 1
                                  ) )
        ] );
    }

    foreach( $t_bugs as $t_bug ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => $t_bug->id . ': ' . $t_bug->summary,
                                  'callback_data' => json_encode( array(
                                                            'set_bug' => $t_bug->id
                                  ) )
        ] );
    }

    if( $t_current_page > 1 && $t_current_page <= $t_page_count ) {
        $t_inline_keyboard->addRow( [ 'text'          => '<<',
                                  'callback_data' => json_encode( array(
                                                            'get_bugs' => 'use_query',
                                                            'page'     => $t_current_page - 1
                                  ) )
        ] );
    }

    if( $t_current_page >= 1 && $t_current_page < $t_page_count ) {
        $t_inline_keyboard->addRow( [
                                  'text'          => '>>',
                                  'callback_data' => json_encode( array(
                                                            'get_bugs' => 'use_query',
                                                            'page'     => $t_current_page + 1
                                  ) )
        ] );
    }

    $t_inline_keyboard->addRow( [
                              'text'          => '>>' . plugin_lang_get( 'keyboard_button_list_of_sections' ) . '<<',
                              'callback_data' => json_encode( array(
                                                        'get_default_category' => ''
                              ) )
    ] );


    return $t_inline_keyboard;
}
