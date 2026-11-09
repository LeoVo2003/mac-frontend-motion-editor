<?php
/** Run with php tests/updater.php; WordPress HTTP/cache test doubles only. */
define( 'ABSPATH', __DIR__ );
define( 'MFME_FILE', '/plugins/mac-frontend-motion-editor/mac-frontend-motion-editor.php' );
define( 'MFME_VERSION', '0.2.1' );
define( 'HOUR_IN_SECONDS', 3600 );
$cache = []; $http_calls = 0; $code = 200;
$payload = [ 'version' => '0.2.1.1001', 'package' => 'https://github.com/LeoVo2003/mac-frontend-motion-editor/releases/download/v0.2.1.1001/mac-frontend-motion-editor.zip' ];
function get_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['cache'][ $key ] = $value; }
function is_wp_error( $value ) { return false; }
function wp_safe_remote_get( $url, $args ) {
	++$GLOBALS['http_calls'];
	if ( 'https://github.com/LeoVo2003/mac-frontend-motion-editor/releases/latest/download/update.json' !== $url ) { throw new Exception( 'Wrong manifest URL' ); }
	return [ 'code' => $GLOBALS['code'], 'body' => json_encode( $GLOBALS['payload'] ) ];
}
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function plugin_basename( $file ) { return 'mac-frontend-motion-editor/mac-frontend-motion-editor.php'; }
function esc_url( $url ) { return $url; }
require __DIR__ . '/../mac-frontend-motion-editor/includes/class-mfme-updater.php';
function expect( $condition, $label ) { if ( ! $condition ) { throw new RuntimeException( $label ); } echo "PASS $label\n"; }
$release = MFME_Updater::release();
expect( '0.2.1.1001' === $release['version'], 'valid release accepted' );
MFME_Updater::release();
expect( 1 === $http_calls, 'successful checks cached' );
expect( version_compare( $release['version'], MFME_VERSION, '>' ), 'commit build compares newer than bootstrap' );
expect( version_compare( '0.2.1.2001', $release['version'], '>' ), 'next commit version compares newer' );
expect( false === MFME_Updater::update( false, [], 'another/plugin.php', [] ), 'other GitHub plugins untouched' );
expect( $release === MFME_Updater::update( false, [], plugin_basename( MFME_FILE ), [] ), 'WordPress update hook returns matching plugin only' );
$info = MFME_Updater::information( false, 'plugin_information', (object) [ 'slug' => 'mac-frontend-motion-editor' ] );
expect( $release['package'] === $info->download_link, 'plugin details point to same immutable package' );
foreach ( [ 'https://evil.example/plugin.zip', 'http://github.com/LeoVo2003/mac-frontend-motion-editor/releases/download/v1/mac-frontend-motion-editor.zip', 'https://github.com/someone/other/releases/download/v1/mac-frontend-motion-editor.zip', 'https://github.com/LeoVo2003/mac-frontend-motion-editor/releases/download/../mac-frontend-motion-editor.zip', 'https://github.com/LeoVo2003/mac-frontend-motion-editor/releases/download/v9.9.9/mac-frontend-motion-editor.zip' ] as $url ) {
	$cache = []; $payload['package'] = $url;
	expect( false === MFME_Updater::release(), 'reject foreign or malformed package' );
}
$cache = []; $code = 404; $http_calls = 0;
expect( false === MFME_Updater::release(), 'missing first release handled' );
MFME_Updater::release(); expect( 1 === $http_calls, 'failure briefly cached' );
$cache = []; $code = 200; $payload['version'] = [ 'bad' ];
expect( false === MFME_Updater::release(), 'invalid metadata rejected' );
