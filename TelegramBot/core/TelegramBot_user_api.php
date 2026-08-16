<?php

# Copyright (c) 2024 Grigoriy Ermolaev (igflocal@gmail.com)
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

function telegram_bot_associated_all_users_get() {
    $t_user_relationship_table = plugin_table( 'user_relationship' );

    db_param_push();

    $t_query   = "SELECT mantis_user_id 
			FROM " . $t_user_relationship_table;
    $t_results = db_query( $t_query );

    $t_row = array();
    
    foreach( $t_results as $t_result ) {
        $t_row[] = $t_result['mantis_user_id'];
    }

    return $t_row;
}

function telegram_bot_user_mapping_add( $p_user_id, $p_telegram_user_id ) {

    $t_user_id          = (int) $p_user_id;
    $t_telegram_user_id = (int) $p_telegram_user_id;

    $t_user_relationship_table = plugin_table( 'user_relationship' );

    $t_telegram_user_is_associated = telegram_user_is_associated_mantis_user( $p_telegram_user_id );
    $t_mantis_user_is_associated   = user_is_associated_with_telegram( $t_user_id );

    if( $t_telegram_user_is_associated ) {
        $t_query    = "UPDATE $t_user_relationship_table SET mantis_user_id = " . db_param() . " WHERE telegram_user_id = " . db_param();
        $t_db_param = array( $t_user_id, $t_telegram_user_id );
    } else if( $t_mantis_user_is_associated ) {
        $t_query    = "UPDATE $t_user_relationship_table SET telegram_user_id = " . db_param() . " WHERE mantis_user_id = " . db_param();
        $t_db_param = array( $t_telegram_user_id, $t_user_id );
    } else {
        $t_query    = "INSERT INTO $t_user_relationship_table
                                                ( mantis_user_id, telegram_user_id )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ')';
        $t_db_param = array( $t_user_id, $t_telegram_user_id );
    }

    db_query( $t_query, $t_db_param );

    return true;
}

function telegram_bot_user_mapping_delete( $p_user_id ) {

    $t_user_relationship_table = plugin_table( 'user_relationship' );

    $query = "DELETE FROM $t_user_relationship_table";

    $query .= " WHERE mantis_user_id=" . db_param();

    $t_fields[] = $p_user_id;

    db_query( $query, $t_fields );

    return true;
}

function user_get_id_by_telegram_user_id( $p_telegram_user_id ) {

    $t_user_relationship_table = plugin_table( 'user_relationship' );

    db_param_push();

    $t_query  = "SELECT mantis_user_id 
			FROM $t_user_relationship_table
			WHERE telegram_user_id=" . db_param();
    $t_result = db_query( $t_query, array( $p_telegram_user_id ) );

    $t_row     = db_fetch_array( $t_result );
    if( $t_row === false ) {
        $t_user_id = 0;
    } else {
        $t_user_id = $t_row['mantis_user_id'];
    }

    return (int) $t_user_id;
}

function telegram_user_get_id_by_user_id( $p_mantis_user_id ) {

    $t_user_relationship_table = plugin_table( 'user_relationship' );

    db_param_push();

    $t_query  = "SELECT telegram_user_id 
			FROM $t_user_relationship_table
			WHERE mantis_user_id=" . db_param();
    $t_result = db_query( $t_query, array( $p_mantis_user_id ) );

    $t_row     = db_fetch_array( $t_result );
    if( $t_row === false ) {
        $t_user_id = 0;
    } else {
        $t_user_id = $t_row['telegram_user_id'];
    }

    return (int) $t_user_id;
}

function telegram_user_is_associated_mantis_user( $p_telegram_user_id ) {

    $t_user_id = user_get_id_by_telegram_user_id( $p_telegram_user_id );

    if( $t_user_id == 0 ) {
        return false;
    } else {
        return true;
    }
}

function user_is_associated_with_telegram( $p_mantis_user_id ) {

    $t_telegram_user_id = telegram_user_get_id_by_user_id( $p_mantis_user_id );

    if( $t_telegram_user_id == 0 ) {
        return false;
    } else {
        return true;
    }
}

/**
 * Release the binding between a MantisBT user and his telegram account, the way the
 * /stop command does it - but without access to the chat, so that it also works for a
 * lost telegram account, for an administrator and for a user being deleted.
 *
 * Notification preferences are kept: they are of use again once the user comes back.
 *
 * @param integer $p_user_id A valid user identifier.
 * @param boolean $p_notify  Whether to tell the chat that it is unsubscribed.
 * @return integer Telegram user id the account was linked to, 0 if there was no binding.
 */
