<?php
defined( 'ABSPATH' ) || exit;

/** Selectively remove native sessions without storing plaintext session tokens. */
final class Guest_Key_Browser_Sessions extends WP_User_Meta_Session_Tokens {

	public function __construct( $user_id ) {
		parent::__construct( $user_id );
	}

	public function revoke() {
		foreach ( $this->get_sessions() as $verifier => $session ) {
			if ( ! empty( $session[ Guest_Key_Browser::SESSION ] ) ) {
				$this->update_session( $verifier, null );
			}
		}
	}
}
