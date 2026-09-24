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

# The settings of the Calendar integration live on a page of their own: saving
# the main settings page re-installs the webhook, which these settings have
# nothing to do with. The page holds the global .ics switch and the matrix of
# the event notifications, which is kept per project like the issue matrix of
# the notifications page and is coloured the same way.

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$t_project_id = helper_get_current_project();

$t_notify_default    = plugin_config_get( 'calendar_notify_flags', array(), TRUE );
$g_notify_file       = telegram_calendar_notify_flags_normalize( $t_notify_default, $t_notify_default );
$g_notify_global     = telegram_calendar_notify_flags( ALL_PROJECTS );
$g_notify_project    = telegram_calendar_notify_flags( $t_project_id );
$g_notify_project_id = $t_project_id;

if( ALL_PROJECTS == $t_project_id ) {
	$t_has_override = $g_notify_global != $g_notify_file;
} else {
	$t_has_override = $g_notify_project != $g_notify_global;
}

/**
 * CSS classes of one cell of the matrix, saying which level decided its value
 *
 * @param string $p_action Row of the matrix.
 * @param string $p_target Column of the matrix.
 * @return string
 */
function telegram_calendar_notify_cell_class( $p_action, $p_target ) {
	global $g_notify_file, $g_notify_global, $g_notify_project, $g_notify_project_id;

	$t_class = 'center';

	if( $g_notify_global[$p_action][$p_target] != $g_notify_file[$p_action][$p_target] ) {
		$t_class = 'center color-global'; # all projects override
	}

	if( ALL_PROJECTS != $g_notify_project_id
			&& $g_notify_project[$p_action][$p_target] != $g_notify_global[$p_action][$p_target] ) {
		$t_class = 'center color-project'; # project overrides
	}

	return $t_class;
}

/**
 * One checkbox of the matrix, named after the cell it stands for
 *
 * @param string $p_action Row of the matrix.
 * @param string $p_target Column of the matrix.
 * @return string
 */
function telegram_calendar_notify_cell( $p_action, $p_target ) {
	global $g_notify_project;

	$t_checked = ON == $g_notify_project[$p_action][$p_target] ? ' checked="checked"' : '';

	return '<label><input type="checkbox" class="ace" name="calendar_flag[]" value="'
			. string_attribute( $p_action . ':' . $p_target ) . '"' . $t_checked
			. ' /><span class="lbl"></span></label>';
}

layout_page_header( plugin_lang_get( 'calendar_config_page' ) );
layout_page_begin( 'manage_overview_page.php' );
print_manage_menu( 'manage_plugin_page.php' );
telegrambot_print_menu_config( 'calendar_config_page' );

$t_calendar_ics_mode = (int)plugin_config_get( 'calendar_ics_mode' );

# the dependent settings are drawn by the saved state of the master switch,
# server side, the way the settings page shows the fields of the chosen update
# method; the marker tells the handler that they were on the form at all
$t_integration_enabled = ON == (int)plugin_config_get( 'calendar_integration_enabled' );

