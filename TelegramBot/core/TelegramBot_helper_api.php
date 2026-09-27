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

/**
 * Ask for a confirmation of the unlink and let the administrator decide whether the
 * chat is told about it. The core helper_ensure_confirmed() carries no fields of its
 * own, and the decision belongs to the moment of the action rather than to a setting.
 *
 * @param string  $p_message        Question to confirm.
 * @param string  $p_button_label   Label of the confirmation button.
 * @param boolean $p_notify_default State of the notification checkbox, from the settings.
 * @param string  $p_return_url     Page to go back to.
 * @return void
 */
function telegram_ensure_unlink_confirmed( $p_message, $p_button_label, $p_notify_default, $p_return_url ) {
        if( gpc_get_bool( '_confirmed' ) ) {
                return;
        }

        layout_page_header();
        layout_page_begin();

        echo '<div class="col-md-12 col-xs-12">';
        echo '<div class="space-10"></div>';
        echo '<div class="alert alert-warning center">';
        echo '<p class="bigger-110">' . $p_message . '</p>';

        echo '<form method="post" class="center" action="">' . "\n";
        # CSRF protection not required here - the form of the calling page carries the
        # token and it is reprinted below along with the rest of its fields
        print_hidden_inputs( $_POST );
        print_hidden_inputs( $_GET );
        echo '<input type="hidden" name="_confirmed" value="1" />' . "\n";

        echo '<label class="inline">';
        echo '<input type="checkbox" class="ace input-sm" name="notify_user" value="1" ' . ( $p_notify_default ? 'checked="checked" ' : '' ) . '/>';
        echo '<span class="lbl padding-6">' . plugin_lang_get( 'user_unlink_notify_user' ) . '</span>';
        echo '</label>';

        echo '<div class="space-10"></div>';
        echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . string_attribute( $p_button_label ) . '" />';
        echo "\n</form>";

        echo '<div class="space-10"></div>';
        echo '<a href="' . $p_return_url . '">' . lang_get( 'go_back' ) . '</a>';

        echo '<div class="space-10"></div>';
        echo '</div></div>';

        layout_page_end();
        exit;
}

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
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );
    # The binding of the accounts changes data, so the confirmation must not be
    # forgeable cross-site: the fresh token is printed after the reprinted fields
    # and thus overrides a stale one among them
    echo form_security_field( 'plugin_TelegramBot_registred' );
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

        # The key of the entry is localized by the core when the history is shown,
        # so the values may carry language neutral data only
        plugin_history_log( $p_bug_id, 'history_file_added', '' );

        # Files without text produce no bugnote, so there is no note id to link to
        return null;
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
        
        plugin_history_log( $p_bug_id, 'history_note_added', '', (string)$t_noteId['id'] );

        if( count( $p_files ) > 0 ) {
            plugin_history_log( $p_bug_id, 'history_file_added', '' );
        }

        return (int)$t_noteId['id'];
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
 * Turn a fatal error signalled by the core into an exception.
 *
 * The error handler of MantisBT stops the process on E_USER_ERROR, and a single
 * poisonous update would take down the whole batch: in the long polling loop that
 * happens before the offset is stored, so the very same batch is read again on
 * every run of cron and the bot stays deaf for everybody. Only the fatal errors
 * are converted, the rest keeps the handling it had - a warning is not meant to
 * interrupt anything.
 *
 * @param integer $p_type  Level of the error raised.
 * @param string  $p_error Message of the error, the code of the error for a core one.
 * @param string  $p_file  File the error was raised in.
 * @param integer $p_line  Line the error was raised on.
 * @return boolean FALSE to fall back to the standard handling of PHP.
 * @throws ErrorException
 */
function telegram_update_error_handler( $p_type, $p_error, $p_file, $p_line ) {
    global $g_telegram_previous_error_handler;

    if( $p_type != E_USER_ERROR ) {
        if( $g_telegram_previous_error_handler === NULL ) {
            return FALSE;
        }

        return call_user_func( $g_telegram_previous_error_handler, $p_type, $p_error, $p_file, $p_line );
    }

    # The message of a core trigger_error is the numeric code of the error, it is
    # carried in the code of the exception so that the text can be resolved later
    $t_code = is_numeric( $p_error ) ? (int)$p_error : 0;

    throw new ErrorException( $p_error, $t_code, $p_type, $p_file, $p_line );
}

/**
 * Tell the user that the update he has sent could not be processed.
 *
 * Best effort only: the update is dropped either way and a failure of the
 * notification itself must not take down the rest of the batch.
 *
 * @param object    $p_update_content Content of the update being processed, NULL when unknown.
 * @param Throwable $p_exception      Error the processing has died on.
 * @return void
 */
function telegram_update_error_notify( $p_update_content, $p_exception ) {

    # The update is dropped, so the log is the only place the error is left in
    plugin_log_event( sprintf( 'ERROR! The update was dropped: %s, code %d, %s at %s:%d',
                              get_class( $p_exception ),
                              $p_exception->getCode(),
                              $p_exception->getMessage(),
                              $p_exception->getFile(),
                              $p_exception->getLine() ) );

    # An error of the core carries its code, its text is the one the web interface shows
    if( $p_exception instanceof ErrorException && $p_exception->getCode() > 0 ) {
        $t_text = error_string( (int)$p_exception->getCode() );
    } else {
        $t_text = plugin_lang_get( 'error_update_processing' ) . ' ' . $p_exception->getMessage();
    }

    try {
        if( $p_update_content instanceof \Longman\TelegramBot\Entities\CallbackQuery ) {
            # The regular path answers the query at the end of the callback branch, and a
            # query answered twice is simply refused by Telegram, which does no harm here.
            # The pop-up of a callback answer holds 200 characters, a longer one is refused
            $p_update_content->answer( array(
                                      'text'       => mb_substr( $t_text, 0, 200 ),
                                      'show_alert' => true,
            ) );
        } else if( $p_update_content instanceof \Longman\TelegramBot\Entities\Message ) {
            # Not a reply: the dispatcher removes the messages answering the wizard,
            # and a reply to a message already gone is refused by Telegram
            RequestMantis::sendMessage( array(
                                      'chat_id' => $p_update_content->getChat()->getId(),
                                      'text'    => $t_text,
            ) );
        }
    } catch( Throwable $t_exception ) {
        plugin_log_event( 'ERROR! The error notification was not sent: ' . $t_exception->getMessage() );
    }
}

/**
 * Whether the issue draft of the current user is driven by the given message.
 *
 * The state of the wizard is kept per user and bound to a single card message, so
 * a press on any other message is refused: two cards driving one draft show the
 * user two views of it contradicting each other.
 *
 * @param integer $p_message_id Message the callback query has arrived from.
 * @return boolean TRUE when there is no draft yet or the draft belongs to the message.
 */
function telegram_draft_belongs_to_message( $p_message_id ) {

    $t_user_id = auth_get_current_user_id();

    if( json_decode( plugin_config_get( 'bug_data_draft', NULL, FALSE, $t_user_id ), TRUE ) == NULL ) {
        return TRUE;
    }

    $t_message_id = plugin_config_get( 'bug_data_draft_message_id', NULL, FALSE, $t_user_id );

    return is_blank( $t_message_id ) || $t_message_id == $p_message_id;
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

        # The draft is dropped along with the refused answers, so the log is the
        # only place the rejected data is left in
        plugin_log_event( sprintf( 'The issue was not created, error %d ( %s ), draft %s',
                                  $t_error->getCode(),
                                  json_encode( $t_params ),
                                  json_encode( $p_bug_data_draft ) ) );

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

    # The step back button of the card is labelled with the very same step, so both
    # of them are taken out of one list
    $t_answered = telegram_draft_answered_steps( $p_bug_data_draft );

    return empty( $t_answered ) ? NULL : end( $t_answered );
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
            # profile_get_name() is deprecated since 2.28.0 in favour of the class
            # introduced by the same version
            if( class_exists( 'ProfileData' ) ) {
                $t_profile = new ProfileData( (int)$t_value );

                return $t_profile -> get_name();
            }

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

        # The project taken from the profile is marked as the default one
        if( $t_step == 'project' && !empty( $p_bug_data_draft['project_is_default'] ) ) {
            $t_display .= ' ' . plugin_lang_get( 'default_mark' );
        }

        $t_lines[] = telegram_draft_step_label( $t_step ) . ': ' . $t_display;
    }

    return implode( PHP_EOL, $t_lines );
}

