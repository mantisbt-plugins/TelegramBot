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

class TelegramBotPlugin extends MantisPlugin {

    function register() {

        $this->name        = 'TelegramBot';
        $this->description = plugin_lang_get( 'description' );

        $this->version  = '1.5.0';
        $this->requires = array(
                                  'MantisCore' => '2.14.0',
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
                                  // version 0.0.1
                                  array( 'CreateTableSQL', array( plugin_table( 'user_relationship' ), "
                                      mantis_user_id    I   $t_notnull  PRIMARY,
                                      telegram_user_id  I   $t_notnull",
                                                                                      $t_table_options
                                                            ) ),
                                  // version 1.3.0
                                  array( 'CreateTableSQL', array( plugin_table( 'message_relationship' ), "
                                      id                I   $t_notnull  AUTOINCREMENT   PRIMARY,                                     
                                      bug_id            I   UNSIGNED    $t_notnull,
                                      chat_id           N   UNSIGNED    $t_notnull,
                                      msg_id            I   UNSIGNED    $t_notnull",
                                                                                      $t_table_options
                                                            ) ),
                                  array( 'CreateIndexSQL', array( 'idx_msgid_chatid', plugin_table( 'message_relationship' ), array( 'msg_id', 'chat_id' ) ) ),
                                  array( 'CreateIndexSQL', array( 'idx_chatid', plugin_table( 'message_relationship' ), 'chat_id' ) ),
        );
    }

    function init() {
        require_once __DIR__ . '/api/vendor/autoload.php';
        require_once 'core/TelegramBot_bug_api.php';
        require_once 'core/TelegramBot_authentication_api.php';
        require_once 'core/TelegramBot_user_api.php';
        require_once 'core/TelegramBot_helper_api.php';
        require_once 'core/TelegramBot_keyboard_api.php';
        require_once 'core/TelegramBot_fields_api.php';
        require_once 'core/TelegramBot_message_api.php';
        require_once 'core/TelegramBot_message_format_api.php';
	require_once 'core/TelegramBot_menu_api.php';

        global $g_skip_sending_bugnote, $g_account_telegram_menu_active;
        $g_skip_sending_bugnote         = FALSE;
        $g_account_telegram_menu_active = FALSE;
    }

    function config() {
        return array(
                                  'api_token'                                 => NULL,
                                  'bot_name'                                  => NULL,
                                  'bot_father_url'                            => 'https://t.me/BotFather',
                                  'telegram_url'                              => 'tg://resolve?domain=',
                                  'download_path'                             => '/tmp/',
				  'proxy_address'				=> '',
				  'time_out_server_response'			=> 30,
				  'debug_connection_log_path'			=> '/tmp/TelegramBot_debug.log',
				  'debug_connection_enabled'			=> OFF,
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
        return array(
                                  'EVENT_REPORT_BUG'      => 'telegram_message_bug_added',
                                  'EVENT_BUGNOTE_ADD'     => 'telegram_message_bugnote_add',
                                  'EVENT_UPDATE_BUG_DATA' => 'telegram_message_skip_sending',
                                  'EVENT_UPDATE_BUG'      => 'telegram_message_update_bug',
                                  'EVENT_MENU_ACCOUNT'    => 'telegram_account_page_menu'
        );
    }
    
    public function errors() {
        return array(
                                  'BAD_REQUEST' => plugin_lang_get( 'BAD_REQUEST' ),
        );
    }

    function telegram_message_bug_added( $p_type_event, $p_issue, $p_issue_id ) {
        plugin_log_event( sprintf( 'Issue #%d reported', $p_bug_id ) );
        telegram_message_generic( $p_issue_id, 'new', 'telegram_message_notification_title_for_action_bug_submitted' );
    }

    function telegram_message_bugnote_add( $p_type_event, $p_bug_id, $p_bugnote_id ) {
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

    function telegram_account_page_menu( $p_type_event ) {


        global $g_account_telegram_menu_active;
        if( $g_account_telegram_menu_active == TRUE ) {
            return '</li><li class="active"><a href=' . plugin_page( 'account_telegram_prefs_page' ) . '>' . plugin_lang_get( 'account_telegram_prefs_page_header' ) . '</a></li><li>';
        } else {
            return '<a href=' . plugin_page( 'account_telegram_prefs_page' ) . '>' . plugin_lang_get( 'account_telegram_prefs_page_header' ) . '</a>';
        }
    }

}
