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

# Key of the issue draft marking that the user has asked for the optional fields,
# it is dropped along with the draft itself
define( 'TELEGRAM_DRAFT_OPTIONAL_PHASE', 'optional_phase' );

# A question of the wizard is asked, the keyboard of the result belongs to it
define( 'TELEGRAM_DRAFT_NEXT_QUESTION', 'question' );
# Every mandatory question is answered, the user chooses whether to create the issue
# right away or to fill in the optional fields first
define( 'TELEGRAM_DRAFT_NEXT_MENU', 'menu' );
# Nothing is left to ask, the issue can be created
define( 'TELEGRAM_DRAFT_NEXT_SUBMIT', 'submit' );

function helper_ensure_telegram_bot_registred_confirmed( $p_message ) {
    if( true == gpc_get_string( '_confirmed', FALSE ) ) {
        return gpc_get_string( '_confirmed' );
    }

    layout_page_header();
    layout_page_begin();

    echo '<div class="col-md-12 col-xs-12">';
    echo '<div class="space-10"></div>';
    echo '<div class="alert alert-warning center">';
    echo '<p class="bigger-110">';
    echo "\n" . $p_message . "\n";
    echo '</p>';
    echo '<div class="space-10"></div>';

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );
    echo '<input type="hidden" name="_confirmed" value="1" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'user_relationship_yes' ) . '" />';
    echo "\n</form>";

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );
    echo '<input type="hidden" name="_confirmed" value="0" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'user_relationship_no' ) . '" />';
    echo "\n</form>";


    echo '<div class="space-10"></div>';
    echo '</div></div>';

    layout_page_end();
    exit;
}

function bugnote_add_from_telegram( $p_bug_id, $p_text = '', $p_files = array(), $p_duration = '0:00' ) {

    $t_query = array( 'issue_id' => $p_bug_id );

    if( count( $p_files ) > 0 && is_blank( $p_text ) && helper_duration_to_minutes( $p_duration ) == 0 ) {
        $t_payload = array(
                                  'files' => helper_array_transpose( $p_files )
        );

        $t_data = array(
                                  'query'   => $t_query,
                                  'payload' => $t_payload,
        );

        $t_command = new IssueFileAddCommand( $t_data );
        $t_command->execute();

        plugin_history_log( $p_bug_id, lang_get("download_attachments"), "", lang_get("file_added") );

    } else {
        $t_payload = array(
                                  'text'          => $p_text,
                                  'view_state'    => array(
                                                            'id' => VS_PUBLIC
                                  ),
                                  'time_tracking' => array(
                                                            'duration' => $p_duration
                                  ),
                                  'files'         => helper_array_transpose( $p_files )
        );

        $t_data = array(
                                  'query'   => $t_query,
                                  'payload' => $t_payload,
        );

        $t_command = new IssueNoteAddCommand( $t_data );
        $t_noteId = $t_command->execute();
        
        plugin_history_log( $p_bug_id, lang_get( "add_notes" ), "", lang_get( "bugnote_added" ) . ": " . $t_noteId['id']);
       
    }
}

/**
 * Remember the message shown to the user as a pop-up when the callback query is
 * answered.
 *
 * A callback query can only be answered once, so the answer is sent by the update
 * dispatcher after the whole callback is processed.
 *
 * @param string $p_text Message shown to the user.
 * @return void
 */
function telegram_callback_alert_set( $p_text ) {
    global $g_telegram_callback_alert;

    $g_telegram_callback_alert = array(
                              'text'       => $p_text,
                              'show_alert' => true,
    );
}

/**
 * Return the parameters of the callback query answer prepared while the callback
 * was processed and reset them.
 *
 * @return array Parameters of Longman\TelegramBot\Entities\CallbackQuery::answer().
 */
function telegram_callback_alert_get() {
    global $g_telegram_callback_alert;

    $t_alert                   = is_array( $g_telegram_callback_alert ) ? $g_telegram_callback_alert : array();
    $g_telegram_callback_alert = array();

    return $t_alert;
}

/**
 * Create the issue from the draft, clean the draft up and build the final view of
 * the draft card.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return array Data for Longman\TelegramBot\Request::editMessageText().
 */
function telegram_draft_submit( array $p_bug_data_draft ) {

    $t_user_id    = auth_get_current_user_id();
    $t_chat_id    = plugin_config_get( 'bug_data_draft_chat_id', NULL, FALSE, $t_user_id );
    $t_message_id = plugin_config_get( 'bug_data_draft_message_id', NULL, FALSE, $t_user_id );

    # The card of the created issue shows the answers only, no question is left to ask
    $t_answers = telegram_draft_card_text_rebuild( $p_bug_data_draft );

    try {
        $t_issue_id = telegram_bug_add( $p_bug_data_draft, $t_chat_id, $t_message_id );

        $t_text  = $t_answers;
        $t_text .= PHP_EOL;
        $t_text .= '=======================================';
        $t_text .= PHP_EOL;
        $t_text .= sprintf( plugin_lang_get( 'bug_creation_complete' ), lang_get( 'bug' ) ) . $t_issue_id;
        $t_text .= PHP_EOL;
        $t_text .= string_get_bug_view_url_with_fqdn( $t_issue_id );
    } catch( Mantis\Exceptions\MantisException $t_error ) {

        $t_params = $t_error->getParams();
        if( !empty( $t_params ) ) {
            call_user_func_array( 'error_parameters', $t_params );
        }

        $t_text = error_string( $t_error->getCode() );
    }

    plugin_config_delete( 'bug_data_draft', $t_user_id );
    plugin_config_delete( 'bug_data_draft_chat_id', $t_user_id );
    plugin_config_delete( 'bug_data_draft_message_id', $t_user_id );
    # The card text is not kept anymore, the value left by the previous versions is dropped
    plugin_config_delete( 'bug_data_draft_text_msg', $t_user_id );
    plugin_config_delete( 'bug_data_draft_current_field_to_save', $t_user_id );

    return array(
                              'chat_id'    => $t_chat_id,
                              'message_id' => $t_message_id,
                              'text'       => $t_text,
    );
}

/**
 * Return the questions of the issue draft wizard in the order they are asked in.
 *
 * The order repeats the fall through chain of telegram_bug_report(), the text
 * fields asked by the update dispatcher and the custom fields of the project.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return array Steps of the wizard, a custom field step is named "cf_<id>".
 */
function telegram_draft_steps_get( array $p_bug_data_draft ) {

    $t_steps = array(
                              'project',
                              'category',
                              'reproducibility',
                              'eta',
                              'severity',
                              'priority',
                              'due_date',
                              'profile',
                              'product_version',
                              'handler',
                              'status',
                              'resolution',
                              'target_version',
                              'summary',
                              'description',
                              'steps_to_reproduce',
                              'additional_info',
    );

    $t_project_id = array_key_exists( 'project', $p_bug_data_draft ) ? $p_bug_data_draft['project'] : '';

    if( !is_blank( $t_project_id ) ) {
        foreach( custom_field_get_linked_ids( $t_project_id ) as $t_id ) {
            if( telegram_custom_field_is_askable( (int)$t_id, $t_project_id ) ) {
                $t_steps[] = TELEGRAM_CUSTOM_FIELD_STATE_PREFIX . (int)$t_id;
            }
        }
    }

    return $t_steps;
}

