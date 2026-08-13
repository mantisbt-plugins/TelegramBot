<?php
# Copyright (c) 2023 Grigoriy Ermolaev (igflocal@gmail.com)
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

use Mantis\Exceptions\ClientException;

form_security_validate( 'broadcast_message_send' );

$f_project_list = gpc_get_int_array( 'projects_add' );
$f_message      = gpc_get_string( 'bugnote_text' );

$t_mantis_user_list = array();

foreach ( $f_project_list as $t_project ) {
    $t_mantis_user_list += project_get_all_user_rows($t_project);
    
    
}


foreach ($t_mantis_user_list as $t_user) {
        
        $t_telegram_user_id = telegram_user_get_id_by_user_id($t_user['id']);
        if( $t_telegram_user_id != 0 ) {
            $data = [
                'text' => $f_message
            ];

            $t_results = telegram_session_send_message( $t_telegram_user_id, $data );
        }
    }
    


form_security_purge( 'broadcast_message_send' );

$t_redirect_url = plugin_page( 'broadcast_message_page', true );
layout_page_header();
layout_page_begin();

html_operation_successful( $t_redirect_url, 'Send successful' );

layout_page_end();
