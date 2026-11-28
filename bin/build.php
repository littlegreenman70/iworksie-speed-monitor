<?php
/**
 * Build release directory and zip.
 *
 * Assumes a full `composer install` (with dev) has already produced vendor-prefixed/
 * via Strauss. This script refreshes Strauss, builds the pot, then copies a clean tree.
 *
 * @package IWorksIE\SpeedMonitor
 */

$root = dirname( __DIR__ );
chdir( $root );

if ( ! is_readable( $root . '/vendor/bin/strauss' ) ) {
	fwrite( STDERR, "Run composer install (with dev deps) before composer build.\n" );
	exit( 1 );
}

passthru( 'php vendor/bin/strauss', $code );
if ( 0 !== $code ) {
	exit( $code );
}

passthru( 'php bin/copy-core-assets.php vendor-prefixed', $code );
if ( 0 !== $code ) {
	exit( $code );
}

$pot_cmd = 'wp i18n make-pot . languages/iworksie-speed-monitor.pot --domain=iworksie-speed-monitor --include=iworksie-speed-monitor.php,uninstall.php,src,vendor-prefixed --exclude=vendor,build,tests,bin,.github,.wordpress-org 2>/dev/null';
passthru( $pot_cmd, $pot_code );
if ( 0 !== $pot_code ) {
	fwrite( STDERR, "Warning: wp i18n make-pot failed (code {$pot_code}); continuing build.\n" );
}

$distignore = array();
$ignore_file = $root . '/.distignore';
if ( is_readable( $ignore_file ) ) {
	$lines = file( $ignore_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}
		$distignore[] = $line;
	}
}

$build_dir = $root . '/build/iworksie-speed-monitor';
$zip_path  = $root . '/build/iworksie-speed-monitor.zip';

if ( is_dir( $root . '/build' ) ) {
	passthru( 'rm -rf ' . escapeshellarg( $root . '/build' ) );
}
mkdir( $build_dir, 0755, true );

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $iterator as $item ) {
	$full = $item->getPathname();
	$rel  = substr( $full, strlen( $root ) + 1 );
	if ( 0 === strpos( $rel, 'build/' ) || 'build' === $rel ) {
		continue;
	}
	if ( should_ignore( $rel, $distignore ) ) {
		continue;
	}
	$dest = $build_dir . '/' . $rel;
	if ( $item->isDir() ) {
		if ( ! is_dir( $dest ) ) {
			mkdir( $dest, 0755, true );
		}
	} else {
		$dir = dirname( $dest );
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		copy( $full, $dest );
	}
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create zip\n" );
	exit( 1 );
}

$zip_it = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $build_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ( $zip_it as $file ) {
	if ( $file->isDir() ) {
		continue;
	}
	$path    = $file->getRealPath();
	$rel_zip = 'iworksie-speed-monitor/' . substr( $path, strlen( $build_dir ) + 1 );
	$zip->addFile( $path, $rel_zip );
}
$zip->close();

echo "Built {$build_dir}\n";
echo "Zip {$zip_path}\n";

/**
 * Whether a relative path matches .distignore patterns.
 *
 * @param string            $rel      Relative path.
 * @param array<int,string> $patterns Patterns.
 * @return bool
 */
function should_ignore( $rel, array $patterns ) {
	$parts = explode( '/', str_replace( '\\', '/', $rel ) );
	$top   = $parts[0];
	foreach ( $patterns as $pattern ) {
		$pattern = trim( $pattern );
		if ( '' === $pattern ) {
			continue;
		}
		if ( 0 === strpos( $pattern, '*' ) ) {
			if ( fnmatch( $pattern, basename( $rel ) ) ) {
				return true;
			}
			continue;
		}
		if ( $top === $pattern || $rel === $pattern || 0 === strpos( $rel, $pattern . '/' ) ) {
			return true;
		}
	}
	return false;
}
