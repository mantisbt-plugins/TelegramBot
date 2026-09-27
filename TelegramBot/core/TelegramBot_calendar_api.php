<?php

# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
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
 * Integration with the Calendar plugin: the wizard creating a calendar event out
 * of the chat and the notification sent when an event is created.
 *
 * Everything here is optional - the Calendar plugin is not a dependency of this
 * one, so every entry point is guarded by telegram_calendar_available().
 */

# A question of the event wizard is asked, the keyboard of the result belongs to it
define( 'TELEGRAM_EVENT_NEXT_QUESTION', 'question' );
# Every question is answered, the user is offered to create the event
define( 'TELEGRAM_EVENT_NEXT_MENU', 'menu' );

# Action of the inline calendar of the event dates, the tags of its buttons are
# built as 'g'/'s' + this value, see TelegramBotInlineKeyboardCalendar
define( 'TELEGRAM_EVENT_CALENDAR_ACTION', 'ed' );

# Action of the inline time picker of the event dates, tagged the same way
define( 'TELEGRAM_EVENT_TIME_ACTION', 'tm' );

# Minutes offered by the time picker, any other minute is sent as a text
define( 'TELEGRAM_EVENT_TIME_MINUTE_STEP', 5 );

# Longest caption Telegram accepts on a document, the text itself may be 4096
define( 'TELEGRAM_CAPTION_LENGTH_MAX', 1024 );

# Identifiers of the two dates within the callback data of the inline calendar,
# kept to a single character because callback_data is limited to 64 bytes
define( 'TELEGRAM_EVENT_DATE_FROM', 'f' );
define( 'TELEGRAM_EVENT_DATE_TO', 't' );

# Buttons of the member list, ten per page as everywhere else in the plugin
define( 'TELEGRAM_EVENT_MEMBERS_PER_PAGE', 10 );

# Buttons of the issue list, the page is asked from Calendar with this size
define( 'TELEGRAM_EVENT_ISSUES_PER_PAGE', 10 );

# Width of the name column of the events table of the Calendar plugin
define( 'TELEGRAM_EVENT_NAME_LENGTH_MAX', 255 );

# Minutes before the start a reminder may be put off to with the buttons
# under it, 0 being the start itself
define( 'TELEGRAM_EVENT_SNOOZE_MINUTES', array( 60, 30, 15, 10, 5, 0 ) );

# Minutes before the start the reminders of an event may be picked for with
# the buttons under a notification about it, Calendar takes a minute at least
define( 'TELEGRAM_EVENT_REMINDER_MINUTES', array( 60, 30, 15, 10, 5 ) );

# Minutes value telling the reminder is not put off
define( 'TELEGRAM_EVENT_SNOOZE_NONE', -1 );

# Keys of the payload of the buttons under an event notification, kept to a
# single character because callback_data is limited to 64 bytes
define( 'TELEGRAM_EVENT_REPLY_RSVP', 'r' );
define( 'TELEGRAM_EVENT_REPLY_SNOOZE', 'z' );
define( 'TELEGRAM_EVENT_REPLY_REMINDERS_OFF', 'o' );
define( 'TELEGRAM_EVENT_REPLY_REMINDER', 'a' );

/**
 * Whether the Calendar plugin is installed and initialized, and the
 * integration with it is switched on by the administrator.
 *
 * The public api of Calendar names its request class as the documented way of
 * detecting the plugin; the function is checked as well, so that a Calendar
 * older than its public api is treated as absent. The switch is the master
 * one of the whole integration - the event wizard, the notifications, the
 * reminders, the personal settings - and every entry point is guarded by this
 * function, so nothing of the integration needs to know about the switch.
 *
 * @return boolean
 */
function telegram_calendar_available() {

    return class_exists( 'CalendarPluginApi\\EventCreateRequest' )
            && function_exists( 'calendar_api_event_create' )
            && ON == (int)plugin_config_get( 'calendar_integration_enabled' );
}

/**
 * Run a callable of the Calendar plugin with Calendar being the current plugin.
 *
 * The internal functions of Calendar resolve plugin_table() / plugin_config_get()
 * against the current plugin, and the current plugin is this one whenever the
 * call comes from a hook of the bot or from its update dispatcher.
 *
 * @param callable $p_callback  Function to call.
 * @param array    $p_arguments Arguments of the call.
 * @return mixed Result of the call.
 */
function telegram_calendar_call( $p_callback, array $p_arguments = array() ) {

    plugin_push_current( 'Calendar' );

    try {
        return call_user_func_array( $p_callback, $p_arguments );
    } finally {
        plugin_pop_current();
    }
}

/**
 * Whether the user may create calendar events in the given project.
 *
 * The threshold belongs to Calendar, so it is read on behalf of that plugin;
 * calendar_api_event_create() checks the very same rule and answers a failing
 * one with access_denied(), which would end the whole request of the bot.
 *
 * @param integer $p_project_id Project of the event.
 * @param integer $p_user_id    User creating the event.
 * @return boolean
 */
function telegram_calendar_can_report_event( $p_project_id, $p_user_id ) {

    if( !telegram_calendar_available() ) {
        return FALSE;
    }

    plugin_push_current( 'Calendar' );

    try {
        $t_threshold = plugin_config_get( 'report_event_threshold', NULL, FALSE, $p_user_id, $p_project_id );

        return access_has_project_level( $t_threshold, $p_project_id, $p_user_id );
    } finally {
        plugin_pop_current();
    }
}

/**
 * Load the event draft of the current user.
 *
 * @return array|null Draft array or null when no wizard is in progress.
 */
function telegram_event_draft_get() {

    $t_raw = plugin_config_get( 'event_draft', '', FALSE, auth_get_current_user_id() );

    if( is_blank( $t_raw ) ) {
        return NULL;
    }

    $t_draft = json_decode( $t_raw, TRUE );

    return is_array( $t_draft ) ? $t_draft : NULL;
}

/**
 * Store the event draft of the current user.
 *
 * @param array $p_draft Draft array.
 * @return void
 */
function telegram_event_draft_set( array $p_draft ) {

    plugin_config_set( 'event_draft', json_encode( $p_draft ), auth_get_current_user_id() );
}

/**
 * Drop the whole state of the event wizard of the current user.
 *
 * Every key of the draft is removed here, so a new key of the state has to be
 * added to this function and to telegram_bot_user_unlink() as well.
 *
 * @param integer|null $p_user_id User the draft belongs to, the current one by default.
 * @return void
 */
function telegram_event_draft_clear( $p_user_id = NULL ) {

    $t_user_id = $p_user_id === NULL ? auth_get_current_user_id() : $p_user_id;

    plugin_config_delete( 'event_draft', $t_user_id );
    plugin_config_delete( 'event_draft_chat_id', $t_user_id );
    plugin_config_delete( 'event_draft_message_id', $t_user_id );
    plugin_config_delete( 'event_draft_current_field', $t_user_id );
}

/**
 * A brand new event draft: every question is unanswered.
 *
 * A value of a step is three valued the way the issue draft keeps it: an empty
 * string is a question which is not answered yet, null is a question answered
 * with the skip button and anything else is an answer.
 *
 * @return array
 */
function telegram_event_draft_new() {

    return array(
                              'project'     => '',
                              'name'        => '',
                              'description' => '',
                              'date_from'   => '',
                              'date_to'     => '',
                              'bug_ids'     => '',
                              'members'     => '',
    );
}

/**
 * Whether the event draft of the current user is driven by the given message.
 *
 * The state of the wizard is kept per user and bound to a single card message,
 * so a press on any other message is refused, the way the issue wizard does it.
 *
 * @param integer $p_message_id Message the callback query has arrived from.
 * @return boolean TRUE when there is no draft yet or the draft belongs to the message.
 */
function telegram_event_draft_belongs_to_message( $p_message_id ) {

    if( telegram_event_draft_get() === NULL ) {
        return TRUE;
    }

    $t_message_id = plugin_config_get( 'event_draft_message_id', NULL, FALSE, auth_get_current_user_id() );

    return is_blank( (string)$t_message_id ) || $t_message_id == $p_message_id;
}

/**
 * The dialog blocking the start of another wizard, empty when there is none.
 *
 * Only one wizard may be in progress at a time: they all take their answers
 * from the plain text messages of the same chat, so a second one would steal
 * the answers of the first.
 *
 * @return string Message shown to the user, empty when no dialog is in progress.
 */
function telegram_event_busy_dialog_message() {

    $t_user_id = auth_get_current_user_id();

    if( !is_blank( plugin_config_get( 'bug_data_draft', '', FALSE, $t_user_id ) ) ) {
        return plugin_lang_get( 'event_draft_busy_bug' );
    }

    if( !is_blank( plugin_config_get( 'status_change_draft', '', FALSE, $t_user_id ) ) ) {
        return plugin_lang_get( 'event_draft_busy_status' );
    }

    return '';
}

/**
 * The steps of the event wizard in the order they are asked in.
 *
 * @return array
 */
function telegram_event_draft_steps_get() {

    return array( 'project', 'name', 'description', 'date_from', 'date_to', 'bug_ids', 'members' );
}

/**
 * Whether a question of the event wizard has to be asked at all.
 *
 * The member list is only worth showing when the user may sign somebody else
 * up, everything else is always asked.
 *
 * @param string $p_step  Step of the wizard.
 * @param array  $p_draft Event draft.
 * @return boolean
 */
function telegram_event_draft_step_is_applicable( $p_step, array $p_draft ) {

    if( $p_step != 'members' ) {
        return TRUE;
    }

    if( is_blank( (string)$p_draft['project'] ) ) {
        return FALSE;
    }

    # a user who may not sign anybody else up gets only themselves back, and
    # that is exactly the member list the event is created with anyway
    return count( telegram_event_candidate_members( (int)$p_draft['project'] ) ) > 1;
}

/**
 * Whether the user has already answered the given question of the event wizard.
 *
 * @param string $p_step  Step of the wizard.
 * @param array  $p_draft Event draft.
 * @return boolean
 */
function telegram_event_draft_step_is_answered( $p_step, array $p_draft ) {

    if( !array_key_exists( $p_step, $p_draft ) ) {
        return FALSE;
    }

    return $p_draft[$p_step] !== '';
}

/**
 * The first question of the event wizard left unanswered, null when the event
 * can be created.
 *
 * @param array $p_draft Event draft.
 * @return string|null Step of the wizard.
 */
function telegram_event_draft_pending_step( array $p_draft ) {

    foreach( telegram_event_draft_steps_get() as $t_step ) {
        if( telegram_event_draft_step_is_applicable( $t_step, $p_draft )
                && !telegram_event_draft_step_is_answered( $t_step, $p_draft ) ) {
            return $t_step;
        }
    }

    return NULL;
}

/**
 * The steps of the event wizard already answered, in the canonical order.
 *
 * @param array $p_draft Event draft.
 * @return array List of steps.
 */
function telegram_event_draft_answered_steps( array $p_draft ) {
    $t_answered = array();

    foreach( telegram_event_draft_steps_get() as $t_step ) {
        if( telegram_event_draft_step_is_answered( $t_step, $p_draft ) ) {
            $t_answered[] = $t_step;
        }
    }

    return $t_answered;
}

/**
 * The step of the event wizard answered last, null when nothing is answered yet.
 *
 * @param array $p_draft Event draft.
 * @return string|null Step of the wizard.
 */
function telegram_event_draft_step_last_answered( array $p_draft ) {

    $t_answered = telegram_event_draft_answered_steps( $p_draft );

    return empty( $t_answered ) ? NULL : end( $t_answered );
}

/**
 * Describe the event wizard for the navigation buttons of its cards.
 *
 * The steps of the wizard are known beforehand and there are seven of them, so every
 * step back button is written by hand and reads as a sentence of the language of the
 * user, the way the mandatory steps of the issue wizard do.
 *
 * @param array $p_draft Event draft.
 * @return array Descriptor of the wizard.
 */
function telegram_event_wizard_descriptor( array $p_draft ) {

    $t_replace = array();

    foreach( telegram_event_draft_steps_get() as $t_step ) {
        $t_replace[$t_step] = 'event_replace_' . $t_step;
    }

    return keyboard_wizard_descriptor(
                              TelegrambotActions::CREATE_EVENT_TAG,
                              TelegrambotActions::STOP_EVENT_TAG,
                              telegram_event_draft_answered_steps( $p_draft ),
                              'telegram_event_draft_step_label',
                              array(
                                                        'replace'      => $t_replace,
                                                        'project_step' => 'project',
                                                        'cancel_deep'  => 'keyboard_button_delete_draft',
                              )
            );
}

/**
 * Drop the answer given to a single question of the event wizard.
 *
 * @param string $p_step  Step of the wizard.
 * @param array  $p_draft Event draft.
 * @return void
 */
function telegram_event_draft_step_value_reset( $p_step, array &$p_draft ) {

    if( !array_key_exists( $p_step, $p_draft ) ) {
        return;
    }

    $p_draft[$p_step] = '';

    # the day picked with the inline calendar is only half of the answer, it
    # goes away along with the timestamp built out of it
    if( $p_step == 'date_from' || $p_step == 'date_to' ) {
        unset( $p_draft[$p_step . '_day'] );
    }

    # a step answered with a multi select list keeps the entries ticked so far
    # next to its answer, and they are part of the answer being dropped
    if( $p_step == 'bug_ids' || $p_step == 'members' ) {
        unset( $p_draft[$p_step . '_selected'] );
    }
}

/**
 * Drop the answer given to a question of the event wizard so that it can be
 * asked again.
 *
 * @param string $p_step  Step of the wizard.
 * @param array  $p_draft Event draft.
 * @return void
 */
