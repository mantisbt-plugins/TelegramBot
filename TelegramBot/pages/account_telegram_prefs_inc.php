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

if( !defined( 'ACCOUNT_TELEGRAM_PREFS_INC_ALLOW' ) ) {
    return;
}

/**
 * Display html form to edit account preferences
 *
 * @param integer $p_user_id            A valid user identifier.
 * @param boolean $p_error_if_protected Whether to error if the account is protected.
 * @param boolean $p_accounts_menu      Display account preferences menu.
 * @param string  $p_redirect_url       Redirect URI.
 * @return void
 */
function telegram_edit_account_prefs( $p_user_id = null, $p_error_if_protected = true, $p_accounts_menu = true, $p_redirect_url = '' ) {
    global $g_account_telegram_menu_active;
    if( null === $p_user_id ) {
        $p_user_id = auth_get_current_user_id();
    }

    $t_redirect_url = $p_redirect_url;
    if( is_blank( $t_redirect_url ) ) {
        $t_redirect_url = plugin_page( 'account_telegram_prefs_page', TRUE );
    }

    # protected account check
    if( user_is_protected( $p_user_id ) ) {
        if( $p_error_if_protected ) {
            trigger_error( ERROR_PROTECTED_ACCOUNT, ERROR );
        } else {
            return;
        }
    }

    $t_pref = user_pref_get( $p_user_id );

    $t_telegram_message_full_issue              = (int) plugin_config_get( 'telegram_message_notifications_verbose' );
    $t_telegram_message_included_all_bugnote_is = (int) plugin_config_get( 'telegram_message_included_all_bugnote_is' );

# Account Preferences Form BEGIN
    ?>

    <?php
    if( $p_accounts_menu ) {
        $g_account_telegram_menu_active = TRUE;
        print_account_menu( 'account_telegram_prefs_page' );
    }
    ?>

    <div class="col-md-12 col-xs-12">
        <div class="space-10"></div>

        <div id="account-prefs-update-div" class="form-container">
            <form id="account-prefs-update-form" method="post" action="<?php echo plugin_page( 'account_telegram_prefs_update' ) ?>" class="form-inline">
                <fieldset>
                    <?php echo form_security_field( 'account_telegram_prefs_update' ) ?>
                    <input type="hidden" name="user_id" value="<?php echo $p_user_id ?>" />
                    <input type="hidden" name="redirect_url" value="<?php echo $t_redirect_url ?>" />

                    <div class="widget-box widget-color-blue2">
                        <div class="widget-header widget-header-small">
                            <h4 class="widget-title lighter">
                                <i class="ace-icon fa fa-sliders"></i>
                                <?php echo plugin_lang_get( 'default_account_telegram_preferences_title' ) ?>
                            </h4>
                        </div>

                        <div class="widget-body">
                            <div class="widget-main no-padding">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed table-striped">
                                        <?php
                                        if( telegram_user_get_id_by_user_id( $p_user_id ) != NULL ) {
                                            $t_telegram_chat = RequestMantis::getChat( array( 'chat_id' => telegram_user_get_id_by_user_id( $p_user_id ) ) )->getResult();
                                            if( $t_telegram_chat != NULL ) {
                                                ?>
                                                <tr>
                                                    <td class="category">
                                                        <?php echo 'Telegram ' . lang_get( 'username' ) ?>
                                                    </td>
                                                    <td>
                                                        <?php echo '@' . string_display_line( $t_telegram_chat->getUsername() ) ?>
                                                    </td>
                                                </tr>

                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td class="category">
                                                    <?php
                                                    echo sprintf( plugin_lang_get( 'account_telegram_prefs_subscribe_bot' ), plugin_config_get( 'bot_name' ) ) .
                                                    '<a href="' . plugin_config_get( 'telegram_url' ) . plugin_config_get( 'bot_name' ) . '">' . '@' . plugin_config_get( 'bot_name' ) . '</a>';
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>

                                        <?php if( ON == plugin_config_get( 'enable_telegram_message_notification' ) && telegram_user_get_id_by_user_id( $p_user_id ) != NULL ) { ?>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_new' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm" id="email-on-new" name="telegram_message_on_new" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_new' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-new-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-new-min-severity" name="telegram_message_on_new_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_new_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_assigned' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace" id="email-on-assigned" name="telegram_message_on_assigned" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_assigned' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-assigned-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-assigned-min-severity" name="telegram_message_on_assigned_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_assigned_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_feedback' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm" id="email-on-feedback" name="telegram_message_on_feedback" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_feedback' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-feedback-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-feedback-min-severity" name="telegram_message_on_feedback_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_feedback_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_resolved' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm" id="email-on-resolved" name="telegram_message_on_resolved" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_resolved' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-resolved-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-resolved-min-severity" name="telegram_message_on_resolved_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_resolved_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_closed' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm" id="email-on-closed" name="telegram_message_on_closed" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_closed' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-closed-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-closed-min-severity" name="telegram_message_on_closed_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_closed_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_reopened' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm" id="email-on-reopened" name="telegram_message_on_reopened" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_reopened' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-reopened-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-reopened-min-severity" name="telegram_message_on_reopened_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_reopened_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_bugnote_added' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm" id="email-on-bugnote-added" name="telegram_message_on_bugnote" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_bugnote' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-bugnote-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-bugnote-min-severity" name="telegram_message_on_bugnote_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_bugnote_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_status_change' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm" id="email-on-status" name="telegram_message_on_status" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_status' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-status-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-status-min-severity" name="telegram_message_on_status_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_status_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_on_priority_change' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm" id="email-on-priority-change" name="telegram_message_on_priority" <?php check_checked( (int) plugin_config_get( 'telegram_message_on_priority' ), ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                    <label for="email-on-priority-min-severity" class="email-on-severity-label"><span><?php echo lang_get( 'with_minimum_severity' ) ?></span></label>
                                                    <select id="email-on-priority-min-severity" name="telegram_message_on_priority_min_severity" class="input-sm">
                                                        <option value="<?php echo OFF ?>"><?php echo lang_get( 'any' ) ?></option>
                                                        <option disabled="disabled">-----</option>
                                                        <?php print_enum_string_option_list( 'severity', (int) plugin_config_get( 'telegram_message_on_priority_min_severity' ) ) ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_full_issue_details' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm"
                                                               id="email-full-issue" name="telegram_message_full_issue"
                                                               <?php check_checked( $t_telegram_message_full_issue, ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="category">
                                                    <?php echo plugin_lang_get( 'telegram_message_included_all_bugnote_is' ) ?>
                                                </td>
                                                <td>
                                                    <label class="inline">
                                                        <input type="checkbox" class="ace input-sm"
                                                               id="included-all-bugnote" name="telegram_message_included_all_bugnote_is"
                                                               <?php check_checked( $t_telegram_message_included_all_bugnote_is, ON ); ?> />
                                                        <span class="lbl"></span>
                                                    </label>
                                                </td>
                                            </tr>

                                        <?php } else { ?>

                                            <input type="hidden" name="telegram_message_on_new"      value="<?php echo plugin_config_get( 'telegram_message_on_new' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_assigned" value="<?php echo plugin_config_get( 'telegram_message_on_assigned' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_feedback" value="<?php echo plugin_config_get( 'telegram_message_on_feedback' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_resolved" value="<?php echo plugin_config_get( 'telegram_message_on_resolved' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_closed"   value="<?php echo plugin_config_get( 'telegram_message_on_closed' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_reopened" value="<?php echo plugin_config_get( 'telegram_message_on_reopened' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_bugnote"  value="<?php echo plugin_config_get( 'telegram_message_on_bugnote' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_status"   value="<?php echo plugin_config_get( 'telegram_message_on_status' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_priority" value="<?php echo plugin_config_get( 'telegram_message_on_priority' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_new_min_severity"      value="<?php echo plugin_config_get( 'telegram_message_on_new_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_assigned_min_severity" value="<?php echo plugin_config_get( 'telegram_message_on_assigned_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_feedback_min_severity" value="<?php echo plugin_config_get( 'telegram_message_on_feedback_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_resolved_min_severity" value="<?php echo plugin_config_get( 'telegram_message_on_resolved_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_closed_min_severity"   value="<?php echo plugin_config_get( 'telegram_message_on_closed_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_reopened_min_severity" value="<?php echo plugin_config_get( 'telegram_message_on_reopened_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_bugnote_min_severity"  value="<?php echo plugin_config_get( 'telegram_message_on_bugnote_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_status_min_severity"   value="<?php echo plugin_config_get( 'telegram_message_on_status_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_on_priority_min_severity" value="<?php echo plugin_config_get( 'telegram_message_on_priority_min_severity' ) ?>" />
                                            <input type="hidden" name="telegram_message_full_issue" value="<?php echo $t_telegram_message_full_issue ?>" />
                                            <input type="hidden" name="telegram_message_included_all_bugnote_is" value="<?php echo $t_telegram_message_included_all_bugnote_is ?>" />
                                        <?php } ?>

                                    </table>
                                </div>
                            </div>
                            <div class="widget-toolbox padding-8 clearfix">
                                <input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'update_prefs_button' ) ?>" />

                                <?php echo form_security_field( 'account_telegram_prefs_reset' ) ?>
                                <input type="submit" class="btn btn-primary btn-white btn-round"
                                       formaction="<?php echo plugin_page( 'account_telegram_prefs_reset' ) ?>"
                                       value="<?php echo lang_get( 'reset_prefs_button' ) ?>" />
                            </div>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>

    </div>

    <?php
}

# end of edit_account_prefs()