/**
 * The steps of the draft already answered, in the order they are asked in: the
 * required ones first, the optional ones after them, each in the canonical order.
 * The last of them is the one the step back button takes back, so a summary asked
 * before the optional fields must not come after them.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return array Step names.
 */
function telegram_draft_answered_steps( array $p_bug_data_draft ) {
    $t_required = array();
    $t_optional = array();

    foreach( telegram_draft_steps_get( $p_bug_data_draft ) as $t_step ) {
        if( !telegram_draft_step_is_answered( $t_step, $p_bug_data_draft ) ) {
            continue;
        }

        if( telegram_draft_step_is_required( $t_step ) ) {
            $t_required[] = $t_step;
        } else {
            $t_optional[] = $t_step;
        }
    }

    return array_merge( $t_required, $t_optional );
}

/**
 * Describe the issue draft wizard for the navigation buttons of its cards.
 *
 * The steps the core insists on are the ones every reporter walks through, so their
 * step back buttons are written by hand and read as a sentence of the language of the
 * user. The optional fields and the custom fields of the project, the names of which
 * are only known at run time, are named after the label of the step instead: a label
 * put behind a colon needs no grammar of its own.
 *
 * @param array $p_bug_data_draft Issue draft.
 * @return array Descriptor of the wizard.
 */