function telegram_event_draft_step_reset( $p_step, array &$p_draft ) {

    # the project defines the issues and the eligible members of the event, so
    # every answer given after it becomes invalid, the way the issue wizard
    # treats its project
    if( $p_step == 'project' ) {
        foreach( telegram_event_draft_steps_get() as $t_step ) {
            telegram_event_draft_step_value_reset( $t_step, $p_draft );
        }

        return;
    }

    telegram_event_draft_step_value_reset( $p_step, $p_draft );
}

/**
 * Drop the state of the question the user is answering right now.
 *
 * A date the day of which is picked still waits for its time, and the issue and
 * member lists hold the entries ticked so far: the step back gives all of them
 * up, the way the issue wizard drops the state of the custom field being picked.
 *
 * @param array $p_draft Event draft.
 * @return void
 */
function telegram_event_draft_pending_state_reset( array &$p_draft ) {

    foreach( array( 'date_from', 'date_to' ) as $t_step ) {
        if( !telegram_event_draft_step_is_answered( $t_step, $p_draft ) ) {
            unset( $p_draft[$t_step . '_day'] );
        }
    }

    foreach( array( 'bug_ids', 'members' ) as $t_step ) {
        if( !telegram_event_draft_step_is_answered( $t_step, $p_draft ) ) {
            unset( $p_draft[$t_step . '_selected'] );
        }
    }
}

/**
 * Take the name of the event out of the message the wizard has been started from.
 *
 * The user replies to a message to plan something about it, so the text of that
 * message ( the caption of a file ) is the name offered by the wizard, the way
 * the issue wizard offers the summary of the issue.
 *
 * @param array $p_draft Event draft, saved by the function.
 * @param Longman\TelegramBot\Entities\Message $p_orgl_message Message the wizard
 *                               has been started from, null when there is none.
 * @return void
 */
function telegram_event_draft_name_suggest( array &$p_draft, $p_orgl_message ) {

    # the name is offered instead of being asked for, so the questions asked
    # before it have to be answered already
    if( telegram_event_draft_pending_step( $p_draft ) !== 'name' ) {
        return;
    }

    $t_name = telegram_message_text_get( $p_orgl_message );

    if( is_blank( $t_name ) ) {
        return;
    }

    # the name of an event is a single line of at most 255 characters, the
    # message behind it is neither
    $t_name = trim( preg_replace( '/\s+/u', ' ', $t_name ) );
    $t_name = mb_substr( $t_name, 0, TELEGRAM_EVENT_NAME_LENGTH_MAX );

    $p_draft['name'] = $t_name;
    telegram_event_draft_set( $p_draft );
}

/**
 * Users the current user may sign up as members of an event of the project.
 *
 * @param integer $p_project_id Project of the event.
 * @return array List of user identifiers.
 */
function telegram_event_candidate_members( $p_project_id ) {
    static $s_candidates = array();

    if( !telegram_calendar_available() ) {
        return array();
    }

    $c_project_id = (int)$p_project_id;
    $t_user_id    = (int)auth_get_current_user_id();
    $t_cache_key  = $c_project_id . '_' . $t_user_id;

    # the list is walked over on every redraw of the card, so it is kept for
    # the request instead of being read from the database again and again
    if( !array_key_exists( $t_cache_key, $s_candidates ) ) {
        # the facade of Calendar pushes the plugin itself and never raises an error
        $s_candidates[$t_cache_key] = calendar_api_candidate_members( $c_project_id, $t_user_id );
    }

    return $s_candidates[$t_cache_key];
}

/**
 * One page of the issues the current user may attach an event of the project to.
 *
 * @param integer $p_project_id Project of the event.
 * @param integer $p_page       One-based number of the page, ten issues per page.
 * @return array List of arrays with the 'id', 'summary' and 'status' of an issue.
 */
function telegram_event_candidate_issues( $p_project_id, $p_page = 1 ) {
    static $s_candidates = array();

    if( !telegram_calendar_available() || !function_exists( 'calendar_api_candidate_issues' ) ) {
        return array();
    }

    $c_project_id = (int)$p_project_id;
    $t_user_id    = (int)auth_get_current_user_id();
    $t_page       = (int)$p_page < 1 ? 1 : (int)$p_page;
    $t_cache_key  = $c_project_id . '_' . $t_user_id . '_' . $t_page;

    # the page is read for the buttons and once more to tell whether the answer
    # names an issue of it, so it is kept for the request
    if( !array_key_exists( $t_cache_key, $s_candidates ) ) {
        # the facade of Calendar pushes the plugin itself and never raises an error
        $s_candidates[$t_cache_key] = calendar_api_candidate_issues( $c_project_id, $t_user_id, $t_page, TELEGRAM_EVENT_ISSUES_PER_PAGE );
    }

    return $s_candidates[$t_cache_key];
}

/**
 * Whether one more page of issues follows the given one.
 *
 * The facade of Calendar answers with the rows of the page alone, so the only
 * way to know is to ask for the page behind it - which is done for a full page
 * only and is cached along with it, so leafing forward costs nothing extra.
 *
 * @param integer $p_project_id Project of the event.
 * @param integer $p_page       One-based number of the page shown.
 * @return boolean
 */
function telegram_event_candidate_issues_has_next( $p_project_id, $p_page = 1 ) {

    if( count( telegram_event_candidate_issues( $p_project_id, $p_page ) ) < TELEGRAM_EVENT_ISSUES_PER_PAGE ) {
        return FALSE;
    }

    return count( telegram_event_candidate_issues( $p_project_id, (int)$p_page + 1 ) ) > 0;
}

/**
 * The label of a question of the event wizard, the same one the card shows.
 *
 * @param string $p_step Step of the wizard.
 * @return string
 */
function telegram_event_draft_step_label( $p_step ) {

    switch( $p_step ) {
        case 'project':
            return lang_get( 'email_project' );

        case 'name':
            return plugin_lang_get( 'event_name' );

        case 'description':
            return plugin_lang_get( 'event_description' );

        case 'date_from':
            return plugin_lang_get( 'event_date_from' );

        case 'date_to':
            return plugin_lang_get( 'event_date_to' );

        case 'bug_ids':
            return plugin_lang_get( 'event_bugs' );

        case 'members':
            return plugin_lang_get( 'event_members' );
    }

    return $p_step;
}

/**
 * The answer given to a question of the event wizard the way the card shows it.
 *
 * @param string $p_step  Step of the wizard.
 * @param array  $p_draft Event draft.
 * @return string
 */
function telegram_event_draft_step_display( $p_step, array $p_draft ) {

    $t_value = $p_draft[$p_step];

    # a skipped step holds null and shows no value on the card
    if( $t_value === NULL ) {
        return '';
    }

    switch( $p_step ) {
        case 'project':
            return project_get_name( (int)$t_value, /* trigger_errors */ FALSE );

        case 'date_from':
        case 'date_to':
            return telegram_event_datetime_display( (int)$t_value );

        case 'bug_ids':
            if( !is_array( $t_value ) || count( $t_value ) == 0 ) {
                return '';
            }

            $t_issues = array();

            foreach( $t_value as $t_bug_id ) {
                $t_bug_id = (int)$t_bug_id;

                # the answer may outlive the issue it names, and a card redrawn out
                # of the draft must not end the request over a deleted one
                if( !bug_exists( $t_bug_id ) ) {
                    $t_issues[] = lang_get( 'issue_id' ) . $t_bug_id;

                    continue;
                }

                $t_issues[] = telegram_event_issue_display( $t_bug_id );
            }

            # one issue per line, the summaries make a single line unreadable
            return implode( PHP_EOL, $t_issues );

        case 'members':
            if( !is_array( $t_value ) || count( $t_value ) == 0 ) {
                return user_get_name( auth_get_current_user_id() );
            }

            $t_names = array();

            foreach( $t_value as $t_member_id ) {
                $t_names[] = user_get_name( (int)$t_member_id );
            }

            return implode( ', ', $t_names );
    }

    return (string)$t_value;
}

/**
 * An issue of an event the way the card and the notifications name it.
 *
 * @param integer $p_bug_id Identifier of an existing issue.
 * @return string
 */
function telegram_event_issue_display( $p_bug_id ) {

    return lang_get( 'issue_id' ) . $p_bug_id . ': ' . bug_get_field( $p_bug_id, 'summary' );
}

/**
 * Format a moment of an event the way the core formats its date and time.
 *
 * @param integer $p_timestamp Unix timestamp.
 * @return string
 */
function telegram_event_datetime_display( $p_timestamp ) {

    return date( config_get( 'normal_date_format' ), (int)$p_timestamp );
}

/**
 * Build the whole text of the event draft card.
 *
 * The card is rebuilt out of the draft at every step, so the answers are shown
 * the same way no matter how the card has been redrawn.
 *
 * @param array  $p_draft    Event draft.
 * @param string $p_question Question of the wizard, or the prompt of its menu.
 * @param string $p_error    Message about the answer being rejected.
 * @return string Text of the event draft card.
 */
function telegram_event_draft_card_compose( array $p_draft, $p_question = '', $p_error = '' ) {

    $t_lines = array();

    $t_lines[] = plugin_lang_get( 'action_label' ) . ': ' . plugin_lang_get( 'menu_create_event' );

    foreach( telegram_event_draft_steps_get() as $t_step ) {
        if( !telegram_event_draft_step_is_answered( $t_step, $p_draft ) ) {
            continue;
        }

        $t_display = telegram_event_draft_step_display( $t_step, $p_draft );

        # a skipped step shows a dash instead of a value
        if( is_blank( $t_display ) ) {
            $t_display = plugin_lang_get( 'skipped_mark' );
        }

        $t_lines[] = telegram_event_draft_step_label( $t_step ) . ': ' . $t_display;
    }

    $t_prompt = array();

    if( !is_blank( $p_error ) ) {
        $t_prompt[] = plugin_lang_get( 'card_error_prefix' ) . $p_error;
    }

    if( !is_blank( $p_question ) ) {
        $t_prompt[] = plugin_lang_get( 'card_question_prefix' ) . $p_question;
    }

    if( empty( $t_prompt ) ) {
        return implode( PHP_EOL, $t_lines );
    }

    return telegram_card_prompt_append( implode( PHP_EOL, $t_lines ), implode( PHP_EOL, $t_prompt ) );
}

/**
 * Build the question of the given step of the event wizard.
 *
 * The plugin is told here whether a text answer is expected, the same way the
 * issue wizard remembers the field the next message belongs to.
 *
 * @param string $p_step     Step of the wizard.
 * @param array  $p_draft    Event draft.
 * @param string $p_question Out: question shown under the answers given so far.
 * @param mixed  $p_page     Page of the issue or the member list, month of the calendar of a date.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function telegram_event_draft_step_ask( $p_step, array $p_draft, &$p_question, $p_page = 1 ) {

    $t_user_id         = auth_get_current_user_id();
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
    $t_await           = '';

    $p_question = telegram_event_draft_step_label( $p_step );

    switch( $p_step ) {
        case 'project':
            $t_inline_keyboard = keyboard_projects_get( ALL_PROJECTS, 1, 1, TelegrambotActions::CREATE_EVENT_TAG );
            break;

        case 'name':
            $t_await = 'name';
            break;

        case 'description':
            $t_await    = 'description';
            $p_question = plugin_lang_get( 'event_description_prompt' );

            keyboard_skip_button_add( $t_inline_keyboard, array(
                                      TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::SKIP_FIELD => 'description' )
            ) );
            break;

        case 'date_from':
        case 'date_to':
            # the end of the event opens on the month its start belongs to, an
            # event spanning two months is the exception rather than the rule
            $t_shown = date( 'Y-n', time() );

            if( is_string( $p_page ) && !is_blank( $p_page ) ) {
                $t_shown = $p_page;
            } else if( $p_step == 'date_to' && !is_blank( (string)$p_draft['date_from'] ) ) {
                $t_shown = date( 'Y-n', (int)$p_draft['date_from'] );
            }

            $t_calendar        = new TelegramBotInlineKeyboardCalendar( $t_shown );
            $t_inline_keyboard = telegram_event_calendar_keyboard_get( $t_calendar, $p_step, $p_page );

            # the end of the event is offered an hour after its start, which is
            # the answer wanted most of the time and saves two more taps
            if( $p_step == 'date_to' && !is_blank( (string)$p_draft['date_from'] ) ) {
                $t_inline_keyboard->addRow( [
                                          'text'          => plugin_lang_get( 'event_hour_button' ),
                                          'callback_data' => json_encode( array(
                                                                    TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::SET_EVENT_HOUR => 1 )
                                          ) )
                ] );
            }
            break;

        case 'bug_ids':
            $p_question        = plugin_lang_get( 'event_bugs_prompt' );
            $t_inline_keyboard = keyboard_event_issues_get(
                                      telegram_event_candidate_issues( (int)$p_draft['project'], (int)$p_page ),
                                      telegram_event_draft_issues_selected( $p_draft ),
                                      (int)$p_page,
                                      telegram_event_candidate_issues_has_next( (int)$p_draft['project'], (int)$p_page )
                    );

            keyboard_skip_button_add( $t_inline_keyboard, array(
                                      TelegrambotActions::CREATE_EVENT_TAG => array( TelegrambotActions::SKIP_FIELD => 'bug_ids' )
            ) );
            break;

        case 'members':
            $t_inline_keyboard = keyboard_event_members_get(
                                      telegram_event_candidate_members( (int)$p_draft['project'] ),
                                      telegram_event_draft_members_selected( $p_draft ),
                                      (int)$p_page
                    );
            break;
    }

    plugin_config_set( 'event_draft_current_field', $t_await, $t_user_id );

    keyboard_event_buttons_add( $t_inline_keyboard, $p_draft );

    return $t_inline_keyboard;
}

/**
 * Build the inline calendar of one of the dates of the event.
 *
 * The month, the year and the block of years are told apart the way the due
 * date of the issue wizard does it: a 'd' prefixed year asks for the block of
 * years, a bare year for the months of that year and a year-month for the days.
 *
 * @param TelegramBotInlineKeyboardCalendar $p_calendar Calendar positioned on the date shown.
 * @param string $p_step Step of the wizard the calendar belongs to.
 * @param mixed  $p_page Date shown, as it came back from the callback data.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function telegram_event_calendar_keyboard_get( TelegramBotInlineKeyboardCalendar $p_calendar, $p_step, $p_page ) {

    $t_id  = $p_step == 'date_from' ? TELEGRAM_EVENT_DATE_FROM : TELEGRAM_EVENT_DATE_TO;
    $t_tag = TelegrambotActions::CREATE_EVENT_TAG;

    if( is_string( $p_page ) && preg_match( '/^d\d{4}$/', $p_page ) ) {
        return $p_calendar->getYearsKeyboard( TELEGRAM_EVENT_CALENDAR_ACTION, $t_id, $t_tag );
    }

    if( is_string( $p_page ) && preg_match( '/^\d{4}$/', $p_page ) ) {
        return $p_calendar->getYearKeyboard( TELEGRAM_EVENT_CALENDAR_ACTION, $t_id, $t_tag );
    }

    return $p_calendar->getKeyboard( TELEGRAM_EVENT_CALENDAR_ACTION, $t_id, $t_tag );
}

/**
 * Ask for the time of one of the dates of the event.
 *
 * The day has been picked with the inline calendar and is waiting in the draft,
 * only the time is left. It is picked with the inline time picker in two taps,
 * the hour and then the minute, or sent as a plain text message, so the step
 * is remembered as the one the next message belongs to.
 *
 * @param array   $p_draft Event draft.
 * @param string  $p_step  Step of the wizard, 'date_from' or 'date_to'.
 * @param string  $p_error Message about the answer being rejected.
 * @param integer $p_hour  Hour already picked, null while the hours are shown.
 * @return array Data for Longman\TelegramBot\Request::editMessageText().
 */
