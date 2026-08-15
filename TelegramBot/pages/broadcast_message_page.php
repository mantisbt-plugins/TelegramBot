<?php
# Copyright (c) 2023 Grigoriy Ermolaev (igflocal@gmail.com)
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

auth_reauthenticate();

if( !telegram_broadcast_can_send( auth_get_current_user_id() ) ) {
	access_denied();
}

layout_page_header( plugin_lang_get( 'menu_main_broadcast_message_page' ) );
layout_page_begin( plugin_page( 'broadcast_message_page' ) );

$t_allowed_project_ids = telegram_broadcast_allowed_project_ids( auth_get_current_user_id() );

$t_project_list = array();
foreach( project_get_all_rows() as $t_project ) {
	if( in_array( (int)$t_project['id'], $t_allowed_project_ids ) ) {
		$t_project_list[] = $t_project;
	}
}

$t_file_upload_max_num = max( 1, config_get( 'file_upload_max_num' ) );
$t_max_file_size       = file_get_max_file_size();
?>

<div class="col-md-12 col-xs-12">
	<div class="space-10"></div>

	<div class="form-container">
	<form id="broadcast-message-form" method="post" action="<?php echo plugin_page( 'broadcast_message_send' ) ?>" enctype="multipart/form-data">
		<?php echo form_security_field( 'broadcast_message_send' ) ?>

	<div id="broadcast-message" class="widget-box widget-color-blue2">
		<div class="widget-header widget-header-small">
			<h4 class="widget-title lighter">
				<?php print_icon( 'fa-comment', 'ace-icon' ); ?>
				<?php echo plugin_lang_get( 'menu_main_broadcast_message_page' ) ?>
			</h4>
		</div>

		<div class="widget-body">
		<div class="widget-main no-padding">

		<div class="table-responsive">
		<table class="table table-bordered table-condensed">
		<tbody>

			<tr>
				<th class="category" width="15%">
					<?php echo plugin_lang_get( 'broadcast_projects' ) ?>
				</th>
				<td width="85%">
<?php
	foreach( $t_project_list as $t_project ) {
		$t_project_id   = (int)$t_project['id'];
		$t_project_name = string_display_line( $t_project['name'] );

		echo '<div class="ace">';
		echo '<label for="project_' . $t_project_id . '">';
		echo '<input type="checkbox" class="ace" name="projects[]" id="project_' . $t_project_id . '" value="' . $t_project_id . '" />';
		echo '<span class="lbl padding-6">' . $t_project_name . '</span>';
		echo '</label>';
		echo '</div>';
	}
?>
				</td>
			</tr>

			<tr>
				<th class="category">
					<?php echo plugin_lang_get( 'broadcast_message' ) ?>
				</th>
				<td>
					<textarea name="message" id="broadcast-message-text" class="form-control" rows="7"></textarea>
				</td>
			</tr>

			<tr id="broadcast-attach-files">
				<th class="category">
					<?php echo lang_get( $t_file_upload_max_num == 1 ? 'upload_file' : 'upload_files' ) ?>
					<br />
					<?php print_max_filesize( $t_max_file_size ); ?>
				</th>
				<td>
					<?php print_dropzone_template() ?>
					<input type="hidden" name="max_file_size" value="<?php echo $t_max_file_size ?>" />
					<div class="dropzone center" <?php print_dropzone_form_data() ?>>
						<?php print_icon( 'fa-cloud-upload', 'upload-icon ace-icon blue fa-3x' ); ?>
						<br>
						<span class="bigger-150 grey"><?php echo lang_get( 'dropzone_default_message' ) ?></span>
						<div id="dropzone-previews-box" class="dz dropzone-previews dz-max-files-reached"></div>
					</div>
					<div class="fallback">
						<input id="ufile[]" name="ufile[]" type="file" size="50" />
					</div>
				</td>
			</tr>

		</tbody>
		</table>
		</div>
		</div>

		<div class="widget-toolbox padding-8 clearfix">
			<input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo plugin_lang_get( 'broadcast_send_button' ) ?>" />
		</div>
	</div>
	</form>
	</div>
</div>

<?php
layout_page_end();
