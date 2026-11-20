<?php
defined( 'ABSPATH' ) || exit;

/** ACF form saves for existing posts, independent of public REST exposure. */
final class Guest_Key_ACF_Abilities {
	private static function error( $code, $message, $post_id, $key = null, $extra = array() ) {
		return new WP_Error( 'guest_key_acf_' . $code, $message, array_merge( array( 'post_id' => $post_id, 'field_key' => $key ), $extra ) );
	}

	public static function fields( $input ) {
		$id = (int) $input['post_id'];
		$key = $input['field_key'] ?? null;
		if ( ! Guest_Key_Access::administrator() || ! current_user_can( 'edit_post', $id ) ) {
			return self::error( 'denied', 'Your account cannot edit this post.', $id, $key );
		}
		$post = get_post( $id );
		if ( ! $post || wp_is_post_revision( $id ) || 'attachment' === $post->post_type || 0 === strpos( $post->post_type, 'acf-' ) ) {
			return self::error( 'invalid', 'Provide an existing content post, excluding revisions, attachments and ACF definitions.', $id, $key );
		}
		if ( ! function_exists( 'acf_filter_save_values' ) || ! function_exists( 'acf_validate_save_post' ) || ! function_exists( 'acf_save_post' ) || version_compare( acf_get_setting( 'version' ), '6.8.11', '<' ) ) {
			return self::error( 'unavailable', 'This pathway requires active ACF 6.8.11 or later with its form-save APIs.', $id, $key );
		}
		// ACF's form hooks consume request and form state. Isolate each command in a batch.
		$previous_post = $_POST;
		$previous_request = $_REQUEST;
		$form = acf_get_store( 'form' )->get();
		$errors = acf()->validation->errors;
		try {
			$_POST = array( 'post_ID' => $id, 'post_id' => $id, 'post_type' => $post->post_type, '_acf_post_id' => $id, '_acf_screen' => 'post', 'acf' => array() );
			$_REQUEST = $_POST;
			acf_set_form_data( array( 'post_id' => $id, 'screen' => 'post' ) );
			acf_reset_validation_errors();
			$fields = array();
			$screen = array( 'post_id' => $id, 'post_type' => $post->post_type );
			foreach ( acf_get_field_groups( $screen ) as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $field ) {
					$field['value'] = acf_get_value( $id, $field );
					$prepared = acf_prepare_field( $field );
					if ( ! $prepared ) { continue; }
					$reason = '';
					if ( $field['key'] !== $prepared['key'] || 'text' !== $field['type'] || 'text' !== $prepared['type'] || ! empty( $prepared['conditional_logic'] ) ) {
						$reason = 'Only top-level text fields without conditional logic are supported. Use the WordPress editor for this field.';
					} elseif ( ! empty( $prepared['readonly'] ) || ! empty( $prepared['disabled'] ) ) {
						$reason = 'ACF marks this field readonly or disabled for this context.';
					}
					$fields[ $field['key'] ] = array( 'field_key' => $field['key'], 'name' => $field['name'], 'label' => $prepared['label'], 'type' => $prepared['type'],
						'group_key' => $group['key'], 'required' => (bool) $prepared['required'], 'maxlength' => $prepared['maxlength'] ?? null,
						'can_edit' => ! $reason, 'restriction' => $reason );
				}
			}
			$args = array( 'context' => 'post', 'post_id' => $id, 'screen' => $screen, 'lists' => array(), 'render_ids' => array() );
			$allowed = acf_filter_save_values( array_fill_keys( array_keys( $fields ), '' ), $args );
			$fields = array_intersect_key( $fields, $allowed );
			if ( 'list' === $input['action'] ) { return array_values( $fields ); }
			if ( ! $key || ! acf_get_field( $key ) ) { return self::error( 'unknown_field', 'Provide an existing stable ACF field_key from post acf list.', $id, $key ); }
			if ( ! isset( $fields[ $key ] ) ) { return self::error( 'denied', 'This field is hidden, disallowed, or does not belong to an applicable field group.', $id, $key ); }
			$field = $fields[ $key ];
			if ( ! $field['can_edit'] ) { return self::error( 'unsupported_field', $field['restriction'], $id, $key ); }
			if ( 'get' === $input['action'] ) {
				return array_merge( $field, array( 'exists' => metadata_exists( 'post', $id, $field['name'] ), 'value' => get_field( $key, $id, false ) ) );
			}
			if ( ! array_key_exists( 'value', $input ) || ! is_string( $input['value'] ) ) { return self::error( 'invalid', 'Text fields require a string value.', $id, $key ); }
			// Match ACF form input: WP-slash for native metadata storage; hooks see the same form shape.
			$_POST['acf'] = wp_slash( array( $key => $input['value'] ) );
			$_REQUEST = $_POST;
			if ( ! acf_validate_save_post() ) {
				return self::error( 'validation', 'ACF validation rejected the field; no save was attempted.', $id, $key, array( 'errors' => acf_get_validation_errors() ) );
			}
			if ( 'validate' === $input['action'] ) { return array( 'post_id' => $id, 'field_key' => $key, 'valid' => true ); }
			// Hooks may change access during validation. Recheck native object and ACF save restrictions.
			if ( ! current_user_can( 'edit_post', $id ) || ! acf_filter_save_values( array( $key => '' ), $args ) ) { return self::error( 'denied', 'Access changed during validation.', $id, $key ); }
			$current_field = acf_get_field( $key );
			$current_field['value'] = acf_get_value( $id, $current_field );
			$prepared = acf_prepare_field( $current_field );
			if ( ! $prepared || ! empty( $prepared['readonly'] ) || ! empty( $prepared['disabled'] ) || ! empty( $prepared['conditional_logic'] ) || 'text' !== $prepared['type'] || $key !== $prepared['key'] ) { return self::error( 'denied', 'Field access changed during validation.', $id, $key ); }
			$expected = null;
			$observe = static function ( $check, $post_id, $name, $value ) use ( &$expected, $id, $field ) {
				if ( (int) $post_id === $id && $name === $field['name'] ) { $expected = $value; }
				return $check;
			};
			add_filter( 'update_post_metadata', $observe, PHP_INT_MAX, 4 );
			try { $accepted = acf_save_post( $id ); }
			finally { remove_filter( 'update_post_metadata', $observe, PHP_INT_MAX ); }
			// ACF dispatches hooks without reporting storage failures. Verify the resulting value and reference.
			if ( ! $accepted || ! is_string( $expected ) || get_post_meta( $id, $field['name'], true ) !== $expected || get_post_meta( $id, '_' . $field['name'], true ) !== $key ) {
				return self::error( 'save', 'ACF did not persist the expected text value and reference. Inspect current values before retrying; save hooks may have made changes.', $id, $key,
					array( 'value' => get_post_meta( $id, $field['name'], true ), 'reference' => get_post_meta( $id, '_' . $field['name'], true ) ) );
			}
			acf_flush_value_cache( $id, $field['name'] );
			return array( 'post_id' => $id, 'field_key' => $key, 'value' => get_field( $key, $id, false ), 'exists' => metadata_exists( 'post', $id, $field['name'] ) );
		} finally {
			$_POST = $previous_post;
			$_REQUEST = $previous_request;
			acf_get_store( 'form' )->reset();
			acf_get_store( 'form' )->set( $form );
			acf()->validation->errors = $errors;
		}
	}
}
