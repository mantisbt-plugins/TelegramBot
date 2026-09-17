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

# Ways to link a Telegram account to a MantisBT one, see the 'registration_method' config option.
# The link carries the telegram user id to the registred page, the PIN code goes the other way
# round - the bot shows it in the chat and the user types it in his account preferences, which
# is the only method that works when MantisBT is not reachable from the user's phone.
define( 'TELEGRAM_REGISTRATION_LINK', 0 );
define( 'TELEGRAM_REGISTRATION_PIN', 1 );
define( 'TELEGRAM_REGISTRATION_BOTH', 2 );

# Whether the iCalendar file of a calendar event goes along with the notifications about
# the event, see the 'calendar_ics_mode' config option: never, for everybody unless the
# user turns it off in his preferences, or for nobody unless the user turns it on there.
define( 'TELEGRAM_ICS_OFF', 0 );
define( 'TELEGRAM_ICS_ON', 1 );
define( 'TELEGRAM_ICS_OPT_IN', 2 );

# Where the unlink button was pressed: the account page of the user himself or the
# plugin pages, where it is an administrative action even for one's own binding
define( 'TELEGRAM_UNLINK_SOURCE_ACCOUNT', 'account' );
define( 'TELEGRAM_UNLINK_SOURCE_ADMIN', 'admin' );

# Seconds a PIN code stays valid
define( 'TELEGRAM_PIN_CODE_TTL', 15 * 60 );

# Seconds the registration state is kept: the PIN code inside it expires much earlier,
# but the id of the invitation is still needed to remove that message from the chat when
# the user follows the link later. Telegram lets a bot delete its own message for 48 hours.
define( 'TELEGRAM_REGISTRATION_STATE_TTL', 48 * 60 * 60 );

class TelegramBotPlugin extends MantisPlugin {

    function register() {

        $this->name        = 'TelegramBot';
        $this->description = plugin_lang_get( 'description' );

        $this->version  = '2.0.0-dev';
        $this->requires = array(
                                  'MantisCore' => '2.26.0',
        );

        $this->author  = 'Grigoriy Ermolaev';
        $this->contact = 'igflocal@gmail.com';
        $this->url     = 'http://github.com/mantisbt-plugins/TelegramBot';
        $this->page    = 'config_page';
    }