function telegram_event_time_question_get( array $p_draft, $p_step, $p_error = '', $p_hour = NULL ) {

    plugin_config_set( 'event_draft_current_field', $p_step . '_time', auth_get_current_user_id() );

    $t_id  = $p_step == 'date_from' ? TELEGRAM_EVENT_DATE_FROM : TELEGRAM_EVENT_DATE_TO;
    $t_tag = TelegrambotActions::CREATE_EVENT_TAG;

    if( $p_hour === NULL ) {
        $t_inline_keyboard = keyboard_time_hours_get( TELEGRAM_EVENT_TIME_ACTION, $t_id, $t_tag );
    } else {
        $t_inline_keyboard = keyboard_time_minutes_get( $p_hour, TELEGRAM_EVENT_TIME_MINUTE_STEP, TELEGRAM_EVENT_TIME_ACTION, $t_id, $t_tag );
    }

    keyboard_event_buttons_add( $t_inline_keyboard, $p_draft );

    # the day comes from the calendar as Y-n-j, it is shown the way the card shows dates
    $t_question = sprintf(
                              plugin_lang_get( 'event_time_prompt' ),
                              telegram_event_draft_step_label( $p_step ),
                              date( 'Y-m-d', strtotime( (string)$p_draft[$p_step . '_day'] ) )
    );

    return array(
                              'text'         => telegram_event_draft_card_compose( $p_draft, $t_question, $p_error ),
                              'reply_markup' => $t_inline_keyboard,
    );
}

/**
 * The issues currently ticked in the issue list of the wizard.
 *
 * An event does not have to be attached to an issue at all, so an untouched
 * list starts out empty.
 *
 * @param array $p_draft Event draft.
 * @return array List of issue identifiers.
 */
function telegram_event_draft_issues_selected( array $p_draft ) {

    if( array_key_exists( 'bug_ids_selected', $p_draft ) && is_array( $p_draft['bug_ids_selected'] ) ) {
        return $p_draft['bug_ids_selected'];
    }

    return array();
}

/**
 * The members currently ticked in the member list of the wizard.
 *
 * The author of the event is always a member, so an untouched list starts with
 * the author alone.
 *
 * @param array $p_draft Event draft.
 * @return array List of user identifiers.
 */
function telegram_event_draft_members_selected( array $p_draft ) {

    if( array_key_exists( 'members_selected', $p_draft ) && is_array( $p_draft['members_selected'] ) ) {
        return $p_draft['members_selected'];
    }

    return array( (int)auth_get_current_user_id() );
}

/**
 * Ask the question the event wizard has to ask next.
 *
 * @param array  $p_draft    Event draft.
 * @param string $p_question Out: question of the wizard shown under the answers.
 * @param mixed  $p_page     Page of the issue or the member list, month of the calendar of a date.
 * @return array array(
 *         'state'    => what the wizard is up to, TELEGRAM_EVENT_NEXT_*,
 *         'keyboard' => Longman\TelegramBot\Entities\InlineKeyboard keyboard to show,
 *         'question' => question of the wizard shown under the answers given so far
 *         )
 */
function telegram_event_draft_ask_next_step( array $p_draft, $p_page = 1 ) {

    $t_step = telegram_event_draft_pending_step( $p_draft );

    if( $t_step !== NULL ) {
        $t_question        = '';
        $t_inline_keyboard = telegram_event_draft_step_ask( $t_step, $p_draft, $t_question, $p_page );

        return array(
                                  'state'    => TELEGRAM_EVENT_NEXT_QUESTION,
                                  'keyboard' => $t_inline_keyboard,
                                  'question' => $t_question,
        );
    }

    # nothing is left to ask, the event is created on demand only
    plugin_config_set( 'event_draft_current_field', '', auth_get_current_user_id() );

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    keyboard_event_create_button_add( $t_inline_keyboard );
    keyboard_event_buttons_add( $t_inline_keyboard, $p_draft );

    return array(
                              'state'    => TELEGRAM_EVENT_NEXT_MENU,
                              'keyboard' => $t_inline_keyboard,
                              'question' => plugin_lang_get( 'event_menu_prompt' ),
    );
}

/**
 * Redraw the card of the event wizard with the question it has to ask next.
 *
 * @param array  $p_draft Event draft.
 * @param string $p_error Message about the answer being rejected.
 * @param mixed  $p_page  Page of the issue or the member list, month of the calendar of a date.
 * @return array Data for Longman\TelegramBot\Request::editMessageText().
 */
function telegram_event_draft_card_refresh( array $p_draft, $p_error = '', $p_page = 1 ) {

    $t_next = telegram_event_draft_ask_next_step( $p_draft, $p_page );

    return array(
                              'text'         => telegram_event_draft_card_compose( $p_draft, $t_next['question'], $p_error ),
                              'reply_markup' => $t_next['keyboard'],
    );
}

/**
 * Create the event out of the draft, clean the draft up and build the final
 * view of its card.
 *
 * @param array $p_draft Event draft.
 * @return array Data for Longman\TelegramBot\Request::editMessageText().
 */
function telegram_event_draft_submit( array $p_draft ) {

    $t_user_id = auth_get_current_user_id();

    $t_request = new \CalendarPluginApi\EventCreateRequest();

    $t_request->project_id         = (int)$p_draft['project'];
    $t_request->name               = (string)$p_draft['name'];
    # a skipped description holds null, the request wants an empty string
    $t_request->description        = (string)$p_draft['description'];
    $t_request->user_id            = (int)$t_user_id;
    $t_request->date_from          = (int)$p_draft['date_from'];
    $t_request->date_to            = (int)$p_draft['date_to'];
    $t_request->bug_ids            = is_array( $p_draft['bug_ids'] ) ? array_map( 'intval', $p_draft['bug_ids'] ) : array();
    $t_request->members            = is_array( $p_draft['members'] ) ? $p_draft['members'] : array();
    # a recurring event is filled in on the web form of the calendar only
    $t_request->recurrence_pattern = '';
    $t_request->timezone           = (string)user_pref_get_pref( $t_user_id, 'timezone' );

    try {
        $t_event_id = calendar_api_event_create( $t_request );
    } catch( Mantis\Exceptions\MantisException $t_error ) {
        # the draft survives a rejected request, so the user may correct it
        $t_params = $t_error->getParams();

        if( !empty( $t_params ) ) {
            call_user_func_array( 'error_parameters', $t_params );
        }

        return telegram_event_draft_card_refresh( $p_draft, error_string( $t_error->getCode() ) );
    }

    plugin_log_event( sprintf( 'Calendar event #%d created from the chat', $t_event_id ) );

    # mark the origin in the history of the event; the guard keeps the older
    # Calendar versions working, which lack the plugin history facade. The
    # event is already created, so a failure here must not fail the card.
    if( function_exists( 'calendar_api_event_history_log' ) ) {
        try {
            calendar_api_event_history_log( $t_event_id, 'history_event_created' );
        } catch( Mantis\Exceptions\MantisException $t_error ) {
            plugin_log_event( sprintf( 'History of event #%d not written: %s', $t_event_id, $t_error->getMessage() ) );
        }
    }

    # the base name is passed explicitly: plugin_page() of the current plugin
    # would point the link at TelegramBot instead of Calendar
    $t_url = config_get_global( 'path' ) . plugin_page( 'view', /* redirect */ TRUE, 'Calendar' )
            . '&event_id=' . (int)$t_event_id . '&date=' . (int)$p_draft['date_from'];

    $t_text  = telegram_event_draft_card_compose( $p_draft );
    $t_text .= PHP_EOL;
    $t_text .= plugin_lang_get( 'card_separator' );
    $t_text .= PHP_EOL;
    $t_text .= sprintf( plugin_lang_get( 'event_creation_complete' ), (int)$t_event_id );
    $t_text .= PHP_EOL;
    $t_text .= $t_url;

    telegram_event_draft_clear( $t_user_id );

    return array( 'text' => $t_text );
}

/**
 * Take the message text as the answer to a text question of the event wizard.
 *
 * The dispatcher offers every plain text message to this function before the
 * issue wizard sees it, so the answer is only taken while a text question of
 * this wizard is pending.
 *
 * @param string $p_text Text of the message.
 * @return array|null Data of the card message to edit or null when no text question is pending.
 */
function telegram_event_draft_text_answer( $p_text ) {

    $t_user_id = auth_get_current_user_id();
    $t_await   = plugin_config_get( 'event_draft_current_field', '', FALSE, $t_user_id );

    if( is_blank( $t_await ) ) {
        return NULL;
    }

    $t_draft = telegram_event_draft_get();

    if( $t_draft === NULL ) {
        plugin_config_delete( 'event_draft_current_field', $t_user_id );

        return NULL;
    }

    $t_text  = trim( (string)$p_text );
    $t_error = '';

    switch( $t_await ) {
        case 'name':
            if( is_blank( $t_text ) ) {
                $t_error = plugin_lang_get( 'event_error_name' );
                break;
            }

            $t_draft['name'] = $t_text;
            break;

        case 'description':
            # the field is optional, but a blank message is a slip rather than
            # an answer: the skip button is the way to leave it out
            if( is_blank( $t_text ) ) {
                $t_error = plugin_lang_get( 'event_error_description' );
                break;
            }

            $t_draft['description'] = $t_text;
            break;

        case 'date_from_time':
        case 'date_to_time':
            $t_step      = $t_await == 'date_from_time' ? 'date_from' : 'date_to';
            $t_timestamp = telegram_event_time_apply( $t_draft, $t_step, $t_text, $t_error );

            # a rejected time leaves the day of the step in the draft, so the
            # time is asked once again instead of the calendar being reopened
            if( $t_error != '' ) {
                return telegram_event_time_question_get( $t_draft, $t_step, $t_error );
            }

            $t_draft[$t_step] = $t_timestamp;
            break;

        default:
            return NULL;
    }

    # a rejected value leaves the state of the step untouched, so the same
    # question is asked again along with the error
    if( $t_error == '' ) {
        telegram_event_draft_set( $t_draft );
    }

    return telegram_event_draft_card_refresh( $t_draft, $t_error );
}

/**
 * Turn the "HH:MM" answer into the timestamp of one of the dates of the event.
 *
 * The day has been picked with the inline calendar and is waiting in the draft,
 * the time answered here completes it.
 *
 * @param array  $p_draft Event draft.
 * @param string $p_step  Step of the wizard, 'date_from' or 'date_to'.
 * @param string $p_text  Text of the message.
 * @param string $p_error Out: message about the answer being rejected.
 * @return integer Unix timestamp, 0 when the answer is rejected.
 */
function telegram_event_time_apply( array $p_draft, $p_step, $p_text, &$p_error ) {

    $t_day_key = $p_step . '_day';

    if( !array_key_exists( $t_day_key, $p_draft ) || is_blank( (string)$p_draft[$t_day_key] ) ) {
        $p_error = plugin_lang_get( 'event_error_time' );

        return 0;
    }

    if( !preg_match( '/^([01]?\d|2[0-3])[:.]([0-5]\d)$/', $p_text, $t_matches ) ) {
        $p_error = plugin_lang_get( 'event_error_time' );

        return 0;
    }

    $t_timestamp = strtotime( $p_draft[$t_day_key] . ' ' . $t_matches[1] . ':' . $t_matches[2] . ':00' );

    if( $t_timestamp === FALSE ) {
        $p_error = plugin_lang_get( 'event_error_time' );

        return 0;
    }

    # the calendar refuses an event ending before it starts, the wizard says so
    # itself instead of letting the request be rejected
    if( $p_step == 'date_to'
            && !is_blank( (string)$p_draft['date_from'] )
            && $t_timestamp <= (int)$p_draft['date_from'] ) {
        $p_error = plugin_lang_get( 'event_error_range' );

        return 0;
    }

    return $t_timestamp;
}