if( ALL_PROJECTS == $t_project_id ) {
	$t_project_title = lang_get( 'config_all_projects' );
} else {
	$t_project_title = sprintf( lang_get( 'config_project' ), string_display_line( project_get_name( $t_project_id ) ) );
}
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <div class="form-container">
        <form action="<?php echo plugin_page( 'calendar_config' ) ?>" method="post">
            <?php echo form_security_field( 'calendar_config' ) ?>
            <?php if( $t_integration_enabled ) { ?><input type="hidden" name="calendar_settings_shown" value="1" /><?php } ?>

            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-calendar"></i>
                        <?php echo plugin_lang_get( 'calendar_config_title' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:25%" />
                                </colgroup>

                                <tr>
                                    <th class="category" width="5%">
                                        <?php echo plugin_lang_get( 'calendar_integration_enabled' ) ?>
                                        <br><span class="small"><?php echo plugin_lang_get( 'calendar_integration_enabled_notice' ) ?></span>
                                    </th>
                                    <td class="left" colspan="1">
                                        <label><input type="checkbox" class="ace" name="calendar_integration_enabled" value="1" <?php check_checked( $t_integration_enabled, TRUE ) ?>/>
                                            <span class="lbl"></span></label>
                                    </td>
                                </tr>

                                <?php if( $t_integration_enabled ) { ?>
                                <tr>
                                    <th class="category" width="5%">
                                        <?php echo plugin_lang_get( 'calendar_ics_mode' ) ?>
                                        <br><span class="small"><?php echo plugin_lang_get( 'calendar_ics_mode_notice' ) ?></span>
                                    </th>
                                    <td class="left" colspan="1">
                                        <label><input type="radio" class="ace" name="calendar_ics_mode" value="<?php echo TELEGRAM_ICS_OFF ?>" <?php echo( TELEGRAM_ICS_OFF == $t_calendar_ics_mode ) ? 'checked="checked" ' : '' ?>/>
                                            <span class="lbl padding-6"><?php echo plugin_lang_get( 'calendar_ics_mode_off' ) ?></span></label>
                                        <label><input type="radio" class="ace" name="calendar_ics_mode" value="<?php echo TELEGRAM_ICS_ON ?>" <?php echo( TELEGRAM_ICS_ON == $t_calendar_ics_mode ) ? 'checked="checked" ' : '' ?>/>
                                            <span class="lbl padding-6"><?php echo plugin_lang_get( 'calendar_ics_mode_on' ) ?></span></label>
                                        <label><input type="radio" class="ace" name="calendar_ics_mode" value="<?php echo TELEGRAM_ICS_OPT_IN ?>" <?php echo( TELEGRAM_ICS_OPT_IN == $t_calendar_ics_mode ) ? 'checked="checked" ' : '' ?>/>
                                            <span class="lbl padding-6"><?php echo plugin_lang_get( 'calendar_ics_mode_opt_in' ) ?></span></label>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="category" width="5%">
                                        <?php echo plugin_lang_get( 'calendar_reminders_enabled' ) ?>
                                        <br><span class="small"><?php echo plugin_lang_get( 'calendar_reminders_enabled_notice' ) ?></span>
                                    </th>
                                    <td class="left" colspan="1">
                                        <label><input type="checkbox" class="ace" name="calendar_reminders_enabled" value="1" <?php check_checked( (int)plugin_config_get( 'calendar_reminders_enabled' ), ON ) ?>/>
                                            <span class="lbl"></span></label>
                                    </td>
                                </tr>
                                <?php } ?>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if( !$t_integration_enabled ) { ?>
                <div class="widget-toolbox center clearfix">
                    <input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'change_configuration' ) ?>" />
                </div>
                <?php } ?>
            </div>

            <?php if( $t_integration_enabled ) { ?>
            <div class="space-10"></div>

            <div class="well">
                <p class="bold"><i class="fa fa-info-circle"></i> <?php echo $t_project_title ?></p>
                <p><?php echo lang_get( 'colour_coding' ) ?><br />
                <?php if( ALL_PROJECTS != $t_project_id ) { ?>
                    <span class="color-project"><?php echo lang_get( 'colour_project' ) ?></span><br />
                <?php } ?>
                <span class="color-global"><?php echo lang_get( 'colour_global' ) ?></span></p>
            </div>

            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter uppercase">
                        <i class="ace-icon fa fa-envelope"></i>
                        <?php echo plugin_lang_get( 'manage_config_calendar_message_title' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed checkbox-range-selection">
                                <thead>
                                    <tr>
                                        <th width="30%" rowspan="2"><?php echo lang_get( 'message' ) ?></th>
                                        <th class="bold" style="text-align:center" colspan="<?php echo count( telegram_calendar_notify_targets() ) ?>">
                                            &#160;<?php echo plugin_lang_get( 'calendar_notify_recipients' ) ?>&#160;
                                        </th>
                                    </tr>
                                    <tr>
                                        <?php foreach( telegram_calendar_notify_targets() as $t_target ) { ?>
                                            <th class="bold" style="text-align:center">&#160;<?php echo plugin_lang_get( 'calendar_notify_target_' . $t_target ) ?>&#160;</th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach( telegram_calendar_notify_actions() as $t_action ) { ?>
                                        <tr>
                                            <td><?php echo plugin_lang_get( 'calendar_notify_row_' . $t_action ) ?></td>
                                            <?php foreach( telegram_calendar_notify_targets() as $t_target ) { ?>
                                                <td class="<?php echo telegram_calendar_notify_cell_class( $t_action, $t_target ) ?>">
                                                    <?php echo telegram_calendar_notify_cell( $t_action, $t_target ) ?>
                                                </td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="widget-toolbox center clearfix">
                    <input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'change_configuration' ) ?>" />
                </div>
            </div>
            <?php } ?>
        </form>

        <?php if( $t_integration_enabled && $t_has_override ) { ?>
            <div class="space-10"></div>
            <form method="post" action="<?php echo plugin_page( 'manage_config_revert' ) ?>">
                <?php echo form_security_field( 'manage_config_revert' ) ?>
                <input name="revert" type="hidden" value="calendar_notify_flags" />
                <input name="project" type="hidden" value="<?php echo $t_project_id ?>" />
                <input name="return" type="hidden" value="<?php echo string_attribute( plugin_page( 'calendar_config_page' ) ) ?>" />
                <input type="submit" class="btn btn-primary btn-sm btn-white btn-round"
                       value="<?php echo ALL_PROJECTS == $t_project_id ? lang_get( 'revert_to_system' ) : lang_get( 'revert_to_all_project' ) ?>" />
            </form>
        <?php } ?>
    </div>
</div>

<?php
layout_page_end();
