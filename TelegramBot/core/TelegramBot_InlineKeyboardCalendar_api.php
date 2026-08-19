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

class TelegramBotInlineKeyboardCalendar {

        protected $date;
        protected $year;
        protected $month;
        protected $month_array = [
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'september',
            'october',
            'november',
            'december'
        ];
        protected $weekdays = [
            '1' => 'Mon',
            '2' => 'Tue',
            '3' => 'Wed',
            '4' => 'Thu',
            '5' => 'Fri',
            '6' => 'Sat',
            '0' => 'Sun'
        ];

        public function __construct( $p_date ) {
                $this -> setConfigDate( $p_date );
                return $this;
        }

        /**
         * @param date $date
         *
         * @return int
         */
        public function setConfigDate( $date ) {
                $this -> date = $date;
        }

        /**
         * List of date labels.
         *
         *
         * @return array
         */
        private function listOfDate() {

                list($year, $month) = explode( '-', $this -> date );
                $this -> month = $month;
                $this -> year = $year;

                $start_date = "01-" . $month . "-" . $year;
                $start_time = strtotime( $start_date );

                $end_time = strtotime( "+1 month", $start_time );

                for( $i = $start_time; $i < $end_time; $i += 86400 ) {
                        $date = date( 'Y-m-d', $i );
                        $day = date( 'w', $i );
                        $list[$this -> weekOfMonth( $date )][$day] = date( 'd', $i );
                }

                return $list;
        }