/**
 * Process a step of the wizard creating a calendar event out of the chat.
 *
 * @param array $p_current_action Payload of the button pressed.
 * @param Longman\TelegramBot\Entities\CallbackQuery $p_callback_query Callback query being processed.
 * @return array Data for Longman\TelegramBot\Request::editMessageText().
 */
function telegram_event_report( $p_current_action, Longman\TelegramBot\Entities\CallbackQuery $p_callback_query ) {

    $t_user_id         = auth_get_current_user_id();
    $t_callback_msg_id = $p_callback_query->getMessage()->getMessageId();
    $t_orgl_chat_id    = $p_callback_query->getMessage()->getChat()->getId();

    if( !telegram_calendar_available() ) {
        telegram_callback_alert_set( plugin_lang_get( 'event_calendar_missing' ) );

        return telegram_action_select( $t_orgl_chat_id, $t_callback_msg_id );
    }

    # the draft is being filled in somewhere else, so this message shows a draft
    # which does not exist anymore, the way the issue wizard treats it
    if( !telegram_event_draft_belongs_to_message( $t_callback_msg_id ) ) {
        telegram_callback_alert_set( plugin_lang_get( 'event_draft_other_message' ) );

        return telegram_action_select( $t_orgl_chat_id, $t_callback_msg_id );
    }

    $t_draft = telegram_event_draft_get();

    if( $t_draft === NULL ) {
        # only one wizard at a time takes the text messages of the chat
        $t_busy = telegram_event_busy_dialog_message();

        if( $t_busy != '' ) {
            telegram_callback_alert_set( $t_busy );

            return telegram_action_select( $t_orgl_chat_id, $t_callback_msg_id );
        }

        $t_draft = telegram_event_draft_new();

        telegram_event_draft_set( $t_draft );
        plugin_config_set( 'event_draft_chat_id', $t_orgl_chat_id, $t_user_id );
        plugin_config_set( 'event_draft_message_id', $t_callback_msg_id, $t_user_id );
    }

    $t_action = array_keys( $p_current_action )[0];
    $t_error  = '';
    $t_page   = 1;

    switch( $t_action ) {
//PROJECT
        case TelegrambotActions::GET_PROJECT:
            $t_question        = telegram_event_draft_step_label( 'project' );
            $t_inline_keyboard = keyboard_projects_get(
                                      $p_current_action[TelegrambotActions::GET_PROJECT]['id'],
                                      $p_current_action[TelegrambotActions::GET_PROJECT]['p'],
                                      $p_current_action[TelegrambotActions::GET_PROJECT]['fp'],
                                      TelegrambotActions::CREATE_EVENT_TAG
                    );

            keyboard_event_buttons_add( $t_inline_keyboard, $t_draft );

            return array(
                                      'chat_id'      => $t_orgl_chat_id,
                                      'message_id'   => $t_callback_msg_id,
                                      'text'         => telegram_event_draft_card_compose( $t_draft, $t_question ),
                                      'reply_markup' => $t_inline_keyboard,
            );

        case TelegrambotActions::SET_PROJECT:
            $t_project_id = (int)$p_current_action[TelegrambotActions::SET_PROJECT]['id'];

            # calendar_api_event_create() answers a missing permission with
            # access_denied(), which would end the whole request of the bot
            if( !telegram_calendar_can_report_event( $t_project_id, $t_user_id ) ) {
                $t_error = error_string( ERROR_ACCESS_DENIED );
                break;
            }

            $t_draft['project'] = $t_project_id;
            telegram_event_draft_set( $t_draft );
            break;

//DATES
        case TelegrambotActions::GET_EVENT_DATE:
            # the action leafs through the calendar, so the same question is
            # asked again with the calendar positioned on the requested date
            $t_step = telegram_event_date_step_get( $p_current_action[TelegrambotActions::GET_EVENT_DATE] );
            $t_page = reset( $p_current_action[TelegrambotActions::GET_EVENT_DATE] );

            if( $t_step === NULL ) {
                break;
            }

            $t_question        = telegram_event_draft_step_label( $t_step );
            $t_inline_keyboard = telegram_event_draft_step_ask( $t_step, $t_draft, $t_question, $t_page );

            return array(
                                      'chat_id'      => $t_orgl_chat_id,
                                      'message_id'   => $t_callback_msg_id,
                                      'text'         => telegram_event_draft_card_compose( $t_draft, $t_question ),
                                      'reply_markup' => $t_inline_keyboard,
            );

        case TelegrambotActions::SET_EVENT_DATE:
            $t_step = telegram_event_date_step_get( $p_current_action[TelegrambotActions::SET_EVENT_DATE] );

            if( $t_step === NULL ) {
                break;
            }

            $t_day = reset( $p_current_action[TelegrambotActions::SET_EVENT_DATE] );

            # an end day before the start day is refused right away: the time
            # asked next only offers the hours of the day picked, so the day
            # could not be changed any more without taking back the start
            if( $t_step == 'date_to'
                    && !is_blank( (string)$t_draft['date_from'] )
                    && strtotime( $t_day ) < strtotime( date( 'Y-m-d', (int)$t_draft['date_from'] ) ) ) {
                $t_error = plugin_lang_get( 'event_error_range' );
                break;
            }

            # the day is only half of the answer, the time is asked as a text
            $t_draft[$t_step . '_day'] = $t_day;
            telegram_event_draft_set( $t_draft );

            $t_data_send               = telegram_event_time_question_get( $t_draft, $t_step );
            $t_data_send['chat_id']    = $t_orgl_chat_id;
            $t_data_send['message_id'] = $t_callback_msg_id;

            return $t_data_send;

        case TelegrambotActions::GET_EVENT_TIME:
            # the action reopens the hours of the time picker
            $t_step = telegram_event_date_step_get( $p_current_action[TelegrambotActions::GET_EVENT_TIME] );

            if( $t_step === NULL ) {
                break;
            }

            $t_data_send               = telegram_event_time_question_get( $t_draft, $t_step );
            $t_data_send['chat_id']    = $t_orgl_chat_id;
            $t_data_send['message_id'] = $t_callback_msg_id;

            return $t_data_send;

        case TelegrambotActions::SET_EVENT_TIME:
            $t_step = telegram_event_date_step_get( $p_current_action[TelegrambotActions::SET_EVENT_TIME] );

            if( $t_step === NULL ) {
                break;
            }

            $t_time = (string)reset( $p_current_action[TelegrambotActions::SET_EVENT_TIME] );

            # a bare hour is only half of the answer, the minutes are asked next
            if( preg_match( '/^([01]?\d|2[0-3])$/', $t_time ) ) {
                $t_data_send = telegram_event_time_question_get( $t_draft, $t_step, '', (int)$t_time );
            } else {
                $t_timestamp = telegram_event_time_apply( $t_draft, $t_step, $t_time, $t_error );

                if( $t_error == '' ) {
                    $t_draft[$t_step] = $t_timestamp;
                    telegram_event_draft_set( $t_draft );
                    break;
                }

                # the button goes stale along with the card it sits on: the day
                # may be gone from the draft, so the time is asked once again
                $t_data_send = telegram_event_time_question_get( $t_draft, $t_step, $t_error );
            }

            $t_data_send['chat_id']    = $t_orgl_chat_id;
            $t_data_send['message_id'] = $t_callback_msg_id;

            return $t_data_send;

        case TelegrambotActions::SET_EVENT_HOUR:
            if( is_blank( (string)$t_draft['date_from'] ) ) {
                break;
            }

            $t_draft['date_to'] = (int)$t_draft['date_from'] + 3600;
            telegram_event_draft_set( $t_draft );
            break;

//ISSUES THE EVENT IS ATTACHED TO
        case TelegrambotActions::SKIP_FIELD:
            $t_field_to_skip = $p_current_action[TelegrambotActions::SKIP_FIELD];

            # null marks the field as skipped: unlike removing the key it keeps
            # the step known to the wizard and shown on the card
            if( is_string( $t_field_to_skip )
                    && array_key_exists( $t_field_to_skip, $t_draft )
                    && $t_draft[$t_field_to_skip] === '' ) {
                $t_draft[$t_field_to_skip] = NULL;
                telegram_event_draft_set( $t_draft );
            }
            break;

        case TelegrambotActions::GET_EVENT_ISSUE:
            $t_page = (int)$p_current_action[TelegrambotActions::GET_EVENT_ISSUE]['p'];
            break;

        case TelegrambotActions::TOGGLE_EVENT_ISSUE:
            $t_page       = (int)$p_current_action[TelegrambotActions::TOGGLE_EVENT_ISSUE]['p'];
            $t_bug_id     = (int)$p_current_action[TelegrambotActions::TOGGLE_EVENT_ISSUE]['id'];
            $t_candidates = telegram_event_candidate_issues( (int)$t_draft['project'], $t_page );

            # the button goes stale along with the card it sits on, and an issue
            # which is not offered any more must not end up in the request
            if( !in_array( $t_bug_id, array_column( $t_candidates, 'id' ) ) ) {
                telegram_callback_alert_set( plugin_lang_get( 'event_error_bug' ) );
                break;
            }

            $t_selected = telegram_event_draft_issues_selected( $t_draft );
            $t_position = array_search( $t_bug_id, $t_selected );

            if( $t_position === FALSE ) {
                $t_selected[] = $t_bug_id;
            } else {
                unset( $t_selected[$t_position] );
            }

            $t_draft['bug_ids_selected'] = array_values( $t_selected );
            telegram_event_draft_set( $t_draft );
            break;

        case TelegrambotActions::END_EVENT_ISSUE:
            $t_selected = telegram_event_draft_issues_selected( $t_draft );

            # an event does not have to be attached to an issue, so an empty
            # list is the very answer the skip button gives
            $t_draft['bug_ids'] = count( $t_selected ) == 0 ? NULL : array_values( $t_selected );
            telegram_event_draft_set( $t_draft );
            break;

//MEMBERS
        case TelegrambotActions::GET_EVENT_MEMBER:
            $t_page = (int)$p_current_action[TelegrambotActions::GET_EVENT_MEMBER]['p'];
            break;

        case TelegrambotActions::TOGGLE_EVENT_MEMBER:
            $t_page       = (int)$p_current_action[TelegrambotActions::TOGGLE_EVENT_MEMBER]['p'];
            $t_member_id  = (int)$p_current_action[TelegrambotActions::TOGGLE_EVENT_MEMBER]['id'];
            $t_candidates = telegram_event_candidate_members( (int)$t_draft['project'] );

            if( !in_array( $t_member_id, $t_candidates ) ) {
                telegram_callback_alert_set( plugin_lang_get( 'event_error_member' ) );
                break;
            }

            $t_selected = telegram_event_draft_members_selected( $t_draft );
            $t_position = array_search( $t_member_id, $t_selected );

            if( $t_position === FALSE ) {
                $t_selected[] = $t_member_id;
            } else {
                unset( $t_selected[$t_position] );
            }

            $t_draft['members_selected'] = array_values( $t_selected );
            telegram_event_draft_set( $t_draft );
            break;

        case TelegrambotActions::END_EVENT_MEMBER:
            $t_selected = telegram_event_draft_members_selected( $t_draft );

            if( count( $t_selected ) == 0 ) {
                telegram_callback_alert_set( plugin_lang_get( 'event_error_members_empty' ) );
                break;
            }

            $t_draft['members'] = array_values( $t_selected );
            telegram_event_draft_set( $t_draft );
            break;

//BACK TO THE QUESTION ANSWERED LAST
//The answer given last is dropped and the question is asked once again, the way
//the issue wizard does it. The card is rebuilt right here on purpose: the tail
//of this function offers the name taken from the message the wizard has been
//started from, and a name undone by this very button must not come back on its
//own - the user takes the step back to type the name by hand.
        case TelegrambotActions::BACK_FIELD:
            # the question the user is looking at is given up, including the day
            # of a date waiting for its time and the issues or members ticked so far
            telegram_event_draft_pending_state_reset( $t_draft );

            $t_step_to_ask = telegram_event_draft_step_last_answered( $t_draft );

            if( $t_step_to_ask === NULL ) {
                # nothing is answered yet, the first question stays as it is
                telegram_callback_alert_set( plugin_lang_get( 'back_nothing' ) );

                $t_step_to_ask = 'project';
            } else {
                telegram_event_draft_step_reset( $t_step_to_ask, $t_draft );
            }

            telegram_event_draft_set( $t_draft );

            $t_question        = '';
            $t_inline_keyboard = telegram_event_draft_step_ask( $t_step_to_ask, $t_draft, $t_question );

            return array(
                                      'chat_id'      => $t_orgl_chat_id,
                                      'message_id'   => $t_callback_msg_id,
                                      'text'         => telegram_event_draft_card_compose( $t_draft, $t_question ),
                                      'reply_markup' => $t_inline_keyboard,
            );

//CREATION OF THE EVENT OUT OF THE DRAFT
        case TelegrambotActions::CREATE_EVENT:
            # the button goes stale along with the card it sits on, so the draft
            # is checked again instead of being trusted to be complete
            if( telegram_event_draft_pending_step( $t_draft ) !== NULL ) {
                telegram_callback_alert_set( plugin_lang_get( 'wizard_required_missing' ) );
                break;
            }

            $t_data_send               = telegram_event_draft_submit( $t_draft );
            $t_data_send['chat_id']    = $t_orgl_chat_id;
            $t_data_send['message_id'] = $t_callback_msg_id;

            return $t_data_send;
    }

    # the message the wizard has been started from offers the name of the event
    telegram_event_draft_name_suggest( $t_draft, $p_callback_query->getMessage()->getReplyToMessage() );

    $t_data_send               = telegram_event_draft_card_refresh( $t_draft, $t_error, $t_page );
    $t_data_send['chat_id']    = $t_orgl_chat_id;
    $t_data_send['message_id'] = $t_callback_msg_id;

    return $t_data_send;
}

