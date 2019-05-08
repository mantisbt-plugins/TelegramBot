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

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

layout_page_header( plugin_lang_get( 'name_plugin_description_page' ) );
layout_page_begin( 'manage_overview_page.php' );
print_manage_menu( 'manage_plugin_page.php' );
telegrambot_print_menu_config( 'config_page' );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="form-container">
        <form action="<?php echo plugin_page( 'config' ) ?>" method="post" enctype="multipart/form-data"> 
            <?php echo form_security_field( 'config' ) ?>
            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-cubes"></i>
                        <?php echo plugin_lang_get( 'name_plugin_description_page' ) . ': ' . plugin_lang_get( 'config_title' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:25%" />
                                </colgroup>

                                <tr <?php echo helper_alternate_class() ?>>
                                    <th class="category" width="5%">
                                        <?php echo plugin_lang_get( 'help_registration_bot_header' ) ?>
                                    </th>
                                    <td class="left" colspan="1"> 

                                        <p>
                                            <?php echo sprintf( plugin_lang_get( 'help_registration_bot_message' ), '<a href=' . plugin_config_get( 'bot_father_url' ) . ' target="_blank">' . plugin_config_get( 'bot_father_url' ) . '</a>' ) ?>
                                        </p>                                        

                                    </td>
                                </tr>

                                <tr <?php echo helper_alternate_class() ?>>
                                    <th class="category" width="5%">
                                        <span class="required">*</span>
                                        <?php echo plugin_lang_get( 'bot_name' ) ?>
                                    </th>
                                    <td class="center" colspan="1"> 
                                        <textarea name="bot_name" id="bot_name" class="form-control" rows="1" required><?php echo plugin_config_get( 'bot_name' ) == NULL ? '' : plugin_config_get( 'bot_name' ) ?></textarea>
                                    </td>
                                </tr>

                                <tr <?php echo helper_alternate_class() ?>>
                                    <th class="category" width="5%">
                                        <span class="required">*</span>
                                        <?php echo plugin_lang_get( 'api_key' ) ?>
                                    </th>
                                    <td class="center" colspan="1"> 
                                        <textarea name="api_key" id="api_key" class="form-control" rows="1" required><?php echo plugin_config_get( 'api_key' ) == NULL ? '' : plugin_config_get( 'api_key' ) ?></textarea>
                                    </td>
                                </tr>
				
				<tr <?php echo helper_alternate_class() ?>>
                                    <th class="category" width="5%">
					<?php echo plugin_lang_get( 'time_out_server_response_header' ) ?>
                                    </th>
                                    <td class="center" colspan="1"> 
                                        <input type="number" name="time_out_server_response" id="proxy_address" class="form-control" min="0" value="<?php echo plugin_config_get( 'time_out_server_response' ) == NULL ? '' : plugin_config_get( 'time_out_server_response' ) ?>">
                                    </td>
                                </tr>
				
				<?php
				$t_curl_version = curl_version();
//				$t_curl_version = '7.19.7';
				if( $t_curl_version['version'] >= '7.21.7' ) { ?>
					<tr <?php echo helper_alternate_class() ?>>
	                                    <th class="category" width="5%">
						<?php echo plugin_lang_get( 'proxy_address_header' ) ?>
	                                    </th>
	                                    <td class="center" colspan="1"> 
	                                        <textarea name="proxy_address" id="proxy_address" class="form-control" rows="1" placeholder="login:password@address:port"><?php echo plugin_config_get( 'proxy_address' ) == NULL ? '' : plugin_config_get( 'proxy_address' ) ?></textarea>
	                                    </td>
	                                </tr>
				<?php				
				} else { ?>
						<tr <?php echo helper_alternate_class() ?>>
						    <th class="category" width="5%">
							<?php echo plugin_lang_get( 'proxy_address_header' ) ?>
						    </th>
						    <td class="center" colspan="1"> 
							<textarea name="proxy_address" id="proxy_address" class="form-control" rows="1" disabled="true"><?php echo plugin_lang_get( 'ERROR_CURL_VERSION' ) ?></textarea>
						    </td>
						</tr>
					<?php
				} ?>
				
				<tr <?php echo helper_alternate_class() ?>>
                                    <th class="category" width="5%">
					<?php echo plugin_lang_get( 'debug_connection_log_path_title' ) ?>
                                    </th>
                                    <td class="center" colspan="1"> 
                                        <textarea name="debug_connection_log_path" id="debug_connection_log_path" class="form-control" rows="1"><?php echo plugin_config_get( 'debug_connection_log_path' ) ?></textarea>
                                    </td>
                                </tr>
								
				<tr <?php echo helper_alternate_class() ?>>
                                    <th class="category" width="5%">
					<?php echo plugin_lang_get( 'debug_connection_enabled' ) ?>
                                    </th>
                                    <td> 
                                        <label class="inline">
					    <input type="checkbox" class="ace" id="debug_connection_enabled" name="debug_connection_enabled" <?php check_checked( (int)plugin_config_get( 'debug_connection_enabled' ), ON ); ?> />
					    <span class="lbl"></span>
					</label>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="center" colspan="2">
                                        <input type="submit" class="button" value="<?php echo lang_get( 'change_configuration' ) ?>" />
                                    </td>
                                </tr>

                            </table>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
layout_page_end();

