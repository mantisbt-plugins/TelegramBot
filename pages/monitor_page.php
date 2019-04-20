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
telegrambot_print_menu_config( 'monitor_page' );
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
                        <?php echo plugin_lang_get( 'name_plugin_description_page' ) . ': ' . plugin_lang_get( 'monitor_page' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:25%" />
                                </colgroup>


                                <?php
                                $t_bot_name = plugin_config_get( 'bot_name' );
                                $t_api_key  = plugin_config_get( 'api_key' );

                                if( $t_bot_name && $t_api_key ) {

                                    try {
					telegram_session_start();
                                        $t_result      = Longman\TelegramBot\Request::getWebhookInfo();
                                        $t_webhook_url = $t_result->result->getUrl();
                                        $t_pending_update_count = $t_result->result->getPendingUpdateCount();
                                        $t_last_error_date = $t_result->result->getLastErrorDate();
                                        $t_last_error_message = $t_result->result->getLastErrorMessage();
                                        $t_max_connection_current_settings = $t_result->result->getMaxConnections();
                                        $t_current_subscribe_updates = $t_result->result->getAllowedUpdates();
                                    } catch( Longman\TelegramBot\Exception\TelegramException $t_errors ) {
                                        $t_webhook_url = $t_errors->getMessage();
                                    }

                                        echo '<tr' . helper_alternate_class() . '>';
                                        echo '<td class="category" width="50%">';
                                        echo plugin_lang_get( 'current_config' );
                                        echo '</td>';
                                        echo '<td colspan="2">';
                                        echo is_blank( $t_webhook_url ) ? plugin_lang_get( 'monitor_page_url_not_set' ) : $t_webhook_url;
                                        echo '</td>';
                                        echo '</tr>';
                                        
                                    if( isset( $t_result ) ) {
                                        echo '<tr' . helper_alternate_class() . '>';
                                        echo '<td class="category" width="50%">';
                                        echo plugin_lang_get( 'monitor_page_pending_update_count' );
                                        echo '</td>';
                                        echo '<td colspan="2">';
                                        echo $t_pending_update_count;
                                        echo '</td>';
                                        echo '</tr>';
                                        
                                        echo '<tr' . helper_alternate_class() . '>';
                                        echo '<td class="category" width="50%">';
                                        echo plugin_lang_get( 'monitor_page_last_error_date' );
                                        echo '</td>';
                                        echo '<td colspan="2">';
                                        echo $t_last_error_date !== null ? date( config_get_global( 'normal_date_format' ), $t_last_error_date ) : '';
                                        echo '</td>';
                                        echo '</tr>';

                                        echo '<tr' . helper_alternate_class() . '>';
                                        echo '<td class="category" width="50%">';
                                        echo plugin_lang_get( 'monitor_page_last_error_message' );
                                        echo '</td>';
                                        echo '<td colspan="2">';
                                        echo $t_last_error_message !== null ? $t_last_error_message : '';
                                        echo '</td>';
                                        echo '</tr>';                                        

                                    }
                                }
                                ?>

                                <tr <?php echo helper_alternate_class() ?>>
                                    <th class="category" width="5%">
                                        <?php echo plugin_lang_get( 'account_telegram_prefs_associated_users_head' ) ?>
                                    </th>
                                    <td class="left" colspan="1"> 

                                        <p>
                                            <?php
                                            $t_associated_user_ids = telegram_bot_associated_all_users_get();
                                            foreach( $t_associated_user_ids as $t_user_id ) {
                                                echo user_get_field( $t_user_id, 'username' ) . '</br>';
                                            }
                                            ?>
                                        </p>                                        

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