function telegram_draft_wizard_descriptor( array $p_bug_data_draft ) {

    return keyboard_wizard_descriptor(
                              TelegrambotActions::REPORT_BUG_TAG,
                              TelegrambotActions::STOP_REPORT_ISSUE_TAG,
                              telegram_draft_answered_steps( $p_bug_data_draft ),
                              'telegram_draft_step_label',
                              array(
                                                        'replace'      => array(
                                                                                  'category'    => 'draft_replace_category',
                                                                                  'summary'     => 'draft_replace_summary',
                                                                                  'description' => 'draft_replace_description',
                                                        ),
                                                        'project_step' => 'project',
                                                        'cancel_deep'  => 'keyboard_button_delete_draft',
                              )
            );
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

    $t_action_line = telegram_action_line( TelegrambotActions::REPORT_BUG_TAG );
    $t_answers     = telegram_draft_card_text_rebuild( $p_bug_data_draft );
    $t_prompt      = array();

    if( !is_blank( $p_suffix ) ) {
        $t_prompt[] = plugin_lang_get( 'card_question_prefix' ) . $p_suffix;
    }

    if( !is_blank( $p_error ) ) {
        $t_prompt[] = plugin_lang_get( 'card_error_prefix' ) . $p_error;
    }

    $t_context = is_blank( $t_answers ) ? $t_action_line : $t_action_line . PHP_EOL . $t_answers;

    if( empty( $t_prompt ) ) {
        return $t_context;
    }

    return telegram_card_prompt_append( $t_context, implode( PHP_EOL, $t_prompt ) );
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
 * The text a message carries: the text of a plain message, the caption of a file.
 *
 * @param Longman\TelegramBot\Entities\Message|null $p_message Message to read, null when there is none.
 * @return string Text of the message, empty when it carries none.
 */
function telegram_message_text_get( $p_message ) {

    if( $p_message === NULL ) {
        return '';
    }

    $t_text = $p_message->getText();

    if( is_blank( $t_text ) ) {
        $t_text = $p_message->getCaption();
    }

    return (string)$t_text;
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
    if( telegram_draft_required_step_pending( $p_bug_data_draft ) !== 'summary' ) {
        return;
    }

    $t_summary = telegram_message_text_get( $p_orgl_message );

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

    # The draft is being filled in somewhere else, so this message shows a draft
    # which does not exist anymore: keeping its buttons would leave the user with
    # a card looking alive, it turns into the list of the actions instead
    if( !telegram_draft_belongs_to_message( $t_callback_msg_id ) ) {
        telegram_callback_alert_set( plugin_lang_get( 'draft_other_message' ) );

        return telegram_action_select( $t_orgl_chat_id, $t_callback_msg_id );
    }

    $t_orgl_message = $p_callback_query->getMessage()->getReplyToMessage();
    $t_content_type = $t_orgl_message->getType();

    # The wizard entry is the callback creating the draft, the callbacks of the
    # project list navigation always arrive with an existing draft
    $t_draft_is_new = ( $t_bug_data_draft == NULL );

    if( $t_bug_data_draft == NULL ) {

        # Only one wizard may be in progress at a time: they all take their answers
        # from the plain text messages of the same chat, so a second one would steal
        # the answers of the first
        if( !is_blank( plugin_config_get( 'event_draft', '', FALSE, auth_get_current_user_id() ) ) ) {
            telegram_callback_alert_set( plugin_lang_get( 'event_draft_busy_event' ) );

            return telegram_action_select( $t_orgl_chat_id, $t_callback_msg_id );
        }

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

                #The upload rules are checked before the download on the size known
                #from the update; a photo carries no name of its own, its name is
                #taken from the file path and checked after getFile
                $t_file_name       = $t_file_orgl->getFileName() == NULL ? '' : $t_file_orgl->getFileName();
                $t_upload_is_error = FALSE;
                $t_error_text      = telegram_file_check( $t_file_name, $t_file_orgl->getFileSize() );

                if( $t_error_text == '' ) {
                    $t_download = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_file_orgl->getFileId() ] );
                    $t_file     = $t_download->getResult();

                    if( $t_file_name == '' ) {
                        $t_file_name  = $t_file->getFilePath();
                        $t_error_text = telegram_file_check( $t_file_name, 0 );
                    }
                }

                if( $t_error_text != '' ) {
                    $t_data_send       = [
                                              'chat_id'    => $t_orgl_chat_id,
                                              'message_id' => $t_callback_msg_id,
                                              'text'       => $t_error_text
                    ];
                    $t_upload_is_error = TRUE;
                    break;
                }

                $t_data_send_action = [
                                          'chat_id' => $t_orgl_chat_id,
                                          'action'  => 'upload_document'
                ];
                $t_rttt             = Longman\TelegramBot\Request::sendChatAction( $t_data_send_action );
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
                                          'name'           => [ 0 => $t_file_name ]
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
                        # The card labels the project of the profile as the default one
                        $p_current_action[TelegrambotActions::SET_PROJECT]['default'] = 1;
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
                    $t_bug_data_draft['project']            = $p_current_action[TelegrambotActions::SET_PROJECT]['id'];
                    $t_bug_data_draft['project_is_default'] = !empty( $p_current_action[TelegrambotActions::SET_PROJECT]['default'] );
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

#The size of an attachment the bot is able to take: the Bot API refuses to
#serve files bigger than 20 MB to bots, the limit of MantisBT applies when
#it is the stricter one.
function telegram_file_max_size() {
    return (int)min( 20971520, file_get_max_file_size() );
}

#The file types the upload rules of MantisBT leave for the user. The core
#checks the extension against a white list, and against a black list when
#the white one is empty, so the answer takes the form of the list in force.
#An empty string means every type is allowed.
function telegram_file_types_info() {

    $t_allowed_files = trim( config_get( 'allowed_files' ) );

    if( !is_blank( $t_allowed_files ) ) {
        return sprintf( plugin_lang_get( 'file_types_allowed' ), telegram_file_types_format( $t_allowed_files ) );
    }

    $t_disallowed_files = trim( config_get( 'disallowed_files' ) );

    if( !is_blank( $t_disallowed_files ) ) {
        return sprintf( plugin_lang_get( 'file_types_disallowed' ), telegram_file_types_format( $t_disallowed_files ) );
    }

    return '';
}

#The lists of the core are comma separated extensions typed by hand,
#so the spacing of the source is not to be trusted
function telegram_file_types_format( $p_file_types ) {

    $t_types = array_filter( array_map( 'trim', explode( ',', $p_file_types ) ), 'strlen' );

    return implode( ', ', $t_types );
}

#Check a file received from Telegram against the upload rules of MantisBT
#and the Bot API download limit. Returns the localized error text or an
#empty string when the file is accepted. An empty name skips the name
#checks: a photo gets its name from the file path only after getFile.
function telegram_file_check( $p_file_name, $p_file_size ) {

    $t_max_file_size = telegram_file_max_size();

    #Both limits mean the same thing to the user - the file will not go through -
    #so the message names the effective one instead of its origin. The size is
    #worded the way the report page of MantisBT words it.
    if( $p_file_size > $t_max_file_size ) {
        return sprintf(
                                  plugin_lang_get( 'error_file_size' ),
                                  get_filesize_info( $t_max_file_size / 1024, lang_get( 'kib' ) )
        );
    }

    if( !is_blank( $p_file_name ) ) {
        if( strlen( $p_file_name ) > DB_FIELD_SIZE_FILENAME ) {
            error_parameters( $p_file_name );
            return error_string( ERROR_FILE_NAME_TOO_LONG );
        }

        if( !file_type_check( $p_file_name ) ) {
            $t_types_info = telegram_file_types_info();

            return error_string( ERROR_FILE_NOT_ALLOWED )
                    . ( $t_types_info == '' ? '' : ' ' . $t_types_info );
        }
    }

    return '';
}

/**
 * The first line of every card of a dialog: the action the dialog belongs to.
 *
 * @param string $p_action_tag TelegrambotActions tag of the flow.
 * @return string
 */
function telegram_action_line( $p_action_tag ) {
    switch( $p_action_tag ) {
        case TelegrambotActions::REPORT_BUG_TAG:
            $t_action = lang_get( 'report_bug_link' );
            break;

        case TelegrambotActions::ADD_COMMENT_TAG:
            $t_action = lang_get( 'add_bugnote_title' );
            break;

        case TelegrambotActions::UPDATE_BUG_TAG:
            $t_action = plugin_lang_get( 'menu_update_bug' );
            break;

        # The status change dialog is a part of the update issue flow, the line
        # chains the operation picked within it
        case TelegrambotActions::CHANGE_STATUS_TAG:
            $t_action = plugin_lang_get( 'menu_update_bug' ) . ' → ' . lang_get( 'bug_status_to_button' );
            break;

        default:
            return '';
    }

    return plugin_lang_get( 'action_label' ) . ': ' . $t_action;
}

/**
 * Put the prompt telling the user what to do now under the context lines of a
 * dialog card, separated the way the draft card separates its question.
 *
 * @param string $p_context Context lines of the card.
 * @param string $p_prompt  Prompt of the current step.
 * @return string
 */
function telegram_card_prompt_append( $p_context, $p_prompt ) {
    return $p_context . PHP_EOL . plugin_lang_get( 'card_separator' ) . PHP_EOL . $p_prompt;
}

/**
 * The line naming the issue on the cards of a dialog, labeled the way the
 * issue view page of the core is titled.
 *
 * @param BugData $p_bug A valid bug object.
 * @return string
 */
function telegram_bug_line( BugData $p_bug ) {
    return lang_get( 'issue_id' ) . $p_bug->id . ': ' . $p_bug->summary;
}

/**
 * The line naming the filter picked on the issue list step, empty until one is
 * picked.
 *
 * @return string
 */
function telegram_bug_select_filter_line() {
    switch( plugin_config_get( 'bug_select_filter', '', FALSE, auth_get_current_user_id() ) ) {
        case 'assigned':
            $t_name = lang_get( 'my_view_title_assigned' );
            break;

        case 'monitored':
            $t_name = lang_get( 'my_view_title_monitored' );
            break;

        case 'reported':
            $t_name = lang_get( 'my_view_title_reported' );
            break;

        case 'use_query':
            $t_name = lang_get( 'use_query' );
            break;

        default:
            return '';
    }

    return plugin_lang_get( 'filter_label' ) . ': ' . $t_name;
}

/**
 * The context lines every card of an issue picking flow starts with: the
 * action, the picked project (named as the default one when it came from the
 * profile) and, once picked, the filter.
 *
 * @param string  $p_action_tag  TelegrambotActions tag of the flow.
 * @param boolean $p_with_filter False on the steps before the filter is picked.
 * @return string
 */
function telegram_bug_select_context( $p_action_tag, $p_with_filter = TRUE ) {
    $t_user_id = auth_get_current_user_id();

    $t_lines   = array();
    $t_lines[] = telegram_action_line( $p_action_tag );

    $t_project_id   = (int)plugin_config_get( 'bug_select_project', ALL_PROJECTS, FALSE, $t_user_id );
    $t_project_name = $t_project_id == ALL_PROJECTS ? lang_get( 'all_projects' ) : project_get_name( $t_project_id, /* trigger_errors */ false );

    if( plugin_config_get( 'bug_select_project_is_default', 0, FALSE, $t_user_id ) ) {
        $t_project_name .= ' ' . plugin_lang_get( 'default_mark' );
    }

    $t_lines[] = lang_get( 'email_project' ) . ': ' . $t_project_name;

    if( $p_with_filter ) {
        $t_filter_line = telegram_bug_select_filter_line();

        if( $t_filter_line != '' ) {
            $t_lines[] = $t_filter_line;
        }
    }

    return implode( PHP_EOL, $t_lines );
}

/**
 * Whether the current user, being the reporter of the issue, may close it: the
 * user still has the rights to report issues (to prevent users downgraded to
 * viewers from updating issues) and reporters are allowed to close their own
 * issues.
 *
 * @param BugData $p_bug A valid bug object.
 * @return boolean
 */
function telegram_bug_reporter_can_close( BugData $p_bug ) {
    return bug_is_user_reporter( $p_bug->id, auth_get_current_user_id() )
            && access_has_bug_level( config_get( 'report_bug_threshold' ), $p_bug->id )
            && ON == config_get( 'allow_reporter_close' );
}

/**
 * Build the message of a step picking the issue to act on: the list of the
 * projects, the sections narrowing the issues down within the picked project or
 * a page of the issues of a section. The buttons carry the action tag of the
 * flow the step belongs to, the picked project is kept per user.
 *
 * @param array  $p_current_action Decoded callback data of the step.
 * @param string $p_action_tag     TelegrambotActions tag of the flow.
 * @return array Data of the message to send.
 */
function telegram_bug_select_step( $p_current_action, $p_action_tag ) {

    $t_command = array_keys( $p_current_action );
    $t_user_id = auth_get_current_user_id();

    if( $t_command[0] == 'start' ) {
        # On the flow entry the default project of the profile is taken without
        # asking, the way the report wizard does; the section list carries the
        # button going back to the project list
        $t_default_project = user_pref_get_pref( $t_user_id, 'default_project' );

        if( ALL_PROJECTS == $t_default_project ) {
            $p_current_action = array( 'get_projects' => array( 'page' => 1 ) );
        } else {
            plugin_config_set( 'bug_select_project', (int)$t_default_project, $t_user_id );
            plugin_config_set( 'bug_select_project_is_default', 1, $t_user_id );

            $p_current_action = array( 'get_default_category' => '' );
        }

        $t_command = array_keys( $p_current_action );
    }

    if( $t_command[0] == 'get_projects' ) {
        return [
                                  'text'         => telegram_card_prompt_append( telegram_action_line( $p_action_tag ), lang_get( 'select_project_button' ) ),
                                  'reply_markup' => keyboard_bug_select_projects_get( $p_action_tag, (int)$p_current_action['get_projects']['page'] ),
        ];
    }

    if( $t_command[0] == 'sprj' ) {
        plugin_config_set( 'bug_select_project', (int)$p_current_action['sprj']['id'], $t_user_id );
        plugin_config_set( 'bug_select_project_is_default', 0, $t_user_id );

        $t_command[0] = 'get_default_category';
    }

    if( $t_command[0] == 'get_default_category' ) {
        # The filter is being picked anew, the one of the previous walk is gone
        plugin_config_delete( 'bug_select_filter', $t_user_id );

        return [
                                  'text'         => telegram_card_prompt_append(
                                          telegram_bug_select_context( $p_action_tag, FALSE ),
                                          plugin_lang_get( 'bug_section_select' ) ),
                                  'reply_markup' => telegram_bot_get_keyboard_default_filter( $p_action_tag ),
        ];
    }

    # The list below is narrowed down to the project picked above, the picked
    # filter is kept for the cards of the later steps
    $t_project_id = (int)plugin_config_get( 'bug_select_project', ALL_PROJECTS, FALSE, $t_user_id );

    $t_command_get_bugs = array_keys( $p_current_action['get_bugs'] );

    plugin_config_set( 'bug_select_filter', $t_command_get_bugs[0], $t_user_id );

    switch( $t_command_get_bugs[0] ) {
        case 'assigned':
            $t_custom_filter = filter_create_assigned_to_unresolved( $t_project_id, $t_user_id );
            break;

        case 'monitored':
            $t_custom_filter = filter_create_monitored_by( $t_project_id, $t_user_id );
            break;

        case 'reported':
            $t_custom_filter = filter_create_reported_by( $t_project_id, $t_user_id );
            break;

        case 'use_query':
            $t_custom_filter = filter_get_default();

            if( $t_project_id != ALL_PROJECTS ) {
                $t_custom_filter[FILTER_PROPERTY_PROJECT_ID] = array( '0' => $t_project_id );
                $t_custom_filter = filter_ensure_valid_filter( $t_custom_filter );
            }
            break;
    }

    $t_inline_keyboard = keyboard_bugs_get( $t_custom_filter, $p_current_action['get_bugs'][$t_command_get_bugs[0]]['page'], $p_action_tag, $t_command_get_bugs[0] );

    return [
                              'text'         => telegram_card_prompt_append(
                                      telegram_bug_select_context( $p_action_tag ),
                                      plugin_lang_get( 'bug_select' ) ),
                              'reply_markup' => $t_inline_keyboard,
    ];
}

/**
 * Process a step of the flow updating an existing issue: pick the project, the
 * filter, the issue, then the operation to run on it, for now the only
 * operation is the status change.
 *
 * @param array $p_current_action Decoded callback data of the step.
 * @return array Data of the message to send.
 */
function telegram_update_bug( $p_current_action ) {

    # Walking the lists drops a stale status change dialog
    telegram_status_change_draft_clear();

    $t_command = array_keys( $p_current_action );

    switch( $t_command[0] ) {
        case 'start':
        case 'get_projects':
        case 'sprj':
        case 'get_default_category':
        case 'get_bugs':
            $t_data_send = telegram_bug_select_step( $p_current_action, TelegrambotActions::UPDATE_BUG_TAG );
            break;

        case 'set_bug':
            $t_bug_id = (int)$p_current_action['set_bug'];
            $t_bug    = bug_get( $t_bug_id );

            $t_data_send = [
                                      'text'         => telegram_card_prompt_append(
                                              telegram_bug_select_context( TelegrambotActions::UPDATE_BUG_TAG ) . PHP_EOL . telegram_bug_line( $t_bug ),
                                              plugin_lang_get( 'action_select' ) ),
                                      'reply_markup' => keyboard_bug_actions_get( $t_bug ),
            ];
            break;

        default:
            $t_data_send = telegram_bug_select_step( array( 'start' => '' ), TelegrambotActions::UPDATE_BUG_TAG );
            break;
    }

    return $t_data_send;
}

/**
 * Load the state of the status change dialog of the current user.
 *
 * @return array|null Draft array or null when no dialog is in progress.
 */
function telegram_status_change_draft_get() {
    $t_raw = plugin_config_get( 'status_change_draft', '', FALSE, auth_get_current_user_id() );

    if( is_blank( $t_raw ) ) {
        return NULL;
    }

    return json_decode( $t_raw, TRUE );
}

/**
 * Store the state of the status change dialog of the current user.
 *
 * @param array $p_draft Draft array.
 * @return void
 */
function telegram_status_change_draft_set( $p_draft ) {
    plugin_config_set( 'status_change_draft', json_encode( $p_draft ), auth_get_current_user_id() );
}

/**
 * Drop the whole state of the status change dialog of the current user.
 *
 * @return void
 */
function telegram_status_change_draft_clear() {
    $t_user_id = auth_get_current_user_id();

    plugin_config_delete( 'status_change_draft', $t_user_id );
    plugin_config_delete( 'status_change_draft_chat_id', $t_user_id );
    plugin_config_delete( 'status_change_draft_message_id', $t_user_id );
    plugin_config_delete( 'status_change_draft_await', $t_user_id );
}

/**
 * Distill the attachment out of the message the dialog was started from: for the
 * media messages the id of the file picked up at the submit step. The change
 * status page of the core carries a file next to its note the same way; the text
 * of the message is the note offered by the dialog, see
 * telegram_status_change_bugnote_suggest().
 *
 * @param Longman\TelegramBot\Entities\Message|null $p_message Message the action menu replied to.
 * @return array Content descriptor stored in the draft.
 */
function telegram_status_change_content_descriptor( $p_message ) {
    $t_content = array( 'file_id' => '', 'file_name' => '', 'file_size' => 0 );

    if( $p_message === NULL ) {
        return $t_content;
    }

    switch( $p_message->getType() ) {
        case 'video':
            $t_file = $p_message->getVideo();
            break;
        case 'photo':
            $t_content_photo = $p_message->getPhoto();
            $t_file          = $t_content_photo[count( $t_content_photo ) - 1];
            break;
        case 'document':
            $t_file = $p_message->getDocument();
            break;
        default:
            $t_file = NULL;
    }

    if( $t_file !== NULL ) {
        $t_content['file_id']   = $t_file->getFileId();
        $t_content['file_name'] = (string)$t_file->getFileName();
        $t_content['file_size'] = (int)$t_file->getFileSize();
    }

    return $t_content;
}

/**
 * The access checks bug_change_status_page.php of the core does before drawing
 * the form.
 *
 * @param BugData $p_bug        A valid bug object.
 * @param integer $p_new_status Status the issue is moved to.
 * @param string  $p_warning    Out: warning shown on the card when the transition may go on.
 * @return string Error text, empty when the transition may go on.
 */
function telegram_status_change_entry_check( BugData $p_bug, $p_new_status, &$p_warning ) {
    $t_project_id = $p_bug->project_id;
    $t_reopen     = config_get( 'bug_reopen_status', null, null, $t_project_id );
    $t_resolved   = config_get( 'bug_resolved_status_threshold', null, null, $t_project_id );
    $t_closed     = config_get( 'bug_closed_status_threshold', null, null, $t_project_id );
    $t_user_id    = auth_get_current_user_id();

    if( $p_bug->status >= $t_resolved && $p_new_status <= $t_reopen ) {
        if( !access_can_reopen_bug( $p_bug, $t_user_id ) ) {
            return error_string( ERROR_ACCESS_DENIED );
        }
    } else if( $p_new_status == $t_closed ) {
        if( !access_can_close_bug( $p_bug, $t_user_id ) ) {
            return error_string( ERROR_ACCESS_DENIED );
        }
    } else if( bug_is_readonly( $p_bug->id )
            || !access_has_bug_level( access_get_status_threshold( $p_new_status, $t_project_id ), $p_bug->id, $t_user_id ) ) {
        return error_string( ERROR_ACCESS_DENIED );
    }

    if( $p_new_status >= $t_resolved && !relationship_can_resolve_bug( $p_bug->id ) ) {
        if( OFF == config_get( 'allow_parent_of_unresolved_to_close' ) ) {
            return error_string( ERROR_BUG_RESOLVE_DEPENDANTS_BLOCKING );
        }

        $p_warning = lang_get( 'relationship_warning_blocking_bugs_not_resolved_2' );
    }

    return '';
}

/**
 * The questions of the status change dialog in the order they are asked in, the
 * order the fields are shown in on bug_change_status_page.php of the core.
 *
 * @return array Step names.
 */
function telegram_status_change_steps_get() {

    return array( 'resolution', 'duplicate_id', 'handler', 'fixed_in_version', 'bugnote' );
}

/**
 * Whether the step of the status change dialog applies to the transition, the
 * conditions mirror the fields of bug_change_status_page.php.
 *
 * @param string  $p_step  Step name.
 * @param array   $p_draft Draft of the dialog.
 * @param BugData $p_bug   A valid bug object.
 * @return boolean
 */
function telegram_status_change_step_is_applicable( $p_step, $p_draft, BugData $p_bug ) {
    $t_project_id = $p_bug->project_id;
    $t_resolved   = config_get( 'bug_resolved_status_threshold', null, null, $t_project_id );
    $t_closed     = config_get( 'bug_closed_status_threshold', null, null, $t_project_id );
    $t_new_status = (int)$p_draft['new_status'];

    switch( $p_step ) {
        case 'resolution':
            return $t_new_status >= $t_resolved
                    && ( $t_new_status < $t_closed
                            || $p_bug->resolution < config_get( 'bug_resolution_fixed_threshold', null, null, $t_project_id ) );

        case 'duplicate_id':
            # The page shows the field along with the resolution one, the dialog
            # only asks for the id when the issue is resolved as a duplicate
            return $p_draft['resolution'] !== '' && $p_draft['resolution'] !== null
                    && (int)$p_draft['resolution'] == config_get( 'bug_duplicate_resolution', null, null, $t_project_id );

        case 'handler':
            return access_has_bug_level( config_get( 'update_bug_assign_threshold', config_get( 'update_bug_threshold' ) ), $p_bug->id );

        case 'fixed_in_version':
            return $t_new_status >= $t_resolved
                    && version_should_show_product_version( $t_project_id )
                    && !bug_is_readonly( $p_bug->id )
                    && access_has_bug_level( config_get( 'update_bug_threshold' ), $p_bug->id );

        case 'bugnote':
            return access_has_bug_level( config_get( 'add_bugnote_threshold' ), $p_bug->id );
    }

    return FALSE;
}

/**
 * The label of a question of the status change dialog, the same one the card
 * shows the answer under.
 *
 * @param string $p_step Step name.
 * @return string
 */
function telegram_status_change_step_label( $p_step ) {

    switch( $p_step ) {
        case 'handler':
            return lang_get( 'assigned_to' );
    }

    return lang_get( $p_step );
}

/**
 * The steps of the status change dialog already answered, in the canonical order.
 *
 * A step holding an empty string is not asked yet, null is a skipped one and a
 * skip is an answer to return to, the way the wizards treat theirs.
 *
 * @param array   $p_draft Draft of the dialog.
 * @param BugData $p_bug   A valid bug object.
 * @return array Step names.
 */
function telegram_status_change_answered_steps( array $p_draft, BugData $p_bug ) {
    $t_answered = array();

    foreach( telegram_status_change_steps_get() as $t_step ) {
        if( $p_draft[$t_step] !== ''
                && telegram_status_change_step_is_applicable( $t_step, $p_draft, $p_bug ) ) {
            $t_answered[] = $t_step;
        }
    }

    return $t_answered;
}

/**
 * Drop the answer given to a question of the status change dialog so that it can
 * be asked again.
 *
 * @param string $p_step  Step name.
 * @param array  $p_draft Draft of the dialog.
 * @return void
 */
function telegram_status_change_step_reset( $p_step, array &$p_draft ) {

    $p_draft[$p_step] = '';

    # The duplicate issue is only asked about when the issue is resolved as a
    # duplicate, so the answer given to it dies along with the resolution
    if( $p_step == 'resolution' ) {
        $p_draft['duplicate_id'] = '';
    }
}

/**
 * Describe the status change dialog for the navigation buttons of its cards.
 *
 * The dialog holds no draft of its own to delete and starts at the issue picked
 * beforehand rather than at a project, so both ends of it lead to the list of the
 * actions; its questions are named after the fields of the core, which the step
 * back button takes as they are.
 *
 * @param array   $p_draft Draft of the dialog.
 * @param BugData $p_bug   A valid bug object.
 * @return array Descriptor of the dialog.
 */
function telegram_status_change_wizard_descriptor( array $p_draft, BugData $p_bug ) {

    return keyboard_wizard_descriptor(
                              TelegrambotActions::CHANGE_STATUS_TAG,
                              TelegrambotActions::STOP_CHANGE_STATUS_TAG,
                              telegram_status_change_answered_steps( $p_draft, $p_bug ),
                              'telegram_status_change_step_label'
            );
}

/**
 * A string of the core named after the process the transition stands for, the
 * way bug_change_status_page.php takes the title of its form ( '_bug_title' )
 * and the label of its submit button ( '_bug_button' ).
 *
 * @param integer $p_new_status Status the issue is moved to.
 * @param string  $p_suffix     Suffix of the string key following the status label.
 * @return string
 */
function telegram_status_change_process_string( $p_new_status, $p_suffix ) {
    $t_status_label = str_replace( ' ', '_', MantisEnum::getLabel( config_get( 'status_enum_string' ), (int)$p_new_status ) );

    return lang_get( $t_status_label . $p_suffix );
}

/**
 * Offer the text of the message the dialog was started from as the note of the
 * transition, the way the issue wizard offers the summary of the issue.
 *
 * The note is offered instead of being asked for, so the questions asked before
 * it have to be answered already; the offer is made once and dies with the
 * making, a note undone by the step back button must not come back on its own.
 *
 * @param array $p_draft Draft of the dialog, saved by the function.
 * @return void
 */
function telegram_status_change_bugnote_suggest( array &$p_draft ) {

    if( !array_key_exists( 'bugnote_suggested', $p_draft ) ) {
        return;
    }

    if( telegram_status_change_pending_step( $p_draft ) !== 'bugnote' ) {
        return;
    }

    $t_bugnote = trim( (string)$p_draft['bugnote_suggested'] );
    unset( $p_draft['bugnote_suggested'] );

    if( $t_bugnote != '' ) {
        $p_draft['bugnote'] = $t_bugnote;
    }

    telegram_status_change_draft_set( $p_draft );
}

/**
 * The first question of the status change dialog left unanswered, null when the
 * change can be applied.
 *
 * @param array $p_draft Draft of the dialog.
 * @return string|null Step name.
 */
function telegram_status_change_pending_step( array $p_draft ) {
    $t_bug = bug_get( (int)$p_draft['bug_id'] );

    foreach( telegram_status_change_steps_get() as $t_step ) {
        # An empty string is a step not asked yet, null is a skipped one
        if( $p_draft[$t_step] === ''
                && telegram_status_change_step_is_applicable( $t_step, $p_draft, $t_bug ) ) {
            return $t_step;
        }
    }

    return NULL;
}

/**
 * Compose the card of the status change dialog: the issue, the transition, the
 * answers given so far and the pending question or error.
 *
 * @param array  $p_draft    Draft of the dialog.
 * @param string $p_question Question the card ends with.
 * @param string $p_error    Error shown before the question.
 * @return string
 */
function telegram_status_change_card_compose( $p_draft, $p_question = '', $p_error = '' ) {
    $t_bug = bug_get( (int)$p_draft['bug_id'] );

    $t_lines   = array();
    $t_lines[] = telegram_bug_select_context( TelegrambotActions::CHANGE_STATUS_TAG );
    $t_lines[] = telegram_bug_line( $t_bug );
    $t_lines[] = telegram_status_change_process_string( (int)$p_draft['new_status'], '_bug_title' );

    if( $p_draft['resolution'] !== '' && $p_draft['resolution'] !== null ) {
        $t_lines[] = lang_get( 'resolution' ) . ': ' . get_enum_element( 'resolution', (int)$p_draft['resolution'] );
    }

    if( $p_draft['duplicate_id'] !== '' && $p_draft['duplicate_id'] !== null ) {
        $t_lines[] = lang_get( 'duplicate_id' ) . ': ' . $p_draft['duplicate_id'];
    }

    if( $p_draft['handler'] !== '' && $p_draft['handler'] !== null ) {
        $t_lines[] = lang_get( 'assigned_to' ) . ': ' . user_get_name( (int)$p_draft['handler'] );
    }

    if( $p_draft['fixed_in_version'] !== '' && $p_draft['fixed_in_version'] !== null ) {
        $t_lines[] = lang_get( 'fixed_in_version' ) . ': ' . $p_draft['fixed_in_version'];
    }

    if( $p_draft['bugnote'] !== '' && $p_draft['bugnote'] !== null ) {
        $t_lines[] = lang_get( 'bugnote' ) . ': ' . $p_draft['bugnote'];
    }

    if( isset( $p_draft['warning'] ) && $p_draft['warning'] != '' ) {
        $t_lines[] = '⚠ ' . $p_draft['warning'];
    }

    $t_prompt = array();

    if( $p_error != '' ) {
        $t_prompt[] = '⚠ ' . $p_error;
    }

    if( $p_question != '' ) {
        $t_prompt[] = '❓ ' . $p_question;
    }

    if( empty( $t_prompt ) ) {
        return implode( PHP_EOL, $t_lines );
    }

    return telegram_card_prompt_append( implode( PHP_EOL, $t_lines ), implode( PHP_EOL, $t_prompt ) );
}

/**
 * Build the question of the given step of the status change dialog.
 *
 * @param string  $p_step  Step name.
 * @param array   $p_draft Draft of the dialog.
 * @param BugData $p_bug   A valid bug object.
 * @param string  $p_error Error shown on the card when the previous answer was rejected.
 * @return array Data of the message to send.
 */
function telegram_status_change_step_ask( $p_step, $p_draft, BugData $p_bug, $p_error = '' ) {
    $t_user_id = auth_get_current_user_id();

    switch( $p_step ) {
        case 'resolution':
            # The page preselects the duplicate resolution when a duplicate
            # relationship exists, the fixed one otherwise
            $t_fixed_threshold = config_get( 'bug_resolution_fixed_threshold', null, null, $p_bug->project_id );
            $t_default         = $p_bug->resolution >= $t_fixed_threshold ? $p_bug->resolution : $t_fixed_threshold;

            foreach( relationship_get_all_src( $p_bug->id ) as $t_relationship ) {
                if( $t_relationship->type == BUG_DUPLICATE ) {
                    $t_default = config_get( 'bug_duplicate_resolution', null, null, $p_bug->project_id );
                    break;
                }
            }

            $t_inline_keyboard = keyboard_enum_string_get( 'resolution', $t_default, TelegrambotActions::CHANGE_STATUS_TAG, TelegrambotActions::SET_STATUS_RESOLUTION );
            $t_question        = lang_get( 'resolution' );
            break;

        case 'duplicate_id':
            plugin_config_set( 'status_change_draft_await', 'duplicate_id', $t_user_id );

            $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );
            $t_question        = lang_get( 'duplicate_id' );
            break;

        case 'handler':
            $t_inline_keyboard = keyboard_handler_get( $p_bug->project_id, TelegrambotActions::CHANGE_STATUS_TAG, TelegrambotActions::SET_STATUS_HANDLER );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::CHANGE_STATUS_TAG => array( TelegrambotActions::SKIP_FIELD => 'handler' ) ) );
            $t_question = lang_get( 'assigned_to' );
            break;

        case 'fixed_in_version':
            $t_inline_keyboard = keyboard_version_option_list( $p_bug->fixed_in_version, $p_bug->project_id, VERSION_ALL, TelegrambotActions::SET_STATUS_FIXED_VERSION, TelegrambotActions::CHANGE_STATUS_TAG );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::CHANGE_STATUS_TAG => array( TelegrambotActions::SKIP_FIELD => 'fixed_in_version' ) ) );
            $t_question = lang_get( 'fixed_in_version' );
            break;

        case 'bugnote':
            plugin_config_set( 'status_change_draft_await', 'bugnote', $t_user_id );

            $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

            keyboard_skip_button_add( $t_inline_keyboard, array( TelegrambotActions::CHANGE_STATUS_TAG => array( TelegrambotActions::SKIP_FIELD => 'bugnote' ) ) );
            $t_question = lang_get( 'bugnote' );
            break;
    }

    keyboard_status_change_buttons_add( $t_inline_keyboard, $p_draft, $p_bug );

    return array(
                              'text'         => telegram_status_change_card_compose( $p_draft, $t_question, $p_error ),
                              'reply_markup' => $t_inline_keyboard,
    );
}

