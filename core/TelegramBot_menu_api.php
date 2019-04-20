<?php
# Copyright (c) 2019 Grigoriy Ermolaev (igflocal@gmail.com)
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

function telegrambot_print_menu_config( $p_page = '' ) {
	$t_pages = array(
				  'config_page',
				  'monitor_page',
                                  'manage_config_message_page',
	);

	if( access_has_global_level( config_get( 'manage_plugin_threshold' ) ) ) {
		?>
		<div class="col-md-12 col-xs-12">
		    <div class="space-10"></div>
		    <div class="center">
			<div class="btn-toolbar inline">
			    <div class="btn-group">
				<?php
				foreach( $t_pages as $t_page ) {
					$t_active = ( ( $t_page === $p_page ) ? ' active' : '' );
					?>
					<a class="btn btn-sm btn-white btn-primary<?php echo $t_active ?>" href="<?php echo plugin_page( $t_page ) ?>">
					    <?php echo plugin_lang_get( $t_page ) ?>
					</a>

					<?php
				}
				?>

			    </div>
			</div>
		    </div>
		</div>
		<?php
	}
}
