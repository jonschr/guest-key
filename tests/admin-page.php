<?php
/** wp --user=admin eval-file tests/admin-page.php; no persistent fixtures. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
$owner = get_current_user_id(); $grants = Guest_Key_Access::grants(); $checks = 0; $fixture = array();
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$metadata = static function ( $value, $id, $key ) use ( $owner, &$fixture ) { return $owner === (int) $id && Guest_Key_Access::META === $key ? array( $fixture ) : $value; };
$unavailable = '__return_false';
$render = static function () {
	ob_start();
	try { Guest_Key_Admin::render(); $html = ob_get_contents(); }
	finally { ob_end_clean(); }
	$dom = new DOMDocument();
	$errors = libxml_use_internal_errors( true );
	try { $dom->loadHTML( '<?xml encoding="UTF-8">' . $html ); }
	finally { libxml_clear_errors(); libxml_use_internal_errors( $errors ); }
	return new DOMXPath( $dom );
};
add_filter( 'get_user_metadata', $metadata, 10, 3 );
try {
	$x = $render();
	$assert( 1 === $x->query( '//button[@data-guest-key-create]' )->length, 'The primary action creates and copies connection details' );
	$assert( $x->query( '//button[@data-guest-key-revoke]' )->item( 0 )->hasAttribute( 'hidden' ), 'No-access state hides the revoke action' );
	$assert( 2 === $x->query( '//details' )->length && 0 === $x->query( '//details[@open]' )->length, 'Connection details and inventory are collapsed by default' );
	$assert( false !== strpos( $x->query( '//section[@aria-labelledby="guest-key-connect-title"]' )->item( 0 )->textContent, 'administrator access' ), 'The connection card explains the scope of access' );
	$assert( false !== strpos( $x->query( '//section[@aria-labelledby="guest-key-connect-title"]' )->item( 0 )->textContent, 'paste them into your local agent' ), 'The page explains the handoff to a local agent' );
	$assert( $x->query( '//p[@id="guest-key-grant-status"]' )->item( 0 )->hasAttribute( 'aria-live' ), 'Access status updates are announced to assistive technology' );
	$assert( $x->query( '//details[@id="guest-key-inventory"]//*[@data-guest-key-item]' )->length > 0, 'The collapsed inventory retains registered providers' );
	$fixture = array( 'uuid' => 'page-fixture', 'expires' => time() + HOUR_IN_SECONDS, 'blog_id' => get_current_blog_id(), 'network_id' => get_current_network_id() );
	$x = $render();
	$assert( ! $x->query( '//button[@data-guest-key-revoke]' )->item( 0 )->hasAttribute( 'hidden' ), 'Active access exposes revocation' );
	$assert( false !== strpos( $x->query( '//p[@id="guest-key-grant-status"]' )->item( 0 )->textContent, 'Active until' ), 'Active access displays its expiry' );
	$fixture['expires'] = time() - 1; $x = $render();
	$assert( $x->query( '//button[@data-guest-key-revoke]' )->item( 0 )->hasAttribute( 'hidden' ), 'Expired access is displayed as inactive' );
	$fixture['expires'] = time() + HOUR_IN_SECONDS; $fixture['blog_id'] = get_current_blog_id() + 1; $x = $render();
	$assert( $x->query( '//button[@data-guest-key-revoke]' )->item( 0 )->hasAttribute( 'hidden' ), 'Access issued on another site is not displayed as active here' );
	add_filter( 'wp_is_application_passwords_available_for_user', $unavailable );
	$x = $render(); $button = $x->query( '//button[@data-guest-key-create]' )->item( 0 );
	$assert( $button->hasAttribute( 'disabled' ) && $button->hasAttribute( 'data-guest-key-unavailable' ), 'Unavailable application passwords disable creation across busy-state updates' );
	$assert( false !== strpos( $x->query( '//section[@aria-labelledby="guest-key-connect-title"]' )->item( 0 )->textContent, 'Check HTTPS' ), 'Unavailable access has an actionable explanation' );
} finally {
	remove_filter( 'wp_is_application_passwords_available_for_user', $unavailable );
	remove_filter( 'get_user_metadata', $metadata, 10 );
	$assert( $grants === Guest_Key_Access::grants(), 'Page checks preserve existing access grants' );
}
echo "Completed {$checks} settings-page checks; no persistent fixtures created.\n";