/**
 * Ask the question of the first unanswered applicable step of the status change
 * dialog or, when nothing is left to ask, show the menu the change is applied
 * from: the change is applied on demand only, the way the wizards create their
 * issue and event.
 *
 * @param array $p_draft Draft of the dialog.
 * @return array Data of the message to send.
 */
function telegram_status_change_ask_next_step( $p_draft ) {
    $t_user_id = auth_get_current_user_id();
    plugin_config_delete( 'status_change_draft_await', $t_user_id );

    # The message the dialog was started from offers the note of the transition
    telegram_status_change_bugnote_suggest( $p_draft );

    $t_bug  = bug_get( (int)$p_draft['bug_id'] );
    $t_step = telegram_status_change_pending_step( $p_draft );

    if( $t_step !== NULL ) {
        return telegram_status_change_step_ask( $t_step, $p_draft, $t_bug );
    }

    $t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

    keyboard_status_change_apply_button_add( $t_inline_keyboard, (int)$p_draft['new_status'] );
    keyboard_status_change_buttons_add( $t_inline_keyboard, $p_draft, $t_bug );

    return array(
                              'text'         => telegram_status_change_card_compose( $p_draft, plugin_lang_get( 'status_change_menu_prompt' ) ),
                              'reply_markup' => $t_inline_keyboard,
    );
}

