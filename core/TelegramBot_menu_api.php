<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function telegrambot_print_menu_config( $p_page = '' ) {
	$t_pages = array(
				  'config_page',
				  'monitor_page',
	);

	if( access_has_global_level( config_get( 'manage_plugin_threshold' ) ) ) {
		?>
		<div class="col-md-12 col-xs-12">
		    <div class="space-10"></div>
		    <div class="left">
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