/**
 * The step of the wizard a date carried by the inline calendar or by the inline
 * time picker belongs to.
 *
 * The identifier of the date is the key of the payload, kept to a single
 * character because callback_data is limited to 64 bytes.
 *
 * @param array $p_payload Payload of the calendar or time picker button.
 * @return string|null Step of the wizard, null for an unknown identifier.
 */
function telegram_event_date_step_get( $p_payload ) {

    if( !is_array( $p_payload ) || count( $p_payload ) == 0 ) {
        return NULL;
    }

    $t_keys = array_keys( $p_payload );

    switch( $t_keys[0] ) {
        case TELEGRAM_EVENT_DATE_FROM:
            return 'date_from';

        case TELEGRAM_EVENT_DATE_TO:
            return 'date_to';
    }

    return NULL;
}

/**
 * Actions the notification matrix of the calendar events has a row for, in
 * the order they are shown. Only the actions this plugin announces: a row
 * nothing ever reads would be a promise on the settings page. The rows are
 * the ones of the matrix of Calendar, so that the two matrices read alike.
 *
 * @return array
 */
function telegram_calendar_notify_actions() {

    return array( 'created', 'updated', 'deleted', 'member_added', 'member_removed', 'rsvp' );
}

/**
 * Personal switch gating the notifications of the given action.
 *
 * Mapped the way Calendar maps its personal settings: joining an event is
 * news of the kind of its creation, leaving it of the kind of its deletion,
 * and a reply of a member is a change of the event.
 *
 * @param string $p_action Row of the matrix, see telegram_calendar_notify_actions().
 * @return string Option name, one of telegram_calendar_event_prefs().
 */
function telegram_calendar_notify_action_pref( $p_action ) {

    switch( $p_action ) {
        case 'created':
        case 'member_added':
            return 'telegram_message_on_event_created';

        case 'updated':
        case 'rsvp':
            return 'telegram_message_on_event_updated';
    }

    return 'telegram_message_on_event_deleted';
}

/**
 * Groups of recipients the notification matrix of the calendar events has a
 * column for, in the order they are shown: the author of the event, its
 * members, and the user who acts. The last one is the counterpart of
 * telegram_message_receive_own of the issues, a matrix cell here the way the
 * Calendar plugin has it, so that the two matrices read alike.
 *
 * @return array
 */
function telegram_calendar_notify_targets() {

    return array( 'author', 'members', 'actor' );
}

/**
 * Personal switches of the calendar event notifications, in the order they
 * are shown on the account page: one per kind of news, see
 * telegram_calendar_notify_action_pref(), and the reminders, whose
 * recipients Calendar chooses on its own. Each one is a per-user option
 * of this plugin, declared among its defaults, read wherever a recipient is
 * chosen and written, reset and deleted by the account pages.
 *
 * @return array
 */
function telegram_calendar_event_prefs() {

    return array(
                              'telegram_message_on_event_created',
                              'telegram_message_on_event_updated',
                              'telegram_message_on_event_deleted',
                              'telegram_message_on_event_reminder',
    );
}

/**
 * Whether the reminders of Calendar are repeated in Telegram at all.
 *
 * The switch of the administrator, read where a reminder is about to be sent
 * and where the personal choice is shown or saved: while the reminders are
 * off for everybody, the personal choice is neither asked for nor applied.
 *
 * @return boolean
 */
function telegram_calendar_reminders_offered() {

    return telegram_calendar_available() && ON == (int)plugin_config_get( 'calendar_reminders_enabled' );
}

/**
 * Whether a personal switch of telegram_calendar_event_prefs() is offered to
 * the users: the one of the reminders follows the global switch, the rest
 * are always there.
 *
 * @param string $p_pref Option name, one of telegram_calendar_event_prefs().
 * @return boolean
 */
function telegram_calendar_pref_offered( $p_pref ) {

    if( 'telegram_message_on_event_reminder' == $p_pref ) {
        return telegram_calendar_reminders_offered();
    }

    return telegram_calendar_available();
}

/**
 * Merge a stored matrix of the calendar events over a complete one.
 *
 * A matrix is written as a whole, but a hand edited or an older one may miss
 * a cell, so every cell of the answer is taken from the fallback unless the
 * stored matrix has a value for it.
 *
 * @param mixed $p_flags   Matrix as read from the configuration, of any shape.
 * @param array $p_default Matrix to fall back to, cell by cell.
 * @return array Complete matrix of ON and OFF.
 */
function telegram_calendar_notify_flags_normalize( $p_flags, array $p_default ) {

    if( !is_array( $p_flags ) ) {
        $p_flags = array();
    }

    $t_flags = array();

    foreach( telegram_calendar_notify_actions() as $t_action ) {
        foreach( telegram_calendar_notify_targets() as $t_target ) {

            if( isset( $p_flags[$t_action][$t_target] ) ) {
                $t_value = $p_flags[$t_action][$t_target];
            } else if( isset( $p_default[$t_action][$t_target] ) ) {
                $t_value = $p_default[$t_action][$t_target];
            } else {
                $t_value = OFF;
            }

            $t_flags[$t_action][$t_target] = (int)$t_value > 0 ? ON : OFF;
        }
    }

    return $t_flags;
}

/**
 * The notification matrix of the calendar events that applies to a project.
 *
 * The whole matrix is one option, so the cascade of the configuration does
 * the choosing: a project given its own copy uses it, every other one falls
 * back to the global matrix and, failing that, to the built-in one.
 *
 * @param integer $p_project_id Project of the event.
 * @return array Complete matrix of ON and OFF.
 */
function telegram_calendar_notify_flags( $p_project_id = ALL_PROJECTS ) {

    $t_default = plugin_config_get( 'calendar_notify_flags', array(), TRUE );

    return telegram_calendar_notify_flags_normalize(
                              plugin_config_get( 'calendar_notify_flags', $t_default, FALSE, NO_USER, (int)$p_project_id ),
                              $t_default );
}

/**
 * Users to be told about an action on a calendar event through Telegram.
 *
 * The counterpart of telegram_message_collect_recipients() for the events,
 * laid out the way calendar_notify_recipients() of the Calendar plugin is:
 * the matrix of this plugin names the groups - the author, the members, the
 * actor - and every candidate then passes the same checks the mails of the
 * calendar apply, only against the settings of this plugin. The mails of the
 * calendar keep their own matrix and their own personal choices, the way the
 * mails of the core and the Telegram messages about an issue do.
 *
 * The other plugins have the same say they have in the mails of the calendar:
 * EVENT_CALENDAR_NOTIFY_USER_INCLUDE widens the circle before anything is
 * filtered out, EVENT_CALENDAR_NOTIFY_USER_EXCLUDE vetoes a candidate that
 * passed every check - the core signals of the issues are raised by
 * telegram_message_collect_recipients() in the same manner.
 *
 * @param array        $p_event_row       Row of the event, as event_get_row() returns it.
 * @param string       $p_action          Row of the matrix, see telegram_calendar_notify_actions().
 * @param integer      $p_actor_id        User whose action is announced.
 * @param integer|null $p_exclude_user_id User the action is about, who is told on their own.
 * @return array Recipients: user id => telegram user id.
 */
function telegram_calendar_collect_recipients( array $p_event_row, $p_action, $p_actor_id, $p_exclude_user_id = NULL ) {

    $t_event_id = (int)$p_event_row['id'];

    $t_flags = telegram_calendar_notify_flags( (int)$p_event_row['project_id'] );
    $t_flags = $t_flags[$p_action];

    $t_user_ids = array();

    if( ON == $t_flags['author'] ) {
        $t_user_ids[] = (int)$p_event_row['author_id'];
        plugin_log_event( sprintf( 'Calendar event = #%d, add @U%d (author)', $t_event_id, (int)$p_event_row['author_id'] ) );
    } else {
        plugin_log_event( sprintf( 'Calendar event = #%d, skip @U%d (author disabled)', $t_event_id, (int)$p_event_row['author_id'] ) );
    }

    if( ON == $t_flags['members'] ) {
        try {
            $t_member_ids = calendar_api_event_members( $t_event_id );
        } catch( \Mantis\Exceptions\ClientException $t_exception ) {
            plugin_log_event( sprintf( 'Calendar event = #%d, members unavailable: %s', $t_event_id, $t_exception->getMessage() ) );
            $t_member_ids = array();
        }

        foreach( $t_member_ids as $t_member_id ) {
            $t_user_ids[] = (int)$t_member_id;
            plugin_log_event( sprintf( 'Calendar event = #%d, add @U%d (member)', $t_event_id, (int)$t_member_id ) );
        }
    }

    $t_include_data = event_signal( 'EVENT_CALENDAR_NOTIFY_USER_INCLUDE', array( $t_event_id, $p_action ) );

    foreach( $t_include_data as $t_plugin => $t_plugin_answers ) {
        foreach( $t_plugin_answers as $t_included_users ) {

            if( !is_array( $t_included_users ) ) {
                continue;
            }

            foreach( $t_included_users as $t_included_user_id ) {
                $t_user_ids[] = (int)$t_included_user_id;
                plugin_log_event( sprintf( 'Calendar event = #%d, add @U%d (by %s plugin)', $t_event_id, (int)$t_included_user_id, $t_plugin ) );
            }
        }
    }

    $t_pref_field = telegram_calendar_notify_action_pref( $p_action );

    $t_recipients = array();

    foreach( array_unique( $t_user_ids ) as $t_user_id ) {

        if( $t_user_id <= 0 || !user_exists( $t_user_id ) || !user_is_enabled( $t_user_id ) ) {
            plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (user disabled)', $t_event_id, $t_user_id ) );
            continue;
        }

        if( $t_user_id == (int)$p_actor_id && ON != $t_flags['actor'] ) {
            plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (own action)', $t_event_id, $t_user_id ) );
            continue;
        }

        if( $p_exclude_user_id !== NULL && $t_user_id == (int)$p_exclude_user_id ) {
            continue;
        }

        if( OFF == plugin_config_get( $t_pref_field, NULL, FALSE, $t_user_id ) ) {
            plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (pref %s off)', $t_event_id, $t_user_id, $t_pref_field ) );
            continue;
        }

        # the replies of others mean nothing to a user who does not take part
        # in the replies, the rule Calendar applies to its mails
        if( 'rsvp' == $p_action && !calendar_api_rsvp_enabled( $t_user_id ) ) {
            plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (no replies)', $t_event_id, $t_user_id ) );
            continue;
        }

        # the author and the members have access implicitly, this only catches
        # users that were meanwhile removed from the project
        $t_view_threshold = telegram_calendar_call( 'plugin_config_get', array( 'view_event_threshold' ) );

        if( !telegram_calendar_call( 'access_has_event_level', array( $t_view_threshold, $t_event_id, $t_user_id ) ) ) {
            plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (access level)', $t_event_id, $t_user_id ) );
            continue;
        }

        $t_exclude_data = event_signal( 'EVENT_CALENDAR_NOTIFY_USER_EXCLUDE', array( $t_event_id, $p_action, $t_user_id ) );
        $t_excluded     = FALSE;

        foreach( $t_exclude_data as $t_plugin => $t_plugin_answers ) {
            foreach( $t_plugin_answers as $t_plugin_answer ) {
                if( $t_plugin_answer ) {
                    $t_excluded = TRUE;
                    plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (by %s plugin)', $t_event_id, $t_user_id, $t_plugin ) );
                }
            }
        }

        if( $t_excluded ) {
            continue;
        }

        $t_telegram_user_id = telegram_user_get_id_by_user_id( $t_user_id );

        if( $t_telegram_user_id == 0 ) {
            plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (no telegram account)', $t_event_id, $t_user_id ) );
            continue;
        }

        $t_recipients[$t_user_id] = $t_telegram_user_id;
    }

    return $t_recipients;
}

/**
 * Notify the circle of a calendar event about an action on it.
 *
 * The circle is chosen by telegram_calendar_collect_recipients() against the
 * matrix and the personal settings of this plugin, the way the notifications
 * about an issue are chosen against its copy of the settings of the core
 * rather than against the mails. Whether the acting user hears about the own
 * action is a matrix decision, which is why the current user is passed as the
 * actor instead of being filtered here.
 *
 * A deletion is announced while the event can still be read: Calendar raises
 * EVENT_CALENDAR_EVENT_DELETED before it touches the rows, the way the core
 * raises EVENT_BUG_DELETED, and only when the whole event goes - a cancelled
 * occurrence or a cut off tail of a series is a change of the event. The text
 * of that notification carries neither the link nor the .ics file: both would
 * point at an event that is gone by the time the reader taps them.
 *
 * The subscribers of the calendar events are called with this plugin being
 * the current one, so every function of Calendar is called on behalf of
 * Calendar itself, otherwise its tables and settings would be looked up under
 * the name of this plugin.
 *
 * @param integer $p_event_id Identifier of the event.
 * @param string  $p_action   Row of the notification matrix, 'created',
 *                            'updated' or 'deleted', see telegram_calendar_notify_actions().
 * @return void
 */
function telegram_calendar_message_event( $p_event_id, $p_action ) {

    if( !telegram_calendar_available() || OFF == plugin_config_get( 'enable_telegram_message_notification' ) ) {
        return;
    }

    $t_event_row = telegram_calendar_call( 'event_get_row', array( (int)$p_event_id ) );

    if( !is_array( $t_event_row ) ) {
        return;
    }

    $t_recipients = telegram_calendar_collect_recipients( $t_event_row, $p_action, auth_get_current_user_id() );
    $t_deleted    = 'deleted' == $p_action;

    foreach( $t_recipients as $t_user_id => $t_telegram_user_id ) {

        lang_push( user_pref_get_language( $t_user_id, (int)$t_event_row['project_id'] ) );

        $t_text = telegram_calendar_event_message_compose( $t_event_row, plugin_lang_get( 'event_message_' . $p_action ), $t_user_id, /* link */ !$t_deleted );

        # a moved event drops the replies given to it, so the invitation is
        # answered anew under the notification about the change
        $t_keyboard = $t_deleted ? NULL : telegram_calendar_event_keyboard( $t_event_row, $t_user_id );

        lang_pop();

        plugin_log_event( sprintf( 'Calendar event = #%d, %s, add @U%d', (int)$p_event_id, $p_action, $t_user_id ) );

        if( $t_deleted ) {
            telegram_session_send_message( $t_telegram_user_id, array( 'text' => $t_text ) );
        } else {
            telegram_calendar_event_notification_send( (int)$p_event_id, (int)$t_event_row['project_id'], $t_user_id, $t_telegram_user_id, $t_text, $t_keyboard );
        }
    }
}