/**
 * Apply the change out of the draft, clean the draft up and build the final view
 * of its card.
 *
 * @param array $p_draft Draft of the dialog.
 * @return array Data of the message to send.
 */
function telegram_status_change_submit( $p_draft ) {

    $t_result = telegram_bug_status_change( $p_draft );
    telegram_status_change_draft_clear();

    if( !$t_result['ok'] ) {
        return array( 'text' => $t_result['error'] );
    }

    $t_text = sprintf(
                              plugin_lang_get( 'status_change_complete' ),
                              $p_draft['bug_id'],
                              get_enum_element( 'status', (int)$t_result['old_status'] ),
                              get_enum_element( 'status', (int)$t_result['new_status'] )
    );

    if( $t_result['warning'] != '' ) {
        $t_text .= PHP_EOL . '⚠ ' . $t_result['warning'];
    }

    $t_text .= PHP_EOL . string_get_bug_view_url_with_fqdn( (int)$p_draft['bug_id'] );

    return array( 'text' => $t_text );
}

/**
 * Take an answer of a keyboard step of the status change dialog and go on with
 * the next question.
 *
 * @param string $p_step  Step name.
 * @param mixed  $p_value Answer, null for a skipped step.
 * @return array Data of the message to send.
 */
function telegram_status_change_answer( $p_step, $p_value ) {
    $t_draft = telegram_status_change_draft_get();

    # The step name comes back inside callback_data, only the known ones are taken
    if( $t_draft === NULL || !in_array( $p_step, telegram_status_change_steps_get(), TRUE ) ) {
        # The dialog is gone, the button went stale: the flow starts over
        return telegram_bug_select_step( array( 'start' => '' ), TelegrambotActions::UPDATE_BUG_TAG );
    }

    $t_draft[$p_step] = $p_value;
    telegram_status_change_draft_set( $t_draft );

    return telegram_status_change_ask_next_step( $t_draft );
}

