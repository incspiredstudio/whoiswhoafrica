<?php
/**
 * Zip a folder: php bin/zip-dir.php <parent-folder> <zip-file> <entry> [<entry> ...]
 * Adds each named entry (a file or a folder) found in <parent-folder> to the zip, keeping the entry name as the top level of the
 * archive, with forward slashes. Used by package-release.sh, which cannot count on a zip tool; PHP's ZipArchive is already
 * required to run WordPress.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput,WordPress.NamingConventions.PrefixAllGlobals -- a command line script.

/**
 * Build the zip.
 *
 * @param string   $folder  Folder that holds the entries.
 * @param string   $target  Zip file to write.
 * @param string[] $entries Entry names (files or folders) inside $folder.
 * @return int Exit code.
 */
function wiw_zip_dir( string $folder, string $target, array $entries ): int {
	$folder = rtrim( str_replace( '\\', '/', $folder ), '/' );
	if ( file_exists( $target ) ) {
		unlink( $target );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $target, ZipArchive::CREATE ) ) {
		fwrite( STDERR, "Cannot create $target\n" );
		return 1;
	}
	$count = 0;
	foreach ( $entries as $name ) {
		$full = $folder . '/' . $name;
		if ( is_file( $full ) ) {
			$zip->addFile( $full, $name );
			++$count;
			continue;
		}
		if ( ! is_dir( $full ) ) {
			fwrite( STDERR, "Missing entry: $name\n" );
			return 1;
		}
		$zip->addEmptyDir( $name );
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $full, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $iterator as $item ) {
			$relative = $name . '/' . ltrim( substr( str_replace( '\\', '/', $item->getPathname() ), strlen( $full ) ), '/' );
			if ( $item->isDir() ) {
				$zip->addEmptyDir( $relative );
			} else {
				$zip->addFile( $item->getPathname(), $relative );
				++$count;
			}
		}//end foreach
	}//end foreach
	$zip->close();
	echo basename( $target ) . ': ' . $count . " files\n";
	return 0;
}

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
if ( $argc < 4 || ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "Usage: php bin/zip-dir.php <parent-folder> <zip-file> <entry> [<entry> ...] (needs the PHP zip extension)\n" );
	exit( 2 );
}
exit( wiw_zip_dir( $argv[1], $argv[2], array_slice( $argv, 3 ) ) );