/**
 * Return true when the user has already answered the given question of the issue
 * draft wizard.
 *
 * The way an answer is kept depends on the field: the fields taken from
 * "bug_report_page_fields" are initialized with an empty string, the keys of the
 * fields answered with a keyboard of their own ( category, profile ) appear in the
 * draft only when they are answered, a custom field is answered when it holds a
 * value ( an empty string for a skipped one ) instead of the state of the question
 * being asked.
 *
 * @param string $p_step           Step of the wizard.
 * @param array  $p_bug_data_draft Issue draft.
 * @return boolean
 */
function telegram_draft_step_is_answered( $p_step, array $p_bug_data_draft ) {

    $t_custom_field_id = telegram_custom_field_pending_id( $p_step );

    if( $t_custom_field_id > 0 ) {
        if( !array_key_exists( 'custom_fields', $p_bug_data_draft )
                || !array_key_exists( $t_custom_field_id, $p_bug_data_draft['custom_fields'] ) ) {
            return FALSE;
        }

        $t_state = $p_bug_data_draft['custom_fields'][$t_custom_field_id];

        return $t_state !== NULL && !is_array( $t_state );
    }

    if( !array_key_exists( $p_step, $p_bug_data_draft ) ) {
        return FALSE;
    }

    switch( $p_step ) {
        case 'category':
        case 'profile':
            return TRUE;
    }

    # An empty string means the field is not asked yet, NULL marks it as skipped
    # and the skip is a valid answer to return to
    return $p_bug_data_draft[$p_step] !== '';
}

/**
 * Return true when the user has asked the wizard for the optional fields of the
 * issue draft.
 *
 * The wizard asks about the fields the core insists on first, the marker is set by
 * the button of the menu shown afterwards and is dropped along with the draft.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return boolean
 */
function telegram_draft_optional_phase_is_on( array $p_bug_data_draft ) {

    return array_key_exists( TELEGRAM_DRAFT_OPTIONAL_PHASE, $p_bug_data_draft )
            && $p_bug_data_draft[TELEGRAM_DRAFT_OPTIONAL_PHASE];
}

/**
 * Return true when a question of the issue draft wizard has to be asked at all.
 *
 * The web report form shows a field when it is enabled in "bug_report_page_fields"
 * ( such a field appears in the draft ) and the user is allowed to fill it in, the
 * wizard repeats these conditions.
 *
 * @param string $p_step           Step of the wizard.
 * @param array  $p_bug_data_draft Issue draft.
 * @return boolean
 */
function telegram_draft_step_is_applicable( $p_step, array $p_bug_data_draft ) {

    if( $p_step == 'project' ) {
        return TRUE;
    }

    $t_project_id = array_key_exists( 'project', $p_bug_data_draft ) ? $p_bug_data_draft['project'] : '';

    # The categories, the versions, the handlers and the custom fields of the issue
    # are defined by the project, so nothing can be asked before it is chosen
    if( is_blank( (string)$t_project_id ) ) {
        return FALSE;
    }

    # The list of the steps holds the custom fields of the project only
    if( telegram_custom_field_pending_id( $p_step ) > 0 ) {
        return TRUE;
    }

    $t_user_id = auth_get_current_user_id();

    switch( $p_step ) {
        case 'summary':
        case 'description':
            return TRUE;

        case 'category':
            # The answer is kept in the "category" key, the form field is "category_id"
            return array_key_exists( 'category_id', $p_bug_data_draft );

        case 'profile':
            return config_get( 'enable_profiles' )
                    && ( array_key_exists( 'platform', $p_bug_data_draft )
                        || array_key_exists( 'os', $p_bug_data_draft )
                        || array_key_exists( 'os_build', $p_bug_data_draft ) )
                    && count( profile_get_all_for_user( $t_user_id ) ) > 0;

        case 'due_date':
            return array_key_exists( 'due_date', $p_bug_data_draft )
                    && access_has_project_level( config_get( 'due_date_update_threshold' ), $t_project_id, $t_user_id );

        case 'product_version':
            return array_key_exists( 'product_version', $p_bug_data_draft )
                    && version_should_show_product_version( $t_project_id );

        case 'target_version':
            return array_key_exists( 'target_version', $p_bug_data_draft )
                    && version_should_show_product_version( $t_project_id )
                    && access_has_project_level( config_get( 'roadmap_update_threshold' ) );

        case 'handler':
            return array_key_exists( 'handler', $p_bug_data_draft )
                    && access_has_project_level( config_get( 'update_bug_assign_threshold' ) );
    }

    return array_key_exists( $p_step, $p_bug_data_draft );
}

/**
 * Return true when a question of the issue draft wizard belongs to its first phase,
 * the one asking about the fields the core insists on.
 *
 * @param string $p_step Step of the wizard.
 * @return boolean
 */
function telegram_draft_step_is_required( $p_step ) {

    $t_custom_field_id = telegram_custom_field_pending_id( $p_step );

    if( $t_custom_field_id > 0 ) {
        return (bool)custom_field_get_field( $t_custom_field_id, 'require_report' );
    }

    switch( $p_step ) {
        case 'project':
        case 'category':
        case 'summary':
        case 'description':
            return TRUE;
    }

    return FALSE;
}

/**
 * Return the first mandatory question of the issue draft wizard left unanswered,
 * null when the issue can be created.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return string|null
 */
function telegram_draft_required_step_pending( array $p_bug_data_draft ) {

    foreach( telegram_draft_steps_get( $p_bug_data_draft ) as $t_step ) {
        if( telegram_draft_step_is_required( $t_step )
                && telegram_draft_step_is_applicable( $t_step, $p_bug_data_draft )
                && !telegram_draft_step_is_answered( $t_step, $p_bug_data_draft ) ) {
            return $t_step;
        }
    }

    return NULL;
}

/**
 * Return the question of the issue draft wizard a plain text message answers, null
 * when no answer of the kind is expected.
 *
 * The answer of the fields asked with a keyboard of their own comes from a callback
 * query, only the standard text fields of the draft are answered with a message.
 *
 * @param array  $p_bug_data_draft        Issue draft.
 * @param string $p_current_field_to_save Value of the "bug_data_draft_current_field_to_save" config.
 * @return string|null
 */
/**
 * Return the step the wizard is going to ask next, without asking it.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return string|null Step of the wizard, NULL when nothing is left to ask.
 */
function telegram_draft_next_step_get( array $p_bug_data_draft ) {

    $t_optional_phase = telegram_draft_optional_phase_is_on( $p_bug_data_draft );

    foreach( telegram_draft_steps_get( $p_bug_data_draft ) as $t_step ) {
        if( !telegram_draft_step_is_applicable( $t_step, $p_bug_data_draft )
                || telegram_draft_step_is_answered( $t_step, $p_bug_data_draft ) ) {
            continue;
        }

        if( !$t_optional_phase && !telegram_draft_step_is_required( $t_step ) ) {
            continue;
        }

        return $t_step;
    }

    return NULL;
}