        /**
         * Generate calendar
         *
         *
         * @return array
         */
        public function getKeyboard( $p_action, $p_id = 0, $p_tag = TelegrambotActions::REPORT_BUG_TAG ) {
                $t_lists = $this -> listOfDate();

                $t_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

                # The header switches the calendar to the overview of the whole year
                $t_keyboard -> addRow(
                        ['text' => plugin_lang_get( 'month_' . $this -> month_array[( $this -> month ) - 1] ) . ' ' . $this -> year, 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array(
                            $p_id => '' . $this -> year
                            ) ) ) )]
                );

                foreach( $this -> weekdays as $t_day_name ) {
                        $t_days_name_payload[] = ['text' => plugin_lang_get( 'weekday_' . $t_day_name ), 'callback_data' => '' . $t_day_name];
                }

                call_user_func_array( array( $t_keyboard, 'addRow' ), $t_days_name_payload );

                # The button of the current day is marked so the user can tell
                # today's date without leaving the chat
                $t_today = date( 'Y-n-j' );

                foreach( $t_lists as $t_keyList => $t_valueList ) {
                        $t_row = [];
                        foreach( $this -> weekdays as $t_keyArray => $t_valueArray ) {
                                if( isset( $t_lists[$t_keyList][$t_keyArray] ) ) {
                                        $t_day = $t_lists[$t_keyList][$t_keyArray];
                                        $t_is_today = ( $t_today == (int)$this -> year . '-' . (int)$this -> month . '-' . (int)$t_day );
                                        $t_row[] = ['text' => ( $t_is_today ? '🟢' : '' ) . $t_day, 'callback_data' => json_encode( array( $p_tag => array( 's' . $p_action => array(
                                            $p_id => $this -> year . '-' . $this -> month . '-' . $t_day
                                            ) ) ) )];
                                } else {
                                        $t_row[] = ['text' => '-', 'callback_data' => '0'];
                                }
                        }
                        call_user_func_array( array( $t_keyboard, 'addRow' ), $t_row );
                }

                $t_keyboard -> addRow(
                        ['text' => '« ' . plugin_lang_get( 'prev_month' ), 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array( 
                            $p_id => $this -> prevMonth( $this -> year . '-' . $this -> month ) 
                            ) ) ) )],
                        ['text' => plugin_lang_get( 'next_month' ) . ' »', 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array( 
                            $p_id => $this -> nextMonth( $this -> year . '-' . $this -> month )
                            ) ) ) )]
                );

                return $t_keyboard;
        }

        /**
         * Generate the overview of the whole year: a keyboard of the twelve months.
         *
         * A month switches the calendar back to the grid of its days, the bottom
         * row leafs through the years.
         *
         * @param string  $p_action Action the calendar is shown for.
         * @param integer $p_id     Identifier passed back along with the action.
         * @return Longman\TelegramBot\Entities\InlineKeyboard
         */
        public function getYearKeyboard( $p_action, $p_id = 0, $p_tag = TelegrambotActions::REPORT_BUG_TAG ) {
                list( $t_year ) = explode( '-', $this -> date );
                $t_year = (int)$t_year;

                $t_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

                # The header switches the calendar to the overview of the years
                $t_keyboard -> addRow(
                        ['text' => '' . $t_year, 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array(
                            $p_id => 'd' . $t_year
                            ) ) ) )]
                );

                for( $i = 0; $i < 4; $i++ ) {
                        $t_row = [];
                        for( $j = 0; $j < 3; $j++ ) {
                                $t_month = $i * 3 + $j + 1;
                                $t_row[] = ['text' => plugin_lang_get( 'month_' . $this -> month_array[$t_month - 1] ), 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array(
                                    $p_id => $t_year . '-' . $t_month
                                    ) ) ) )];
                        }
                        call_user_func_array( array( $t_keyboard, 'addRow' ), $t_row );
                }

                $t_keyboard -> addRow(
                        ['text' => '« ' . ( $t_year - 1 ), 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array(
                            $p_id => '' . ( $t_year - 1 )
                            ) ) ) )],
                        ['text' => ( $t_year + 1 ) . ' »', 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array(
                            $p_id => '' . ( $t_year + 1 )
                            ) ) ) )]
                );

                return $t_keyboard;
        }

        /**
         * Generate the overview of a twelve year block.
         *
         * A year switches the calendar back to the overview of its months, the
         * bottom row leafs through the blocks. The blocks are aligned to twelve
         * years, so leafing back and forth always shows the same blocks.
         *
         * @param string  $p_action Action the calendar is shown for.
         * @param integer $p_id     Identifier passed back along with the action.
         * @return Longman\TelegramBot\Entities\InlineKeyboard
         */
        public function getYearsKeyboard( $p_action, $p_id = 0, $p_tag = TelegrambotActions::REPORT_BUG_TAG ) {
                $t_year  = (int)preg_replace( '/\D/', '', $this -> date );
                $t_start = $t_year - ( $t_year % 12 );

                $t_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

                $t_keyboard -> addRow(
                        ['text' => $t_start . ' - ' . ( $t_start + 11 ), 'callback_data' => '0']
                );

                for( $i = 0; $i < 4; $i++ ) {
                        $t_row = [];
                        for( $j = 0; $j < 3; $j++ ) {
                                $t_item = $t_start + $i * 3 + $j;
                                $t_row[] = ['text' => '' . $t_item, 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array(
                                    $p_id => '' . $t_item
                                    ) ) ) )];
                        }
                        call_user_func_array( array( $t_keyboard, 'addRow' ), $t_row );
                }

                $t_keyboard -> addRow(
                        ['text' => '« ' . ( $t_start - 12 ) . ' - ' . ( $t_start - 1 ), 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array(
                            $p_id => 'd' . ( $t_start - 12 )
                            ) ) ) )],
                        ['text' => ( $t_start + 12 ) . ' - ' . ( $t_start + 23 ) . ' »', 'callback_data' => json_encode( array( $p_tag => array( 'g' . $p_action => array(
                            $p_id => 'd' . ( $t_start + 12 )
                            ) ) ) )]
                );

                return $t_keyboard;
        }

        /**
         * Set the the prev month on callback.
         *
         *
         * @return date
         */
        private function prevMonth() {
                $month = date( 'Y-m', strtotime( $this -> date . " -1 month" ) );
                return $month;
        }

        /**
         * Set the the next month on callback.
         *
         *
         * @return date
         */
        private function nextMonth() {
                $month = date( 'Y-m', strtotime( $this -> date . " +1 month" ) );
                return $month;
        }

        /**
         * @param date $date
         *
         * @return int
         */
        private function weekOfMonth( $date ) {
                $firstOfMonth = date( "Y-m-01", strtotime( $date ) );
                return intval( date( "W", strtotime( $date ) ) ) - intval( date( "W", strtotime( $firstOfMonth ) ) );
        }

}