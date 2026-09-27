<?php

# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
# TelegramBot for MantisBT is free software:
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation,
# either version 2 of the License, or (at your option) any later version.
#
# TelegramBot plugin for MantisBT is distributed in the hope
# that it will be useful, but WITHOUT ANY WARRANTY; without even the
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with TelegramBot plugin for MantisBT.
# If not, see <http://www.gnu.org/licenses/>.

/**
 * Logger of the Telegram connection debug, a PSR-3 one writing into a plain file.
 *
 * The library takes a PSR-3 logger and nothing else ( the initDebugLog() family
 * of the 0.4x versions is gone ), and the plugin carries no logging package of
 * its own, so the debug of the exchange is written by this class.
 *
 * @author g.ermolaev
 */
class TelegramBotFileLogger extends \Psr\Log\AbstractLogger {

        /**
         * File the messages are appended to.
         *
         * @var string
         */
        private $log_path;

        /**
         * Whether a failed write has been reported to the log of MantisBT already:
         * an unwritable debug file is worth a single complaint, not one per request.
         *
         * @var boolean
         */
        private $write_failure_logged = FALSE;

        /**
         * @param string $p_log_path File the messages are appended to.
         */
        public function __construct( $p_log_path ) {
                $this->log_path = $p_log_path;
        }

        /**
         * Append a message to the log file.
         *
         * @param mixed  $p_level   Severity of the message, a Psr\Log\LogLevel constant.
         * @param string $p_message Message itself.
         * @param array  $p_context Values of the placeholders of the message.
         * @return void
         */
        public function log( $p_level, $p_message, array $p_context = array() ) {

                if( is_blank( $this->log_path ) ) {
                        return;
                }

                $t_message = (string)$p_message;

                foreach( $p_context as $t_key => $t_value ) {
                        if( is_scalar( $t_value ) || $t_value === NULL
                                        || ( is_object( $t_value ) && method_exists( $t_value, '__toString' ) ) ) {
                                $t_message = str_replace( '{' . $t_key . '}', (string)$t_value, $t_message );
                        }
                }

                $t_line = sprintf( '[%s] %s: %s' . PHP_EOL,
                                          date( 'Y-m-d H:i:s' ),
                                          strtoupper( (string)$p_level ),
                                          $t_message );

                if( @file_put_contents( $this->log_path, $t_line, FILE_APPEND | LOCK_EX ) !== FALSE ) {
                        return;
                }

                if( !$this->write_failure_logged ) {
                        $this->write_failure_logged = TRUE;

                        plugin_log_event( 'ERROR! The debug of the connection is not written to "' . $this->log_path . '"' );
                }
        }

}
