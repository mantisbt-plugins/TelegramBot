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

        $this->version  = '1.2.0-dev';
        $this->requires = array(
                                  'MantisCore' => '2.14.0',
        );

        $this->author  = 'Grigoriy Ermolaev';
        $this->contact = 'igflocal@gmail.com';
        $this->url     = 'http://github.com/mantisbt-plugins/TelegramBot';
        $this->page    = 'config_page';
    }

    function schema() {

        return array(
                                  // version 0.0.1
                                  array( "CreateTableSQL", array( plugin_table( "user_relationship" ), "
                                      mantis_user_id INT(10) NOTNULL PRIMARY,
                                      telegram_user_id INT(10) NOTNULL                                        
				" ) )
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
    }

    function config() {
        return array(
                                  'api_token'                                 => NULL,
                                  'bot_name'                                  => NULL,
                                  'bot_father_url'                            => 'https://t.me/BotFather',
                                  'telegram_url'                              => 'https://telegram.me/',
                                  'download_path'                             => '/tmp/',
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
                                  'telegram_message_on_priority_min_severity' => 10,
                                  'telegram_message_on_status_min_severity'   => 10,
                                  'telegram_message_on_bugnote_min_severity'  => 10,
                                  'telegram_message_on_reopened_min_severity' => 10,
                                  'telegram_message_on_closed_min_severity'   => 10,
                                  'telegram_message_on_resolved_min_severity' => 10,
                                  'telegram_message_on_feedback_min_severity' => 10,
                                  'telegram_message_on_assigned_min_severity' => 10,
                                  'telegram_message_on_new_min_severity'      => 10,
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
        );
    }

    public function hooks() {
        return array(
                                  'EVENT_REPORT_BUG' => 'telegram_message_bug_added'
        );
    }

    function telegram_message_bug_added( $p_type_event, $p_issue, $p_issue_id ) {
//        log_event( LOG_EMAIL, sprintf( 'Issue #%d reported', $p_bug_id ) );
        telegram_message_generic( $p_issue_id, 'new', 'telegram_message_notification_title_for_action_bug_submitted' );


//        $t_tg = new \Longman\TelegramBot\Telegram( $token, $botname );
//
//        $t_bugnote = bugnote_get($p_bugnote_id);
//        $t_text       = bugnote_get_text($p_bugnote_id);
//
//        $data1 = [
//                                  'chat_id'    => get_telegram_user_id_from_mantis_user_id( $t_bugnote->reporter_id ),
////                                  'message_id' => $t_callback_query->getMessage()->getMessageId(),
//                                  'text'       => $t_text
//        ];
//
//        Longman\TelegramBot\Request::sendMessage( $data1 );
    }

}
