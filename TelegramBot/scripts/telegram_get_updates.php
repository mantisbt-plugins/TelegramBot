#!/usr/bin/php -q
<?php
# Copyright (c) 2020 Grigoriy Ermolaev (igflocal@gmail.com)
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

/**
 * Cron Script to get updates from Telegram.
 */

/**
 * Global Bypass http headers
 */
global $g_bypass_headers;
$g_bypass_headers = 1;
global $g_tg;

require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/core.php' );

# Make sure this script doesn't run via the webserver
if( php_sapi_name() != 'cli' ) {
	echo "telegram_get_updates.php is not allowed to run through the webserver.\n";
	exit( 1 );
}

$t_plugin = plugin_get( 'TelegramBot' );

if( plugin_needs_upgrade( $t_plugin ) ) {
	error_parameters( 'TelegramBot' );
	trigger_error( ERROR_PLUGIN_UPGRADE_NEEDED, ERROR );
}

# Plugin can be registered but fail to load e.g. due to unmet dependencies
if( !plugin_is_loaded( 'TelegramBot' ) ) {
	error_parameters( 'TelegramBot' );
	trigger_error( ERROR_PLUGIN_NOT_LOADED, ERROR );
}

plugin_push_current( 'TelegramBot' );

echo "Get updates...\n";
telegram_session_start();
$t_updates = $g_tg->handleGetUpdates();
$t_results = $t_updates->getResult();
echo "Received " . count( $t_results ) . " updates.\n";
echo "Start process updates...\n\n";

define( 'UPDATE_PROCESS_INC_ALLOW', true );
include( dirname( dirname( __FILE__ )) . '/pages/update_process_inc.php' );

echo "\nDone.\n\n";

exit( 0 );