function telegram_draft_text_step_pending( array $p_bug_data_draft, $p_current_field_to_save ) {

    if( in_array( $p_current_field_to_save, array( 'steps_to_reproduce', 'additional_info' ), TRUE )
            && array_key_exists( $p_current_field_to_save, $p_bug_data_draft )
            && !telegram_draft_step_is_answered( $p_current_field_to_save, $p_bug_data_draft ) ) {
        return $p_current_field_to_save;
    }

    # The summary and the description take a text answer only while one of them is
    # the question the wizard is asking right now: a text message sent amid a
    # button question is not an answer and has to be ignored
    $t_next_step = telegram_draft_next_step_get( $p_bug_data_draft );

    if( in_array( $t_next_step, array( 'summary', 'description' ), TRUE ) ) {
        return $t_next_step;
    }

    return NULL;
}

/**
 * Return the question of the issue draft wizard answered last, null when the draft
 * holds no answer at all.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return string|null
 */
function telegram_draft_step_last_answered( array $p_bug_data_draft ) {

    $t_last_step = NULL;

    foreach( telegram_draft_steps_get( $p_bug_data_draft ) as $t_step ) {
        if( telegram_draft_step_is_answered( $t_step, $p_bug_data_draft ) ) {
            $t_last_step = $t_step;
        }
    }

    return $t_last_step;
}

/**
 * Drop the answer given to a single question of the issue draft wizard.
 *
 * @param string $p_step           Step of the wizard.
 * @param array  $p_bug_data_draft Issue draft.
 * @return void
 */
function telegram_draft_step_value_reset( $p_step, array &$p_bug_data_draft ) {

    $t_custom_field_id = telegram_custom_field_pending_id( $p_step );

    if( $t_custom_field_id > 0 ) {
        unset( $p_bug_data_draft['custom_fields'][$t_custom_field_id] );

        return;
    }

    if( !array_key_exists( $p_step, $p_bug_data_draft ) ) {
        return;
    }

    switch( $p_step ) {
        case 'category':
        case 'profile':
            unset( $p_bug_data_draft[$p_step] );
            break;

        default:
            $p_bug_data_draft[$p_step] = '';
            break;
    }
}

/**
 * Drop the answer given to a question of the issue draft wizard so that it can be
 * asked again.
 *
 * @param string $p_step           Step of the wizard.
 * @param array  $p_bug_data_draft Issue draft.
 * @return void
 */
function telegram_draft_step_reset( $p_step, array &$p_bug_data_draft ) {

    # The project defines the categories, the versions, the handlers and the custom
    # fields of the issue, so every answer given after it becomes invalid
    if( $p_step == 'project' ) {
        foreach( telegram_draft_steps_get( $p_bug_data_draft ) as $t_step ) {
            telegram_draft_step_value_reset( $t_step, $p_bug_data_draft );
        }

        $p_bug_data_draft['custom_fields'] = array();

        return;
    }

    telegram_draft_step_value_reset( $p_step, $p_bug_data_draft );
}

/**
 * Drop the state of the custom field question the user is answering right now.
 *
 * A null state means that the text value of the field is expected, an array holds
 * the values picked so far.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return void
 */
function telegram_draft_pending_custom_fields_reset( array &$p_bug_data_draft ) {

    telegram_custom_field_draft_prepare( $p_bug_data_draft );

    foreach( $p_bug_data_draft['custom_fields'] as $t_id => $t_state ) {
        if( $t_state === NULL || is_array( $t_state ) ) {
            unset( $p_bug_data_draft['custom_fields'][$t_id] );
        }
    }
}

/**
 * Return the label of a question of the issue draft wizard, the same one the chain
 * of questions writes into the draft card.
 *
 * @param string $p_step Step of the wizard.
 * @return string
 */
function telegram_draft_step_label( $p_step ) {

    $t_custom_field_id = telegram_custom_field_pending_id( $p_step );

    if( $t_custom_field_id > 0 ) {
        return lang_get_defaulted( custom_field_get_field( $t_custom_field_id, 'name' ) );
    }

    switch( $p_step ) {
        case 'project':
            return lang_get( 'email_project' );

        case 'profile':
            return lang_get( 'select_profile' );

        case 'handler':
            return lang_get( 'issue_handler' );

        case 'additional_info':
            return lang_get( 'additional_information' );
    }

    return lang_get( $p_step );
}

/**
 * Return the answer given to a question of the issue draft wizard the way the chain
 * of questions writes it into the draft card.
 *
 * @param string $p_step           Step of the wizard.
 * @param array  $p_bug_data_draft Issue draft.
 * @return string
 */
function telegram_draft_step_display( $p_step, array $p_bug_data_draft ) {

    $t_custom_field_id = telegram_custom_field_pending_id( $p_step );

    if( $t_custom_field_id > 0 ) {
        return telegram_custom_field_display_value(
                                  custom_field_get_definition( $t_custom_field_id ),
                                  $p_bug_data_draft['custom_fields'][$t_custom_field_id]
                );
    }

    $t_value = $p_bug_data_draft[$p_step];

    # A skipped step holds NULL and shows no value on the card
    if( $t_value === null ) {
        return '';
    }

    switch( $p_step ) {
        case 'project':
            return project_get_field( $t_value, 'name' );

        case 'category':
            return $t_value != 0 ? category_get_name( $t_value ) : lang_get( 'no_category' );

        case 'reproducibility':
        case 'eta':
        case 'severity':
        case 'priority':
        case 'status':
        case 'resolution':
            return get_enum_element( $p_step, $t_value );

        case 'due_date':
            return date( config_get( 'normal_date_format' ), $t_value );

        case 'profile':
            return profile_get_name( $t_value );

        case 'handler':
            return user_get_name( $t_value );
    }

    return (string)$t_value;
}

/**
 * Build the list of the answers given to the issue draft wizard so far.
 *
 * The draft itself is the only source of the card text, so the answers are shown
 * the same way no matter how the card has been redrawn.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return string Answered part of the draft card.
 */
function telegram_draft_card_text_rebuild( array $p_bug_data_draft ) {

    $t_lines = array();

    foreach( telegram_draft_steps_get( $p_bug_data_draft ) as $t_step ) {
        if( !telegram_draft_step_is_answered( $t_step, $p_bug_data_draft ) ) {
            continue;
        }

        $t_display = telegram_draft_step_display( $t_step, $p_bug_data_draft );

        # A skipped step shows a dash instead of a value
        if( is_blank( $t_display ) ) {
            $t_display = plugin_lang_get( 'skipped_mark' );
        }

        $t_lines[] = telegram_draft_step_label( $t_step ) . ': ' . $t_display;
    }

    return implode( PHP_EOL, $t_lines );
}

/**
 * Build the whole text of the draft card.
 *
 * The card keeps the data of the draft apart from what the wizard says to the user:
 * the answers given so far come first, the question being asked and the message
 * about the answer rejected last are shown under a separator.
 *
 * @param array  $p_bug_data_draft Issue draft.
 * @param string $p_suffix         Question of the wizard, or the prompt of its menu.
 * @param string $p_error          Message about the answer being rejected.
 * @return string Text of the draft card.
 */
function telegram_draft_card_compose( array $p_bug_data_draft, $p_suffix = '', $p_error = '' ) {

    $t_answers = telegram_draft_card_text_rebuild( $p_bug_data_draft );
    $t_prompt  = array();

    if( !is_blank( $p_suffix ) ) {
        $t_prompt[] = plugin_lang_get( 'card_question_prefix' ) . $p_suffix;
    }

    if( !is_blank( $p_error ) ) {
        $t_prompt[] = plugin_lang_get( 'card_error_prefix' ) . $p_error;
    }

    if( empty( $t_prompt ) ) {
        return $t_answers;
    }

    # Nothing is answered yet, so there is nothing to separate the question from
    if( is_blank( $t_answers ) ) {
        return implode( PHP_EOL, $t_prompt );
    }

    return $t_answers . PHP_EOL . plugin_lang_get( 'card_separator' ) . PHP_EOL . implode( PHP_EOL, $t_prompt );
}