/**
 * Notify about a user joining or leaving the members of a calendar event.
 *
 * Laid out the way Calendar mails it: the user the change is about is told
 * on their own - whoever made the change is not told about it - and the
 * others are told by the row of the matrix, the user in question left out.
 * The one who joins gets the invitation along with the buttons of the reply
 * and the .ics file; the one who leaves gets neither the file nor the link,
 * as the event is not theirs any more.
 *
 * @param integer $p_event_id Identifier of the event.
 * @param integer $p_user_id  User who joined or left.
 * @param string  $p_action   'member_added' or 'member_removed'.
 * @param integer $p_actor_id User who made the change.
 * @return void
 */
function telegram_calendar_message_member( $p_event_id, $p_user_id, $p_action, $p_actor_id ) {

    if( !telegram_calendar_available() || OFF == plugin_config_get( 'enable_telegram_message_notification' ) ) {
        return;
    }

    $t_event_row = telegram_calendar_call( 'event_get_row', array( (int)$p_event_id ) );

    if( !is_array( $t_event_row ) ) {
        return;
    }

    $t_added     = 'member_added' == $p_action;
    $t_member_id = (int)$p_user_id;

    if( telegram_calendar_member_notified( $t_event_row, $t_member_id, $p_action, $p_actor_id ) ) {
        $t_telegram_user_id = telegram_user_get_id_by_user_id( $t_member_id );

        lang_push( user_pref_get_language( $t_member_id, (int)$t_event_row['project_id'] ) );

        $t_text     = telegram_calendar_event_message_compose( $t_event_row, plugin_lang_get( 'event_message_' . $p_action ), $t_member_id, /* link */ $t_added );
        $t_keyboard = $t_added ? telegram_calendar_event_keyboard( $t_event_row, $t_member_id ) : NULL;

        lang_pop();

        plugin_log_event( sprintf( 'Calendar event = #%d, %s, add @U%d (the member)', (int)$p_event_id, $p_action, $t_member_id ) );

        if( $t_added ) {
            telegram_calendar_event_notification_send( (int)$p_event_id, (int)$t_event_row['project_id'], $t_member_id, $t_telegram_user_id, $t_text, $t_keyboard );
        } else {
            telegram_session_send_message( $t_telegram_user_id, array( 'text' => $t_text ) );
        }
    }

    $t_member_name = user_get_name( $t_member_id );
    $t_recipients  = telegram_calendar_collect_recipients( $t_event_row, $p_action, $p_actor_id, $t_member_id );

    foreach( $t_recipients as $t_user_id => $t_telegram_user_id ) {

        lang_push( user_pref_get_language( $t_user_id, (int)$t_event_row['project_id'] ) );

        $t_header = sprintf( plugin_lang_get( 'event_message_' . $p_action . '_others' ), $t_member_name );
        $t_text   = telegram_calendar_event_message_compose( $t_event_row, $t_header, $t_user_id );

        lang_pop();

        plugin_log_event( sprintf( 'Calendar event = #%d, %s of @U%d, add @U%d', (int)$p_event_id, $p_action, $t_member_id, $t_user_id ) );

        telegram_session_send_message( $t_telegram_user_id, array( 'text' => $t_text ) );
    }
}

/**
 * Whether the user who joined or left an event is told about it.
 *
 * The counterpart of calendar_notify_member() of Calendar: no matrix cell
 * decides it, the change concerns the user and nobody else, so only the
 * user themselves may refuse the news - by the personal switch of its kind.
 *
 * @param array   $p_event_row Row of the event, as event_get_row() returns it.
 * @param integer $p_user_id   User who joined or left.
 * @param string  $p_action    'member_added' or 'member_removed'.
 * @param integer $p_actor_id  User who made the change.
 * @return boolean
 */
function telegram_calendar_member_notified( array $p_event_row, $p_user_id, $p_action, $p_actor_id ) {

    if( $p_user_id <= 0 || !user_exists( $p_user_id ) || !user_is_enabled( $p_user_id ) ) {
        return FALSE;
    }

    if( $p_user_id == (int)$p_actor_id ) {
        return FALSE;
    }

    if( OFF == plugin_config_get( telegram_calendar_notify_action_pref( $p_action ), NULL, FALSE, $p_user_id ) ) {
        return FALSE;
    }

    $t_view_threshold = telegram_calendar_call( 'plugin_config_get', array( 'view_event_threshold' ) );

    if( !telegram_calendar_call( 'access_has_event_level', array( $t_view_threshold, (int)$p_event_row['id'], $p_user_id ) ) ) {
        return FALSE;
    }

    return telegram_user_get_id_by_user_id( $p_user_id ) != 0;
}

/**
 * Notify about a member replying whether they will take part in an event.
 *
 * The recipients are named by the 'rsvp' row of the matrix, the member who
 * replied left out, and a reply of the author to their own event is
 * announced to nobody - the rules Calendar applies to its mails.
 *
 * @param integer $p_event_id Identifier of the event.
 * @param integer $p_user_id  Member who replied.
 * @param integer $p_status   The reply, one of the CALENDAR_RSVP_* constants.
 * @return void
 */
function telegram_calendar_message_rsvp( $p_event_id, $p_user_id, $p_status ) {

    if( !telegram_calendar_available() || OFF == plugin_config_get( 'enable_telegram_message_notification' ) ) {
        return;
    }

    $t_event_row = telegram_calendar_call( 'event_get_row', array( (int)$p_event_id ) );

    if( !is_array( $t_event_row ) || (int)$p_user_id == (int)$t_event_row['author_id'] ) {
        return;
    }

    $t_member_name = user_get_name( (int)$p_user_id );
    $t_recipients  = telegram_calendar_collect_recipients( $t_event_row, 'rsvp', (int)$p_user_id, (int)$p_user_id );

    foreach( $t_recipients as $t_user_id => $t_telegram_user_id ) {

        lang_push( user_pref_get_language( $t_user_id, (int)$t_event_row['project_id'] ) );

        $t_header = sprintf( plugin_lang_get( 'event_message_rsvp' ), $t_member_name,
                             plugin_lang_get( 'event_rsvp_status_' . telegram_calendar_rsvp_name( $p_status ) ) );
        $t_text   = telegram_calendar_event_message_compose( $t_event_row, $t_header, $t_user_id );

        lang_pop();

        plugin_log_event( sprintf( 'Calendar event = #%d, rsvp of @U%d, add @U%d', (int)$p_event_id, (int)$p_user_id, $t_user_id ) );

        telegram_session_send_message( $t_telegram_user_id, array( 'text' => $t_text ) );
    }
}

/**
 * Whether the iCalendar file is offered to the users at all.
 *
 * The switch of the administrator, read where the preference of a user is
 * shown or saved: while the file is off for everybody, the personal choice is
 * neither asked for nor applied.
 *
 * @return boolean
 */
function telegram_calendar_ics_offered() {

    return telegram_calendar_available() && TELEGRAM_ICS_OFF != (int)plugin_config_get( 'calendar_ics_mode' );
}

/**
 * Whether the given user gets the iCalendar file along with the notifications.
 *
 * The personal choice is the 'calendar_ics_attach' option of the user, and it
 * is not declared among the defaults of the plugin on purpose: the value a
 * user gets before making a choice depends on the mode - on when the file goes
 * to everybody, off when the users are to turn it on themselves - so the
 * default is computed here rather than stored.
 *
 * @param integer $p_user_id    User the notification goes to.
 * @param integer $p_project_id Project of the event.
 * @return boolean
 */
function telegram_calendar_ics_wanted( $p_user_id, $p_project_id ) {

    if( !telegram_calendar_ics_offered() ) {
        return FALSE;
    }

    $t_default = TELEGRAM_ICS_ON == (int)plugin_config_get( 'calendar_ics_mode' ) ? ON : OFF;

    return ON == (int)plugin_config_get( 'calendar_ics_attach', $t_default, FALSE, (int)$p_user_id, (int)$p_project_id );
}

/**
 * Deliver the notification about a calendar event along with its iCalendar file.
 *
 * The file goes as a document of the chat rather than as a link in the text:
 * a tap on the document opens the calendar of the phone right away, while a
 * link leads through the login page of the tracker first. For the same reason
 * Telegram is given the content instead of the URL of the file - fetching the
 * URL, its servers would get the login page too.
 *
 * The text is the caption of the document, so the notification is a single
 * message of the chat. A caption is limited to 1024 characters though, a
 * quarter of a text message, and a long description or a long list of issues
 * does not fit: such a text goes as a message of its own, the document being
 * sent as a reply to it, which keeps the two together on the screen.
 *
 * Whether the file goes at all is the choice of the administrator and of the
 * recipient, see telegram_calendar_ics_wanted(). The file is built by Calendar
 * for the recipient, so the issues it lists are the ones the recipient may
 * view, and a recipient who may not view the event gets the text alone. The
 * document is uploaded from memory: the multipart part is named after the uri
 * metadata of the stream, which is where the file name goes, so nothing is
 * written to the disk.
 *
 * A failure of the document is logged and nothing else: the text is sent
 * anyway, and the file can be downloaded from the event page.
 *
 * @param integer $p_event_id         Identifier of the event.
 * @param integer $p_project_id       Project of the event.
 * @param integer $p_user_id          Recipient of the notification.
 * @param integer $p_telegram_user_id Telegram account of the recipient.
 * @param string  $p_text             Text of the notification.
 * @param Longman\TelegramBot\Entities\InlineKeyboard|null $p_keyboard Buttons
 *                                    going with the text, null for none.
 * @return void
 */
function telegram_calendar_event_notification_send( $p_event_id, $p_project_id, $p_user_id, $p_telegram_user_id, $p_text, $p_keyboard = NULL ) {

    # the buttons go with the text, wherever the text ends up
    $t_text_data = array( 'text' => $p_text );

    if( $p_keyboard !== NULL ) {
        $t_text_data['reply_markup'] = $p_keyboard;
    }

    if( !telegram_calendar_ics_wanted( $p_user_id, $p_project_id ) ) {
        telegram_session_send_message( $p_telegram_user_id, $t_text_data );
        return;
    }

    try {
        $t_ics = calendar_api_event_ics( (int)$p_event_id, (int)$p_user_id );
    } catch( \Mantis\Exceptions\ClientException $t_exception ) {
        plugin_log_event( sprintf( 'Calendar event = #%d, ics for @U%d unavailable: %s', (int)$p_event_id, (int)$p_user_id, $t_exception->getMessage() ) );
        telegram_session_send_message( $p_telegram_user_id, $t_text_data );
        return;
    }

    $t_data = array( 'chat_id' => $p_telegram_user_id );

    if( mb_strlen( $p_text, 'UTF-8' ) <= TELEGRAM_CAPTION_LENGTH_MAX ) {
        $t_data['caption'] = $p_text;

        if( $p_keyboard !== NULL ) {
            $t_data['reply_markup'] = $p_keyboard;
        }
    } else {
        $t_results = telegram_session_send_message( $p_telegram_user_id, $t_text_data );
        $t_last    = end( $t_results );

        if( $t_last instanceof \Longman\TelegramBot\Entities\ServerResponse && $t_last->isOk() ) {
            $t_data['reply_to_message_id'] = $t_last->getResult()->getMessageId();
        }
    }

    $t_resource = fopen( 'php://temp', 'r+' );
    fwrite( $t_resource, $t_ics['content'] );
    rewind( $t_resource );

    $t_data['document'] = new \GuzzleHttp\Psr7\Stream( $t_resource, array( 'metadata' => array( 'uri' => $t_ics['filename'] ) ) );

    telegram_session_start();

    try {
        $t_response = \Longman\TelegramBot\Request::sendDocument( $t_data );

        if( !$t_response->isOk() ) {
            plugin_log_event( sprintf( 'ERROR! Calendar event = #%d, sendDocument to %d failed: %s', (int)$p_event_id, (int)$p_telegram_user_id, $t_response->getDescription() ) );
        }
    } catch( Exception $t_error ) {
        plugin_log_event( sprintf( 'ERROR! Calendar event = #%d, sendDocument to %d failed: %s', (int)$p_event_id, (int)$p_telegram_user_id, $t_error->getMessage() ) );
    }
}

/**
 * Remind one user about an occurrence of a calendar event coming up.
 *
 * The recipient is chosen by the reminder dispatcher of Calendar: the
 * reminders of the event or the personal defaults of the recipient, the
 * personal opt-out and the view threshold of the event are all applied there,
 * so this plugin adds nothing but its own switches - the global one of the
 * administrator and the personal one - and its transport requirement, a
 * linked Telegram account. The switches are a choice about the channel, not
 * about the reminders: whoever turns them off keeps the mails.
 * The reminder goes alongside the mail of Calendar, the way the notifications
 * about the issues go alongside the mails of the core.
 *
 * The occurrence is what the reminder is about, not the first start of the
 * event: a recurring event is stored once while every occurrence of it is
 * reminded of, so the dates and the link of the text are moved to the
 * occurrence, the length of the event being kept.
 *
 * @param integer $p_event_id   Identifier of the event.
 * @param integer $p_occurrence Timestamp the occurrence starts at.
 * @param integer $p_user_id    Recipient of the reminder.
 * @param integer $p_offset     Seconds before the start the reminder was asked for.
 * @return void
 */