function telegram_bot_user_unlink( $p_user_id, $p_notify = true ) {

    $t_telegram_user_id = telegram_user_get_id_by_user_id( $p_user_id );

    if( $t_telegram_user_id == 0 ) {
        return 0;
    }

    telegram_message_realatationship_delete( $t_telegram_user_id );
    telegram_bot_user_mapping_delete( $p_user_id );
    telegram_registration_complete( $t_telegram_user_id );

    # the draft of an unfinished issue belongs to the user, not to the chat
    plugin_config_delete( 'bug_data_draft', $p_user_id );
    plugin_config_delete( 'bug_data_draft_chat_id', $p_user_id );
    plugin_config_delete( 'bug_data_draft_message_id', $p_user_id );
    plugin_config_delete( 'bug_data_draft_text_msg', $p_user_id );
    plugin_config_delete( 'bug_data_draft_current_field_to_save', $p_user_id );

    plugin_log_event( 'Telegram user id#' . $t_telegram_user_id . ' is unlinked from mantisbt user ' . user_get_username( $p_user_id ) );

    if( $p_notify ) {
        telegram_session_send_message( $t_telegram_user_id, array( 'text' => plugin_lang_get( 'end_message' ) ) );
    }

    return $t_telegram_user_id;
}

/**
 * Delete every plugin configuration option belonging to a user, called when the user
 * account itself goes away: the core removes profiles, preferences and access levels,
 * but plugin options in the config table are left behind.
 *
 * @param integer $p_user_id A valid user identifier.
 * @return void
 */
function telegram_user_config_delete_all( $p_user_id ) {

    $t_basename = plugin_get_current();

    # An empty basename would turn the pattern into "plugin_%", deleting the options
    # of every plugin - the caller is out of the plugin context and has nothing to do here
    if( is_blank( $t_basename ) ) {
        return;
    }

    $t_config_table = db_get_table( 'config' );

    db_param_push();

    $t_query = "DELETE FROM $t_config_table
			WHERE user_id=" . db_param() . '
			AND config_id LIKE ' . db_param();
    db_query( $t_query, array( (int) $p_user_id, 'plugin_' . $t_basename . '_%' ) );
}

/**
 * Return the state of the registration a telegram user has started: the PIN code
 * issued to him and the id of the invitation the bot has sent.
 *
 * @param integer $p_telegram_user_id Telegram user id.
 * @return array|false Database row, false when no registration is in progress.
 */
function telegram_registration_state_get( $p_telegram_user_id ) {

    $t_pin_codes_table = plugin_table( 'pin_codes' );

    db_param_push();

    $t_query  = "SELECT pin_code, timestamp, message_id
			FROM $t_pin_codes_table
			WHERE telegram_user_id=" . db_param();
    $t_result = db_query( $t_query, array( $p_telegram_user_id ) );

    return db_fetch_array( $t_result );
}

/**
 * Return the PIN code the telegram user has to enter in his MantisBT account
 * preferences. A code issued earlier and still valid is reused, so that every
 * message the bot sends to an unregistred user shows the same code.
 *
 * The state row is created even when the code is not going to be shown: it also
 * keeps the id of the invitation, which is needed to remove that message later.
 *
 * @param integer $p_telegram_user_id Telegram user id.
 * @return integer PIN code.
 */
function telegram_pin_code_get( $p_telegram_user_id ) {

    $t_pin_codes_table = plugin_table( 'pin_codes' );

    telegram_registration_states_clear_expired();

    $t_state = telegram_registration_state_get( $p_telegram_user_id );

    if( $t_state !== false && $t_state['timestamp'] >= db_now() - TELEGRAM_PIN_CODE_TTL ) {
        return (int) $t_state['pin_code'];
    }

    $t_pin_code = telegram_pin_code_free_get();

    if( $t_state === false ) {
        db_param_push();

        $t_query = "INSERT INTO $t_pin_codes_table
                                                ( telegram_user_id, pin_code, timestamp )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ',' . db_param() . ')';
        db_query( $t_query, array( $p_telegram_user_id, $t_pin_code, db_now() ) );
    } else {
        # The invitation is still in the chat, only the code has expired
        db_param_push();

        $t_query = "UPDATE $t_pin_codes_table
			SET pin_code=" . db_param() . ', timestamp=' . db_param() . '
			WHERE telegram_user_id=' . db_param();
        db_query( $t_query, array( $t_pin_code, db_now(), $p_telegram_user_id ) );
    }

    return $t_pin_code;
}

