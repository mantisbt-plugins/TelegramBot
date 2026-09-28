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

use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\CallbackQuery;

if( !telegram_webhook_request_is_authentic() ) {
    plugin_log_event( 'ERROR! Webhook request with a wrong secret token.' );
    http_response_code( HTTP_STATUS_FORBIDDEN );
    exit();
}

$t_post = json_decode( Longman\TelegramBot\Request::getInput(), true );

$t_results[0] = new Longman\TelegramBot\Entities\Update( $t_post );

define( 'UPDATE_PROCESS_INC_ALLOW', true );
include( dirname( __FILE__ ) . '/update_process_inc.php' );