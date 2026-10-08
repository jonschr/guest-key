<?php
defined( 'ABSPATH' ) || exit;

/** Generic data operations using native APIs and object/field permissions. */
final class Guest_Key_Data_Abilities {

	public static function args( $label, $description, $method, $properties, $required = array( 'action' ) ) {
		$args = Guest_Key_Content_Abilities::args( $label, $description, $method, $properties, $required );
		$args['execute_callback'] = array( __CLASS__, $method );
		return $args;
	}

	public static function value_schema() {
		return array( 'type' => array( 'string', 'number', 'boolean', 'object', 'array', 'null' ), 'description' => 'JSON value matching any registered field schema. Required for writes.' );
	}

	private static function invalid( $message ) { return new WP_Error( 'guest_key_data_invalid', $message ); }
	private static function denied() { return new WP_Error( 'guest_key_data_denied', 'This field is protected or your account cannot perform this operation.' ); }

	private static function valid_key( $key ) {
		// Restrict identifiers so accent-insensitive SQL comparisons cannot alias protected keys.
		return is_string( $key ) && (bool) preg_match( '/^[\x21-\x7e]+$/D', $key );
	}

	private static function protected_key( $kind, $key ) {
		$key = strtolower( $key );
		if ( preg_match( '/^_?guest_key(?:_|$)/', $key ) ) { return true; }
		if ( 'user' === $kind ) {
			return in_array( $key, array( 'session_tokens', '_application_passwords', 'capabilities', 'user_level' ), true ) || preg_match( '/_(?:capabilities|user_level)$/', $key );
		}
		if ( 'option' === $kind ) {
			return in_array( $key, array( 'active_plugins', 'active_sitewide_plugins', 'stylesheet', 'template', 'current_theme', 'cron', 'siteurl', 'home', 'users_can_register', 'default_role', 'admin_email', 'new_admin_email', 'ftp_credentials', 'db_version', 'initial_db_version', 'alloptions', 'notoptions' ), true )
				|| preg_match( '/(?:_user_roles$)|^(?:theme_mods_|_site_transient_|_transient_)/', $key );
		}
		return false;
	}

	private static function field_schema( $args ) {
		$rest = is_array( $args['show_in_rest'] ?? false ) ? ( $args['show_in_rest']['schema'] ?? array() ) : array();
		return array_merge( array( 'type' => $args['type'] ?? 'string' ), $rest );
	}

	private static function registered_key( $key, $registered ) {
		// SQL key comparisons can be case-insensitive; use the registered key for auth/schema checks.
		if ( isset( $registered[ $key ] ) ) { return $key; }
		foreach ( $registered as $name => $args ) { if ( 0 === strcasecmp( $name, $key ) ) { return $name; } }
		return $key;
	}

	public static function options( $input ) {
		rest_get_server();
		$settings = get_registered_settings();
		$action = $input['action'];
		$key = $input['key'] ?? '';
		if ( 'describe' === $action ) {
			$fields = array();
			foreach ( $settings as $name => $args ) {
				if ( ( $key && $key !== $name ) || self::protected_key( 'option', $name ) ) { continue; }
				$fields[ $name ] = array( 'schema' => self::field_schema( $args ), 'group' => $args['group'], 'can_edit' => current_user_can( apply_filters( 'option_page_capability_' . $args['group'], 'manage_options' ) ) );
			}
			return $fields;
		}
		if ( ! self::valid_key( $key ) ) { return self::invalid( 'Provide an exact, nonempty ASCII option key without whitespace.' ); }
		if ( self::protected_key( 'option', $key ) ) { return self::denied(); }
		$key = self::registered_key( $key, $settings );
		$args = $settings[ $key ] ?? array();
		$cap = $args ? apply_filters( 'option_page_capability_' . $args['group'], 'manage_options' ) : 'manage_options';
		if ( ! current_user_can( $cap ) ) { return self::denied(); }
		$missing = new stdClass();
		if ( 'get' === $action ) { $value = get_option( $key, $missing ); return array( 'key' => $key, 'exists' => $value !== $missing, 'value' => $value === $missing ? null : $value ); }
		$schema = $args ? self::field_schema( $args ) : array();
		if ( ! empty( $schema['readonly'] ) ) { return self::denied(); }
		if ( 'update' === $action ) {
			if ( ! array_key_exists( 'value', $input ) ) { return self::invalid( 'Provide a value to update.' ); }
			if ( $args ) { $valid = rest_validate_value_from_schema( $input['value'], $schema, $key ); if ( is_wp_error( $valid ) ) { return $valid; } }
			$changed = get_option( $key, $missing ) === $missing ? add_option( $key, $input['value'], '', false ) : update_option( $key, $input['value'] );
		} else { $changed = delete_option( $key ); }
		$value = get_option( $key, $missing );
		if ( 'update' === $action && $value === $missing ) { return self::invalid( 'WordPress could not save the option.' ); }
		return array( 'key' => $key, 'changed' => (bool) $changed, 'exists' => $value !== $missing, 'value' => $value === $missing ? null : $value );
	}

