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

if( $g_tg == NULL ) {
    plugin_error('ERROR_TG_SESSION_NOT_INITIALIZED');
}

# Only one instance per installation: parallel getUpdates calls are rejected by Telegram with 409 Conflict.
# The lock lives in the world-writable temp under a predictable name, so the file is trusted only
# after the checks below: one planted by another local user has to fail loudly instead of holding
# the flock() and posing as a running instance forever.
$t_lock_path = rtrim( sys_get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR . 'mantis_telegrambot_' . md5( __FILE__ ) . '.lock';

# A symlink or a FIFO in place of the lock redirects the open elsewhere or hangs it
if( file_exists( $t_lock_path ) && ( is_link( $t_lock_path ) || !is_file( $t_lock_path ) ) ) {
	plugin_log_event( 'Get updates refused: lock path ' . $t_lock_path . ' is not a regular file.' );
	echo "Lock path $t_lock_path is not a regular file, exiting.\n";
	exit( 1 );
}

$t_lock_handle = @fopen( $t_lock_path, 'c' );

if( $t_lock_handle === false ) {
	# The file of this user always opens - it is created 0600 below, so a failure
	# means a foreign one, and the sticky bit on temp keeps it there until its owner
	# or root removes it
	plugin_log_event( 'Get updates refused: lock file ' . $t_lock_path . ' is not writable, probably created by another user.' );
	echo "Lock file $t_lock_path is not writable, exiting.\n";
	exit( 1 );
}

# The very first run may still open a file planted before it: the owner gives that away
$t_lock_stat = fstat( $t_lock_handle );
if( function_exists( 'posix_geteuid' ) && $t_lock_stat['uid'] !== posix_geteuid() ) {
	plugin_log_event( 'Get updates refused: lock file ' . $t_lock_path . ' is owned by another user.' );
	echo "Lock file $t_lock_path is owned by another user, exiting.\n";
	exit( 1 );
}

# Nobody else may open the file: anybody able to open it can hold flock() on it
# and silence the polling
@chmod( $t_lock_path, 0600 );

if( !flock( $t_lock_handle, LOCK_EX | LOCK_NB ) ) {
	plugin_log_event( 'Get updates skipped: another instance is already running.' );
	echo "Another instance is already running, exiting.\n";
	exit( 0 );
}

# Let the plugin configuration page tell whether the script is scheduled and actually running.
plugin_config_set( 'get_updates_last_run', time() );

# Long polling: Telegram holds the connection for $t_timeout seconds and answers as soon as an update arrives.
$t_timeout  = (int)plugin_config_get( 'get_updates_timeout' );
$t_run_time = (int)plugin_config_get( 'get_updates_run_time' );

# The connection would be dropped before the answer if curl waits less than Telegram holds the request.
$t_response_timeout = (int)plugin_config_get( 'time_out_server_response' );
if( $t_timeout > 0 && $t_timeout >= $t_response_timeout ) {
	$t_timeout = max( 0, $t_response_timeout - 5 );
}

define( 'UPDATE_PROCESS_INC_ALLOW', true );

# Request::getUpdates() is called directly instead of Telegram::handleGetUpdates(): without a
# database the latter does not know last_update_id, so it polls from offset 0 and then spends
# another full $t_timeout on a confirming request before returning - the answer to the user
# would be delayed by that timeout. Telegram confirms updates by the offset of the next call,
# so the offset is kept here and stored between runs.
$t_offset     = (int)plugin_config_get( 'get_updates_offset' );
$t_started_at = time();

do {
	# The last poll of the run is shortened to the time left instead of being skipped: skipping it
	# leaves the bot deaf until the next scheduled run, which delays the answer by up to a timeout.
	$t_poll_timeout = $t_timeout;
	if( $t_run_time > 0 ) {
		$t_poll_timeout = min( $t_timeout, $t_run_time - ( time() - $t_started_at ) );
	}

	echo "Get updates...\n";

	$t_updates = Longman\TelegramBot\Request::getUpdates( array(
		'offset'  => $t_offset,
		'timeout' => $t_poll_timeout > 0 ? $t_poll_timeout : null,
	) );

	if( !$t_updates->getOk() ) {
		plugin_log_event( 'Get updates failed: ' . $t_updates->getDescription() );
		error_parameters( $t_updates->getDescription() );
		plugin_error('ERROR_TG_GET_UPDATE');
	}

	$t_results = $t_updates->getResult();

	echo "Received " . count( $t_results ) . " updates.\n";

	if( count( $t_results ) > 0 ) {
		$t_last_update = end( $t_results );
		$t_offset      = $t_last_update->getUpdateId() + 1;

		plugin_log_event( sprintf( 'Received %d updates, next offset %d.', count( $t_results ), $t_offset ) );

		echo "Start process updates...\n\n";
		include( dirname( dirname( __FILE__ )) . '/pages/update_process_inc.php' );

		# Stored after processing: a run that dies earlier makes Telegram resend the updates.
		plugin_config_set( 'get_updates_offset', $t_offset );
	}

	# Keep polling while there is time left in this run.
	$t_elapsed = time() - $t_started_at;
} while( $t_run_time > 0 && $t_elapsed < $t_run_time );

echo "\nDone.\n\n";

flock( $t_lock_handle, LOCK_UN );
fclose( $t_lock_handle );

exit( 0 );