/**
 * Ask a question of the issue draft wizard again.
 *
 * The question is given back as the suffix of the draft card and the plugin is told
 * whether a text answer is expected.
 *
 * @param string  $p_step           Step of the wizard.
 * @param array   $p_bug_data_draft Issue draft.
 * @param string  $p_suffix         Question shown under the answers given so far.
 * @param mixed   $p_page           Page of the possible values list of a custom field
 *                                  ( month of the calendar for a date field ).
 * @param boolean $p_required_only  True to ask about the mandatory custom fields only.
 * @return Longman\TelegramBot\Entities\InlineKeyboard|null Keyboard of the question,
 *         null when a custom field question is left out of the current phase.
 */
function telegram_draft_step_ask( $p_step, array &$p_bug_data_draft, &$p_suffix, $p_page = 1, $p_required_only = FALSE ) {

    $t_user_id = auth_get_current_user_id();

    # A custom field question is built by the custom field api itself, including
    # the label of the question and the state of the field
    if( telegram_custom_field_pending_id( $p_step ) > 0 ) {
        return telegram_custom_field_ask_next( $p_bug_data_draft, $p_suffix, $p_page, $p_required_only );
    }

    $t_project_id      = array_key_exists( 'project', $p_bug_data_draft ) ? $p_bug_data_draft['project'] : '';
    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    switch( $p_step ) {
        case 'project':
            $t_inline_keyboard = keyboard_projects_get( ALL_PROJECTS, 1, 1 );
            break;

        case 'category':
            $t_inline_keyboard = keyboard_category_get( $t_project_id );
            break;

        case 'reproducibility':
        case 'eta':
        case 'severity':
        case 'priority':
            $t_inline_keyboard = keyboard_enum_string_get( $p_step, (int)config_get( 'default_bug_' . $p_step ) );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::SKIP_FIELD => $p_step ) ) );
            break;

        case 'due_date':
            $t_calendar        = new TelegramBotInlineKeyboardCalendar( date( 'Y-n', time() ) );
            $t_inline_keyboard = $t_calendar->getKeyboard( 'duedate' );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::SKIP_FIELD => $p_step ) ) );
            break;

        case 'profile':
            $t_inline_keyboard = keyboard_profile_option_list( $t_user_id, 0 );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array(
                                      TelegrambotActions::SET_PROFILE => array( 'id' => TelegrambotActions::SKIP_VALUE )
            ) ) );
            break;

        case 'product_version':
            $t_product_version_released_mask = VERSION_RELEASED;

            if( access_has_project_level( config_get( 'report_issues_for_unreleased_versions_threshold' ) ) ) {
                $t_product_version_released_mask = VERSION_ALL;
            }

            $t_inline_keyboard = keyboard_version_option_list( '', $t_project_id, $t_product_version_released_mask, TelegrambotActions::SET_PRODUCT_VERSION );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array(
                                      TelegrambotActions::SET_PRODUCT_VERSION => array( 'version' => TelegrambotActions::SKIP_VALUE )
            ) ) );
            break;

        case 'handler':
            $t_inline_keyboard = keyboard_handler_get( $t_project_id );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array(
                                      TelegrambotActions::SET_HANDLER => array( 'id' => 0 )
            ) ) );
            break;

        case 'status':
            $t_inline_keyboard = keyboard_status_get( $t_project_id );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::SKIP_FIELD => $p_step ) ) );
            break;

        case 'resolution':
            $t_inline_keyboard = keyboard_enum_string_get( 'resolution' );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::SKIP_FIELD => $p_step ) ) );
            break;

        case 'target_version':
            $t_inline_keyboard = keyboard_version_option_list( '', $t_project_id, VERSION_FUTURE, TelegrambotActions::SET_TARGET_VERSION );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array(
                                      TelegrambotActions::SET_TARGET_VERSION => array( 'version' => TelegrambotActions::SKIP_VALUE )
            ) ) );
            break;

        case 'steps_to_reproduce':
        case 'additional_info':
            //The field is optional on the web report form, so it can be left out here as well
            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array( TelegrambotActions::SKIP_FIELD => $p_step ) ) );
            break;
    }

    $p_suffix = telegram_draft_step_label( $p_step ) . ': ';

    # The answer of a text field is sent as a message, the answer of the remaining
    # fields comes from the keyboard
    $t_field_to_save = in_array( $p_step, array( 'steps_to_reproduce', 'additional_info' ), TRUE ) ? $p_step : '';

    plugin_config_set( 'bug_data_draft_current_field_to_save', $t_field_to_save, $t_user_id );

    return $t_inline_keyboard;
}

/**
 * Ask the question the issue draft wizard has to ask next.
 *
 * The questions are asked in the canonical order, a question is left out when it
 * does not apply to the draft ( the field is disabled on the web report form or the
 * user is not allowed to fill it in ), when it is answered already, or when it does
 * not belong to the current phase of the wizard: the fields the core insists on are
 * asked first, the optional ones only on demand.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @param mixed $p_page           Page of the possible values list of a custom field
 *                                ( month of the calendar for a date field ).
 * @return array array(
 *         'state'    => what the wizard is up to, TELEGRAM_DRAFT_NEXT_*,
 *         'keyboard' => Longman\TelegramBot\Entities\InlineKeyboard|null keyboard to show,
 *         'suffix'   => question of the wizard shown under the answers given so far
 *         )
 */
function telegram_draft_ask_next_step( array &$p_bug_data_draft, $p_page = 1 ) {

    $t_optional_phase = telegram_draft_optional_phase_is_on( $p_bug_data_draft );

    foreach( telegram_draft_steps_get( $p_bug_data_draft ) as $t_step ) {

        if( !telegram_draft_step_is_applicable( $t_step, $p_bug_data_draft )
                || telegram_draft_step_is_answered( $t_step, $p_bug_data_draft ) ) {
            continue;
        }

        if( !$t_optional_phase && !telegram_draft_step_is_required( $t_step ) ) {
            continue;
        }

        $t_suffix          = '';
        $t_inline_keyboard = telegram_draft_step_ask( $t_step, $p_bug_data_draft, $t_suffix, $p_page, !$t_optional_phase );

        # A custom field left out of the current phase gives no keyboard back
        if( $t_inline_keyboard === NULL ) {
            continue;
        }

        return array(
                                  'state'    => TELEGRAM_DRAFT_NEXT_QUESTION,
                                  'keyboard' => $t_inline_keyboard,
                                  'suffix'   => $t_suffix,
        );
    }

    if( $t_optional_phase ) {
        return array(
                                  'state'    => TELEGRAM_DRAFT_NEXT_SUBMIT,
                                  'keyboard' => NULL,
                                  'suffix'   => '',
        );
    }

    return array(
                              'state'    => TELEGRAM_DRAFT_NEXT_MENU,
                              'keyboard' => keyboard_draft_menu_get(),
                              'suffix'   => plugin_lang_get( 'draft_menu_prompt' ),
    );
}

