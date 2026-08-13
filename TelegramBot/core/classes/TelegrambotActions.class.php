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

/**
 * Description of TelegramBot_menu
 *
 * @author g.ermolaev
 */
class TelegrambotActions {

        const ACTION_SELECT_TAG         = 'action_select';
        const STOP_REPORT_ISSUE_TAG     = 'stop_report_issue';
        const ADD_COMMENT_TAG           = 'add_comment';
        const REPORT_BUG_TAG            = 'rb';
        const GET_PROJECT               = 'gp';
        const SET_PROJECT               = 'sp';
        const GET_CATEGORY              = 'gc';
        const SET_CATEGORY              = 'sc';
        const GET_REPRODUCIBILITY       = 'greproducibility';
        const SET_REPRODUCIBILITY       = 'sreproducibility';
        const GET_ETA                   = 'geta';
        const SET_ETA                   = 'seta';

        private $text = array();
        private $buttons;

        public function __construct() {
                $this->buttons = array();
        }

        public function setText($p_text) {
                $this->text = $p_text;
        }

        public function getText() {
                return $this->text;
        }

        public function addButton(Button $p_buttons) {
                
        }

        public function getButtons() {
                return $this->buttons;
        }

}

class Button {
        private $text = '';
        private $action = '';
}


class TelegramBotChat {
        private $telegramUserId = '';
        private $mantisUserId   = '';
        
        private $messages       = [];
        private $buttons        = [];
        
        


        public function setMessage( TelegramMessage $p_message ) {
                
        }
        
}