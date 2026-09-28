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
 * Remove from the dependencies what the plugin never loads.
 *
 * Run by composer after install and update ( see "scripts" in composer.json ):
 * the plugin is unpacked into the web root of MantisBT, so a script of a package
 * ( longman/telegram-bot/utils/importFromLog.php ), its tests or its database
 * dump would be reachable from the web on any server that does not honor the
 * .htaccess of the plugin.
 */

$t_vendor_dir = dirname( __DIR__ ) . '/TelegramBot/api/vendor';

# Directories of a package that hold no code of the library
$t_dirs_removed = array( 'utils', 'bin', 'tests', 'Tests', 'test', 'Test', 'examples' );

if( !is_dir( $t_vendor_dir ) ) {
	exit( 0 );
}

/**
 * Delete a directory with all of its content.
 *
 * @param string $p_dir Path of the directory.
 * @return void
 */
function strip_vendor_delete( $p_dir ) {
	foreach( new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $p_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST ) as $t_entry ) {
		if( $t_entry->isDir() && !$t_entry->isLink() ) {
			rmdir( $t_entry->getPathname() );
		} else {
			unlink( $t_entry->getPathname() );
		}
	}

	rmdir( $p_dir );
}

$t_removed = array();

# The packages are two levels down ( vendor/<vendor>/<package> ), composer's own
# vendor/bin and vendor/composer stay untouched
foreach( glob( $t_vendor_dir . '/*/*', GLOB_ONLYDIR ) as $t_package_dir ) {
	$t_iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $t_package_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST );

	$t_found = array();

	foreach( $t_iterator as $t_entry ) {
		if( $t_entry->isDir() && in_array( $t_entry->getFilename(), $t_dirs_removed, TRUE ) ) {
			$t_found[] = $t_entry->getPathname();
		} else if( $t_entry->isFile() && strtolower( $t_entry->getExtension() ) == 'sql' ) {
			$t_found[] = $t_entry->getPathname();
		}
	}

	# Deleted after the walk: the iterator must not lose the ground under its feet;
	# a path inside a directory already deleted is gone by then and is skipped
	foreach( $t_found as $t_path ) {
		if( is_dir( $t_path ) ) {
			strip_vendor_delete( $t_path );
		} else if( is_file( $t_path ) ) {
			unlink( $t_path );
		} else {
			continue;
		}

		$t_removed[] = substr( $t_path, strlen( $t_vendor_dir ) + 1 );
	}
}

foreach( $t_removed as $t_path ) {
	echo 'Stripped vendor/' . str_replace( '\\', '/', $t_path ) . "\n";
}