/**
 * Take the summary of the issue out of the message the wizard has been started from.
 *
 * The user replies to a message to report an issue about it, so the text of that
 * message ( the caption of a file ) is the summary offered by the wizard.
 *
 * @param array  $p_bug_data_draft Issue draft, saved by the function.
 * @param Longman\TelegramBot\Entities\Message $p_orgl_message Message the wizard has
 *                                 been started from.
 * @return void
 */
function telegram_draft_summary_suggest( array &$p_bug_data_draft, $p_orgl_message ) {

    # The summary is offered instead of being asked for, so the questions asked
    # before it have to be answered already
    if( $p_orgl_message === NULL
            || telegram_draft_required_step_pending( $p_bug_data_draft ) !== 'summary' ) {
        return;
    }

    $t_summary = $p_orgl_message->getText();

    if( is_blank( $t_summary ) ) {
        $t_summary = $p_orgl_message->getCaption();
    }

    if( is_blank( $t_summary ) ) {
        return;
    }

    $p_bug_data_draft['summary'] = $t_summary;
    plugin_config_set( 'bug_data_draft', json_encode( $p_bug_data_draft ), auth_get_current_user_id() );
}

function telegram_bug_report( $p_current_action, Longman\TelegramBot\Entities\CallbackQuery $p_callback_query ) {

    $t_bug_data_draft = json_decode( plugin_config_get( 'bug_data_draft', NULL, FALSE, auth_get_current_user_id() ), TRUE );

    $t_callback_msg_id = $p_callback_query->getMessage()->getMessageId();
    $t_orgl_chat_id    = $p_callback_query->getMessage()->getChat()->getId();

    $t_orgl_message = $p_callback_query->getMessage()->getReplyToMessage();
    $t_content_type = $t_orgl_message->getType();

    # The wizard entry is the callback creating the draft, the callbacks of the
    # project list navigation always arrive with an existing draft
    $t_draft_is_new = ( $t_bug_data_draft == NULL );

    if( $t_bug_data_draft == NULL ) {

        $t_issue = array(
                                  'project'     => '',
                                  'reporter'    => '',
                                  'summary'     => '',
                                  'description' => '',
        );

        $t_fields = config_get( 'bug_report_page_fields' );
        $t_fields = columns_filter_disabled( $t_fields );

        $t_fields_temp = array_fill_keys( $t_fields, '' );

        $t_final_fields = array_merge( $t_issue, $t_fields_temp );
        
        $t_final_fields['custom_fields'] = array();

        plugin_config_set( 'bug_data_draft', json_encode( $t_final_fields ), auth_get_current_user_id() );
        plugin_config_set( 'bug_data_draft_chat_id', $t_orgl_chat_id, auth_get_current_user_id() );
        plugin_config_set( 'bug_data_draft_message_id', $t_callback_msg_id, auth_get_current_user_id() );
        
        $t_bug_data_draft = $t_final_fields;
    }
    
    $t_inline_keyboard = null;

    switch( $t_content_type ) {
        case 'video':
        case 'photo':
        case 'document':
            if( array_key_exists( 'attachments', $t_bug_data_draft ) ) {

                switch( $t_content_type ) {
                    case 'video':
                        $t_file_orgl     = $t_orgl_message->getVideo();
                        break;
                    case 'photo':
                        $t_content_photo = $t_orgl_message->getPhoto();
                        $t_file_orgl     = $t_content_photo[count( $t_content_photo ) - 1];

                        break;
                    case 'document':
                        $t_file_orgl = $t_orgl_message->getDocument();
                        break;
                }

                $t_download = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_file_orgl->getFileId() ] );

                $t_file = $t_download->getResult();

                $t_data_send_action = [
                                          'chat_id' => $t_orgl_chat_id,
                                          'action'  => 'upload_document'
                ];
                $t_rttt             = Longman\TelegramBot\Request::sendChatAction( $t_data_send_action );
                $t_upload_is_error  = FALSE;
                try {
                    Longman\TelegramBot\Request::downloadFile( $t_file );
                } catch( Longman\TelegramBot\Exception\TelegramException $e ) {
                    $t_data_send       = [
                                              'chat_id'    => $t_orgl_chat_id,
                                              'message_id' => $t_callback_msg_id,
                                              'text'       => $e->getMessage()
                    ];
                    $t_upload_is_error = TRUE;
                    break;
                }
                $t_file_path = plugin_config_get( 'download_path' ) . $t_file->getFilePath();

                $t_bug_data_draft['attachments'] = [
                                          'browser_upload' => [ 0 => FALSE ],
                                          'tmp_name'       => [ 0 => $t_file_path ],
                                          'name'           => $t_file_orgl->getFileName() == NULL ? [ 0 => $t_file->getFilePath() ] : [ 0 => $t_file_orgl->getFileName() ]
                ];
            }

        case 'text':

            $t_action   = array_keys( $p_current_action )[0];
            $t_page     = 1;
            #The card is built out of the draft, the question being asked is the only
            #part of its text an action has to take care of
            $t_suffix   = '';
            #An accepted answer lets the wizard choose the question to be asked next
            $t_ask_next = FALSE;

            switch( $t_action ) {
//PROJECT
                case TelegrambotActions::GET_PROJECT:
                    $t_project_id = (int)$p_current_action[TelegrambotActions::GET_PROJECT]['id'];

                    # On the wizard entry the report goes straight into the default
                    # project of the user, the way the web report page does. Leafing
                    # through the project list honors the requested project instead.
                    $t_default_project = user_pref_get_pref( auth_get_current_user_id(), 'default_project' );
                    if( $t_draft_is_new && ALL_PROJECTS == $t_project_id && ALL_PROJECTS != $t_default_project ) {
                        $p_current_action = array();
                        $p_current_action[TelegrambotActions::SET_PROJECT]['id'] = $t_default_project;
                    } else {
                        $t_inline_keyboard = keyboard_projects_get(
                                                        $p_current_action[TelegrambotActions::GET_PROJECT]['id'],
                                                        $p_current_action[TelegrambotActions::GET_PROJECT]['p'],
                                                        $p_current_action[TelegrambotActions::GET_PROJECT]['fp']
                                                        );
                        # Leafing through the list answers nothing, the question stays as it is
                        $t_suffix          = telegram_draft_step_label( 'project' ) . ': ';
                        break;
                    }

                case TelegrambotActions::SET_PROJECT:
                    $t_bug_data_draft['project'] = $p_current_action[TelegrambotActions::SET_PROJECT]['id'];
                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    $t_ask_next = TRUE;
                    break;

//CATEGORY
                case TelegrambotActions::SET_CATEGORY:
                    if( telegram_draft_step_is_applicable( 'category', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['category'] = $p_current_action[TelegrambotActions::SET_CATEGORY]['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                    }

                    $t_ask_next = TRUE;
                    break;

//REPRODUCIBILITY, ETA, SEVERITY, PRIORITY, STATUS, RESOLUTION
                case TelegrambotActions::SET_REPRODUCIBILITY:
                case TelegrambotActions::SET_ETA:
                case TelegrambotActions::SET_SEVERITY:
                case TelegrambotActions::SET_PRIORITY:
                case TelegrambotActions::SET_STATUS:
                case TelegrambotActions::SET_RESOLUTION:
                    # The action of an enumeration field is the name of the field with
                    # an "s" prefix, see keyboard_enum_string_get()
                    $t_step = substr( $t_action, 1 );

                    if( telegram_draft_step_is_applicable( $t_step, $t_bug_data_draft ) ) {
                        $t_bug_data_draft[$t_step] = $p_current_action[$t_action]['id'];

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                    }

                    $t_ask_next = TRUE;
                    break;

//DUE_DATE
                case TelegrambotActions::GET_DUE_DATE:
                    # The action leafs through the calendar, so the same question is
                    # asked again. A 'd' prefixed year requests the overview of the
                    # years, a bare year the overview of its months, a year-month the
                    # grid of the month.
                    if( !telegram_draft_step_is_applicable( 'due_date', $t_bug_data_draft ) ) {
                        # The field does not belong to the draft anymore, the wizard
                        # asks about the current state of the draft instead
                        $t_ask_next = TRUE;
                        break;
                    }

                    $t_suffix        = telegram_draft_step_label( 'due_date' ) . ': ';
                    $t_calendar_date = reset( $p_current_action[TelegrambotActions::GET_DUE_DATE] );
                    $t_calendar      = new TelegramBotInlineKeyboardCalendar( $t_calendar_date );

                    if( preg_match( '/^d\d{4}$/', $t_calendar_date ) ) {
                        $t_inline_keyboard = $t_calendar->getYearsKeyboard( 'duedate' );
                    } else if( preg_match( '/^\d{4}$/', $t_calendar_date ) ) {
                        $t_inline_keyboard = $t_calendar->getYearKeyboard( 'duedate' );
                    } else {
                        $t_inline_keyboard = $t_calendar->getKeyboard( 'duedate' );
                    }

                    keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array(
                                              TelegrambotActions::SKIP_FIELD => 'due_date'
                    ) ) );

                    break;

                case TelegrambotActions::SET_DUE_DATE:
                    if( telegram_draft_step_is_applicable( 'due_date', $t_bug_data_draft ) ) {
                        $t_bug_data_draft['due_date'] = date_strtotime( $p_current_action[TelegrambotActions::SET_DUE_DATE][0] );

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                    }

                    $t_ask_next = TRUE;
                    break;

//$t_show_platform || $t_show_os || $t_show_os_version
//Implemented only the choice of platform from the available list.
//TODO: Implement the ability to select options by severity and/or manually fill in with arbitrary data ( config_get( 'allow_freetext_in_profile_fields' ) == OFF )
                case TelegrambotActions::SET_PROFILE:
                    if( telegram_draft_step_is_applicable( 'profile', $t_bug_data_draft ) ) {
                        if( $p_current_action[TelegrambotActions::SET_PROFILE]['id'] == TelegrambotActions::SKIP_VALUE ) {
                            #NULL marks the step as skipped, so the back button can return to it
                            $t_bug_data_draft['profile'] = null;
                        } else {
                            $t_bug_data_draft['profile'] = $p_current_action[TelegrambotActions::SET_PROFILE]['id'];
                        }

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                    }

                    $t_ask_next = TRUE;
                    break;

//$t_show_product_version
                case TelegrambotActions::SET_PRODUCT_VERSION:
                    if( telegram_draft_step_is_applicable( 'product_version', $t_bug_data_draft ) ) {
                        if( $p_current_action[TelegrambotActions::SET_PRODUCT_VERSION]['version'] == TelegrambotActions::SKIP_VALUE ) {
                            #NULL marks the step as skipped, so the back button can return to it
                            $t_bug_data_draft['product_version'] = null;
                        } else {
                            $t_bug_data_draft['product_version'] = $p_current_action[TelegrambotActions::SET_PRODUCT_VERSION]['version'];
                        }

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                    }

                    $t_ask_next = TRUE;
                    break;

//TODO: $t_show_product_build Text area
//HANDLER
                case TelegrambotActions::SET_HANDLER:
                    if( telegram_draft_step_is_applicable( 'handler', $t_bug_data_draft ) ) {
                        if( $p_current_action[TelegrambotActions::SET_HANDLER]['id'] === 0 ) {
                            #NULL marks the step as skipped, so the back button can return to it
                            $t_bug_data_draft['handler'] = null;
                        } else {
                            $t_bug_data_draft['handler'] = $p_current_action[TelegrambotActions::SET_HANDLER]['id'];
                        }

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                    }

                    $t_ask_next = TRUE;
                    break;

//TODO: $t_show_monitors (new element)
//TARGET_VERSION
                case TelegrambotActions::SET_TARGET_VERSION:
                    if( telegram_draft_step_is_applicable( 'target_version', $t_bug_data_draft ) ) {
                        if( $p_current_action[TelegrambotActions::SET_TARGET_VERSION]['version'] == TelegrambotActions::SKIP_VALUE ) {
                            #NULL marks the step as skipped, so the back button can return to it
                            $t_bug_data_draft['target_version'] = null;
                        } else {
                            $t_bug_data_draft['target_version'] = $p_current_action[TelegrambotActions::SET_TARGET_VERSION]['version'];
                        }

                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                    }

                    $t_ask_next = TRUE;
                    break;

//TODO: $t_show_tags

//BACK TO THE QUESTION ANSWERED LAST
//The answer given last is dropped and the question is asked once again. The wizard
//skips the questions which are already answered, so it goes on where it has been
//interrupted.
                case TelegrambotActions::BACK_FIELD:
                    # The question the user is looking at is given up, including the
                    # state of a custom field the value of which is being picked
                    telegram_draft_pending_custom_fields_reset( $t_bug_data_draft );

                    $t_step_to_ask = telegram_draft_step_last_answered( $t_bug_data_draft );

                    if( $t_step_to_ask === NULL ) {
                        # Nothing is answered yet, the first question stays as it is
                        telegram_callback_alert_set( plugin_lang_get( 'back_nothing' ) );

                        $t_step_to_ask = 'project';
                    } else {
                        telegram_draft_step_reset( $t_step_to_ask, $t_bug_data_draft );
                    }

                    # The custom fields left out of the current phase stay unanswered
                    $t_inline_keyboard = telegram_draft_step_ask(
                                              $t_step_to_ask,
                                              $t_bug_data_draft,
                                              $t_suffix,
                                              1,
                                              !telegram_draft_optional_phase_is_on( $t_bug_data_draft )
                            );

                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    break;

//SKIP OF AN OPTIONAL FIELD
//The field is left out of the draft and the wizard moves on to the next question.
                case TelegrambotActions::SKIP_FIELD:
                    $t_field_to_skip = $p_current_action[TelegrambotActions::SKIP_FIELD];

                    #NULL marks the field as skipped: unlike removing the key it keeps
                    #the step known to the wizard, so the back button can return to it
                    if( is_string( $t_field_to_skip )
                            && key_exists( $t_field_to_skip, $t_bug_data_draft )
                            && !telegram_draft_step_is_required( $t_field_to_skip )
                            && $t_bug_data_draft[$t_field_to_skip] === '' ) {
                        $t_bug_data_draft[$t_field_to_skip] = null;
                        plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );
                    }

                    $t_ask_next = TRUE;
                    break;

//CREATION OF THE ISSUE OUT OF THE DRAFT
//The button is offered by the menu shown once every mandatory question is answered
//and along with every question of the optional fields.
                case TelegrambotActions::CREATE_ISSUE:
                    if( telegram_draft_required_step_pending( $t_bug_data_draft ) === NULL ) {
                        return telegram_draft_submit( $t_bug_data_draft );
                    }

                    # The mandatory question the user has returned to is asked again,
                    # the state of a custom field being picked is given up
                    telegram_callback_alert_set( plugin_lang_get( 'draft_required_missing' ) );

                    telegram_draft_pending_custom_fields_reset( $t_bug_data_draft );
                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    $t_ask_next = TRUE;
                    break;

//THE OPTIONAL FIELDS ARE ASKED FOR
//The marker stays in the draft until the issue is created or the draft is dropped.
                case TelegrambotActions::FILL_OPTIONAL:
                    $t_bug_data_draft[TELEGRAM_DRAFT_OPTIONAL_PHASE] = 1;
                    plugin_config_set( 'bug_data_draft', json_encode( $t_bug_data_draft ), auth_get_current_user_id() );

                    $t_ask_next = TRUE;
                    break;

//CUSTOM FIELDS
//The value of a custom field is picked with a keyboard of its own, so these actions
//are applied to the draft by the custom field api.
                case TelegrambotActions::GET_CUSTOM_FIELD:
                case TelegrambotActions::SET_CUSTOM_FIELD:
                case TelegrambotActions::SKIP_CUSTOM_FIELD:
                case TelegrambotActions::TOGGLE_CUSTOM_FIELD:
                case TelegrambotActions::END_CUSTOM_FIELD:
                    if( !key_exists( 'project', $t_bug_data_draft ) || is_blank( $t_bug_data_draft['project'] ) ) {
                        # The draft the keyboard belongs to does not exist anymore, the
                        # wizard asks about the current state of the draft instead
                        telegram_callback_alert_set( plugin_lang_get( 'custom_field_error_not_available' ) );

                        $t_ask_next = TRUE;
                        break;
                    }

                    telegram_custom_field_callback_process(
                                                    $t_action,
                                                    (array)$p_current_action[$t_action],
                                                    $t_bug_data_draft,
                                                    $t_page
                                            );

                    $t_ask_next = TRUE;
                    break;
            }

            if( $t_ask_next ) {
                # The message the wizard has been started from offers the summary
                telegram_draft_summary_suggest( $t_bug_data_draft, $t_orgl_message );

                $t_next = telegram_draft_ask_next_step( $t_bug_data_draft, $t_page );

                if( $t_next['state'] == TELEGRAM_DRAFT_NEXT_SUBMIT ) {
                    return telegram_draft_submit( $t_bug_data_draft );
                }

                $t_inline_keyboard = $t_next['keyboard'];
                $t_suffix          = $t_next['suffix'];
            }

            if( is_null( $t_inline_keyboard )) {
                $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
            }
            keyboard_draft_buttons_add( $t_inline_keyboard, $t_bug_data_draft );

            #The answers are drawn out of the draft, the question is shown under them.
            #A rejected keyboard answer is reported by a pop-up instead.
            $t_data_send = [
                                      'chat_id'      => $t_orgl_chat_id,
                                      'message_id'   => $t_callback_msg_id,
                                      'text'         => telegram_draft_card_compose( $t_bug_data_draft, $t_suffix ),
                                      'reply_markup' => $t_inline_keyboard,
            ];
            break;

        default :
            $t_data_send = [
                                      'chat_id' => $t_orgl_chat_id,
                                      'text'    => plugin_lang_get( 'error_content_type' )
            ];
            break;
    }


    return $t_data_send;
}

