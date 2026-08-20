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

/**
 * Whether the Calendar plugin is installed and initialized.
 *
 * The public api of Calendar names its request class as the documented way of
 * detecting the plugin; the function is checked as well, so that a Calendar
 * older than its public api is treated as absent.
 *
 * @return boolean
 */
function telegram_calendar_available() {

    return class_exists( 'CalendarPluginApi\\EventCreateRequest' )
            && function_exists( 'calendar_api_event_create' );
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
                              'project'   => '',
                              'name'      => '',
                              'date_from' => '',
                              'date_to'   => '',
                              'bug_ids'   => '',
                              'members'   => '',
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

    return array( 'project', 'name', 'date_from', 'date_to', 'bug_ids', 'members' );
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
 * The steps of the wizard are known beforehand and there are six of them, so every
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

                $t_issues[] = lang_get( 'issue_id' ) . $t_bug_id . ': ' . bug_get_field( $t_bug_id, 'summary' );
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
 * only the time is left; it is answered with a plain text message, so the step
 * is remembered as the one the next message belongs to.
 *
 * @param array  $p_draft Event draft.
 * @param string $p_step  Step of the wizard, 'date_from' or 'date_to'.
 * @param string $p_error Message about the answer being rejected.
 * @return array Data for Longman\TelegramBot\Request::editMessageText().
 */
function telegram_event_time_question_get( array $p_draft, $p_step, $p_error = '' ) {

    plugin_config_set( 'event_draft_current_field', $p_step . '_time', auth_get_current_user_id() );

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    keyboard_event_buttons_add( $t_inline_keyboard, $p_draft );

    $t_question = sprintf(
                              plugin_lang_get( 'event_time_prompt' ),
                              telegram_event_draft_step_label( $p_step ),
                              (string)$p_draft[$p_step . '_day']
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

            # the day is only half of the answer, the time is asked as a text
            $t_draft[$t_step . '_day'] = reset( $p_current_action[TelegrambotActions::SET_EVENT_DATE] );
            telegram_event_draft_set( $t_draft );

            $t_data_send               = telegram_event_time_question_get( $t_draft, $t_step );
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
                telegram_callback_alert_set( plugin_lang_get( 'event_required_missing' ) );
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
 * The step of the wizard a date carried by the inline calendar belongs to.
 *
 * The identifier of the date is the key of the payload, kept to a single
 * character because callback_data is limited to 64 bytes.
 *
 * @param array $p_payload Payload of the calendar button.
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
 * Notify the members of a calendar event about its creation.
 *
 * The subscribers of EVENT_CALENDAR_EVENT_CREATED are called with this plugin
 * being the current one, so every function of Calendar is called on behalf of
 * Calendar itself, otherwise its tables and settings would be looked up under
 * the name of this plugin.
 *
 * @param integer $p_event_id Identifier of the created event.
 * @return void
 */
function telegram_calendar_message_event_created( $p_event_id ) {

    if( !telegram_calendar_available() || OFF == plugin_config_get( 'enable_telegram_message_notification' ) ) {
        return;
    }

    $t_event_row = telegram_calendar_call( 'event_get_row', array( (int)$p_event_id ) );

    if( !is_array( $t_event_row ) ) {
        return;
    }

    $t_members = telegram_calendar_call( 'event_get_members', array( (int)$p_event_id ) );

    if( !is_array( $t_members ) || count( $t_members ) == 0 ) {
        return;
    }

    $t_author_id = (int)$t_event_row['author_id'];

    foreach( $t_members as $t_member_id ) {
        $t_member_id = (int)$t_member_id;

        # the notifications of the issues drop the user acting, this one drops
        # the author of the event for the very same reason
        if( $t_member_id == $t_author_id && OFF == plugin_config_get( 'telegram_message_receive_own' ) ) {
            continue;
        }

        if( !user_exists( $t_member_id ) || !user_is_enabled( $t_member_id ) ) {
            continue;
        }

        $t_telegram_user_id = telegram_user_get_id_by_user_id( $t_member_id );

        if( $t_telegram_user_id == 0 ) {
            continue;
        }

        lang_push( user_pref_get_language( $t_member_id, (int)$t_event_row['project_id'] ) );

        $t_text = telegram_calendar_event_message_compose( $t_event_row );

        lang_pop();

        plugin_log_event( sprintf( 'Calendar event = #%d, add @U%d (member)', (int)$p_event_id, $t_member_id ) );

        telegram_session_send_message( $t_telegram_user_id, array( 'text' => $t_text ) );
    }
}

/**
 * The text of the notification about a created calendar event.
 *
 * @param array $p_event_row Row of the event, as event_get_row() returns it.
 * @return string
 */
function telegram_calendar_event_message_compose( array $p_event_row ) {

    $t_lines = array();

    $t_lines[] = plugin_lang_get( 'event_message_created' );
    $t_lines[] = plugin_lang_get( 'event_name' ) . ': ' . $p_event_row['name'];
    $t_lines[] = lang_get( 'email_project' ) . ': ' . project_get_name( (int)$p_event_row['project_id'], /* trigger_errors */ FALSE );
    $t_lines[] = plugin_lang_get( 'event_date_from' ) . ': ' . telegram_event_datetime_display( (int)$p_event_row['date_from'] );
    $t_lines[] = plugin_lang_get( 'event_date_to' ) . ': ' . telegram_event_datetime_display( (int)$p_event_row['date_to'] );
    $t_lines[] = plugin_lang_get( 'event_author' ) . ': ' . user_get_name( (int)$p_event_row['author_id'] );

    return implode( PHP_EOL, $t_lines );
}
