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
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

layout_page_header( plugin_lang_get( 'menu_main_broadcast_message_page' ) );
layout_page_begin( plugin_page( 'broadcast_message_page' ));
//print_manage_menu( 'manage_plugin_page.php' );
//telegrambot_print_menu_config( 'config_page' );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    
<?php
	$t_collapse_block = is_collapsed( 'bugnote_add' );
	$t_block_css = $t_collapse_block ? 'collapsed' : '';
	$t_block_icon = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';
	$t_allow_file_upload = true;
?>
    <div class="form-container">
        <form id="broadcastmessagesend" method="post" action="<?php echo plugin_page('broadcast_message_send') ?>" enctype="multipart/form-data">
            <?php echo form_security_field( 'broadcast_message_send' ) ?>
	
	<div id="bugnote_add" class="widget-box widget-color-blue2">
		<div class="widget-header widget-header-small">
			<h4 class="widget-title lighter">
				<?php print_icon( 'fa-comment', 'ace-icon' ); ?>
				<?php echo lang_get( 'add_bugnote_title' ) ?>
			</h4>
		</div>

		<div class="widget-body">
		<div class="widget-main no-padding">

		<div class="table-responsive">
		<table class="table table-bordered table-condensed">
		<tbody>

<?php
	$t_default_bugnote_view_status = config_get( 'default_bugnote_view_status' );
	$t_bugnote_private = $t_default_bugnote_view_status == VS_PRIVATE;
	$t_bugnote_class = $t_bugnote_private ? 'form-control bugnote-private' : 'form-control';

//	if( access_has_bug_level( config_get( 'set_view_status_threshold' ), $f_bug_id ) ) {
?>

<!--                     <div class="col-md-12 col-xs-12">
                    <div class="space-10"></div>-->

                    <?php
                    
                    $t_project_list = project_get_all_rows();
                    $t_collapse_block = false;
                    $t_block_css      = $t_collapse_block ? 'collapsed' : '';
                    $t_block_icon     = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';
                    ?>
<!--                    <div id="relationships" class="widget-box widget-color-blue2 <?php echo $t_block_css ?>">
                        <div class="widget-header widget-header-small">
                            <h4 class="widget-title lighter">
                                <i class="ace-icon fa fa-sitemap"></i>
                                <?php // echo "Проекты" ?>
                            </h4>
                            <div class="widget-toolbar">
                                <a data-action="collapse" href="#">
                                    <i class="1 ace-icon fa <?php // echo $t_block_icon ?> bigger-125"></i>
                                </a>
                            </div>
                        </div>
                        <div class="widget-body">
                            <div class="widget-main no-padding">
                                <div class="table-responsive">
                                    <div class="tasks-list-area">
                                        <?php
//                                        foreach( $t_project_list as $project ) {
//                                            $project_id           = $project["id"];
//                                            $project_name         = $project["name"];
//
//                                            echo '<div class="project-area" style="background-color:#c2dfff;">';
//                                            echo '<label for="project_' . $project_id . '">';
//                                            echo '<input
//							type="checkbox"
//							name="projects_add[]"
//							id="project_' . $project_id . '"
//							value="' . $project_id . '"
//                                                        ' . "" . '
//							data-title="' . $project_name . '"
//							data-options="{background-color:#c2dfff;}"
//							>';
//
//                                            echo '<b>' . $project_id . '</b>: ' . $project_name;
//                                            echo '</label>';
//                                            echo '</div>';
//                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>-->

			<tr>
				<th class="category">
					<?php echo lang_get( 'view_status' ) ?>
				</th>
				<td>
                                    <?php
                                        foreach( $t_project_list as $project ) {
                                            $project_id           = $project["id"];
                                            $project_name         = $project["name"];

                                            echo '<div class="ace">';
                                            echo '<label for="project_' . $project_id . '">';
                                            echo '<input
							type="checkbox"
							name="projects_add[]"
							id="project_' . $project_id . '"
							value="' . $project_id . '"
                                                        ' . "" . '
							data-title="' . $project_name . '"
							>';

                                            echo  $project_name;
                                            echo '</label>';
                                            echo '</div>';
                                        }
                                        ?>
<!--				<label for="bugnote_add_view_status">
					<input type="checkbox" class="ace" id="bugnote_add_view_status" name="private" <?php // check_checked( $t_default_bugnote_view_status, VS_PRIVATE ); ?> />
					<span class="lbl padding-6"><?php // echo lang_get( 'private' ) ?></span>
				</label>-->
				</td>
			</tr>                
                
<?php // }?>

			<tr>
				<th class="category" width="15%">
					<?php echo lang_get( 'bugnote' ) ?>
				</th>
				<td width="85%">
					<textarea name="bugnote_text" id="bugnote_text" class="<?php echo $t_bugnote_class ?>" rows="7"></textarea>
				</td>
			</tr>

<?php
	if( $t_allow_file_upload ) {
		$t_file_upload_max_num = max( 1, config_get( 'file_upload_max_num' ) );
		$t_max_file_size = file_get_max_file_size();
?>
			<tr id="bugnote-attach-files">
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
<?php
	}

?>
		</tbody>
</table>
</div>
</div>
	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'add_bugnote_button' ) ?>" />
	</div>
</div>
</div>
</form>


</div>
</div>

<?php
layout_page_end();