/**
 * Pick a PIN code no other registration is using.
 *
 * @return integer PIN code.
 */
function telegram_pin_code_free_get() {

    $t_pin_codes_table = plugin_table( 'pin_codes' );

    db_param_push();

    $t_query  = "SELECT pin_code FROM $t_pin_codes_table";
    $t_result = db_query( $t_query );

    $t_codes_in_use = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_codes_in_use[(int) $t_row['pin_code']] = true;
    }

    # 9000 possible codes against the few registrations running at the same time:
    # a free code is found on the first attempts, the limit only guards the loop.
    # random_int(): the code is a secret, so a CSPRNG - mt_rand() is predictable
    for( $i = 0; $i < 100; $i++ ) {
        $t_candidate = random_int( 1000, 9999 );
        if( !isset( $t_codes_in_use[$t_candidate] ) ) {
            return $t_candidate;
        }
    }

    plugin_error( 'ERROR_TG_PIN_CODE_GENERATE', ERROR );
}

/**
 * Seconds the PIN code lockout window lasts, from the settings of the plugin.
 *
 * @return integer
 */
function telegram_pin_code_attempts_window_get() {

    return 60 * max( 1, (int) plugin_config_get( 'pin_code_attempts_window' ) );
}

/**
 * Return true when the user has spent every PIN code guess of the current window.
 *
 * A 4-digit code holds no more than 9000 values, so it is only a secret while the
 * guesses are counted: the state is "count:window_start" per MantisBT user, the
 * limit and the window come from the settings of the plugin.
 *
 * @param integer $p_user_id MantisBT user id.
 * @return boolean
 */
function telegram_pin_code_attempts_exceeded( $p_user_id ) {

    $t_state = plugin_config_get( 'pin_code_attempts', '', FALSE, (int) $p_user_id );

    if( is_blank( $t_state ) ) {
        return false;
    }

    list( $t_count, $t_started ) = array_pad( explode( ':', $t_state ), 2, 0 );

    if( (int) $t_started < db_now() - telegram_pin_code_attempts_window_get() ) {
        return false;
    }

    return (int) $t_count >= max( 1, (int) plugin_config_get( 'pin_code_attempts_max' ) );
}

/**
 * Count a wrong PIN code guess. An expired window starts over.
 *
 * @param integer $p_user_id MantisBT user id.
 * @return void
 */
function telegram_pin_code_attempt_failed( $p_user_id ) {

    $t_state   = plugin_config_get( 'pin_code_attempts', '', FALSE, (int) $p_user_id );
    $t_count   = 0;
    $t_started = db_now();

    if( !is_blank( $t_state ) ) {
        list( $t_old_count, $t_old_started ) = array_pad( explode( ':', $t_state ), 2, 0 );

        if( (int) $t_old_started >= db_now() - telegram_pin_code_attempts_window_get() ) {
            $t_count   = (int) $t_old_count;
            $t_started = (int) $t_old_started;
        }
    }

    plugin_config_set( 'pin_code_attempts', ( $t_count + 1 ) . ':' . $t_started, (int) $p_user_id );
}

/**
 * Return the PIN code guess counters of every user, keyed by user id.
 *
 * The rows are read straight from the config table: the core has no way to list
 * the users a plugin option is set for. Stale windows are included, filtering is
 * up to the caller.
 *
 * @return array array( user_id => array( 'count' => int, 'started' => int ) )
 */
function telegram_pin_code_attempts_all_get() {

    $t_basename = plugin_get_current();

    if( is_blank( $t_basename ) ) {
        return array();
    }

    $t_config_table = db_get_table( 'config' );

    db_param_push();

    $t_query  = "SELECT user_id, value FROM $t_config_table
			WHERE config_id=" . db_param() . ' AND user_id<>0';
    $t_result = db_query( $t_query, array( 'plugin_' . $t_basename . '_pin_code_attempts' ) );

    $t_rows = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        list( $t_count, $t_started ) = array_pad( explode( ':', (string) $t_row['value'] ), 2, 0 );

        $t_rows[(int) $t_row['user_id']] = array(
                                  'count'   => (int) $t_count,
                                  'started' => (int) $t_started,
        );
    }

    return $t_rows;
}

/**
 * Forget the guesses counted for the user, called when a code is accepted.
 *
 * @param integer $p_user_id MantisBT user id.
 * @return void
 */
function telegram_pin_code_attempts_reset( $p_user_id ) {

    plugin_config_delete( 'pin_code_attempts', (int) $p_user_id );
}

