<?php

# Copyright (c) 2017 Grigoriy Ermolaev (igflocal@gmail.com)
# Calendar for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 2 of the License, or (at your option) any later version.
#
# Customer management plugin for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Customer management plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.

class TelegramBotPlugin extends MantisPlugin {

    function register() {

        $this->name        = 'TelegramBot';
        $this->description = '';

        $this->version  = '0.0.1';
        $this->requires = array(
                                  'MantisCore' => '2.0.0',
        );

        $this->author  = 'Grigoriy Ermolaev';
        $this->contact = 'igflocal@gmail.com';
        //$this->url = 'http://github.com/mantisbt-plugins/calendar';
    }

    function schema() {

        return array(
                                  // version 0.0.1
                                  array( "CreateTableSQL", array( plugin_table( "user_relationship" ), "
                                      mantis_user_id INT(10) NOTNULL PRIMARY,
                                      telegram_user_id INT(10) NOTNULL                                        
				" ) )
        );
    }

    function init() {
        require_once __DIR__ . '/api/vendor/autoload.php';
        require_once 'core/TelegramBot_helper_api.php';
    }

    function config() {
        return array(
//                                  'telegram_user_id' => ''
        );
    }

}