	public static function theme_mods( $input ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) { return self::denied(); }
		$action = $input['action'];
		$mods = get_theme_mods() ?: array();
		if ( 'list' === $action ) { return array( 'stylesheet' => get_stylesheet(), 'keys' => array_values( array_filter( array_keys( $mods ), static function ( $key ) { return ! self::protected_key( 'theme', $key ); } ) ) ); }
		$key = $input['key'] ?? '';
		if ( ! self::valid_key( $key ) ) { return self::invalid( 'Provide an exact, nonempty ASCII theme modification key without whitespace.' ); }
		if ( self::protected_key( 'theme', $key ) ) { return self::denied(); }
		$before = $mods[ $key ] ?? null;
		$had = array_key_exists( $key, $mods );
		if ( 'get' === $action ) { return array( 'key' => $key, 'exists' => $had, 'value' => get_theme_mod( $key, null ) ); }
		if ( 'update' === $action ) {
			if ( ! array_key_exists( 'value', $input ) ) { return self::invalid( 'Provide a value to update.' ); }
			set_theme_mod( $key, $input['value'] );
		} else { remove_theme_mod( $key ); }
		$mods = get_theme_mods() ?: array();
		$exists = array_key_exists( $key, $mods );
		if ( 'update' === $action && ! $exists ) { return self::invalid( 'WordPress could not save the theme modification.' ); }
		return array( 'key' => $key, 'changed' => $had !== $exists || $before !== ( $mods[ $key ] ?? null ), 'exists' => $exists, 'value' => get_theme_mod( $key, null ) );
	}

	public static function user_meta( $input ) { return self::metadata( 'user', $input ); }
	public static function term_meta( $input ) { return self::metadata( 'term', $input ); }

	private static function metadata( $type, $input ) {
		$id = $input[ $type . '_id' ] ?? 0;
		$object = 'user' === $type ? get_userdata( $id ) : get_term( $id );
		if ( ! $object || is_wp_error( $object ) ) { return self::invalid( 'Provide an existing ' . $type . ' ID.' ); }
		if ( ! current_user_can( 'edit_' . $type, $id ) ) { return self::denied(); }
		$subtype = get_object_subtype( $type, $id );
		$registered = array_merge( get_registered_meta_keys( $type ), get_registered_meta_keys( $type, $subtype ) );
		$action = $input['action'];
		$key = $input['key'] ?? '';
		if ( 'describe' === $action ) {
			$fields = array();
			foreach ( $registered as $name => $args ) {
				if ( ( $key && $key !== $name ) || self::protected_key( $type, $name ) ) { continue; }
				unset( $args['auth_callback'], $args['sanitize_callback'] );
				$fields[ $name ] = array( 'registration' => $args, 'protected' => is_protected_meta( $name, $type ), 'can_edit' => current_user_can( 'edit_' . $type . '_meta', $id, $name ) );
			}
			return $fields;
		}
		if ( 'list' === $action ) {
			$keys = array_filter( array_keys( get_metadata( $type, $id ) ), static function ( $name ) use ( $type, $id, $registered ) { return ! self::protected_key( $type, $name ) && current_user_can( 'edit_' . $type . '_meta', $id, self::registered_key( $name, $registered ) ); } );
			return array( 'keys' => array_values( $keys ) );
		}
		if ( ! self::valid_key( $key ) ) { return self::invalid( 'Provide an exact, nonempty ASCII metadata key without whitespace.' ); }
		if ( self::protected_key( $type, $key ) ) { return self::denied(); }
		$key = self::registered_key( $key, $registered );
		$cap = ( 'delete' === $action ? 'delete' : ( 'add' === $action ? 'add' : 'edit' ) ) . '_' . $type . '_meta';
		if ( ! current_user_can( $cap, $id, $key ) ) { return self::denied(); }
		if ( 'get' === $action ) { return array( 'key' => $key, 'exists' => metadata_exists( $type, $id, $key ), 'values' => get_metadata( $type, $id, $key, false ) ); }
		$args = $registered[ $key ] ?? array();
		$schema = $args ? self::field_schema( $args ) : array();
		if ( ! empty( $schema['readonly'] ) ) { return self::denied(); }
		if ( 'delete' === $action ) {
			if ( array_key_exists( 'value', $input ) && in_array( $input['value'], array( '', null, false ), true ) ) { return self::invalid( 'Omit value to delete all values, or provide a nonempty exact-value filter.' ); }
			$changed = delete_metadata( $type, $id, wp_slash( $key ), wp_slash( $input['value'] ?? '' ) );
		} else {
			if ( ! array_key_exists( 'value', $input ) ) { return self::invalid( 'Provide a value to add or update.' ); }
			if ( $args ) { $valid = rest_validate_value_from_schema( $input['value'], $schema, $key ); if ( is_wp_error( $valid ) ) { return $valid; } }
			$changed = 'add' === $action ? add_metadata( $type, $id, wp_slash( $key ), wp_slash( $input['value'] ), ! empty( $input['unique'] ) || ! empty( $args['single'] ) ) : update_metadata( $type, $id, wp_slash( $key ), wp_slash( $input['value'] ) );
			if ( ! $changed && ( 'add' === $action || ! metadata_exists( $type, $id, $key ) ) ) { return self::invalid( 'WordPress could not save the metadata; a unique key may already exist.' ); }
		}
		return array( 'key' => $key, 'changed' => (bool) $changed, 'exists' => metadata_exists( $type, $id, $key ), 'values' => get_metadata( $type, $id, $key, false ) );
	}
}