function telegram_calendar_message_reminder( $p_event_id, $p_occurrence, $p_user_id, $p_offset ) {

    if( !telegram_calendar_reminders_offered() || OFF == plugin_config_get( 'enable_telegram_message_notification' ) ) {
        return;
    }

    $t_telegram_user_id = telegram_user_get_id_by_user_id( (int)$p_user_id );

    if( $t_telegram_user_id == 0 ) {
        return;
    }

    if( OFF == plugin_config_get( 'telegram_message_on_event_reminder', NULL, FALSE, (int)$p_user_id ) ) {
        plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (pref telegram_message_on_event_reminder off)', (int)$p_event_id, (int)$p_user_id ) );
        return;
    }

    # a reminder put off past the start by Calendar itself - its own pages
    # allow that - comes with a negative offset, and nothing is reminded of
    # in the chat once the event is on
    if( (int)$p_offset < 0 ) {
        plugin_log_event( sprintf( 'Calendar event = #%d, drop @U%d (reminder after the start)', (int)$p_event_id, (int)$p_user_id ) );
        return;
    }

    $t_event_row = telegram_calendar_call( 'event_get_row', array( (int)$p_event_id ) );

    if( !is_array( $t_event_row ) ) {
        return;
    }

    # the buttons are built against the stored row: the end of the occurrence
    # is computed out of it, which the row moved to the occurrence breaks
    $t_occurrence_row              = $t_event_row;
    $t_occurrence_row['date_to']   = telegram_calendar_occurrence_end( $t_event_row, (int)$p_occurrence );
    $t_occurrence_row['date_from'] = (int)$p_occurrence;

    lang_push( user_pref_get_language( (int)$p_user_id, (int)$t_event_row['project_id'] ) );

    $t_text     = telegram_calendar_event_message_compose( $t_occurrence_row, telegram_calendar_reminder_header( (int)$p_offset ), (int)$p_user_id );
    $t_keyboard = telegram_calendar_event_keyboard( $t_event_row, (int)$p_user_id, (int)$p_occurrence );

    lang_pop();

    plugin_log_event( sprintf( 'Calendar event = #%d, reminder at %s, add @U%d', (int)$p_event_id, date( 'c', (int)$p_occurrence ), (int)$p_user_id ) );

    $t_data = array( 'text' => $t_text );

    if( $t_keyboard !== NULL ) {
        $t_data['reply_markup'] = $t_keyboard;
    }

    telegram_session_send_message( $t_telegram_user_id, $t_data );
}

/**
 * The first line of a reminder.
 *
 * A reminder put off to the start of the occurrence has no distance left,
 * so it says the event starts instead of counting down to it.
 *
 * @param integer $p_offset Seconds before the start.
 * @return string
 */
function telegram_calendar_reminder_header( $p_offset ) {

    if( $p_offset == 0 ) {
        return plugin_lang_get( 'event_message_reminder_now' );
    }

    # the distance is worded by Calendar, in the units it was entered in
    return sprintf( plugin_lang_get( 'event_message_reminder' ),
                    telegram_calendar_call( 'calendar_reminder_format_offset', array( (int)$p_offset ) ) );
}

/**
 * The moment an occurrence of an event ends.
 *
 * A single event keeps the end of its only occurrence in date_to, a series
 * keeps the length of one occurrence in duration - date_to of a series is
 * where the series itself stops.
 *
 * @param array   $p_event_row  Row of the event, as event_get_row() returns it.
 * @param integer $p_occurrence Timestamp the occurrence starts at.
 * @return integer
 */
function telegram_calendar_occurrence_end( array $p_event_row, $p_occurrence ) {

    $t_duration = isset( $p_event_row['duration'] ) ? (int)$p_event_row['duration'] : 0;

    if( $t_duration > 0 ) {
        return (int)$p_occurrence + $t_duration;
    }

    return (int)$p_occurrence + (int)$p_event_row['date_to'] - (int)$p_event_row['date_from'];
}

/**
 * The reminders of the user about the event, as far as the buttons under a
 * notification can tell and change them.
 *
 * The switch turns off what the user is reminded of by removing every
 * offset, which leaves a personal set that is empty; it turns back on by
 * dropping that set. Reminders that are off for any other reason - switched
 * off for the instance or in the account of the user - are not the business
 * of the buttons, and neither are those of a user who is not reminded about
 * the event at all.
 *
 * @param integer $p_event_id Identifier of the event.
 * @param integer $p_user_id  User the reminders are for.
 * @return array|null array( 'off' => whether the switch is off, 'offsets' =>
 *                    seconds before the start, 'held' => why the reminders
 *                    are held back, see calendar_api_event_reminders() ),
 *                    null when there are no buttons.
 */
function telegram_calendar_user_reminders_state( $p_event_id, $p_user_id ) {

    try {
        $t_reminders = calendar_api_event_reminders( (int)$p_event_id, (int)$p_user_id );
    } catch( \Mantis\Exceptions\ClientException $t_exception ) {
        return NULL;
    }

    if( !$t_reminders['enabled'] || !$t_reminders['is_recipient'] || $t_reminders['opted_out'] ) {
        return NULL;
    }

    return array(
                              'off'     => empty( $t_reminders['offsets'] ) && 'personal' == $t_reminders['source'] && $t_reminders['held'] === NULL,
                              'offsets' => $t_reminders['offsets'],
                              'held'    => $t_reminders['held'],
    );
}

/**
 * The moment a reminder put off to the given minutes before the start goes out at.
 *
 * @param integer $p_occurrence Timestamp the occurrence starts at.
 * @param integer $p_minutes    Minutes before the start, 0 for the start itself.
 * @return integer Unix timestamp.
 */
function telegram_calendar_snooze_fire_at( $p_occurrence, $p_minutes ) {

    return (int)$p_occurrence - (int)$p_minutes * 60;
}

/**
 * The name of a reply the lang strings are keyed by.
 *
 * @param integer $p_status One of the CALENDAR_RSVP_* constants.
 * @return string
 */
function telegram_calendar_rsvp_name( $p_status ) {

    switch( (int)$p_status ) {
        case CALENDAR_RSVP_ACCEPTED:
            return 'accepted';

        case CALENDAR_RSVP_TENTATIVE:
            return 'tentative';

        case CALENDAR_RSVP_DECLINED:
            return 'declined';
    }

    return 'none';
}

/**
 * The reply of a user to the invitation to an event, null when the user is
 * not invited: not a member, the author of the event, or somebody who does
 * not take part in the replies, unless asked to disregard that.
 *
 * @param array   $p_event_row      Row of the event, as event_get_row() returns it.
 * @param integer $p_user_id        User asked about.
 * @param boolean $p_while_disabled Whether the reply is told even while the
 *                                  user does not take part in the replies.
 * @return integer|null One of the CALENDAR_RSVP_* constants.
 */
function telegram_calendar_rsvp_status( array $p_event_row, $p_user_id, $p_while_disabled = FALSE ) {

    # the author is marked as taking part by the creation itself, the replies
    # are the business of the invited ones
    if( (int)$p_user_id == (int)$p_event_row['author_id'] ) {
        return NULL;
    }

    if( !$p_while_disabled && !calendar_api_rsvp_enabled( (int)$p_user_id ) ) {
        return NULL;
    }

    try {
        $t_statuses = calendar_api_event_member_statuses( (int)$p_event_row['id'] );
    } catch( \Mantis\Exceptions\ClientException $t_exception ) {
        return NULL;
    }

    return array_key_exists( (int)$p_user_id, $t_statuses ) ? (int)$t_statuses[(int)$p_user_id] : NULL;
}

/**
 * The buttons under a notification about an event: under an invitation the
 * reply to it and the reminders of the event, under a reminder the ways to
 * put the reminder off; both carry the switch of the reminders.
 *
 * The reply given and the moment the reminder is put off to are marked on
 * their buttons, so the buttons show the state and let it be changed. Every
 * button carries the whole state of the message - the occurrence of the
 * reminder and the minutes it is put off to - since Calendar tells neither
 * back, and a press on any of them draws the buttons anew out of it.
 *
 * A reminder can be put off only to a moment before the start, and not at
 * all by a member who declined - such a member is not reminded any more.
 *
 * Every button carries array( event, value, occurrence, snoozed ), the value
 * being the reply, the minutes or nothing, see telegram_calendar_reply().
 *
 * @param array   $p_event_row  Row of the event, as event_get_row() returns it.
 * @param integer $p_user_id    Recipient of the notification.
 * @param integer $p_occurrence Start of the occurrence a reminder is about, 0 for no reminder.
 * @param boolean $p_keep_rsvp  Whether the buttons of the reply stay while the
 *                              user does not take part in the replies: a
 *                              reply refused for that is pressed again once
 *                              the replies are switched on.
 * @param integer $p_snoozed    Minutes before the start the reminder is put
 *                              off to, TELEGRAM_EVENT_SNOOZE_NONE for none.
 * @return Longman\TelegramBot\Entities\InlineKeyboard|null Null when there is no button.
 */
function telegram_calendar_event_keyboard( array $p_event_row, $p_user_id, $p_occurrence = 0, $p_keep_rsvp = FALSE, $p_snoozed = TELEGRAM_EVENT_SNOOZE_NONE ) {

    $t_event_id = (int)$p_event_row['id'];
    $t_status   = telegram_calendar_rsvp_status( $p_event_row, $p_user_id, $p_keep_rsvp );
    $t_rows     = array();

    # a reminder only lets its time be changed, the reply belongs to the invitation
    if( $t_status !== NULL && $p_occurrence == 0 ) {
        $t_row = array();

        foreach( array( CALENDAR_RSVP_ACCEPTED, CALENDAR_RSVP_TENTATIVE, CALENDAR_RSVP_DECLINED ) as $t_reply ) {
            $t_label = plugin_lang_get( 'event_rsvp_button_' . telegram_calendar_rsvp_name( $t_reply ) );

            if( $t_reply == $t_status ) {
                $t_label = plugin_lang_get( 'event_rsvp_chosen_mark' ) . $t_label;
            }

            $t_row[] = keyboard_event_reply_button( $t_label, TELEGRAM_EVENT_REPLY_RSVP, array( $t_event_id, $t_reply, (int)$p_occurrence, (int)$p_snoozed ) );
        }

        $t_rows[] = $t_row;
    }

    # a reminder is put off to a number of minutes before the start - nothing
    # is reminded of once the event is on - and only the moments still ahead
    # are offered
    # an invitation offers its reminders once it is accepted, even if only
    # tentatively: an unanswered one is still to be decided on, a declined
    # one takes no reminder at all. The author and a user who does not take
    # part in the replies have nothing to answer and get the reminders at once.
    $t_invited = $p_occurrence == 0 && $t_status === CALENDAR_RSVP_NONE;

    $t_reminders = NULL;

    if( $t_status !== CALENDAR_RSVP_DECLINED && !$t_invited ) {
        $t_reminders = telegram_calendar_user_reminders_state( $t_event_id, $p_user_id );
    }

    # under a notification the user picks the reminders of the event: every
    # button switches one offset of the personal set, the ones that apply are
    # marked; a single event is not reminded of at a moment already gone
    if( $p_occurrence == 0 && $t_reminders !== NULL && $t_reminders['held'] !== 'declined' ) {
        $t_buttons = array();

        foreach( TELEGRAM_EVENT_REMINDER_MINUTES as $t_minutes ) {
            if( is_blank( $p_event_row['recurrence_pattern'] )
                    && (int)$p_event_row['date_from'] - $t_minutes * 60 <= time() ) {
                continue;
            }

            $t_label = sprintf( plugin_lang_get( 'event_snooze_button' ), $t_minutes );

            if( in_array( $t_minutes * 60, $t_reminders['offsets'] ) ) {
                $t_label = plugin_lang_get( 'event_rsvp_chosen_mark' ) . $t_label;
            }

            $t_buttons[] = keyboard_event_reply_button( $t_label, TELEGRAM_EVENT_REPLY_REMINDER,
                                                        array( $t_event_id, $t_minutes, 0, (int)$p_snoozed ) );
        }

        foreach( array_chunk( $t_buttons, 3 ) as $t_row ) {
            $t_rows[] = $t_row;
        }
    }

    if( $p_occurrence > 0 && $t_status !== CALENDAR_RSVP_DECLINED ) {
        $t_buttons = array();

        foreach( TELEGRAM_EVENT_SNOOZE_MINUTES as $t_minutes ) {
            if( telegram_calendar_snooze_fire_at( $p_occurrence, $t_minutes ) <= time() ) {
                continue;
            }

            $t_label = sprintf( plugin_lang_get( 'event_snooze_button' ), $t_minutes );

            if( $t_minutes == $p_snoozed ) {
                $t_label = plugin_lang_get( 'event_rsvp_chosen_mark' ) . $t_label;
            }

            $t_buttons[] = keyboard_event_reply_button( $t_label, TELEGRAM_EVENT_REPLY_SNOOZE,
                                                        array( $t_event_id, $t_minutes, (int)$p_occurrence, (int)$p_snoozed ) );
        }

        foreach( array_chunk( $t_buttons, 3 ) as $t_row ) {
            $t_rows[] = $t_row;
        }
    }

    # the reminders still to come about the event are switched off, and on
    # again with the same button
    if( $t_reminders !== NULL && ( $t_reminders['off'] || !empty( $t_reminders['offsets'] ) ) ) {
        $t_label = plugin_lang_get( 'event_reminders_off_button' );

        if( $t_reminders['off'] ) {
            $t_label = plugin_lang_get( 'event_rsvp_chosen_mark' ) . $t_label;
        }

        $t_rows[] = array( keyboard_event_reply_button( $t_label, TELEGRAM_EVENT_REPLY_REMINDERS_OFF,
                                                        array( $t_event_id, 0, (int)$p_occurrence, (int)$p_snoozed ) ) );
    }

    if( empty( $t_rows ) ) {
        return NULL;
    }

    $t_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    foreach( $t_rows as $t_row ) {
        call_user_func_array( array( $t_keyboard, 'addRow' ), $t_row );
    }

    return $t_keyboard;
}