/**
 * Take the message text as the answer to the text question of the status change
 * dialog: the duplicate issue id or the note of the transition.
 *
 * @param string $p_text Text of the message.
 * @return array|null Data of the card message to edit or null when no text question is pending.
 */
function telegram_status_change_text_answer( $p_text ) {
    $t_user_id = auth_get_current_user_id();
    $t_await   = plugin_config_get( 'status_change_draft_await', '', FALSE, $t_user_id );

    if( is_blank( $t_await ) ) {
        return NULL;
    }

    $t_draft = telegram_status_change_draft_get();

    if( $t_draft === NULL ) {
        plugin_config_delete( 'status_change_draft_await', $t_user_id );
        return NULL;
    }

    $t_text  = trim( (string)$p_text );
    $t_error = '';

    switch( $t_await ) {
        case 'duplicate_id':
            # The checks bug_update.php of the core runs on the duplicate id
            $t_value = (int)$t_text;

            if( $t_value == (int)$t_draft['bug_id'] ) {
                $t_error = error_string( ERROR_BUG_DUPLICATE_SELF );
            } else if( $t_value <= 0 || !bug_exists( $t_value ) ) {
                error_parameters( $t_value );
                $t_error = error_string( ERROR_BUG_NOT_FOUND );
            } else if( !access_has_bug_level( config_get( 'update_bug_threshold' ), $t_value ) ) {
                $t_error = error_string( ERROR_RELATIONSHIP_ACCESS_LEVEL_TO_DEST_BUG_TOO_LOW );
            }
            break;

        case 'bugnote':
            # The note is optional, but leaving it out is a press on the skip
            # button rather than an empty message ( a file without a caption )
            $t_value = $t_text;

            if( $t_value == '' ) {
                $t_error = plugin_lang_get( 'custom_field_error_empty' );
            }
            break;

        default:
            plugin_config_delete( 'status_change_draft_await', $t_user_id );
            return NULL;
    }

    if( $t_error != '' ) {
        # A rejected value leaves the state untouched, the question is asked again
        return telegram_status_change_step_ask( $t_await, $t_draft, bug_get( (int)$t_draft['bug_id'] ), $t_error );
    }

    plugin_config_delete( 'status_change_draft_await', $t_user_id );

    $t_draft[$t_await] = $t_value;
    telegram_status_change_draft_set( $t_draft );

    return telegram_status_change_ask_next_step( $t_draft );
}

