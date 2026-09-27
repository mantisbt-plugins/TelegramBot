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

use Longman\TelegramBot\Request;

form_security_validate( 'broadcast_message_send' );

auth_ensure_user_authenticated();

if( !telegram_broadcast_can_send( auth_get_current_user_id() ) ) {
	access_denied();
}

$f_project_list = gpc_get_int_array( 'projects', array() );

# Every selected project must be granted to the sender.
$t_allowed_project_ids = telegram_broadcast_allowed_project_ids( auth_get_current_user_id() );
foreach( $f_project_list as $t_project_id ) {
	if( !in_array( (int)$t_project_id, $t_allowed_project_ids ) ) {
		access_denied();
	}
}
$f_message      = trim( gpc_get_string( 'message', '' ) );
$f_files        = gpc_get_file( 'ufile', array() );

# Keep only successfully uploaded files.
$t_files = array();
if( is_array( $f_files ) && isset( $f_files['name'] ) ) {
	foreach( helper_array_transpose( $f_files ) as $t_file ) {
		if( isset( $t_file['error'] ) && $t_file['error'] == UPLOAD_ERR_OK && !is_blank( $t_file['name'] ) ) {
			$t_files[] = $t_file;
		}
	}
}

if( empty( $f_project_list ) ) {
	error_parameters( plugin_lang_get( 'broadcast_projects' ) );
	trigger_error( ERROR_EMPTY_FIELD, ERROR );
}

if( is_blank( $f_message ) && empty( $t_files ) ) {
	error_parameters( plugin_lang_get( 'broadcast_message' ) );
	trigger_error( ERROR_EMPTY_FIELD, ERROR );
}

# Collect recipients from the selected projects, deduplicated by MantisBT user id.
# project_get_all_user_rows() returns enabled users only.
$t_mantis_user_list = array();
foreach( $f_project_list as $t_project_id ) {
	foreach( project_get_all_user_rows( $t_project_id ) as $t_user_row ) {
		$t_mantis_user_list[(int)$t_user_row['id']] = $t_user_row;
	}
}

# Stage uploaded files under their original names, so Telegram shows proper file names.
$t_staging_dir = '';
if( !empty( $t_files ) ) {
	$t_staging_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid( 'TelegramBot_broadcast_' );
	if( !mkdir( $t_staging_dir ) ) {
		trigger_error( ERROR_FILE_INVALID_UPLOAD_PATH, ERROR );
	}
	foreach( $t_files as $t_key => $t_file ) {
		$t_staged_path = $t_staging_dir . DIRECTORY_SEPARATOR . basename( $t_file['name'] );
		if( move_uploaded_file( $t_file['tmp_name'], $t_staged_path ) ) {
			$t_files[$t_key]['path'] = $t_staged_path;
		} else {
			trigger_error( ERROR_FILE_INVALID_UPLOAD_PATH, ERROR );
		}
	}
}

$t_count_linked   = 0;
$t_count_sent     = 0;
$t_count_no_link  = 0;

$t_sender_name = user_get_name( auth_get_current_user_id() );

foreach( $t_mantis_user_list as $t_user ) {
	$t_telegram_user_id = telegram_user_get_id_by_user_id( $t_user['id'] );
	if( $t_telegram_user_id == 0 ) {
		$t_count_no_link++;
		continue;
	}
	$t_count_linked++;

	$t_user_ok = true;

	# Compose the message with a broadcast header in the recipient's language.
	lang_push( user_pref_get_language( $t_user['id'] ) );
	$t_text = sprintf( plugin_lang_get( 'broadcast_header' ), $t_sender_name );
	lang_pop();
	if( !is_blank( $f_message ) ) {
		$t_text .= "\n\n" . $f_message;
	}

	$t_results = telegram_session_send_message( $t_telegram_user_id, array( 'text' => $t_text ) );
	$t_user_ok = !empty( $t_results );
	foreach( $t_results as $t_result ) {
		$t_user_ok = $t_user_ok && $t_result->isOk();
	}

	# Each file is uploaded to Telegram once; further recipients get it by file_id.
	foreach( $t_files as $t_key => $t_file ) {
		$t_data = array(
			'chat_id'  => $t_telegram_user_id,
			'document' => isset( $t_file['file_id'] ) ? $t_file['file_id'] : $t_file['path'],
		);
		try {
			$t_response = Request::sendDocument( $t_data );
			if( $t_response->isOk() ) {
				if( !isset( $t_file['file_id'] ) ) {
					$t_document = $t_response->getResult()->getDocument();
					if( $t_document !== null ) {
						$t_files[$t_key]['file_id'] = $t_document->getFileId();
					}
				}
			} else {
				$t_user_ok = false;
				plugin_log_event( 'ERROR! Broadcast sendDocument to ' . $t_telegram_user_id . ' failed: ' . $t_response->getDescription() );
			}
		} catch( Exception $t_error ) {
			$t_user_ok = false;
			plugin_log_event( 'ERROR! Broadcast sendDocument to ' . $t_telegram_user_id . ' failed: ' . $t_error->getMessage() );
		}
	}

	if( $t_user_ok ) {
		$t_count_sent++;
	}
}

# Remove staged files.
if( $t_staging_dir != '' ) {
	foreach( $t_files as $t_file ) {
		if( isset( $t_file['path'] ) && file_exists( $t_file['path'] ) ) {
			unlink( $t_file['path'] );
		}
	}
	rmdir( $t_staging_dir );
}

plugin_log_event( sprintf( 'Broadcast message sent by user %d: %d of %d linked users, %d users without Telegram link',
	auth_get_current_user_id(), $t_count_sent, $t_count_linked, $t_count_no_link ) );

form_security_purge( 'broadcast_message_send' );

$t_redirect_url = plugin_page( 'broadcast_message_page', true );
layout_page_header();
layout_page_begin();

html_operation_successful( $t_redirect_url,
	sprintf( plugin_lang_get( 'broadcast_sent' ), $t_count_sent, $t_count_linked, $t_count_no_link ) );

layout_page_end();
