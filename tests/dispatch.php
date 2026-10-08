<?php
/** wp --user=admin eval-file tests/dispatch.php; routes exist only in this process. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
rest_get_server();
$path = '/wp/v2/posts/gk-dispatch-fixture';
register_rest_route( 'wp/v2', '/posts/gk-dispatch-fixture', array( 'methods' => array( 'GET', 'POST', 'OPTIONS' ), 'permission_callback' => '__return_true', 'callback' => static function ( $r ) {
	$response = new WP_REST_Response( array( 'id' => 7, 'title' => array( 'raw' => 'Example', 'rendered' => '<b>Example</b>' ), 'large' => 'omit', 'received' => $r->get_params() ) );
	$response->header( 'X-WP-Total', 42 ); $response->header( 'X-WP-TotalPages', 21 ); return $response;
} ) );
$params = array( 'search' => 'needle', 'page' => 2, 'per_page' => 2, 'context' => 'edit' );
foreach ( array( 'guest-key/request', 'guest-key/manage-content' ) as $name ) {
	$r = wp_get_ability( $name )->execute( array( 'path' => $path, 'parameters' => $params ) );
	$assert( ! is_wp_error( $r ) && $params === $r['data']['received'], 'GET filters and edit context reach the inner request: ' . $name );
	$assert( 42 === $r['total'] && 21 === $r['total_pages'], 'Pagination headers survive response processing: ' . $name );
	$r = wp_get_ability( $name )->execute( array( 'path' => $path, 'parameters' => array( '_fields' => 'id,title.raw' ) ) );
	$assert( ! is_wp_error( $r ) && array( 'id' => 7, 'title' => array( 'raw' => 'Example' ) ) === $r['data'], 'Nested field selection removes sibling and unrelated payloads: ' . $name );
	$r = wp_get_ability( $name )->execute( array( 'method' => 'POST', 'path' => $path, 'parameters' => array( '_fields' => 'title.raw' ) ) );
	$assert( ! is_wp_error( $r ) && array( 'title' => array( 'raw' => 'Example' ) ) === $r['data'], 'Write responses also honor nested field selection: ' . $name );
}
$observed = null;
$capture = static function ( $response, $server, $request ) use ( &$observed, $path ) { if ( 'OPTIONS' === $request->get_method() && $path === $request->get_route() ) { $observed = $request->get_params(); } return $response; };
add_filter( 'rest_pre_dispatch', $capture, 10, 3 );
try { $r = wp_get_ability( 'guest-key/request' )->execute( array( 'method' => 'OPTIONS', 'path' => $path, 'parameters' => $params ) ); }
finally { remove_filter( 'rest_pre_dispatch', $capture ); }
$assert( ! is_wp_error( $r ) && $params === $observed && isset( $r['data']['endpoints'] ), 'OPTIONS arguments reach native discovery processing' );
$r = wp_get_ability( 'guest-key/list-plugins' )->execute( array( 'search' => 'gk-no-match-' . wp_generate_password( 12, false ) ) );
$assert( array() === $r, 'Plugin list helper honors a search with no matches' );
$r = wp_get_ability( 'guest-key/request' )->execute( array( 'path' => '/wp/v2/posts', 'parameters' => array( 'per_page' => 101 ) ) );
$assert( is_wp_error( $r ), 'Native GET validation rejects invalid pagination rather than ignoring it' );
foreach ( array( 'help', 'run' ) as $name ) {
	$r = new WP_REST_Request( 'OPTIONS', '/guest-key/v1/' . $name ); $response = rest_do_request( $r )->get_data();
	$schema = wp_get_ability( 'guest-key/' . $name )->get_input_schema();
	$assert( $schema === $response['schema'], 'OPTIONS publishes the same input schema as the ability: ' . $name );
	$assert( array_keys( $schema['properties'] ) === array_keys( $response['endpoints'][0]['args'] ), 'OPTIONS describes command arguments: ' . $name );
}
$r = new WP_REST_Request( 'POST', '/guest-key/v1/run' );
$response = rest_do_request( $r );
$assert( 400 === $response->get_status() && 'rest_missing_callback_param' === $response->get_data()['code'], 'REST command discovery also enforces required arguments' );
echo "Completed {$checks} REST dispatch checks.\n";
