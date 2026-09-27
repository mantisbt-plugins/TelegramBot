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

/**
 * Per-user broadcast permissions: array( user_id => array( project_id, ... ) ).
 *
 * @return array
 */
function telegram_broadcast_grants_get() {
	$t_grants = plugin_config_get( 'broadcast_grants', array() );

	return is_array( $t_grants ) ? $t_grants : array();
}

/**
 * Project ids the user is allowed to broadcast to.
 *
 * Users at or above broadcast_send_threshold may broadcast to all projects,
 * other users - only to projects explicitly granted to them.
 *
 * @param int $p_user_id MantisBT user id.
 * @return array
 */
function telegram_broadcast_allowed_project_ids( $p_user_id ) {
	$t_all_project_ids = array();
	foreach( project_get_all_rows() as $t_project ) {
		$t_all_project_ids[] = (int)$t_project['id'];
	}

	if( access_has_global_level( plugin_config_get( 'broadcast_send_threshold' ), $p_user_id ) ) {
		return $t_all_project_ids;
	}

	$t_grants = telegram_broadcast_grants_get();
	if( !isset( $t_grants[(int)$p_user_id] ) ) {
		return array();
	}

	return array_values( array_intersect( array_map( 'intval', $t_grants[(int)$p_user_id] ), $t_all_project_ids ) );
}

/**
 * Whether the user may use the broadcast feature at all.
 *
 * @param int $p_user_id MantisBT user id.
 * @return bool
 */
function telegram_broadcast_can_send( $p_user_id ) {
	if( OFF == plugin_config_get( 'broadcast_enabled' ) ) {
		return false;
	}

	$t_allowed = telegram_broadcast_allowed_project_ids( $p_user_id );

	return !empty( $t_allowed );
}
