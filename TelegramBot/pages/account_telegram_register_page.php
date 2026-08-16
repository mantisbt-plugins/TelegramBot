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

auth_ensure_user_authenticated();

current_user_ensure_unprotected();

$t_user_id = auth_get_current_user_id();

# Without a PIN code there is nothing to enter here, the binding is confirmed from the link
if( TELEGRAM_REGISTRATION_LINK == (int) plugin_config_get( 'registration_method' ) ) {
    access_denied();
}

layout_page_header( plugin_lang_get( 'account_telegram_register_page_header' ) );

layout_page_begin();

print_account_menu( 'account_telegram_register_page' );

$t_bot_name = plugin_config_get( 'bot_name' );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <div class="form-container">
        <?php if( user_is_associated_with_telegram( $t_user_id ) ) { ?>
            <div class="alert alert-success">
                <p class="bigger-110"><?php echo plugin_lang_get( 'account_telegram_register_already_done' ) ?></p>
                <p><a href="<?php echo plugin_page( 'account_telegram_prefs_page' ) ?>"><?php echo plugin_lang_get( 'account_telegram_prefs_page_header' ) ?></a></p>
            </div>
        <?php } else { ?>
            <form action="<?php echo plugin_page( 'account_telegram_register' ) ?>" method="post">
                <?php echo form_security_field( 'account_telegram_register' ) ?>

                <div class="widget-box widget-color-blue2">
                    <div class="widget-header widget-header-small">
                        <h4 class="widget-title lighter">
                            <i class="ace-icon fa fa-telegram"></i>
                            <?php echo plugin_lang_get( 'account_telegram_register_page_header' ) ?>
                        </h4>
                    </div>

                    <div class="widget-body">
                        <div class="widget-main no-padding">
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed table-striped">
                                    <tr>
                                        <th class="category" width="35%">
                                            <?php
                                            echo sprintf( plugin_lang_get( 'account_telegram_prefs_subscribe_bot' ), string_display_line( $t_bot_name ) ) .
                                            '<a href="' . string_attribute( plugin_config_get( 'telegram_url' ) . $t_bot_name ) . '">' . '@' . string_display_line( $t_bot_name ) . '</a>';
                                            ?>
                                            <br><span class="small"><?php echo plugin_lang_get( 'account_telegram_register_notice' ) ?></span>
                                        </th>
                                        <td class="left">
                                            <label for="pin_code"><?php echo plugin_lang_get( 'account_telegram_prefs_pin_code' ) ?></label>
                                            <input type="number" name="pin_code" id="pin_code" class="form-control" min="1000" max="9999" required value="">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="widget-toolbox padding-8 clearfix">
                            <input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo plugin_lang_get( 'account_telegram_register_button' ) ?>" />
                        </div>
                    </div>
                </div>
            </form>
        <?php } ?>
    </div>
</div>

<?php
layout_page_end();
