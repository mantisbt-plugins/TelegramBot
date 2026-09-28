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
 * @author Grigoriy Ermolaev <igflocal@gmail.com>
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
         * Extensions a log file may have: the log carries the messages of the users,
         * so it must never become a file the web server executes or interprets.
         *
         * @var array
         */
        const EXTENSIONS_ALLOWED = array( 'log', 'txt' );

        /**
         * @param string $p_log_path File the messages are appended to.
         */
        public function __construct( $p_log_path ) {
                # A path stored before the validation existed is not trusted either
                if( !self::path_is_allowed( $p_log_path ) ) {
                        plugin_log_event( 'ERROR! The debug of the connection is not written to the refused path "' . $p_log_path . '"' );
                        $p_log_path = '';
                }

                $this->log_path = $p_log_path;
        }

        /**
         * Whether the path may take the debug log: a .log or .txt file in an
         * existing directory outside the web root of MantisBT and the document root
         * of the web server.
         *
         * @param string $p_log_path Path to check.
         * @return boolean
         */
        public static function path_is_allowed( $p_log_path ) {

                if( is_blank( $p_log_path ) ) {
                        return FALSE;
                }

                $t_name = basename( $p_log_path );

                if( $t_name === '' || $t_name[0] == '.'
                                || !in_array( strtolower( pathinfo( $t_name, PATHINFO_EXTENSION ) ), self::EXTENSIONS_ALLOWED, TRUE ) ) {
                        return FALSE;
                }

                $t_dir = realpath( dirname( $p_log_path ) );

                if( $t_dir === FALSE || !is_dir( $t_dir ) ) {
                        return FALSE;
                }

                # A link placed at the path would lead the writes anywhere
                $t_path = $t_dir . DIRECTORY_SEPARATOR . $t_name;
                if( is_link( $t_path ) ) {
                        return FALSE;
                }

                $t_roots = array( config_get_global( 'absolute_path' ) );
                if( !empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
                        $t_roots[] = $_SERVER['DOCUMENT_ROOT'];
                }

                foreach( $t_roots as $t_root ) {
                        $t_root = realpath( $t_root );

                        if( $t_root !== FALSE && self::path_is_inside( $t_path, $t_root ) ) {
                                return FALSE;
                        }
                }

                return TRUE;
        }

        /**
         * Create the log file readable by its owner only, unless it exists already.
         *
         * @param string $p_log_path Path of the file, checked by path_is_allowed().
         * @return boolean Whether the file can be written.
         */
        public static function file_prepare( $p_log_path ) {

                # The 'x' mode fails on an existing file, so the mode of a file made
                # by somebody else is left alone
                $t_handle = @fopen( $p_log_path, 'x' );
                if( $t_handle !== FALSE ) {
                        fclose( $t_handle );
                        @chmod( $p_log_path, 0600 );
                }

                return is_file( $p_log_path ) && is_writable( $p_log_path );
        }

        /**
         * Whether the path lies inside the directory.
         *
         * @param string $p_path Resolved path.
         * @param string $p_dir  Resolved directory.
         * @return boolean
         */
        private static function path_is_inside( $p_path, $p_dir ) {
                $t_dir = rtrim( $p_dir, '/\\' ) . DIRECTORY_SEPARATOR;

                # File names on Windows are case insensitive
                if( DIRECTORY_SEPARATOR == '\\' ) {
                        return stripos( $p_path, $t_dir ) === 0;
                }

                return strpos( $p_path, $t_dir ) === 0;
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

                if( self::file_prepare( $this->log_path )
                                && @file_put_contents( $this->log_path, $t_line, FILE_APPEND | LOCK_EX ) !== FALSE ) {
                        return;
                }

                if( !$this->write_failure_logged ) {
                        $this->write_failure_logged = TRUE;

                        plugin_log_event( 'ERROR! The debug of the connection is not written to "' . $this->log_path . '"' );
                }
        }

}