function telegram_add_comment( $p_current_action, $p_message, $p_reply_to_message ) {

    $t_command = array_keys( $p_current_action );

    switch( $t_command[0] ) {
        case 'get_default_category':
            $t_inline_keyboard = telegram_bot_get_keyboard_default_filter();
            $t_data_send       = [
                                      'text'         => plugin_lang_get( 'bug_section_select' ),
                                      'reply_markup' => $t_inline_keyboard,
            ];
            break;

        case 'get_bugs':
            $t_command_get_bugs = array_keys( $p_current_action['get_bugs'] );

            switch( $t_command_get_bugs[0] ) {
                case 'assigned':
                    $t_custom_filter = filter_create_assigned_to_unresolved( 0, auth_get_current_user_id() );
                    break;

                case 'monitored':
                    $t_custom_filter = filter_create_monitored_by( 0, auth_get_current_user_id() );
                    break;

                case 'reported':
                    $t_custom_filter = filter_create_reported_by( 0, auth_get_current_user_id() );
                    break;

                case 'use_query':
                    $t_custom_filter = filter_get_default();
                    break;
            }

            $t_inline_keyboard = keyboard_bugs_get( $t_custom_filter, $p_current_action['get_bugs'][$t_command_get_bugs[0]]['page'] );
            $t_data_send       = [
                                      'text'         => plugin_lang_get( 'bug_select' ),
                                      'reply_markup' => $t_inline_keyboard,
            ];
            break;

        case 'set_bug':
            $t_bug_id = $p_current_action['set_bug'];
            $t_upload_is_error = FALSE;

            $t_orgl_message = $p_reply_to_message;
            $t_content_type = $t_orgl_message->getType();

            switch( $t_content_type ) {
                case 'video':
                case 'photo':
                case 'document':

                    switch( $t_content_type ) {
                        case 'video':
                            $t_file_orgl     = $t_orgl_message->getVideo();
                            break;
                        case 'photo':
                            $t_content_photo = $t_orgl_message->getPhoto();
                            $t_file_orgl     = $t_content_photo[count( $t_content_photo ) - 1];
                            break;
                        case 'document':
                            $t_file_orgl     = $t_orgl_message->getDocument();
                            break;
                    }

                    $t_download = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_file_orgl->getFileId() ] );

                    $t_file = $t_download->getResult();

//                    $t_data_send_action = [
//                                              'chat_id' => $t_orgl_chat_id,
//                                              'action'  => 'upload_document'
//                    ];
//                    $t_rttt             = Longman\TelegramBot\Request::sendChatAction( $t_data_send_action );
                    
                    try {
                        Longman\TelegramBot\Request::downloadFile( $t_file );
                    } catch( Longman\TelegramBot\Exception\TelegramException $e ) {
                        $t_data_send       = [
                                                  'text' => $e->getMessage()
                        ];
                        $t_upload_is_error = TRUE;
                        break;
                    }
                    $t_file_path = plugin_config_get( 'download_path' ) . $t_file->getFilePath();

                    $t_file_for_attach = [
                                              'browser_upload' => [ 0 => FALSE ],
                                              'tmp_name'       => [ 0 => $t_file_path ],
                                              'name'           => $t_file_orgl->getFileName() == NULL ? [ 0 => $t_file->getFilePath() ] : [ 0 => $t_file_orgl->getFileName() ]
                    ];
                    $t_text            = $t_orgl_message->getCaption();
                    break;

                case 'text':
                    $t_text            = $t_orgl_message->getText();
                    $t_file_for_attach = array();
                    break;
            }
            //END SWITCH 'CONTENT TYPE'
