<?php
$root = dirname( __DIR__ );
function fei_zip_dir( string $srcDir, string $zipPath, string $rootName ): void {
	if ( is_file( $zipPath ) ) {
		unlink( $zipPath );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		fwrite( STDERR, "Cannot create $zipPath\n" );
		exit( 1 );
	}
	$srcDir = realpath( $srcDir );
	$iter   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $srcDir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iter as $file ) {
		$full = $file->getPathname();
		$rel  = str_replace( '\\', '/', substr( $full, strlen( $srcDir ) + 1 ) );
		$zip->addFile( $full, $rootName . '/' . $rel );
	}
	$zip->close();
	$check = new ZipArchive();
	$check->open( $zipPath );
	echo basename( $zipPath ), " n=", $check->numFiles, " first=", $check->getNameIndex( 0 ), PHP_EOL;
	$check->close();
}
fei_zip_dir( $root . '/wp-content/plugins/fei-core', $root . '/dist/fei-core.zip', 'fei-core' );
fei_zip_dir( $root . '/wp-content/themes/fei-kadence-child', $root . '/dist/fei-kadence-child.zip', 'fei-kadence-child' );
echo "ok\n";