/**
 * Return the telegram user the PIN code was issued to.
 *
 * @param integer $p_pin_code PIN code entered by the user.
 * @return integer Telegram user id, 0 if the code is unknown or expired.
 */
function telegram_pin_code_telegram_user_get( $p_pin_code ) {

    $t_pin_codes_table = plugin_table( 'pin_codes' );

    telegram_registration_states_clear_expired();

    db_param_push();

    $t_query  = "SELECT telegram_user_id
			FROM $t_pin_codes_table
			WHERE pin_code=" . db_param() . ' AND timestamp>=' . db_param();
    $t_result = db_query( $t_query, array( $p_pin_code, db_now() - TELEGRAM_PIN_CODE_TTL ) );

    $t_row = db_fetch_array( $t_result );
    if( $t_row === false ) {
        return 0;
    }

    return (int) $t_row['telegram_user_id'];
}

/**
 * Return the telegram user an expired PIN code was issued to. The state row outlives
 * the code itself, so the chat is still known and a fresh code can be sent to it.
 *
 * @param integer $p_pin_code PIN code entered by the user.
 * @return integer Telegram user id, 0 if the code is unknown or still valid.
 */
function telegram_expired_pin_code_telegram_user_get( $p_pin_code ) {

    $t_pin_codes_table = plugin_table( 'pin_codes' );

    db_param_push();

    $t_query  = "SELECT telegram_user_id
			FROM $t_pin_codes_table
			WHERE pin_code=" . db_param() . ' AND timestamp<' . db_param();
    $t_result = db_query( $t_query, array( $p_pin_code, db_now() - TELEGRAM_PIN_CODE_TTL ) );

    $t_row = db_fetch_array( $t_result );
    if( $t_row === false ) {
        return 0;
    }

    return (int) $t_row['telegram_user_id'];
}

/**
 * Remember the invitation the bot has just sent, so that it can be removed from the
 * chat once the accounts are linked.
 *
 * @param integer $p_telegram_user_id Telegram user id.
 * @param integer $p_message_id       Id of the message sent to the chat.
 * @return void
 */
function telegram_registration_message_id_set( $p_telegram_user_id, $p_message_id ) {

    $t_pin_codes_table = plugin_table( 'pin_codes' );

    db_param_push();

    $t_query = "UPDATE $t_pin_codes_table
			SET message_id=" . db_param() . '
			WHERE telegram_user_id=' . db_param();
    db_query( $t_query, array( (int) $p_message_id, $p_telegram_user_id ) );
}

/**
 * Remove the invitation from the chat, leaving the registration state in place.
 * Called before a new invitation is sent, so that only one of them is on screen.
 *
 * @param integer $p_telegram_user_id Telegram user id.
 * @return void
 */
function telegram_registration_message_remove( $p_telegram_user_id ) {

    $t_state = telegram_registration_state_get( $p_telegram_user_id );

    if( $t_state === false || 0 == (int) $t_state['message_id'] ) {
        return;
    }

    # A bot may only delete its own message within 48 hours, an older one just stays
    \Longman\TelegramBot\Request::deleteMessage( array(
                              'chat_id'    => $p_telegram_user_id,
                              'message_id' => (int) $t_state['message_id'],
    ) );

    telegram_registration_message_id_set( $p_telegram_user_id, 0 );
}

/**
 * Finish the registration: the invitation is removed from the chat and the state,
 * including the PIN code, is dropped. An unused code must not survive the binding -
 * anybody who saw it would relink the chat to his own MantisBT account.
 *
 * @param integer $p_telegram_user_id Telegram user id.
 * @return void
 */
function telegram_registration_complete( $p_telegram_user_id ) {

    $t_pin_codes_table = plugin_table( 'pin_codes' );

    telegram_registration_message_remove( $p_telegram_user_id );

    db_param_push();

    $t_query = "DELETE FROM $t_pin_codes_table
			WHERE telegram_user_id=" . db_param();
    db_query( $t_query, array( $p_telegram_user_id ) );
}

/**
 * Delete the registrations nobody has finished within TELEGRAM_REGISTRATION_STATE_TTL.
 * The PIN code inside them expires much earlier, this only collects the rows.
 *
 * @return void
 */
function telegram_registration_states_clear_expired() {

    $t_pin_codes_table = plugin_table( 'pin_codes' );

    db_param_push();

    $t_query = "DELETE FROM $t_pin_codes_table
			WHERE timestamp<" . db_param();
    db_query( $t_query, array( db_now() - TELEGRAM_REGISTRATION_STATE_TTL ) );
}