/**
 * Process a step of the flow changing the status of an issue from the chat:
 * pick the issue, pick the status, answer the questions of the transition the
 * way bug_change_status_page.php of the core asks them, apply the change.
 *
 * @param array                                          $p_current_action Decoded callback data of the step.
 * @param Longman\TelegramBot\Entities\CallbackQuery|null $p_callback_query Callback query being processed.
 * @return array Data of the message to send.
 */
function telegram_change_status( $p_current_action, $p_callback_query = NULL ) {

    $t_command = array_keys( $p_current_action );

    switch( $t_command[0] ) {
        case 'set_bug':
            # Entering the status list drops a stale dialog
            telegram_status_change_draft_clear();

            $t_bug_id = (int)$p_current_action['set_bug'];
            $t_bug    = bug_get( $t_bug_id );

            $t_data_send = [
                                      'text'         => telegram_card_prompt_append(
                                              telegram_bug_select_context( TelegrambotActions::CHANGE_STATUS_TAG ) . PHP_EOL . telegram_bug_line( $t_bug ),
                                              '❓ ' . lang_get( 'status' ) ),
                                      'reply_markup' => keyboard_buttons_bug_change_status( $t_bug ),
            ];
            break;

        case TelegrambotActions::SET_BUG_STATUS:
            $t_bug_id     = (int)$p_current_action[TelegrambotActions::SET_BUG_STATUS]['id'];
            $t_new_status = (int)$p_current_action[TelegrambotActions::SET_BUG_STATUS]['s'];

            $t_bug     = bug_get( $t_bug_id, true );
            $t_warning = '';
            $t_error   = telegram_status_change_entry_check( $t_bug, $t_new_status, $t_warning );

            if( $t_error != '' ) {
                $t_data_send = [ 'text' => $t_error ];
                break;
            }

            # The message the action menu replied to offers the note of the
            # transition and carries its attachment
            $t_reply_to_message = NULL;
            if( $p_callback_query !== NULL && $p_callback_query->getMessage() !== NULL ) {
                $t_reply_to_message = $p_callback_query->getMessage()->getReplyToMessage();

                $t_user_id = auth_get_current_user_id();
                plugin_config_set( 'status_change_draft_chat_id', $p_callback_query->getMessage()->getChat()->getId(), $t_user_id );
                plugin_config_set( 'status_change_draft_message_id', $p_callback_query->getMessage()->getMessageId(), $t_user_id );
            }

            $t_draft = array(
                                      'bug_id'            => $t_bug_id,
                                      'new_status'        => $t_new_status,
                                      'resolution'        => '',
                                      'duplicate_id'      => '',
                                      'handler'           => '',
                                      'fixed_in_version'  => '',
                                      'bugnote'           => '',
                                      'bugnote_suggested' => telegram_message_text_get( $t_reply_to_message ),
                                      'content'           => telegram_status_change_content_descriptor( $t_reply_to_message ),
                                      'warning'           => $t_warning,
            );
            telegram_status_change_draft_set( $t_draft );

            $t_data_send = telegram_status_change_ask_next_step( $t_draft );
            break;

        case TelegrambotActions::SET_STATUS_RESOLUTION:
            $t_data_send = telegram_status_change_answer( 'resolution', (int)$p_current_action[TelegrambotActions::SET_STATUS_RESOLUTION]['id'] );
            break;

        case TelegrambotActions::SET_STATUS_HANDLER:
            $t_data_send = telegram_status_change_answer( 'handler', (int)$p_current_action[TelegrambotActions::SET_STATUS_HANDLER]['id'] );
            break;

        case TelegrambotActions::SET_STATUS_FIXED_VERSION:
            $t_data_send = telegram_status_change_answer( 'fixed_in_version', $p_current_action[TelegrambotActions::SET_STATUS_FIXED_VERSION]['version'] );
            break;

        case TelegrambotActions::SKIP_FIELD:
            $t_data_send = telegram_status_change_answer( $p_current_action[TelegrambotActions::SKIP_FIELD], null );
            break;

//BACK TO THE QUESTION ANSWERED LAST
//The answer given last is dropped and the question is asked once again, the way
//the wizards of the bot do it: the dialog skips the questions which are already
//answered, so it goes on where it has been interrupted.
        case TelegrambotActions::BACK_FIELD:
            $t_draft = telegram_status_change_draft_get();

            if( $t_draft === NULL ) {
                # The dialog is gone, the button went stale: the flow starts over
                $t_data_send = telegram_bug_select_step( array( 'start' => '' ), TelegrambotActions::UPDATE_BUG_TAG );
                break;
            }

            $t_answered = telegram_status_change_answered_steps( $t_draft, bug_get( (int)$t_draft['bug_id'] ) );

            if( empty( $t_answered ) ) {
                # Nothing is answered yet, the first question stays as it is
                telegram_callback_alert_set( plugin_lang_get( 'back_nothing' ) );
            } else {
                telegram_status_change_step_reset( end( $t_answered ), $t_draft );
                telegram_status_change_draft_set( $t_draft );
            }

            # The text answer awaited by the question being given up is dropped by
            # the next question of the dialog
            $t_data_send = telegram_status_change_ask_next_step( $t_draft );
            break;

//APPLYING THE CHANGE OUT OF THE DRAFT
        case TelegrambotActions::APPLY_STATUS:
            $t_draft = telegram_status_change_draft_get();

            if( $t_draft === NULL ) {
                # The dialog is gone, the button went stale: the flow starts over
                $t_data_send = telegram_bug_select_step( array( 'start' => '' ), TelegrambotActions::UPDATE_BUG_TAG );
                break;
            }

            # The button goes stale along with the card it sits on, so the draft
            # is checked again instead of being trusted to be complete
            if( telegram_status_change_pending_step( $t_draft ) !== NULL ) {
                telegram_callback_alert_set( plugin_lang_get( 'wizard_required_missing' ) );

                $t_data_send = telegram_status_change_ask_next_step( $t_draft );
                break;
            }

            $t_data_send = telegram_status_change_submit( $t_draft );
            break;

        default:
            # A button of the flow layout before the project step went stale
            $t_data_send = telegram_bug_select_step( array( 'start' => '' ), TelegrambotActions::UPDATE_BUG_TAG );
            break;
    }

    return $t_data_send;
}