//            $inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

            if( $t_upload_is_error ) {
                break;
            }

            try {
                bugnote_add_from_telegram( $t_bug_id, $t_text, $t_file_for_attach );

                $t_data_send = [
                                          'text'         => plugin_lang_get( 'content_upload_complete' ) . $p_current_action['set_bug'],
//                                          'reply_markup' => keyboard_bug_status_change_is( $t_bug_id )
                ];
            } catch( Mantis\Exceptions\MantisException $t_error ) {
                if( isset( $t_file_path ) ) {
                    $t_file_is_deleted = unlink( $t_file_path );
                }

                $t_params = $t_error->getParams();
                if( !empty( $t_params ) ) {
                    call_user_func_array( 'error_parameters', $t_params );
                }

                $t_error_text = error_string( $t_error->getCode() );
                $t_data_send  = [
                                          'text' => $t_error_text
                ];
            }
            break;
    }
    return $t_data_send;
}

function telegram_action_select( $p_orgl_chat_id, $p_callback_msg_id ) {

    $t_inline_keyboard = keyboard_get_menu_operations();
    $t_data_send       = [
                              'chat_id'             => $p_orgl_chat_id,
                              'reply_to_message_id' => $p_callback_msg_id,
                              'message_id'          => $p_callback_msg_id,
                              'text'                => plugin_lang_get( 'action_select' ),
                              'reply_markup'        => $t_inline_keyboard,
    ];
    return $t_data_send;
}

