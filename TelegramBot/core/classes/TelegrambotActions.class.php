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
        # Submenu of the operations updating an existing issue
        const UPDATE_BUG_TAG            = 'update_bug';
        # Change the status of an existing issue picked from the chat
        const CHANGE_STATUS_TAG         = 'chs';
        # Status the picked issue is moved to
        const SET_BUG_STATUS            = 'ss';
        # Cancel the status change dialog and go back to the action list
        const STOP_CHANGE_STATUS_TAG    = 'stop_chs';
        # Steps of the status change dialog
        const SET_STATUS_RESOLUTION     = 'sres';
        const SET_STATUS_HANDLER        = 'sh';
        const SET_STATUS_FIXED_VERSION  = 'sfv';
        # Apply the status change out of the draft as it is
        const APPLY_STATUS              = 'sapply';
        const REPORT_BUG_TAG            = 'rb';
        const GET_PROJECT               = 'gp';
        const SET_PROJECT               = 'sp';
        const SET_CATEGORY              = 'sc';
        const SET_REPRODUCIBILITY       = 'sreproducibility';
        const SET_ETA                   = 'seta';
        const SET_SEVERITY              = 'sseverity';
        const SET_PRIORITY              = 'spriority';
        const SET_STATUS                = 'sstatus';
        const SET_RESOLUTION            = 'sresolution';
        # Leaf through the calendar of the due date, the value is the date shown
        const GET_DUE_DATE              = 'gduedate';
        const SET_DUE_DATE              = 'sduedate';
        const SET_PROFILE               = 'splatform';
        const SET_PRODUCT_VERSION       = 'spversion';
        const SET_TARGET_VERSION        = 'stargetv';
        const SET_HANDLER               = 'shandler';
        # Skip an optional standard field of the draft, the value is the field name
        const SKIP_FIELD                = 'skipf';
        # Marker used instead of a value to skip a field answered with a keyboard of its own
        const SKIP_VALUE                = 'skip';
        # Go back to the question answered last, the answer is asked for again
        const BACK_FIELD                = 'back';
        # Create the issue out of the draft as it is
        const CREATE_ISSUE              = 'crt';
        # Go on with the optional fields of the draft instead of creating the issue
        const FILL_OPTIONAL             = 'opt';
        const GET_CUSTOM_FIELD          = 'gcf';
        const SET_CUSTOM_FIELD          = 'scf';
        const SKIP_CUSTOM_FIELD         = 'skipcf';
        const TOGGLE_CUSTOM_FIELD       = 'tcf';
        const END_CUSTOM_FIELD          = 'endcf';
        # Marker used instead of a value index to request the default value of a custom field
        const CUSTOM_FIELD_DEFAULT_MARK = 'd';
        # Calendar integration: the wizard creating a calendar event out of the chat.
        # The project of the event is picked with GET_PROJECT / SET_PROJECT and the
        # issues it is attached to are left out with SKIP_FIELD, the tags below name
        # the steps of its own.
        const CREATE_EVENT_TAG          = 'ce';
        # Cancel the event wizard and go back to the action list
        const STOP_EVENT_TAG            = 'stop_ce';
        # Leaf through the calendar of a date of the event, the value is the date shown
        const GET_EVENT_DATE            = 'ged';
        const SET_EVENT_DATE            = 'sed';
        # Take the end of the event an hour after its start
        const SET_EVENT_HOUR            = 'seh';
        # Time of a date of the event: reopen the hours, pick an hour or a time
        const GET_EVENT_TIME            = 'gtm';
        const SET_EVENT_TIME            = 'stm';
        # Issues of the event: leaf through the list, tick one off, close the list
        const GET_EVENT_ISSUE           = 'gei';
        const TOGGLE_EVENT_ISSUE        = 'tei';
        const END_EVENT_ISSUE           = 'eei';
        # Members of the event: leaf through the list, tick one off, close the list
        const GET_EVENT_MEMBER          = 'gem';
        const TOGGLE_EVENT_MEMBER       = 'tem';
        const END_EVENT_MEMBER          = 'eem';
        # Create the event out of the draft as it is
        const CREATE_EVENT              = 'cre';

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