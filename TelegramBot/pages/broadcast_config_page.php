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

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

layout_page_header( plugin_lang_get( 'broadcast_config_page' ) );
layout_page_begin( 'manage_overview_page.php' );
print_manage_menu( 'manage_plugin_page.php' );
telegrambot_print_menu_config( 'broadcast_config_page' );

# User being edited in the permissions form (picked via the link in the grants list)
$f_grant_user_id  = gpc_get_int( 'grant_user_id', 0 );

$t_grants         = telegram_broadcast_grants_get();
$t_grant_projects = isset( $t_grants[$f_grant_user_id] ) ? array_map( 'intval', $t_grants[$f_grant_user_id] ) : array();
$t_project_list   = project_get_all_rows();
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <div class="form-container">
        <form action="<?php echo plugin_page( 'broadcast_config' ) ?>" method="post">
            <?php echo form_security_field( 'broadcast_config' ) ?>

            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-cubes"></i>
                        <?php echo plugin_lang_get( 'broadcast_config_title' ) ?>
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
                                        <?php echo plugin_lang_get( 'broadcast_enabled_header' ) ?>
                                    </th>
                                    <td>
                                        <label class="inline">
                                            <input type="checkbox" class="ace" id="broadcast_enabled" name="broadcast_enabled" <?php check_checked( (int)plugin_config_get( 'broadcast_enabled' ), ON ); ?> />
                                            <span class="lbl"></span>
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="category" width="5%">
                                        <?php echo plugin_lang_get( 'broadcast_send_threshold_header' ) ?>
                                    </th>
                                    <td class="center" colspan="1">
                                        <select name="broadcast_send_threshold" id="broadcast_send_threshold" class="form-control">
                                            <?php print_enum_string_option_list( 'access_levels', (int)plugin_config_get( 'broadcast_send_threshold' ) ) ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="widget-toolbox center clearfix">
                    <input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'change_configuration' ) ?>" />
                </div>
            </div>
        </form>
    </div>

    <div class="space-10"></div>

    <div class="form-container">
        <form action="<?php echo plugin_page( 'broadcast_grant' ) ?>" method="post">
            <?php echo form_security_field( 'broadcast_grant' ) ?>

            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-users"></i>
                        <?php echo plugin_lang_get( 'broadcast_grants_title' ) ?>
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
                                    <td colspan="2" class="small">
                                        <?php echo plugin_lang_get( 'broadcast_grants_notice' ) ?>
                                    </td>
                                </tr>

<?php
	# Current per-user permissions
	if( empty( $t_grants ) ) {
?>
                                <tr>
                                    <td colspan="2"><?php echo plugin_lang_get( 'broadcast_grants_empty' ) ?></td>
                                </tr>
<?php
	} else {
		foreach( $t_grants as $t_grant_user => $t_grant_project_ids ) {
			$t_grant_user = (int)$t_grant_user;
			if( !user_exists( $t_grant_user ) ) {
				continue;
			}

			$t_project_names = array();
			foreach( $t_grant_project_ids as $t_grant_project_id ) {
				if( project_exists( (int)$t_grant_project_id ) ) {
					$t_project_names[] = string_display_line( project_get_name( (int)$t_grant_project_id ) );
				}
			}
?>
                                <tr>
                                    <th class="category">
                                        <a href="<?php echo plugin_page( 'broadcast_config_page' ) . '&grant_user_id=' . $t_grant_user ?>">
                                            <?php echo string_display_line( user_get_name( $t_grant_user ) ) ?>
                                        </a>
                                    </th>
                                    <td><?php echo implode( ', ', $t_project_names ) ?></td>
                                </tr>
<?php
		}
	}
?>

                                <tr>
                                    <th class="category" width="5%">
                                        <?php echo lang_get( 'username' ) ?>
                                    </th>
                                    <td class="center" colspan="1">
                                        <select name="grant_user_id" id="grant_user_id" class="form-control" required>
                                            <option value=""></option>
                                            <?php print_user_option_list( $f_grant_user_id, ALL_PROJECTS ) ?>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="category" width="5%">
                                        <?php echo plugin_lang_get( 'broadcast_projects' ) ?>
                                    </th>
                                    <td>
<?php
	foreach( $t_project_list as $t_project ) {
		$t_project_id   = (int)$t_project['id'];
		$t_project_name = string_display_line( $t_project['name'] );
		$t_checked      = in_array( $t_project_id, $t_grant_projects ) ? 'checked="checked" ' : '';

		echo '<div class="ace">';
		echo '<label for="grant_project_' . $t_project_id . '">';
		echo '<input type="checkbox" class="ace" name="grant_projects[]" id="grant_project_' . $t_project_id . '" value="' . $t_project_id . '" ' . $t_checked . '/>';
		echo '<span class="lbl padding-6">' . $t_project_name . '</span>';
		echo '</label>';
		echo '</div>';
	}
?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="widget-toolbox center clearfix">
                    <input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'change_configuration' ) ?>" />
                </div>
            </div>
        </form>
    </div>
</div>

<?php
layout_page_end();