function telegram_lang_map_auto( $p_lang_code = null) {
	$t_lang = config_get_global( 'fallback_language' );

	if( isset( $p_lang_code ) ) {
		$t_auto_map = config_get_global( 'language_auto_map' );

		# Expand language map
		$t_auto_map_exp = array();
		foreach( $t_auto_map as $t_encs => $t_enc_lang ) {
			$t_encs_arr = explode( ',', $t_encs );

			foreach( $t_encs_arr as $t_enc ) {
				$t_auto_map_exp[trim( $t_enc )] = $t_enc_lang;
			}
		}

		# Find encoding
		if( isset( $t_auto_map_exp[$p_lang_code] ) ) {
                        $t_valid_langs = config_get( 'language_choices_arr' );
			$t_found_lang = $t_auto_map_exp[$p_lang_code];

                        if( in_array( $t_found_lang, $t_valid_langs, true ) ) {
				$t_lang = $t_found_lang;
			}
		}
	}

	return $t_lang;
}

function telegram_lang_get_default( $p_lang_code = null ) {
	global $g_active_language;

	$t_lang = false;

	# Confirm that the user's language can be determined
	if( function_exists( 'auth_is_user_authenticated' ) && auth_is_user_authenticated() ) {
		$t_lang = user_pref_get_language( auth_get_current_user_id() );
	}

	# Otherwise fall back to default
	if( !$t_lang ) {
		$t_lang = config_get_global( 'default_language' );
	}

	if( $t_lang == 'auto' ) {
		$t_lang = telegram_lang_map_auto( $p_lang_code );
	}

	# Remember the language
	$g_active_language = $t_lang;

	return $t_lang;
}

/**
 * Attach a "checked" attribute to a HTML element if $p_var === $p_val or
 * a {value within an array passed via $p_var} === $p_val.
 *
 * If the second parameter is not given, the first parameter is compared to
 * the boolean value true.
 *
 * @param mixed   $p_var    The variable to compare.
 * @param mixed   $p_val    The value to compare $p_var with.
 * @param boolean $p_strict Set to false to bypass strict type checking (defaults to true).
 * @return void
 */
function telegrambot_check_default( $p_var, $p_val = true, $p_strict = true ) {
	if( is_array( $p_var ) ) {
		foreach( $p_var as $t_this_var ) {
			if( helper_check_variables_equal( $t_this_var, $p_val, $p_strict ) ) {
				echo ' checked="checked"';
				return;
			}
		}
	} else {
		if( helper_check_variables_equal( $p_var, $p_val, $p_strict ) ) {
//			echo ' checked="checked"';
			return true;
		} else {
                        return false;
                }
	}
}

function telegramMsg_run_command( $t_command ) {
        switch( $t_command->getCommand() ) {
                case 'start':
                        $t_data = [
                            'chat_id' => $t_command->getFrom()->getId(),
                            'text' => sprintf(
                                                plugin_lang_get('first_message'), 
                                                config_get('window_title') . ' ( ' . config_get('path') . ' )', 
                                                ' ( ' . config_get('path') . plugin_page('account_telegram_prefs_page', TRUE) . ' )'
                            ),
                        ];
                        break;

                case 'stop':
                        $t_user_id = user_get_id_by_telegram_user_id($t_command->getFrom()->getId());

                        telegram_message_realatationship_delete($t_command->getFrom()->getId());
                        telegram_bot_user_mapping_delete($t_user_id);
                        plugin_config_delete('bug_data_draft', auth_get_current_user_id());
                        plugin_config_delete('bug_data_draft_chat_id', auth_get_current_user_id());
                        plugin_config_delete('bug_data_draft_message_id', auth_get_current_user_id());
                        plugin_config_delete('bug_data_draft_text_msg', auth_get_current_user_id());
                        plugin_config_delete('bug_data_draft_current_field_to_save', auth_get_current_user_id());

                        $t_data = [
                            'chat_id' => $t_command->getFrom()->getId(),
                            'text' => plugin_lang_get('end_message')
                        ];
                        break;

                default:
                        $t_data = [
                            'chat_id' => $t_command->getFrom()->getId(),
                            'text' => plugin_lang_get('command_not_found')
                        ];
        }
        return $t_data;
}

//function telegrambot_get_pin_code( $p_telegram_user_id ) {
//
//                telegrembot_clear_old_pins();
//
//        //        $t_pin_code = $p_telegram_user_id;
//
//                db_param_push();
//
//        //        $t_query = 'SELECT pin_code FROM {plugin_TelegramBot_pin_codes} WHERE telegram_user_id=' . db_param();
//                $t_query = 'SELECT * FROM {plugin_TelegramBot_pin_codes} WHERE 1';
//        //	$t_result = db_query( $t_query, array( $p_telegram_user_id ) );
//                $t_result = db_query( $t_query );
//
//                $t_rows = array();
//
//                while( $c_row = db_fetch_array( $t_result ) ) {
//                        $t_rows[] = $c_row;
//                }
//
//
//                foreach( $t_rows as $t_row) {
//                        if( $t_row['telegram_user_id'] == $p_telegram_user_id ) {
//                                return $t_row['pin_code'];
//                        }
//                }
//
//                while( true ) {
//                        $t_pin_code = mt_rand(1000, 9999);
//                        foreach( $t_rows as $t_row) {
//                                if( $t_row['pin_code'] == $t_pin_code ) {
//                                        continue;
//                                }
//
//                        }
//
//                        db_param_push();
//
//                        $t_query = 'INSERT INTO {plugin_TelegramBot_pin_codes} (telegram_user_id, pin_code, timestamp) VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ')';
//                        db_query( $t_query, array( $p_telegram_user_id, $t_pin_code, db_now() ) );
//
//                        return $t_pin_code;
//                }
//        }

//function telegrembot_clear_old_pins() {
//
//                $t_time_cut = db_now() - (15*60);
//                db_param_push();
//                $t_query = 'DELETE FROM {plugin_TelegramBot_pin_codes} WHERE timestamp <' . $t_time_cut;
//                db_query( $t_query );
//}