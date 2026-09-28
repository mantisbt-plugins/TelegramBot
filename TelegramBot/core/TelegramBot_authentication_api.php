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

class RequestMantis extends Longman\TelegramBot\Request {

    public static function sendMessage( array $data ) {
        telegram_session_start();

        $text = $data['text'];

        $response = array();
        do {
            //Chop off and send the first message
            $data['text'] = mb_substr( $text, 0, 4096 );
            
            try {
                $response[] = self::send( 'sendMessage', $data );
            } catch( Exception $t_error ) {
                plugin_log_event( 'ERROR! ' . telegram_token_mask( $t_error->getMessage() ) );
            }

            //Prepare the next message
            $text = mb_substr( $text, 4096 );
        } while( mb_strlen( $text, 'UTF-8' ) > 0 );

        return $response;
    }

}

/**
 * Hide the E_DEPRECATED notices raised inside api/vendor/.
 *
 * The pinned longman/telegram-bot cannot be upgraded past the PHP baseline of
 * the plugin, and on a modern PHP the core error handler would print its
 * notices inline on every page that talks to Telegram. Notices of the plugin
 * itself still reach the core handler untouched.
 *
 * @return void
 */
function telegram_vendor_deprecations_suppress() {
	static $s_installed = false;

	if( $s_installed ) {
		return;
	}
	$s_installed = true;

	$t_vendor_dir = realpath( dirname( __FILE__ ) . '/../api/vendor' );
	$t_previous   = null;

	$t_previous = set_error_handler( function( $p_type, $p_error, $p_file, $p_line ) use ( &$t_previous, $t_vendor_dir ) {
		if( ( $p_type & ( E_DEPRECATED | E_USER_DEPRECATED ) ) && strpos( $p_file, $t_vendor_dir ) === 0 ) {
			return true;
		}
		if( $t_previous === null ) {
			return false;
		}
		return call_user_func( $t_previous, $p_type, $p_error, $p_file, $p_line );
	} );
}

function telegram_session_start() {
	global $g_tg;

	telegram_vendor_deprecations_suppress();

	if( $g_tg == NULL && !is_blank( plugin_config_get( 'api_key' ) ) && !is_blank( plugin_config_get( 'bot_name' ) ) )  {
		$g_tg = new \Longman\TelegramBot\Telegram( plugin_config_get( 'api_key' ), plugin_config_get( 'bot_name' ) );

		$t_proxy_address = plugin_config_get( 'proxy_address' );

		$t_client_prop = array();

		$t_client_prop['base_uri']  = plugin_config_get( 'api_url' );
		$t_client_prop['timeout']   = plugin_config_get( 'time_out_server_response' );

		if( !is_blank( $t_proxy_address ) ) {
			$t_client_prop['proxy'] = 'socks5://' . $t_proxy_address;
		}

		\Longman\TelegramBot\Request::setClient( new \GuzzleHttp\Client( $t_client_prop ) );

		$g_tg->setDownloadPath( plugin_config_get( 'download_path' ) );
                $g_tg->useGetUpdatesWithoutDatabase();

		if( plugin_config_get( 'debug_connection_enabled' ) == ON ) {
			$t_logger = new TelegramBotFileLogger( plugin_config_get( 'debug_connection_log_path' ) );

			# The same file takes the exchange and the updates: they belong to one
			# conversation with Telegram and are read together
			Longman\TelegramBot\TelegramLog::initialize( $t_logger, $t_logger );

			# Without this the library keeps the successful requests to itself and
			# writes the failed ones only, which tells nothing about what was sent
			Longman\TelegramBot\TelegramLog::$always_log_request_and_response = TRUE;
		}
	}
}

/**
 * Install the webhook with a fresh secret token.
 *
 * The URL carries no credentials: Telegram sends the secret back in the
 * X-Telegram-Bot-Api-Secret-Token header, so it never reaches the access logs.
 * The secret is stored only after Telegram accepted it, otherwise the
 * installed webhook and the stored secret would disagree.
 *
 * @return \Longman\TelegramBot\Entities\ServerResponse Response of setWebhook.
 * @throws \Longman\TelegramBot\Exception\TelegramException
 */
