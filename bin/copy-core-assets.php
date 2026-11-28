<?php
/**
 * Copy core CSS/JS into vendor-prefixed/assets after Strauss.
 *
 * Usage: php bin/copy-core-assets.php vendor-prefixed
 *
 * @package IWorksIE\SpeedMonitor
 */

$target = isset( $argv[1] ) ? $argv[1] : 'vendor-prefixed';
$cwd    = getcwd();
$src    = $cwd . '/vendor/iworksie/tools-core/assets';
if ( ! is_dir( $src ) ) {
	$src = dirname( $cwd ) . '/iworks-tools-core/assets';
}
$dst = $cwd . '/' . $target . '/assets';

if ( ! is_dir( $src ) ) {
	fwrite( STDERR, "Assets source missing: {$src}\n" );
	exit( 1 );
}

foreach ( array( 'css', 'js' ) as $type ) {
	$dir = $dst . '/' . $type;
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
		fwrite( STDERR, "Could not create {$dir}\n" );
		exit( 1 );
	}
	foreach ( glob( $src . '/' . $type . '/*' ) as $file ) {
		copy( $file, $dir . '/' . basename( $file ) );
	}
}

echo "Copied core assets to {$dst}\n";