function telegram_add_comment( $p_current_action, $p_message, $p_reply_to_message ) {

    $t_command = array_keys( $p_current_action );

    switch( $t_command[0] ) {
        case 'start':
        case 'get_projects':
        case 'sprj':
        case 'get_default_category':
        case 'get_bugs':
            $t_data_send = telegram_bug_select_step( $p_current_action, TelegrambotActions::ADD_COMMENT_TAG );
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

                    #The upload rules are checked before the download on the size known
                    #from the update; a photo carries no name of its own, its name is
                    #taken from the file path and checked after getFile
                    $t_file_name  = $t_file_orgl->getFileName() == NULL ? '' : $t_file_orgl->getFileName();
                    $t_error_text = telegram_file_check( $t_file_name, $t_file_orgl->getFileSize() );

                    if( $t_error_text == '' ) {
                        try {
                            $t_download = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_file_orgl->getFileId() ] );
                            $t_file     = $t_download->getResult();

                            if( $t_file_name == '' ) {
                                $t_file_name  = $t_file->getFilePath();
                                $t_error_text = telegram_file_check( $t_file_name, 0 );
                            }
                        } catch( Longman\TelegramBot\Exception\TelegramException $t_exception ) {
                            $t_error_text = $t_exception->getMessage();
                        }
                    }

                    if( $t_error_text != '' ) {
                        $t_data_send       = [
                                                  'text' => $t_error_text
                        ];
                        $t_upload_is_error = TRUE;
                        break;
                    }

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
                                              'name'           => [ 0 => $t_file_name ]
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
                $t_note_id = bugnote_add_from_telegram( $t_bug_id, $t_text, $t_file_for_attach );

                # A bugnote gets a direct link to its anchor, a bare attachment
                # only has the issue page to point at
                if( $t_note_id === null ) {
                    $t_content_url = string_get_bug_view_url_with_fqdn( $t_bug_id );
                } else {
                    $t_content_url = string_get_bugnote_view_url_with_fqdn( $t_bug_id, $t_note_id );
                }

                $t_data_send = [
                                          'text'         => plugin_lang_get( 'content_upload_complete' ) . $p_current_action['set_bug'] . PHP_EOL . $t_content_url,
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
                            'text'    => telegram_user_info_text( auth_get_current_user_id() ) . PHP_EOL . telegram_message_first_text(),
                        ];
                        break;

                case 'stop':
                        $t_user_id = user_get_id_by_telegram_user_id($t_command->getFrom()->getId());

                        # the answer below is the notification, no second message needed
                        telegram_bot_user_unlink($t_user_id, /* notify */ FALSE);

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

/**
 * The URL of this MantisBT instance as the users see it: the bot puts it into the links
 * it sends, and in CLI the global path is a guess made from the request headers, so the
 * value configured for the script takes precedence there.
 *
 * @return string
 */
function telegram_mantis_url_get() {

        if( php_sapi_name() == 'cli' && plugin_config_get( 'cli_g_path' ) != '' ) {
                return plugin_config_get( 'cli_g_path' );
        }

        return config_get_global( 'path' );
}

/**
 * The MantisBT account the telegram one is linked to, as the /start answer shows it.
 *
 * @param integer $p_user_id MantisBT user id.
 * @return string
 */
function telegram_user_info_text( $p_user_id ) {

        $t_default_project = (int)user_pref_get_pref( $p_user_id, 'default_project' );

        $t_lines = array(
                                plugin_lang_get( 'user_info_header' ),
                                lang_get( 'username' ) . ': ' . user_get_username( $p_user_id ),
                                lang_get( 'realname' ) . ': ' . user_get_realname( $p_user_id ),
                                lang_get( 'email' ) . ': ' . user_get_email( $p_user_id ),
                                lang_get( 'access_level' ) . ': ' . get_enum_element( 'access_levels', user_get_access_level( $p_user_id ) ),
                                lang_get( 'default_project' ) . ': ' . ( ALL_PROJECTS == $t_default_project ? lang_get( 'all_projects' ) : project_get_name( $t_default_project ) ),
        );

        return implode( PHP_EOL, $t_lines ) . PHP_EOL;
}

/**
 * The greeting the bot sends once a telegram account is linked to a MantisBT one.
 *
 * @return string
 */
function telegram_message_first_text() {

        $t_url = telegram_mantis_url_get();

        return sprintf(
                                plugin_lang_get( 'first_message' ),
                                config_get( 'window_title' ) . ' ( ' . $t_url . ' )',
                                ' ( ' . $t_url . plugin_page( 'account_telegram_prefs_page', TRUE ) . ' )'
        );
}