function telegram_webhook_install() {
	$t_secret = bin2hex( random_bytes( 32 ) );
	$t_data   = array(
		'url'          => config_get_global( 'path' ) . plugin_page( 'hook', TRUE ),
		'secret_token' => $t_secret,
	);

	if( plugin_config_get( 'use_cert' ) == ON ) {
		# the handle must stay referenced until setWebhook(): closing it deletes the file;
		# Request turns a local path in 'certificate' into a multipart upload by itself
		$t_cert_file = tmpfile();
		fwrite( $t_cert_file, plugin_config_get( 'bot_cert' ) );

		$t_data['certificate'] = stream_get_meta_data( $t_cert_file )['uri'];
	}

	telegram_session_start();
	$t_response = Request::setWebhook( $t_data );

	if( $t_response->isOk() ) {
		plugin_config_set( 'webhook_secret_token', $t_secret );
	}

	return $t_response;
}

/**
 * Check that a webhook request comes from Telegram.
 *
 * A webhook installed before the secret token existed still carries the bot
 * token in its URL; it is accepted only until the webhook is reinstalled, since
 * from then on the secret is stored and the legacy URL stops working.
 *
 * @return boolean True if the request is authentic.
 */
function telegram_webhook_request_is_authentic() {
	$t_secret = (string)plugin_config_get( 'webhook_secret_token' );

	# hash_equals: a strict constant-time comparison, the loose one is open to type
	# juggling and leaks the position of the first wrong byte through the timing
	if( !is_blank( $t_secret ) ) {
		$t_header = isset( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ? (string)$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';
		return hash_equals( $t_secret, $t_header );
	}

	$t_api_key = (string)plugin_config_get( 'api_key' );
	$t_token   = gpc_get_string( 'token', '' );
	if( is_blank( $t_api_key ) || is_blank( $t_token ) || !hash_equals( $t_api_key, $t_token ) ) {
		return false;
	}

	plugin_log_event( 'WARNING! Webhook with the bot token in its URL, reinstall it on the plugin settings page.' );
	return true;
}

function telegram_session_send_message( $p_telegram_user_id, $p_data ) {
//    telegram_session_start();

    $p_data['chat_id'] = $p_telegram_user_id;

    $t_results_send = RequestMantis::sendMessage( $p_data );

    return $t_results_send;
}

/**
* The function of checking the authorization of a telegram user and issuing an invitation for authorization
*
* @param int $p_telegram_user_id  Telegram user id.
* @param string $p_telegram_user_lang_code Telegram user language code.
* @return bool
*/
function auth_ensure_telegram_user_authenticated( $p_telegram_user_id, $p_telegram_user_lang_code = null ) {

    plugin_log_event( 'Telegram user ' . $p_telegram_user_id . ' request language: "'.$p_telegram_user_lang_code.'"' );
    
    $t_mantis_user_id = user_get_id_by_telegram_user_id( $p_telegram_user_id );

    if( $t_mantis_user_id == 0 ) {
        lang_push( telegram_lang_map_auto( $p_telegram_user_lang_code ) );
        $t_response = user_telegram_signup( $p_telegram_user_id );
        if( !$t_response->isOk() ) {
            error_parameters( $t_response->getDescription() );
            plugin_error( 'ERROR_TG_GET_UPDATE', WARNING );
        }
        plugin_log_event( 'Authorization Error! Telegram user id#' . $p_telegram_user_id . ' is not mapped to any mantisbt user. As a response, an authorization invitation was sent.' );
        return false;
    } else if( !user_exists( $t_mantis_user_id ) || !user_is_enabled( $t_mantis_user_id ) ) {
        # user_exists() comes first: user_is_enabled() halts on a user that is gone.
        # For the same reason the name for the log is taken from user_get_name(),
        # which answers with the placeholder of a deleted user instead of halting
        lang_push( telegram_lang_map_auto( $p_telegram_user_lang_code ) );
        $t_response = user_telegram_signup( $p_telegram_user_id );
        if( !$t_response->isOk() ) {
            error_parameters( $t_response->getDescription() );
            plugin_error( 'ERROR_TG_GET_UPDATE', WARNING );
        }
        plugin_log_event( 'Authorization Error! User ' . user_get_name( $t_mantis_user_id ) . ' (id#' . $t_mantis_user_id . ') is disabled or deleted. As a response, an authorization invitation was sent.' );
        return false;
    } else {
        # The account may get disabled between the check above and the login
        if( !auth_attempt_script_login( user_get_username( $t_mantis_user_id ) ) ) {
            plugin_log_event( 'Authorization Error! Script login failed for user ' . user_get_username( $t_mantis_user_id ) . '.' );
            return false;
        }
        plugin_log_event( 'Authorization success! Server telegrams successfully logged in as user: ' . user_get_username( $t_mantis_user_id ) );

        lang_push( telegram_lang_get_default( $p_telegram_user_lang_code ) );
        return true;
    }
}

/**
 * Return the name of a telegram account as Telegram shows it: first and last name
 * followed by @username. Asked from Telegram rather than taken from the update, so
 * that every caller of user_telegram_signup() gets it the same way.
 *
 * @param integer $p_telegram_user_id Telegram user id.
 * @return string Name, empty when Telegram does not answer.
 */
function telegram_user_display_name_get( $p_telegram_user_id ) {

    $t_response = Request::getChat( array( 'chat_id' => $p_telegram_user_id ) );

    if( !$t_response->isOk() ) {
        return '';
    }

    $t_chat  = $t_response->getResult();
    $t_parts = array( trim( $t_chat->getFirstName() . ' ' . $t_chat->getLastName() ) );

    if( !is_blank( $t_chat->getUsername() ) ) {
        $t_parts[] = '@' . $t_chat->getUsername();
    }

    return trim( implode( ' ', $t_parts ) );
}

function user_telegram_signup( $p_telegram_user_id ) {

    //We correctly form the url, depending on which method of receiving updates from the telegram server is selected.
    $t_url = telegram_mantis_url_get();

    $t_registration_method = (int) plugin_config_get( 'registration_method' );

    # The state row is needed in every method: it holds the id of the invitation
    $t_pin_code = telegram_pin_code_get( $p_telegram_user_id );

    # Only one invitation stays in the chat, the previous one is of no use anymore
    telegram_registration_message_remove( $p_telegram_user_id );

    $data_signup = [
                              'chat_id' => $p_telegram_user_id,
    ];

    # The link binds the account with one tap, but only works when MantisBT is reachable
    # from the phone; the PIN code is typed by the user in his account preferences instead
    if( $t_registration_method != TELEGRAM_REGISTRATION_PIN ) {
        # The telegram user id is public, so the link also carries a one-time secret:
        # without it anybody could send a MantisBT user a link with his own id and get
        # his chat bound to that account by one careless click
        $t_token = telegram_registration_link_token_issue( $p_telegram_user_id, telegram_user_display_name_get( $p_telegram_user_id ) );

        $t_signup_keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard( array() );
        $t_signup_keyboard->addRow( [
                              'text' => plugin_lang_get( 'registration_button_text' ),
                              'url'  => $t_url . plugin_page( 'registred', TRUE ) . '&telegram_user_id=' . $p_telegram_user_id . '&token=' . $t_token
        ] );

        $data_signup['reply_markup'] = $t_signup_keyboard;
    }

    if( $t_registration_method == TELEGRAM_REGISTRATION_LINK ) {
        $data_signup['text'] = sprintf(
                                                            plugin_lang_get( 'registration_message_text' ),
                                                            config_get( 'window_title' ),
                                                            $t_url
                                      );
    } else {
        $t_lang_key = $t_registration_method == TELEGRAM_REGISTRATION_PIN
                              ? 'registration_message_pin_text'
                              : 'registration_message_both_text';

        $data_signup['text'] = sprintf(
                                                            plugin_lang_get( $t_lang_key ),
                                                            config_get( 'window_title' ),
                                                            $t_url . plugin_page( 'account_telegram_register_page', TRUE ),
                                                            $t_pin_code
                                      );
    }

    $t_response = Request::sendMessage( $data_signup );

    if( $t_response->isOk() ) {
        telegram_registration_message_id_set( $p_telegram_user_id, $t_response->getResult()->getMessageId() );
    }

    return $t_response;

}