/**
 * Process a press on a button under a notification about an event: a reply
 * to the invitation or a reminder put off.
 *
 * The press acts on behalf of the user pressing - the payload names the
 * event, never the user - and Calendar checks every rule itself; a refusal
 * is shown as an alert. The buttons are redrawn to the new state: the reply
 * given and the moment the reminder is put off to are marked, and the
 * reminders switched off take the buttons about them away.
 *
 * @param array $p_payload Payload of the button pressed.
 * @return Longman\TelegramBot\Entities\InlineKeyboard|null Buttons to put under
 *         the message, null when they stay as they are.
 */
function telegram_calendar_reply( $p_payload ) {

    $t_no_buttons = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    if( !telegram_calendar_available() ) {
        telegram_callback_alert_set( plugin_lang_get( 'event_calendar_missing' ) );

        return $t_no_buttons;
    }

    $t_action = is_array( $p_payload ) ? key( $p_payload ) : NULL;
    $t_values = $t_action === NULL ? NULL : $p_payload[$t_action];

    if( !is_array( $t_values ) || count( $t_values ) != 4 ) {
        return NULL;
    }

    list( $t_event_id, $t_value, $t_occurrence, $t_snoozed ) = array_map( 'intval', array_values( $t_values ) );

    # the event may be deleted long after the buttons were drawn, and the row
    # of a missing event is an error of Calendar rather than an answer
    $t_event_row = telegram_calendar_call( 'event_cache_row', array( $t_event_id, /* trigger_errors */ FALSE ) );

    if( !is_array( $t_event_row ) ) {
        telegram_callback_alert_set( plugin_lang_get( 'event_reply_gone' ) );

        return $t_no_buttons;
    }

    $t_user_id       = (int)auth_get_current_user_id();
    $t_settings_link = FALSE;

    try {
        switch( $t_action ) {
            case TELEGRAM_EVENT_REPLY_RSVP:
                # the buttons outlive the choice they were drawn under, and the
                # refusal of Calendar would only say the access is denied
                if( !calendar_api_rsvp_enabled( $t_user_id ) ) {
                    telegram_callback_alert_set( plugin_lang_get( 'event_rsvp_disabled' ) );
                    $t_settings_link = TRUE;
                    break;
                }

                calendar_api_event_member_status_set( $t_event_id, $t_user_id, $t_value );
                break;

            case TELEGRAM_EVENT_REPLY_SNOOZE:
                $t_fire_at = telegram_calendar_snooze_fire_at( $t_occurrence, $t_value );

                # said plainly here, Calendar would refuse it with an invalid
                # value of a field the user has never seen; the buttons are
                # drawn anew by the time of the press
                if( $t_fire_at <= time() ) {
                    telegram_callback_alert_set( plugin_lang_get( 'event_snooze_passed' ) );
                    break;
                }

                # a reminder is put off by a user who wants to be reminded, so
                # the reminders switched off under it are switched on again -
                # Calendar drops a put off reminder of a user who has them off
                $t_state = telegram_calendar_user_reminders_state( $t_event_id, $t_user_id );

                if( $t_state !== NULL && $t_state['off'] ) {
                    calendar_api_event_reminder_reset( $t_event_id, $t_user_id );
                }

                # one put off reminder per occurrence, a second press moves it
                calendar_api_event_reminder_snooze( $t_event_id, $t_occurrence, $t_user_id, $t_fire_at );

                $t_snoozed = $t_value;

                telegram_callback_alert_set( sprintf( plugin_lang_get( 'event_snooze_done' ),
                                                      telegram_calendar_user_time_display( $t_fire_at, $t_user_id ) ) );
                break;

            case TELEGRAM_EVENT_REPLY_REMINDER:
                $t_state = telegram_calendar_user_reminders_state( $t_event_id, $t_user_id );

                if( $t_state === NULL ) {
                    break;
                }

                # a picked offset of the personal set goes, any other joins it;
                # Calendar keeps the limits of the set and lifts the hold of a
                # user who has not replied yet
                if( in_array( $t_value * 60, $t_state['offsets'] ) ) {
                    calendar_api_event_reminder_remove( $t_event_id, $t_user_id, $t_value * 60 );
                } else {
                    calendar_api_event_reminder_add( $t_event_id, $t_user_id, $t_value * 60 );
                }
                break;

            case TELEGRAM_EVENT_REPLY_REMINDERS_OFF:
                $t_state = telegram_calendar_user_reminders_state( $t_event_id, $t_user_id );

                if( $t_state === NULL ) {
                    break;
                }

                # the switch is off: the set of the event, or the defaults of
                # the user, apply again
                if( $t_state['off'] ) {
                    calendar_api_event_reminder_reset( $t_event_id, $t_user_id );
                    telegram_callback_alert_set( plugin_lang_get( 'event_reminders_on_done' ) );
                    break;
                }

                # every offset of the user goes, which leaves them a set of
                # their own that is empty - not the set of the event again
                foreach( $t_state['offsets'] as $t_offset ) {
                    calendar_api_event_reminder_remove( $t_event_id, $t_user_id, (int)$t_offset );
                }

                # the reminder put off before goes along: Calendar drops a put
                # off reminder of a user who has the reminders off
                $t_snoozed = TELEGRAM_EVENT_SNOOZE_NONE;

                telegram_callback_alert_set( plugin_lang_get( 'event_reminders_off_done' ) );
                break;

            default:
                return NULL;
        }
    } catch( \Mantis\Exceptions\ClientException $t_exception ) {
        plugin_log_event( sprintf( 'Calendar event = #%d, %s of @U%d refused: %s', $t_event_id, $t_action, $t_user_id, $t_exception->getMessage() ) );
        telegram_callback_alert_set( $t_exception->getMessage() );
    }

    $t_keyboard = telegram_calendar_event_keyboard( $t_event_row, $t_user_id, $t_occurrence, /* keep_rsvp */ $t_settings_link, $t_snoozed );

    if( $t_keyboard === NULL ) {
        $t_keyboard = $t_no_buttons;
    }

    # an alert shows no links, so the way to the switch of the replies is a
    # button under the message instead
    if( $t_settings_link ) {
        $t_keyboard->addRow( array(
                                  'text' => plugin_lang_get( 'event_rsvp_settings_button' ),
                                  'url'  => config_get_global( 'path' ) . plugin_page( 'reminders_page', /* redirect */ TRUE, 'Calendar' ),
        ) );
    }

    return $t_keyboard;
}

/**
 * A moment the way the given user reads it, in the timezone of the user.
 *
 * @param integer $p_timestamp Unix timestamp.
 * @param integer $p_user_id   User the moment is shown to.
 * @return string
 */
function telegram_calendar_user_time_display( $p_timestamp, $p_user_id ) {

    $t_timezone = user_pref_get_pref( $p_user_id, 'timezone' );

    date_set_timezone( is_blank( $t_timezone ) ? config_get_global( 'default_timezone' ) : $t_timezone );

    $t_text = telegram_event_datetime_display( $p_timestamp );

    date_restore_timezone();

    return $t_text;
}

/**
 * The text of the notification about a calendar event.
 *
 * The text is laid out the way the notifications about the issues are: the
 * same separators, the labels padded to the same column and the link to the
 * event at the end, so both kinds of notifications read alike in the chat.
 *
 * The text is built for one recipient, as email_build_visible_bug_data() does
 * it for the issues: the members are listed only when the recipient may see
 * the member list, of the linked issues only those the recipient may view, and
 * the dates are rendered in the timezone of the recipient, the way the
 * reminder mails of Calendar do it - the notification may well be sent from a
 * cron job, where the timezone of the process means nothing to the reader.
 *
 * @param array   $p_event_row Row of the event, as event_get_row() returns it.
 * @param string  $p_header    First line of the text.
 * @param integer $p_user_id   Recipient of the notification.
 * @param boolean $p_link      Whether the link to the event page closes the
 *                             text; off for an event that is being deleted.
 * @return string
 */
function telegram_calendar_event_message_compose( array $p_event_row, $p_header, $p_user_id, $p_link = TRUE ) {

    $t_separator1 = plugin_config_get( 'telegram_message_separator1' ) . PHP_EOL;
    $t_separator2 = plugin_config_get( 'telegram_message_separator2' ) . PHP_EOL;
    $t_event_id   = (int)$p_event_row['id'];

    $t_timezone = user_pref_get_pref( $p_user_id, 'timezone' );

    if( is_blank( $t_timezone ) ) {
        $t_timezone = config_get_global( 'default_timezone' );
    }

    date_set_timezone( $t_timezone );

    $t_message = $p_header . PHP_EOL;
    $t_message .= $t_separator1;
    $t_message .= telegram_message_format_line( lang_get( 'email_project' ), project_get_name( (int)$p_event_row['project_id'], /* trigger_errors */ FALSE ) );
    $t_message .= $t_separator2;
    $t_message .= telegram_message_format_line( plugin_lang_get( 'event_name' ), $p_event_row['name'] );

    # the column is missing in the older Calendar versions and empty in most
    # events, the block is shown only when there is something to show
    if( !empty( $p_event_row['description'] ) ) {
        $t_message .= $t_separator2;
        $t_message .= plugin_lang_get( 'event_description' ) . ':' . PHP_EOL . $p_event_row['description'] . PHP_EOL;
    }

    $t_message .= $t_separator1;
    $t_message .= telegram_message_format_line( plugin_lang_get( 'event_id' ), $t_event_id );
    $t_message .= telegram_message_format_line( plugin_lang_get( 'event_author' ),user_get_name( (int)$p_event_row['author_id'] ) );

    $t_members = telegram_calendar_event_members_display( $t_event_id, $p_user_id );

    if( !is_blank( $t_members ) ) {
        $t_message .= telegram_message_format_line( plugin_lang_get( 'event_members' ), $t_members );
    }

    $t_message .= $t_separator1;
    $t_message .= telegram_message_format_line( plugin_lang_get( 'event_date_from' ), telegram_event_datetime_display( (int)$p_event_row['date_from'] ) );
    $t_message .= telegram_message_format_line( plugin_lang_get( 'event_date_to' ), telegram_event_datetime_display( (int)$p_event_row['date_to'] ) );

    $t_issues = telegram_calendar_event_issues_display( $t_event_id, $p_user_id );

    if( !is_blank( $t_issues ) ) {
        $t_message .= $t_separator1;
        $t_message .= plugin_lang_get( 'event_bugs' ) . ':' . PHP_EOL . $t_issues . PHP_EOL;
    }

    $t_message .= $t_separator1;

    if( $p_link ) {
        $t_message .= telegram_calendar_event_view_url( $p_event_row ) . PHP_EOL;
        $t_message .= $t_separator1;
    }

    date_restore_timezone();

    return $t_message;
}

/**
 * The members of a calendar event as the given user may see them.
 *
 * The member list of Calendar is guarded by its show_member_list_threshold,
 * which event_get_members() checks against the given user: the notification
 * goes to somebody other than the one acting, and the cron has no acting
 * user at all, so the recipient is passed explicitly, the way the visible
 * data of an issue is built for the recipient of its notification.
 *
 * @param integer $p_event_id Event the members belong to.
 * @param integer $p_user_id  User the list is shown to.
 * @return string The names separated by commas, empty when there is nothing to show.
 */
function telegram_calendar_event_members_display( $p_event_id, $p_user_id ) {

    $t_members = telegram_calendar_call( 'event_get_members', array( $p_event_id, $p_user_id ) );

    $t_names = array();

    foreach( $t_members as $t_member_id ) {
        $t_names[] = user_get_name( (int)$t_member_id );
    }

    return implode( ', ', $t_names );
}

/**
 * The issues linked to a calendar event as the given user may see them.
 *
 * @param integer $p_event_id Event the issues are linked to.
 * @param integer $p_user_id  User the list is shown to.
 * @return string An issue per line, each followed by the line of its link;
 *                empty when there is nothing to show.
 */
function telegram_calendar_event_issues_display( $p_event_id, $p_user_id ) {

    $t_issues = array();

    foreach( telegram_calendar_call( 'event_get_attached_bugs_id', array( $p_event_id ) ) as $t_bug_id ) {
        $t_bug_id = (int)$t_bug_id;

        # a link may outlive its issue, and a deleted one has nothing to show
        if( !bug_exists( $t_bug_id ) || !access_has_bug_level( config_get( 'view_bug_threshold' ), $t_bug_id, $p_user_id ) ) {
            continue;
        }

        # the link is the one the notifications about the issues carry
        $t_issues[] = telegram_event_issue_display( $t_bug_id ) . PHP_EOL . string_get_bug_view_url_with_fqdn( $t_bug_id );
    }

    # one issue per line, the summaries make a single line unreadable
    return implode( PHP_EOL, $t_issues );
}

/**
 * The link to the page of a calendar event.
 *
 * Built from the configured path of the installation the way Calendar builds
 * the links of its own mails, so it holds in a notification sent from the
 * polling script as well, where there is no request to take the host from.
 *
 * @param array $p_event_row Row of the event, as event_get_row() returns it.
 * @return string
 */
function telegram_calendar_event_view_url( array $p_event_row ) {

    return config_get_global( 'path' ) . plugin_page( 'view', /* redirect */ TRUE, 'Calendar' )
            . '&event_id=' . (int)$p_event_row['id'] . '&date=' . (int)$p_event_row['date_from'];
}