    function schema() {
        /**
         * Standard table creation options
         * Array key is the ADOdb datadict driver's name
         */
        $t_table_options = array(
                                  'mysql' => 'DEFAULT CHARSET=utf8',
                                  'pgsql' => 'WITHOUT OIDS',
        );

        # Special handling for Oracle (oci8):
        # - Field cannot be null with oci because empty string equals NULL
        # - Oci uses a different date literal syntax
        # - Default BLOBs to empty_blob() function
        if( db_is_oracle() ) {
            $t_notnull      = '';
            $t_blob_default = 'DEFAULT " empty_blob() "';
        } else {
            $t_notnull      = 'NOTNULL';
            $t_blob_default = '';
        }

        return array(
                                  // version 0.0.1 (schema 0)
                                  array( 'CreateTableSQL', array( plugin_table( 'user_relationship' ), "
                                      mantis_user_id    I   $t_notnull  PRIMARY,
                                      telegram_user_id  I   $t_notnull",
                                                                                      $t_table_options
                                                            ) ),
                                  // version 1.3.0 (schema 1)
                                  array( 'CreateTableSQL', array( plugin_table( 'message_relationship' ), "
                                      id                I   $t_notnull  AUTOINCREMENT   PRIMARY,                                     
                                      bug_id            I   UNSIGNED    $t_notnull,
                                      chat_id           N   UNSIGNED    $t_notnull,
                                      msg_id            I   UNSIGNED    $t_notnull",
                                                                                      $t_table_options
                                                            ) ),
                                  // version 1.3.0 (schema 2)                          
                                  array( 'CreateIndexSQL', array( 'idx_msgid_chatid', plugin_table( 'message_relationship' ), array( 'msg_id', 'chat_id' ) ) ),
                                  // version 1.3.0 (schema 3)
                                  array( 'CreateIndexSQL', array( 'idx_chatid', plugin_table( 'message_relationship' ), 'chat_id' ) ),
                                  // version 1.5.1 (schema 4)
                                  // AlterColumnSQL, not ChangeTableSQL: since ADOdb 5.22.8 (MantisBT 2.27.3)
                                  // ChangeTableSQL returns an empty array for a string field definition, which
                                  // MantisBT reports as ERROR_PLUGIN_UPGRADE_FAILED. Up to ADOdb 5.22.7 the
                                  // string definition was passed to alterColumnSql() anyway, so the resulting
                                  // schema is the same as on the installations upgraded before.
                                  array( 'AlterColumnSQL', array( plugin_table( "user_relationship" ), "
                                        telegram_user_id  N   $t_notnull
                                " ) ),
                                  // version 2.0.0 (schema 5)
                                  // One PIN code per telegram user (hence the primary key), looked up
                                  // by code when the user enters it in his account preferences.
                                  // N without a size is DECIMAL(10,0) - too narrow for a telegram user
                                  // id, which the API defines as fitting into 52 bits
                                  array( 'CreateTableSQL', array( plugin_table( 'pin_codes' ), "
                                      telegram_user_id  N(16)   UNSIGNED    $t_notnull  PRIMARY,
                                      pin_code          I   UNSIGNED    $t_notnull,
                                      timestamp         I   UNSIGNED    $t_notnull DEFAULT '1'",
                                                                                      $t_table_options
                                                            ) ),
                                  // version 2.0.0 (schema 6)
                                  // Unique: a code must identify exactly one telegram user
                                  array( 'CreateIndexSQL', array( 'idx_pin_code', plugin_table( 'pin_codes' ), 'pin_code', array( 'UNIQUE' ) ) ),
                                  // version 2.0.0 (schema 7)
                                  // Schema 4 widened the column from I to N, which stops at DECIMAL(10,0):
                                  // enough for the ids issued so far, one digit short of the 52 bits the
                                  // Telegram API allows
                                  array( 'AlterColumnSQL', array( plugin_table( "user_relationship" ), "
                                        telegram_user_id  N(16)   $t_notnull
                                " ) ),
                                  // version 2.0.0 (schema 8)
                                  array( 'AlterColumnSQL', array( plugin_table( "message_relationship" ), "
                                        chat_id  N(16)   UNSIGNED    $t_notnull
                                " ) ),
                                  // version 2.0.0 (schema 9)
                                  // Id of the invitation the bot sent to an unregistred user, so that
                                  // the message can be removed from the chat once the accounts are linked
                                  array( 'AddColumnSQL', array( plugin_table( 'pin_codes' ), "
                                        message_id  I   UNSIGNED    $t_notnull DEFAULT '0'
                                " ) ),
        );
    }

    # Latched decision of upgrade(): the schema config grows as the steps run,
    # so whether this request is an install or an upgrade is decided once,
    # on the first call
    private $backup_confirmed = null;

    # Called by plugin_upgrade() before every schema step. A schema upgrade is
    # one-way: rolling the plugin files back does not roll the tables back, so
    # before the first step runs the administrator must confirm that a database
    # backup has been made. Modeled on helper_ensure_confirmed(): the form
    # re-posts the same upgrade request with _confirmed=1 (the form security
    # token is only purged after plugin_upgrade() finishes), so on confirm this
    # method is entered again and falls through. The checkbox is enforced
    # server-side; the CSS gate on the button is a courtesy (the CSP forbids
    # inline JS but allows inline styles). A fresh install (schema -1) has no
    # data to lose and CLI runs have no one to ask.
    function upgrade( $p_schema ) {
        if( $this->backup_confirmed === null ) {
            $this->backup_confirmed = php_sapi_name() == 'cli'
                    || (int)plugin_config_get( 'schema', -1 ) < 0
                    || ( gpc_get_bool( '_confirmed' ) && gpc_get_bool( 'backup_confirmed' ) );
        }
        if( $this->backup_confirmed ) {
            return true;
        }

        layout_page_header();
        layout_page_begin();

        echo '<div class="col-md-12 col-xs-12">';
        echo '<div class="space-10"></div>';
        echo '<div class="alert alert-warning center">';
        echo '<p class="bigger-110"><strong>' . plugin_lang_get( 'upgrade_backup_warning' ) . '</strong></p>';
        echo '<p>' . plugin_lang_get( 'upgrade_backup_explanation' ) . '</p>';
        echo '<div class="space-10"></div>';

        echo '<style>'
                . '#backup_confirmed:not(:checked) ~ input[type="submit"] { pointer-events: none; opacity: .45; }'
                . '</style>';

        echo '<form method="post" class="center" action="">' . "\n";
        # CSRF protection not required here - user needs to confirm action
        # before the form is accepted.
        $t_post = $_POST;
        $t_get  = $_GET;
        unset( $t_post['_confirmed'], $t_post['backup_confirmed'],
                $t_get['_confirmed'], $t_get['backup_confirmed'] );
        print_hidden_inputs( $t_post );
        print_hidden_inputs( $t_get );

        echo '<input type="hidden" name="_confirmed" value="1" />', "\n";
        echo '<input type="checkbox" id="backup_confirmed" name="backup_confirmed" value="1" /> ';
        echo '<label for="backup_confirmed" class="bold">' . plugin_lang_get( 'upgrade_backup_checkbox' ) . '</label>';
        echo '<div class="space-10"></div>';
        echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'upgrade_confirm_button' ) . '" />';
        echo "\n</form>\n";

        echo '<div class="space-10"></div>';
        echo '</div></div>';

        layout_page_end();
        exit;
    }

    function init() {
        require_once 'api/vendor/autoload.php';
        require_once 'core/TelegramBot_bug_api.php';
        require_once 'core/TelegramBot_authentication_api.php';
        require_once 'core/TelegramBot_user_api.php';
        require_once 'core/TelegramBot_helper_api.php';
        require_once 'core/TelegramBot_keyboard_api.php';
        require_once 'core/TelegramBot_fields_api.php';
        require_once 'core/TelegramBot_message_api.php';
        require_once 'core/TelegramBot_message_format_api.php';
	require_once 'core/TelegramBot_menu_api.php';
        require_once 'core/TelegramBot_InlineKeyboardCalendar_api.php';
//        require_once 'core/cfdefs/TelegramBot_cfdef_standard.php';
        require_once 'core/classes/TelegrambotActions.class.php';
        require_once 'core/classes/TelegramBotFileLogger.class.php';
        require_once 'core/TelegramBot_custom_field_api.php';
        require_once 'core/TelegramBot_broadcast_api.php';
        require_once 'core/TelegramBot_calendar_api.php';
        
        global $g_skip_sending_bugnote, $g_telegram_callback_alert;
        $g_skip_sending_bugnote    = FALSE;
        $g_telegram_callback_alert = array();
        
        #The session is built on every page load, a broken connection setting
        #(api_url, proxy_address) must not take down the whole MantisBT UI
        try {
            telegram_session_start();
        } catch( Exception $t_error ) {
            plugin_log_event( 'ERROR! Telegram session start failed: ' . $t_error->getMessage() );
        }
    }

    function config() {
        return array(
                                  'api_key'                                     => '',
                                  'bot_name'                                    => '',
                                  'use_cert'                                    => OFF,
                                  'bot_cert'                                    => '',
                                  'reinstall_webhook'                           => ON,
                                  # how a telegram account is linked to a MantisBT one:
                                  # TELEGRAM_REGISTRATION_LINK / _PIN / _BOTH
                                  'registration_method'                         => TELEGRAM_REGISTRATION_LINK,
                                  # whether the chat is told about an unlink done by an administrator
                                  'admin_unlink_notify'                         => ON,
                                  'bot_father_url'                              => 'https://t.me/BotFather',
                                  'telegram_url'                                => 'tg://resolve?domain=',
                                  'download_path'                               => '/tmp/',
				  'proxy_address'                               => '',
				  'time_out_server_response'			=> 30,
				  'debug_connection_log_path'			=> '/tmp/TelegramBot_debug.log',
				  'debug_connection_enabled'			=> OFF,
				  # long polling: seconds Telegram holds the connection while there are no updates
				  'get_updates_timeout'				=> 25,
				  # long polling: seconds a single run of telegram_get_updates.php works (0 - poll once and exit)
				  'get_updates_run_time'			=> 55,
				  # long polling: timestamp of the last telegram_get_updates.php start, set by the script itself
				  'get_updates_last_run'			=> 0,
				  # long polling: id of the next expected update, kept by the script between runs
				  'get_updates_offset'				=> 0,
                                  'bug_data_draft'                              => '',
                                  'bug_data_draft_chat_id'                      => '',
                                  'bug_data_draft_message_id'                   => '',
                                  'bug_data_draft_current_field_to_save'        => '',
                                  # master switch of the Calendar integration, folded into
                                  # telegram_calendar_available(): off, the plugin behaves as if
                                  # Calendar were not installed
                                  'calendar_integration_enabled'                => OFF,
                                  # whether the .ics file goes along with the event notifications:
                                  # TELEGRAM_ICS_OFF / _ON / _OPT_IN. The personal choice is the
                                  # per-user 'calendar_ics_attach' option, whose default is derived
                                  # from the mode, see telegram_calendar_ics_wanted()
                                  'calendar_ics_mode'                           => TELEGRAM_ICS_OFF,
                                  # who is told about a calendar event, per action: the author of
                                  # the event, its members, and the user who acts - the counterpart
                                  # of notify_flags below for the events, in the shape of the matrix
                                  # of the Calendar plugin itself. Overridden per project on the
                                  # notifications page, see telegram_calendar_notify_flags()
                                  'calendar_notify_flags'                       => array(
                                                            'created' => array( 'author' => ON, 'members' => ON, 'actor' => OFF ),
                                                            'updated' => array( 'author' => ON, 'members' => ON, 'actor' => OFF ),
                                                            'deleted' => array( 'author' => ON, 'members' => ON, 'actor' => OFF ),
                                  ),
                                  # whether the reminders of Calendar are repeated in Telegram at
                                  # all; the reminders have no matrix row, their recipients are
                                  # chosen by Calendar, so this is the only global switch of them
                                  'calendar_reminders_enabled'                  => ON,
                                  # per-user switches of the calendar event notifications, the
                                  # counterpart of telegram_message_on_* below
                                  'telegram_message_on_event_created'           => ON,
                                  'telegram_message_on_event_updated'           => ON,
                                  'telegram_message_on_event_deleted'           => ON,
                                  'telegram_message_on_event_reminder'          => ON,
                                  # per-user state of the calendar event wizard, see TelegramBot_calendar_api.php
                                  'event_draft'                                 => '',
                                  'event_draft_chat_id'                         => '',
                                  'event_draft_message_id'                      => '',
                                  'event_draft_current_field'                   => '',
                                  # per-user "count:window_start" of wrong PIN code guesses
                                  'pin_code_attempts'                           => '',
                                  # wrong PIN code guesses allowed within one lockout window:
                                  # a 4-digit code is only a secret while the guesses are counted
                                  'pin_code_attempts_max'                       => 5,
                                  # minutes the lockout window lasts, counted from the first wrong guess
                                  'pin_code_attempts_window'                    => 15,
                                  'cli_g_path'                                  => '',
                                  'broadcast_enabled'                           => OFF,
                                  'broadcast_send_threshold'                    => ADMINISTRATOR,
                                  # per-user broadcast permissions: array( user_id => array( project_id, ... ) )
                                  'broadcast_grants'                            => array(),
                                  'api_url'                                     => 'https://api.telegram.org',
                                  /**
                                   * The following two config options allow you to control who should get email
                                   * notifications on different actions/statuses.  The first option
                                   * (default_notify_flags) sets the default values for different user
                                   * categories.  The user categories are:
                                   *
                                   *      'reporter': the reporter of the bug
                                   *       'handler': the handler of the bug
                                   *       'monitor': users who are monitoring a bug
                                   *      'bugnotes': users who have added a bugnote to the bug
                                   *      'category': category owners
                                   *      'explicit': users who are explicitly specified by the code based on the
                                   *                  action (e.g. user added to monitor list).
                                   * 'threshold_max': all users with access <= max
                                   * 'threshold_min': ..and with access >= min
                                   *
                                   * The second config option (notify_flags) sets overrides for specific
                                   * actions/statuses. If a user category is not listed for an action, the
                                   * default from the config option above is used.  The possible actions are:
                                   *
                                   *             'new': a new bug has been added
                                   *           'owner': a bug has been assigned to a new owner
                                   *        'reopened': a bug has been reopened
                                   *         'deleted': a bug has been deleted
                                   *         'updated': a bug has been updated
                                   *         'bugnote': a bugnote has been added to a bug
                                   *         'sponsor': sponsorship has changed on this bug
                                   *        'relation': a relationship has changed on this bug
                                   *         'monitor': an issue is monitored.
                                   *        '<status>': eg: 'resolved', 'closed', 'feedback', 'acknowledged', etc.
                                   *                     this list corresponds to $g_status_enum_string
                                   *
                                   * If you wanted to have all developers get notified of new bugs you might add
                                   * the following lines to your config file:
                                   *
                                   * $g_notify_flags['new']['threshold_min'] = DEVELOPER;
                                   * $g_notify_flags['new']['threshold_max'] = DEVELOPER;
                                   *
                                   * You might want to do something similar so all managers are notified when a
                                   * bug is closed.  If you did not want reporters to be notified when a bug is
                                   * closed (only when it is resolved) you would use:
                                   *
                                   * $g_notify_flags['closed']['reporter'] = OFF;
                                   *
                                   * @global array $g_default_notify_flags
                                   */
                                  'default_notify_flags'                      => array(
                                                            'reporter'      => ON,
                                                            'handler'       => ON,
                                                            'monitor'       => ON,
                                                            'bugnotes'      => ON,
                                                            'category'      => ON,
                                                            'explicit'      => ON,
                                                            'threshold_min' => NOBODY,
                                                            'threshold_max' => NOBODY
                                  ),
                                  /**
                                   * We don't need to send these notifications on new bugs
                                   * (see above for info on this config option)
                                   * @todo (though I'm not sure they need to be turned off anymore
                                   *      - there just won't be anyone in those categories)
                                   *      I guess it serves as an example and a placeholder for this
                                   *      config option
                                   * @see $g_default_notify_flags
                                   * @global array $g_notify_flags
                                   */
                                  'notify_flags'                              => array(
                                                            'new'     => array(
                                                                                      'bugnotes' => OFF,
                                                                                      'monitor'  => OFF
                                                            ),
                                                            'monitor' => array(
                                                                                      'reporter'      => OFF,
                                                                                      'handler'       => OFF,
                                                                                      'monitor'       => OFF,
                                                                                      'bugnotes'      => OFF,
                                                                                      'explicit'      => ON,
                                                                                      'threshold_min' => NOBODY,
                                                                                      'threshold_max' => NOBODY
                                                            )
                                  ),
                                  /**
                                   * Whether user's should receive emails for their own actions
                                   * @global integer $g_email_receive_own
                                   */
                                  'telegram_message_receive_own'              => OFF,
                                  //
                                  'telegram_message_on_new'                   => ON,
                                  'telegram_message_on_assigned'              => ON,
                                  'telegram_message_on_feedback'              => ON,
                                  'telegram_message_on_resolved'              => ON,
                                  'telegram_message_on_closed'                => ON,
                                  'telegram_message_on_reopened'              => ON,
                                  'telegram_message_on_bugnote'               => ON,
                                  'telegram_message_on_status'                => OFF,
                                  'telegram_message_on_priority'              => OFF,
                                  //
                                  'telegram_message_on_priority_min_severity' => 0,
                                  'telegram_message_on_status_min_severity'   => 0,
                                  'telegram_message_on_bugnote_min_severity'  => 0,
                                  'telegram_message_on_reopened_min_severity' => 0,
                                  'telegram_message_on_closed_min_severity'   => 0,
                                  'telegram_message_on_resolved_min_severity' => 0,
                                  'telegram_message_on_feedback_min_severity' => 0,
                                  'telegram_message_on_assigned_min_severity' => 0,
                                  'telegram_message_on_new_min_severity'      => 0,
                                  //
                                  'telegram_message_bugnote_limit'            => 0,
                                  /**
                                   * Allow telegram message notification.
                                   * Set to ON to enable telegram message notifications, OFF to disable them. Note that
                                   * disabling telegram message notifications has no effect on telegram message generated as part
                                   * of the user signup process. When set to OFF, the password reset feature
                                   * is disabled. Additionally, notifications of administrators updating
                                   * accounts are not sent to users.
                                   * @global integer $g_enable_email_notification
                                   */
                                  'enable_telegram_message_notification'      => ON,
                                  //
                                  'telegram_message_separator1'               => str_pad( '', 27, '=' ),
                                  'telegram_message_separator2'               => str_pad( '', 55, '-' ),
                                  'telegram_message_padding_length'           => 13,
                                  /**
                                   * When enabled, the email notifications will send the full issue with
                                   * a hint about the change type at the top, rather than using dedicated
                                   * notifications that are focused on what changed.  This change can be
                                   * overridden in the database per user.
                                   *
                                   * @global integer $g_email_notifications_verbose
                                   */
                                  'telegram_message_notifications_verbose'    => OFF,
                                  'telegram_message_included_all_bugnote_is'  => OFF,
        );
    }

    public function hooks() {
        $t_hooks = array(
                                  'EVENT_REPORT_BUG'      => 'telegram_message_bug_added',
                                  'EVENT_BUGNOTE_ADD'     => 'telegram_message_bugnote_add',
                                  'EVENT_UPDATE_BUG_DATA' => 'telegram_message_skip_sending',
                                  'EVENT_UPDATE_BUG'      => 'telegram_message_update_bug',
                                  'EVENT_MENU_ACCOUNT'    => 'telegram_account_page_menu',
                                  'EVENT_MANAGE_USER_DELETE' => 'telegram_user_deleted',
                                  'EVENT_MENU_MAIN_FRONT' => 'menu_main_front',
                                  //TODO: Delete realatationship
                                  //'EVENT_BUG_DELETED' => 'delete_realatationship_tgmessage',
        );

        # The EVENT_CALENDAR_EVENT_* events belong to the Calendar plugin, and
        # hooking an event nobody has declared raises a warning. The order the
        # plugins are initialized in is not defined, so Calendar may still be
        # waiting for its turn while this runs and its events may not be declared
        # yet; the plugins are all registered before any of them is initialized,
        # though, so the presence of Calendar itself is a reliable test.
        # The events are declared here as well for the case this plugin comes
        # first: event_declare() keeps the declaration made first and the type
        # below is the one Calendar declares, so the declarations cannot disagree.
        # Without Calendar nothing ever signals the events and the callbacks
        # simply never run, which is why no dependency on Calendar is needed.
        if( plugin_is_registered( 'Calendar' ) ) {
            event_declare( 'EVENT_CALENDAR_EVENT_CREATED', EVENT_TYPE_EXECUTE );
            event_declare( 'EVENT_CALENDAR_EVENT_UPDATED', EVENT_TYPE_EXECUTE );
            event_declare( 'EVENT_CALENDAR_EVENT_DELETED', EVENT_TYPE_EXECUTE );
            event_declare( 'EVENT_CALENDAR_EVENT_REMINDER', EVENT_TYPE_EXECUTE );

            $t_hooks['EVENT_CALENDAR_EVENT_CREATED']  = 'telegram_calendar_event_created';
            $t_hooks['EVENT_CALENDAR_EVENT_UPDATED']  = 'telegram_calendar_event_updated';
            $t_hooks['EVENT_CALENDAR_EVENT_DELETED']  = 'telegram_calendar_event_deleted';
            $t_hooks['EVENT_CALENDAR_EVENT_REMINDER'] = 'telegram_calendar_event_reminder';
        }

        return $t_hooks;
    }
    
    public function errors() {
        return array(
                                  'BAD_REQUEST'                 => plugin_lang_get( 'BAD_REQUEST' ),
                                  'ERROR_CERT_FILE_NOT_FOUND'   => plugin_lang_get( 'ERROR_CERT_FILE_NOT_FOUND' ),
                                  'ERROR_TG_SESSION_NOT_INITIALIZED'    => plugin_lang_get('ERROR_TG_SESSION_NOT_INITIALIZED'),
                                  'ERROR_TG_GET_UPDATE'                 => plugin_lang_get('ERROR_TG_GET_UPDATE'),
                                  'ERROR_TG_PIN_CODE_INVALID'           => plugin_lang_get('ERROR_TG_PIN_CODE_INVALID'),
                                  'ERROR_TG_PIN_CODE_ATTEMPTS'          => plugin_lang_get('ERROR_TG_PIN_CODE_ATTEMPTS'),
                                  'ERROR_TG_PIN_CODE_EXPIRED'           => plugin_lang_get('ERROR_TG_PIN_CODE_EXPIRED'),
                                  'ERROR_TG_PIN_CODE_GENERATE'          => plugin_lang_get('ERROR_TG_PIN_CODE_GENERATE'),
                                  'ERROR_TG_USER_ALREADY_ASSOCIATED'    => plugin_lang_get('ERROR_TG_USER_ALREADY_ASSOCIATED'),
        );
    }

    /**
     * Notify the circle of a calendar event about its creation.
     *
     * EVENT_CALENDAR_EVENT_CREATED is declared as EVENT_TYPE_EXECUTE and signalled
     * with a single parameter, so the callback receives the name of the event and
     * the identifier of the calendar event created.
     *
     * @param string  $p_type_event Name of the signalled event.
     * @param integer $p_event_id   Identifier of the created calendar event.
     * @return void
     */
    function telegram_calendar_event_created( $p_type_event, $p_event_id ) {
        plugin_log_event( sprintf( 'Calendar event #%d created', $p_event_id ) );
        telegram_calendar_message_event( $p_event_id, 'created' );
    }

    /**
     * Notify the circle of a calendar event about a change of it.
     *
     * EVENT_CALENDAR_EVENT_UPDATED is declared as EVENT_TYPE_EXECUTE and signalled
     * with a single parameter, so the callback receives the name of the event and
     * the identifier of the calendar event changed.
     *
     * @param string  $p_type_event Name of the signalled event.
     * @param integer $p_event_id   Identifier of the changed calendar event.
     * @return void
     */
    function telegram_calendar_event_updated( $p_type_event, $p_event_id ) {
        plugin_log_event( sprintf( 'Calendar event #%d updated', $p_event_id ) );
        telegram_calendar_message_event( $p_event_id, 'updated' );
    }

    /**
     * Notify the circle of a calendar event about its deletion.
     *
     * EVENT_CALENDAR_EVENT_DELETED is signalled before the rows of the event are
     * removed and only when the whole event goes, so the callback still finds
     * the event and its members.
     *
     * @param string  $p_type_event Name of the signalled event.
     * @param integer $p_event_id   Identifier of the calendar event being deleted.
     * @return void
     */
    function telegram_calendar_event_deleted( $p_type_event, $p_event_id ) {
        plugin_log_event( sprintf( 'Calendar event #%d deleted', $p_event_id ) );
        telegram_calendar_message_event( $p_event_id, 'deleted' );
    }

    /**
     * Remind one user about an occurrence of a calendar event coming up.
     *
     * EVENT_CALENDAR_EVENT_REMINDER is signalled by the reminder dispatcher of
     * Calendar once per due reminder, that is once per occurrence, recipient and
     * offset, and only for the users who may hear about the event and have not
     * opted out of the reminders, so nothing is filtered here.
     *
     * @param string  $p_type_event Name of the signalled event.
     * @param integer $p_event_id   Identifier of the calendar event.
     * @param integer $p_occurrence Timestamp the occurrence starts at.
     * @param integer $p_user_id    Recipient of the reminder.
     * @param integer $p_offset     Seconds before the start the reminder was asked for.
     * @return void
     */
    function telegram_calendar_event_reminder( $p_type_event, $p_event_id, $p_occurrence, $p_user_id, $p_offset ) {
        telegram_calendar_message_reminder( $p_event_id, $p_occurrence, $p_user_id, $p_offset );
    }

    function telegram_message_bug_added( $p_type_event, $p_issue, $p_issue_id ) {
        plugin_log_event( sprintf( 'Issue #%d reported', $p_issue_id ) );
        telegram_message_generic( $p_issue_id, 'new', 'telegram_message_notification_title_for_action_bug_submitted' );
    }

    function telegram_message_bugnote_add( $p_type_event, $p_bug_id, $p_bugnote_id, $files ) {
        global $g_skip_sending_bugnote;

        if( $g_skip_sending_bugnote == TRUE ) {
            $g_skip_sending_bugnote = FALSE;
            return;
        }

        $t_bugnote_text = bugnote_get_text( $p_bugnote_id );

        # Process the mentions that have access to the issue note
        $t_mentioned_user_ids          = mention_get_users( $t_bugnote_text );
        $t_filtered_mentioned_user_ids = access_has_bugnote_level_filter(
                config_get( 'view_bug_threshold' ), $p_bugnote_id, $t_mentioned_user_ids );

        $t_removed_mentions_user_ids = array_diff( $t_mentioned_user_ids, $t_filtered_mentioned_user_ids );

        $t_user_ids_that_got_mention_notifications = telegram_message_user_mention( $p_bug_id, $t_filtered_mentioned_user_ids, $t_bugnote_text, $t_removed_mentions_user_ids );

        telegram_message_bugnote_add_generic( $p_bugnote_id, array(), $t_user_ids_that_got_mention_notifications );
    }

    function telegram_message_skip_sending( $p_type_event, $p_updated_bug, $p_existing_bug ) {
        global $g_skip_sending_bugnote;
        $g_skip_sending_bugnote = TRUE;

        return $p_updated_bug;
    }

    function telegram_message_update_bug( $p_type_event, $p_existing_bug, $p_updated_bug ) {

        # Determine whether the new status will reopen, resolve or close the issue.
        # Note that multiple resolved or closed states can exist and thus we need to
        # look at a range of statuses when performing this check.
        $t_resolved_status = config_get( 'bug_resolved_status_threshold' );
        $t_closed_status   = config_get( 'bug_closed_status_threshold' );
        $t_resolve_issue   = false;
        $t_close_issue     = false;
        $t_reopen_issue    = false;
        if( $p_existing_bug->status < $t_resolved_status &&
                $p_updated_bug->status >= $t_resolved_status &&
                $p_updated_bug->status < $t_closed_status
        ) {
            $t_resolve_issue = true;
        } else if( $p_existing_bug->status < $t_closed_status &&
                $p_updated_bug->status >= $t_closed_status
        ) {
            $t_close_issue = true;
        } else if( $p_existing_bug->status >= $t_resolved_status &&
                $p_updated_bug->status <= config_get( 'bug_reopen_status' )
        ) {
            $t_reopen_issue = true;
        }

        # Send a notification of changes via email.
        if( $t_resolve_issue ) {
            plugin_log_event( sprintf( 'Issue #%d resolved', $p_existing_bug->id ) );
            telegram_message_generic( $p_existing_bug->id, 'resolved', 'telegram_message_notification_title_for_status_bug_resolved' );
            telegram_message_relationship_child_resolved( $p_existing_bug->id );
        } else if( $t_close_issue ) {
            plugin_log_event( sprintf( 'Issue #%d closed', $p_existing_bug->id ) );
            telegram_message_generic( $p_existing_bug->id, 'closed', 'telegram_message_notification_title_for_status_bug_closed' );
            telegram_message_relationship_child_closed( $p_existing_bug->id );
        } else if( $t_reopen_issue ) {
            plugin_log_event( sprintf( 'Issue #%d reopened', $p_existing_bug->id ) );
            telegram_message_generic( $p_existing_bug->id, 'reopened', 'telegram_message_notification_title_for_action_bug_reopened' );
        } else if( $p_existing_bug->handler_id != $p_updated_bug->handler_id ) {
            telegram_message_owner_changed( $p_existing_bug->id, $p_existing_bug->handler_id, $p_updated_bug->handler_id );
        } else if( $p_existing_bug->status != $p_updated_bug->status ) {
            $t_new_status_label = MantisEnum::getLabel( config_get( 'status_enum_string' ), $p_updated_bug->status );
            $t_new_status_label = str_replace( ' ', '_', $t_new_status_label );
            plugin_log_event( sprintf( 'Issue #%d status changed', $p_existing_bug->id ) );
            telegram_message_generic( $p_existing_bug->id, $t_new_status_label, 'telegram_message_notification_title_for_status_bug_' . $t_new_status_label );
        } else {
            plugin_log_event( sprintf( 'Issue #%d updated', $p_existing_bug->id ) );
            telegram_message_generic( $p_existing_bug->id, 'updated', 'telegram_message_notification_title_for_action_bug_updated' );
        }
    }

    /**
     * The core removes profiles, preferences and access levels of a deleted user, but
     * neither the binding of this plugin nor its per user configuration options - the
     * chat would stay in the list of connected users with no account behind it.
     *
     * @param string  $p_type_event Event name.
     * @param integer $p_user_id    Id of the user being deleted.
     * @return void
     */
    function telegram_user_deleted( $p_type_event, $p_user_id ) {
        # No message to the chat: the account is gone, so an invitation to subscribe
        # again would lead nowhere, and deleting a user must not wait for Telegram
        telegram_bot_user_unlink( $p_user_id, /* notify */ FALSE );
        telegram_user_config_delete_all( $p_user_id );
    }

    function telegram_account_page_menu( $p_type_event ) {

        # One <li> per returned link, the core marks the active one by the page name
        $t_items = array(
                                  '<a href=' . plugin_page( 'account_telegram_prefs_page' ) . '>' . plugin_lang_get( 'account_telegram_prefs_page_header' ) . '</a>',
        );

        # Entering a PIN code only makes sense while the account is not linked yet
        if( TELEGRAM_REGISTRATION_LINK != (int)plugin_config_get( 'registration_method' )
                && !user_is_associated_with_telegram( auth_get_current_user_id() )
        ) {
            $t_items[] = '<a href=' . plugin_page( 'account_telegram_register_page' ) . '>' . plugin_lang_get( 'account_telegram_register_page_header' ) . '</a>';
        }

        return $t_items;
    }
    
    function menu_main_front() {
        if( !auth_is_user_authenticated() || !telegram_broadcast_can_send( auth_get_current_user_id() ) ) {
            return array();
        }

        return array(
                                  array(
                                                            'url'          => plugin_page( 'broadcast_message_page' ),
                                                            'title'        => plugin_lang_get( 'menu_main_broadcast_message_page' ),
                                                            # visibility is already decided by telegram_broadcast_can_send()
                                                            'access_level' => ANYBODY,
                                                            'icon'         => 'fa-brands fa-telegram'
                                  ),
        );
    }
}
