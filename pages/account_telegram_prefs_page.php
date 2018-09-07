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

current_user_ensure_unprotected();

define( 'ACCOUNT_TELEGRAM_PREFS_INC_ALLOW', true );
include( dirname( __FILE__ ) . '/account_telegram_prefs_inc.php' );

layout_page_header( plugin_lang_get( 'account_telegram_prefs_page_header' ) );

layout_page_begin();

telegram_edit_account_prefs();

layout_page_end();
