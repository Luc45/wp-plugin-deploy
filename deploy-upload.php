<?php

// Change this path to your plugin zip file.
$plugin_zip = __DIR__ . '/plugin.zip';

// Set a 64 character secret here.
$secret = '';

// Set the URL of the home of the website to do the deploy here.
$url = '';


$file = new SplFileObject( $plugin_zip );

$chunk_size_kb    = 512;
$current_chunk    = 0;
$deploy_upload_id = wp_generate_uuid4();
$expected_size    = $file->getSize();

while ( $file->valid() ) {
	$current_chunk ++;
	$curl = curl_init();
	curl_setopt_array( $curl, [
		CURLOPT_URL            => $url,
		CURLOPT_POST           => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HEADER         => true,
		CURLOPT_POSTFIELDS     => [
			'deploy_upload_id' => $deploy_upload_id,
			'current_chunk'    => $current_chunk,
			'expected_size'    => $expected_size,
			'total_chunks'     => ceil( $file->getSize() / ( $chunk_size_kb * 1024 ) ),
			'chunk'            => base64_encode( $file->fread( $chunk_size_kb * 1024 ) ),
			'deploy_secret'    => $secret,
		]
	] );
	$result = curl_exec( $curl );

	if ( curl_getinfo( $curl, CURLINFO_HTTP_CODE ) !== 200 ) {
		die( $result );
	}

	curl_close( $curl );
}

/** Porting wp_generate_uuid4 from Core */
function wp_generate_uuid4() {
	return sprintf(
		'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0x0fff ) | 0x4000,
		mt_rand( 0, 0x3fff ) | 0x8000,
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff )
	);
}