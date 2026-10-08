<?php
/** wp --user=admin eval-file tests/files.php; temporary directories and credentials are removed. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development single site with its web server running.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$owner = get_current_user_id(); $grants = Guest_Key_Access::grants();
$active = is_plugin_active( Guest_Key_Dependency::PLUGIN ); $managed = get_option( 'guest_key_adapter_managed', null );
$token = strtolower( wp_generate_password( 10, false ) );
$relative = 'gk-files-' . $token; $fixture = WP_CONTENT_DIR . '/' . $relative;
$outside = WP_CONTENT_DIR . '-gk-files-' . $token;
if ( file_exists( $fixture ) || file_exists( $outside ) ) { throw new RuntimeException( 'Fixture paths already exist.' ); }
$uid = wp_insert_user( array( 'user_login' => 'gk-files-' . $token, 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
if ( is_wp_error( $uid ) ) { throw new RuntimeException( $uid->get_error_message() ); }
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$call = static function ( $name, $input = array() ) { return wp_get_ability( 'guest-key/' . $name )->execute( $input ); };
$http = static function ( $path, $parameters = array(), $key = null, $method = 'GET', $plain = false ) {
	$url = $plain ? add_query_arg( array_merge( array( 'rest_route' => '/guest-key/v1/' . $path ), $parameters ), home_url( '/' ) ) : add_query_arg( $parameters, rest_url( 'guest-key/v1/' . $path ) );
	$headers = array();
	if ( $key ) { $headers['Authorization'] = 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ); }
	$r = wp_remote_request( $url, array( 'method' => $method, 'headers' => $headers, 'sslverify' => false, 'timeout' => 20, 'user-agent' => 'GuestKey/' . GUEST_KEY_VERSION . ' (read-only filesystem regression test)' ) );
	if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_message() ); }
	return array( 'status' => wp_remote_retrieve_response_code( $r ), 'data' => json_decode( wp_remote_retrieve_body( $r ), true ), 'headers' => wp_remote_retrieve_headers( $r ) );
};
$remove = static function ( $path ) use ( &$remove ) {
	if ( is_link( $path ) || is_file( $path ) ) { unlink( $path ); }
	elseif ( is_dir( $path ) ) { foreach ( new FilesystemIterator( $path, FilesystemIterator::SKIP_DOTS ) as $entry ) { $remove( $entry->getPathname() ); } rmdir( $path ); }
};
try {
	wp_set_current_user( $uid ); mkdir( $fixture ); mkdir( $outside ); mkdir( $fixture . '/nested' ); mkdir( $fixture . '/clean' );
	$source = "<?php\n// Needle: source behavior\nfile_put_contents(" . var_export( $fixture . '/executed', true ) . ", 'executed');\n// café 🌊\n// needle: second line\n";
	file_put_contents( $fixture . '/example.php', $source ); file_put_contents( $fixture . '/nested/rules.css', "body { color: red; }\r\n/* NEEDLE */\r\n" );
	file_put_contents( $fixture . '/clean/one.txt', "one needle\ntwo needle\nthree needle\n" );
	file_put_contents( $fixture . '/literal.txt', "literal .* string\n" );
	file_put_contents( $fixture . '/empty.txt', '' ); file_put_contents( $fixture . '/binary.dat', "text\0needle" ); file_put_contents( $fixture . '/invalid.txt', "bad\xff" );
	file_put_contents( $fixture . '/.env', 'needle excluded credential fixture' ); file_put_contents( $fixture . '/wp-config.php.bak', 'needle excluded config fixture' );
	mkdir( $fixture . '/.git' ); file_put_contents( $fixture . '/.git/config', 'needle excluded VCS fixture' );
	file_put_contents( $outside . '/private.txt', 'needle outside content' );
	symlink( $outside . '/private.txt', $fixture . '/outside-file' ); symlink( $outside, $fixture . '/outside-dir' );
	symlink( $fixture . '/example.php', $fixture . '/inside-file' ); symlink( $fixture . '/.env', $fixture . '/credential-alias' ); symlink( $fixture, $fixture . '/loop' );
	$hash = hash_file( 'sha256', $fixture . '/example.php' );
	$help = wp_get_ability( 'guest-key/help' )->execute( array( 'command' => 'file' ) );
	$assert( array( 'file list', 'file read', 'file search' ) === $help['commands'], 'Filesystem inspection uses three discoverable commands without new MCP tools' );
	$r = $call( 'list-files', array( 'path' => $relative ) ); $paths = array_column( $r['items'], 'path' );
	$assert( ! is_wp_error( $r ) && in_array( $relative . '/example.php', $paths, true ) && ! in_array( $relative . '/nested/rules.css', $paths, true ), 'Default listing browses one directory with relative paths' );
	$assert( ! in_array( $relative . '/.env', $paths, true ) && ! in_array( $relative . '/wp-config.php.bak', $paths, true ) && ! in_array( $relative . '/.git', $paths, true ), 'Listing excludes known credentials and version-control directories' );
	$by_path = array_column( $r['items'], null, 'path' );
	$assert( 'symlink' === $by_path[ $relative . '/outside-file' ]['type'] && ! $by_path[ $relative . '/outside-file' ]['readable'], 'An escaping symlink is identified without revealing its absolute target' );
	$r = $call( 'list-files', array( 'path' => $relative, 'recursive' => true, 'search' => '.css' ) );
	$assert( array( $relative . '/nested/rules.css' ) === array_column( $r['items'], 'path' ), 'Recursive filename discovery skips symlink cycles' );
	$r = $call( 'list-files', array( 'path' => $relative . '/clean', 'limit' => 1 ) );
	$assert( 1 === count( $r['items'] ) && 1 === $r['next_offset'] && ! $r['complete'], 'Listing reports its page boundary' );
	$r = $call( 'list-files', array( 'path' => $relative . '/clean', 'offset' => 1 ) );
	$assert( array() === $r['items'] && $r['complete'], 'Listing pagination ends cleanly after the last entry' );
	$r = $call( 'read-file', array( 'path' => $relative . '/example.php' ) );
	$assert( $source === $r['content'] && null === $r['next_offset'] && 1 === $r['line'] && ! file_exists( $fixture . '/executed' ), 'Reading PHP returns source without evaluating it' );
	$r = $call( 'read-file', array( 'path' => $relative . '/inside-file' ) );
	$assert( $source === $r['content'], 'Direct reads allow a symlink whose canonical target remains inside the content root' );
	$combined = ''; $offset = 0;
	do { $r = $call( 'read-file', array( 'path' => $relative . '/example.php', 'offset' => $offset, 'length' => 7 ) ); $combined .= $r['content']; $offset = $r['next_offset']; } while ( null !== $offset );
	$assert( $source === $combined, 'Small chunk pagination preserves complete UTF-8 characters and source bytes' );
	$r = $call( 'read-file', array( 'path' => $relative . '/empty.txt' ) );
	$assert( '' === $r['content'] && null === $r['next_offset'], 'An empty text file is a successful read' );
	foreach ( array( '../wp-config.php', $outside . '/private.txt', 'php://filter/resource=wp-config.php', 'plugins/../themes', 'plugins\\example', '%2e%2e/wp-config.php', "plugins\0bad" ) as $path ) {
		$r = $call( 'read-file', array( 'path' => $path ) ); $assert( is_wp_error( $r ), 'Reject traversal, absolute paths and stream/encoded paths: ' . wp_json_encode( $path ) );
	}
	foreach ( array( 'outside-file', 'outside-dir/private.txt', 'credential-alias', '.env', 'wp-config.php.bak', '.git/config' ) as $path ) {
		$r = $call( 'read-file', array( 'path' => $relative . '/' . $path ) ); $assert( is_wp_error( $r ) && 403 === $r->get_error_data()['status'], 'Canonical boundaries and exclusions apply to direct reads: ' . $path );
	}
	foreach ( array( 'binary.dat', 'invalid.txt' ) as $path ) { $r = $call( 'read-file', array( 'path' => $relative . '/' . $path ) ); $assert( is_wp_error( $r ) && 415 === $r->get_error_data()['status'], 'Reject binary or non-UTF-8 text: ' . $path ); }
	$assert( is_wp_error( $call( 'read-file', array( 'path' => $relative . '/missing' ) ) ), 'Missing files return a structured error' );
	$assert( is_wp_error( $call( 'read-file', array( 'path' => $relative . '/example.php', 'length' => 65537 ) ) ), 'Read response size is bounded by the input schema' );
	$r = $call( 'search-files', array( 'path' => $relative, 'query' => 'needle' ) );
	$assert( 6 === count( $r['matches'] ) && $r['scan']['skipped']['symlink'] >= 5 && $r['scan']['skipped']['binary'] >= 2 && $r['scan']['skipped']['excluded'] >= 3 && ! $r['complete'], 'Search returns real matches and reports excluded, binary and symlink omissions' );
	$php_matches = array_values( array_filter( $r['matches'], static function ( $match ) use ( $relative ) { return $relative . '/example.php' === $match['path']; } ) );
	$assert( 2 === count( $php_matches ) && 2 === $php_matches[0]['line'] && strpos( $source, 'Needle' ) === $php_matches[0]['offset'], 'Search reports source line numbers and exact byte offsets' );
	$r = $call( 'search-files', array( 'path' => $relative, 'query' => 'Needle', 'case_sensitive' => true, 'filename' => '*.php' ) );
	$assert( 1 === count( $r['matches'] ) && $relative . '/example.php' === $r['matches'][0]['path'], 'Literal case-sensitive search supports a basename glob' );
	$r = $call( 'search-files', array( 'path' => $relative, 'query' => '.*' ) );
	$assert( 1 === count( $r['matches'] ) && $relative . '/literal.txt' === $r['matches'][0]['path'], 'Search metacharacters are literal, not regular expressions' );
	$r = $call( 'search-files', array( 'path' => $relative . '/clean', 'query' => 'needle', 'limit' => 1 ) );
	$assert( 1 === count( $r['matches'] ) && 1 === $r['next_offset'] && 'results' === $r['scan']['limited'], 'Search reports a result-page continuation' );
	$r = $call( 'search-files', array( 'path' => $relative . '/clean', 'query' => 'needle', 'offset' => 1 ) );
	$assert( 2 === count( $r['matches'] ) && 2 === $r['matches'][0]['line'] && $r['complete'], 'Search pagination resumes with subsequent matching lines' );
	$batch = wp_get_ability( 'guest-key/run' )->execute( array( 'commands' => array(
		array( 'command' => 'file search', 'input' => array( 'path' => $relative . '/clean', 'query' => 'needle', 'limit' => 1 ) ),
		array( 'command' => 'file read', 'input' => array( 'path' => array( '$ref' => '0.data.matches.0.path' ), 'offset' => array( '$ref' => '0.data.matches.0.line_offset' ) ) ),
	) ) );
	$assert( $batch['ok'] && false !== strpos( $batch['results'][1]['data']['content'], 'one needle' ), 'A search result can feed a source read in one command batch' );
	mkdir( $fixture . '/byte-limit' );
	for ( $i = 0; $i < 9; $i++ ) { file_put_contents( $fixture . '/byte-limit/' . $i . '.txt', str_repeat( 'z', Guest_Key_Files::MAX_FILE_BYTES ) ); }
	$r = $call( 'search-files', array( 'path' => $relative . '/byte-limit', 'query' => 'not-present' ) );
	$assert( 'bytes' === $r['scan']['limited'] && ! $r['complete'] && $r['scan']['bytes_scanned'] <= Guest_Key_Files::MAX_SCAN_BYTES, 'Search stops at its total byte budget and reports an incomplete scan' );
	file_put_contents( $fixture . '/oversized.txt', str_repeat( 'x', Guest_Key_Files::MAX_FILE_BYTES + 1 ) );
	$r = $call( 'read-file', array( 'path' => $relative . '/oversized.txt' ) );
	$assert( is_wp_error( $r ) && 413 === $r->get_error_data()['status'], 'Individual file size is bounded' );
	mkdir( $fixture . '/entry-limit' );
	for ( $i = 0; $i <= Guest_Key_Files::MAX_ENTRIES; $i++ ) { touch( $fixture . '/entry-limit/' . $i ); }
	$r = $call( 'search-files', array( 'path' => $relative . '/entry-limit', 'query' => 'not-present' ) );
	$assert( 'entries' === $r['scan']['limited'] && ! $r['complete'] && Guest_Key_Files::MAX_ENTRIES === $r['scan']['entries_scanned'], 'Search stops at its entry budget' );
	$deep = $fixture . '/depth-limit'; mkdir( $deep );
	for ( $i = 0; $i <= Guest_Key_Files::MAX_DEPTH; $i++ ) { $deep .= '/d'; mkdir( $deep ); }
	file_put_contents( $deep . '/deep.txt', 'needle' );
	$r = $call( 'search-files', array( 'path' => $relative . '/depth-limit', 'query' => 'needle' ) );
	$assert( ! $r['complete'] && 1 === $r['scan']['skipped']['depth'], 'Directory-depth omissions are explicit' );
	mkdir( $fixture . '/boolean' ); mkdir( $fixture . '/boolean/child' ); touch( $fixture . '/boolean/child/style.css' );
	$key = Guest_Key_Access::create( $uid );
	if ( is_wp_error( $key ) ) { throw new RuntimeException( $key->get_error_message() ); }
	foreach ( array( 'files' => array( 'path' => $relative . '/clean' ), 'files/read' => array( 'path' => $relative . '/example.php', 'offset' => 6, 'length' => 30 ), 'files/search' => array( 'path' => $relative . '/clean', 'query' => 'needle', 'case_sensitive' => 'true', 'limit' => 1 ) ) as $path => $parameters ) {
		$r = $http( $path, $parameters, $key );
		$assert( 200 === $r['status'], 'Temporary HTTP Basic credentials reach the read-only endpoint: ' . $path );
		$assert( false !== strpos( $r['headers']['cache-control'], 'no-store' ), 'Source inspection responses are not cacheable: ' . $path );
		$options = $http( $path, array(), $key, 'OPTIONS' );
		$assert( 200 === $options['status'] && isset( $options['data']['schema']['properties']['path'] ), 'Native OPTIONS describes filesystem arguments: ' . $path );
	}
	$r = $http( 'files/search', array( 'path' => $relative . '/clean', 'query' => 'NEEDLE', 'case_sensitive' => 'false' ), $key );
	$assert( 200 === $r['status'] && 3 === count( $r['data']['matches'] ), 'GET case_sensitive=false preserves case-insensitive search' );
	foreach ( array( 'false' => 0, 'true' => 1 ) as $recursive => $count ) {
		$r = $http( 'files', array( 'path' => $relative . '/boolean', 'search' => '.css', 'recursive' => $recursive ), $key );
		$assert( 200 === $r['status'] && $count === count( $r['data']['items'] ), 'GET recursive=' . $recursive . ' controls directory traversal' );
	}
	$r = $http( 'files/read', array( 'path' => $relative . '/example.php' ), $key, 'GET', true );
	$assert( 200 === $r['status'] && $source === $r['data']['content'], 'Read-only file routes work with plain-permalink URLs' );
	$r = $http( 'files/read', array( 'path' => $relative . '/example.php' ), null );
	$assert( in_array( $r['status'], array( 401, 403 ), true ), 'Anonymous source access is rejected' );
	$regular = WP_Application_Passwords::create_new_application_password( $uid, array( 'name' => 'Filesystem unrelated credential test' ) );
	$r = $http( 'files/read', array( 'path' => $relative . '/example.php' ), array( 'username' => $key['username'], 'password' => $regular[0] ) );
	$assert( 403 === $r['status'], 'An unrelated administrator application password cannot use Guest Key source endpoints' );
	$r = $http( 'files/read', array( 'path' => $relative . '/example.php' ), $key, 'POST' );
	$assert( in_array( $r['status'], array( 404, 405 ), true ) && $hash === hash_file( 'sha256', $fixture . '/example.php' ), 'Filesystem endpoints expose no write method' );
	get_userdata( $uid )->set_role( 'subscriber' );
	$r = $http( 'files/read', array( 'path' => $relative . '/example.php' ), $key );
	$assert( in_array( $r['status'], array( 401, 403 ), true ), 'Demotion removes filesystem access' );
	get_userdata( $uid )->set_role( 'administrator' );
	$grant = Guest_Key_Access::grant( $uid ); $grant['expires'] = time() - 1; update_user_meta( $uid, Guest_Key_Access::META, $grant );
	$r = $http( 'files/search', array( 'path' => $relative . '/clean', 'query' => 'needle' ), $key );
	$assert( 401 === $r['status'], 'Expired Guest Keys cannot search source files' );
	$assert( ! file_exists( $fixture . '/executed' ) && $hash === hash_file( 'sha256', $fixture . '/example.php' ), 'All source inspection preserved file contents without execution' );
} finally {
	$remove( $fixture ); $remove( $outside );
	Guest_Key_Access::revoke( $uid ); wp_delete_user( $uid ); wp_set_current_user( $owner );
	if ( $active ) { Guest_Key_Dependency::ensure(); } elseif ( is_plugin_active( Guest_Key_Dependency::PLUGIN ) ) { deactivate_plugins( Guest_Key_Dependency::PLUGIN ); }
	if ( null === $managed ) { delete_option( 'guest_key_adapter_managed' ); } else { update_option( 'guest_key_adapter_managed', $managed, false ); }
	$assert( $grants === Guest_Key_Access::grants(), 'Filesystem tests preserve existing access grants' );
}
echo "Completed {$checks} read-only filesystem checks; temporary fixtures removed.\n";
