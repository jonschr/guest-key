<?php
defined( 'ABSPATH' ) || exit;

/** Native content operations for registered types and fields, independent of REST exposure. */
final class Guest_Key_Content_Abilities {

	public static function args( $label, $description, $method, $properties, $required = array( 'action' ) ) {
		return array(
			'label' => $label, 'description' => $description, 'category' => 'site',
			'input_schema' => array( 'type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false ),
			'execute_callback' => array( __CLASS__, $method ),
			'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
			'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
		);
	}

	private static function denied() {
		return new WP_Error( 'guest_key_content_denied', 'Your account cannot perform this operation on this content or field.' );
	}

	private static function invalid( $message ) {
		return new WP_Error( 'guest_key_content_invalid', $message );
	}

	public static function posts( $input ) {
		$action = $input['action'];
		if ( 'describe' === $action ) {
			if ( isset( $input['post_type'] ) && ! post_type_exists( $input['post_type'] ) ) { return self::invalid( 'Unknown post type.' ); }
			$types = array();
			foreach ( get_post_types( array(), 'objects' ) as $type ) {
				if ( isset( $input['post_type'] ) && $input['post_type'] !== $type->name ) { continue; }
				$types[] = array( 'name' => $type->name, 'label' => $type->label, 'hierarchical' => $type->hierarchical,
					'show_in_rest' => $type->show_in_rest, 'rest_namespace' => $type->rest_namespace ?: 'wp/v2', 'rest_base' => $type->rest_base ?: $type->name,
					'supports' => get_all_post_type_supports( $type->name ), 'taxonomies' => get_object_taxonomies( $type->name ), 'capabilities' => (array) $type->cap,
					'can_create' => current_user_can( $type->cap->create_posts ), 'can_edit' => current_user_can( $type->cap->edit_posts ) );
			}
			return $types;
		}
		$post = null;
		if ( ! in_array( $action, array( 'list', 'create' ), true ) ) {
			if ( empty( $input['id'] ) ) { return self::invalid( 'Provide an existing post ID.' ); }
			$post = get_post( $input['id'] ?? 0 );
			if ( ! $post || ( isset( $input['post_type'] ) && $input['post_type'] !== $post->post_type ) ) { return self::invalid( 'Provide an existing post ID matching the post type.' ); }
		}
		$type = get_post_type_object( $post ? $post->post_type : ( $input['post_type'] ?? 'post' ) );
		if ( ! $type ) { return self::invalid( 'Unknown post type.' ); }
		if ( 'list' === $action ) {
			if ( ! current_user_can( $type->cap->edit_posts ) ) { return self::denied(); }
			$status = $input['status'] ?? 'any';
			if ( 'any' !== $status && ! get_post_status_object( $status ) ) { return self::invalid( 'Unknown post status.' ); }
			$args = array( 'post_type' => $type->name, 'post_status' => $status, 'posts_per_page' => $input['per_page'] ?? 20,
				'paged' => $input['page'] ?? 1, 's' => $input['search'] ?? '', 'orderby' => 'ID', 'order' => 'ASC' );
			if ( ! current_user_can( $type->cap->edit_others_posts ) ) { $args['author'] = get_current_user_id(); }
			$query = new WP_Query( $args );
			$items = array();
			foreach ( $query->posts as $item ) {
				if ( current_user_can( 'edit_post', $item->ID ) ) { $items[] = get_post( $item->ID, ARRAY_A ); }
			}
			return array( 'items' => $items, 'total' => (int) $query->found_posts, 'total_pages' => (int) $query->max_num_pages );
		}
		$cap = 'create' === $action ? $type->cap->create_posts : ( in_array( $action, array( 'trash', 'delete' ), true ) ? 'delete_post' : 'edit_post' );
		if ( ! current_user_can( $cap, $post ? $post->ID : 0 ) ) { return self::denied(); }
		if ( 'get' === $action ) { return get_post( $post->ID, ARRAY_A ); }
		if ( in_array( $action, array( 'trash', 'restore', 'delete' ), true ) ) {
			if ( 'delete' === $action && empty( $input['force'] ) ) { return self::invalid( 'Permanent deletion requires force=true. Use trash for reversible removal.' ); }
			if ( 'trash' === $action && ! EMPTY_TRASH_DAYS ) { return self::invalid( 'Trash is disabled on this site. Explicit permanent deletion is required.' ); }
			if ( 'restore' === $action && 'trash' !== $post->post_status ) { return self::invalid( 'Only trashed posts can be restored.' ); }
			$result = 'delete' === $action ? wp_delete_post( $post->ID, true ) : ( 'trash' === $action ? wp_trash_post( $post->ID ) : wp_untrash_post( $post->ID ) );
			if ( ! $result ) { return self::invalid( 'WordPress could not complete the post operation.' ); }
			return array( 'id' => $post->ID, 'action' => $action, 'post' => 'delete' === $action ? null : get_post( $post->ID, ARRAY_A ) );
		}
		$fields = $input['fields'] ?? array();
		if ( ! $fields ) { return self::invalid( 'Provide fields to create or update.' ); }
		$status = $fields['status'] ?? ( $post ? $post->post_status : 'draft' );
		if ( ! get_post_status_object( $status ) || in_array( $status, array( 'trash', 'auto-draft' ), true ) ) { return self::invalid( 'Provide a registered editable post status.' ); }
		$status_object = get_post_status_object( $status );
		if ( ( ! $post || $status !== $post->post_status ) && ( $status_object->public || $status_object->private || 'future' === $status ) && ! current_user_can( $type->cap->publish_posts ) ) { return self::denied(); }
		if ( isset( $fields['author'] ) && ( ! get_user_by( 'id', $fields['author'] ) || ( $fields['author'] !== get_current_user_id() && ! current_user_can( $type->cap->edit_others_posts ) ) ) ) { return self::denied(); }
		if ( ! empty( $fields['parent'] ) ) {
			$parent = get_post( $fields['parent'] );
			if ( ! $parent || $parent->post_type !== $type->name || ! current_user_can( 'edit_post', $parent->ID ) ) { return self::invalid( 'Parent must be an editable post of the same type.' ); }
		}
		$map = array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'slug' => 'post_name', 'author' => 'post_author',
			'parent' => 'post_parent', 'menu_order' => 'menu_order', 'password' => 'post_password', 'comment_status' => 'comment_status', 'ping_status' => 'ping_status' );
		$data = array( 'post_type' => $type->name, 'post_status' => $status );
		foreach ( $map as $field => $native ) { if ( array_key_exists( $field, $fields ) ) { $data[ $native ] = $fields[ $field ]; } }
		if ( isset( $fields['date'] ) ) {
			$timestamp = strtotime( $fields['date'] );
			if ( false === $timestamp ) { return self::invalid( 'Provide a valid ISO 8601 date.' ); }
			$data['post_date'] = wp_date( 'Y-m-d H:i:s', $timestamp );
			$data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp );
		}
		if ( $post ) { $data['ID'] = $post->ID; }
		$id = $post ? wp_update_post( wp_slash( $data ), true ) : wp_insert_post( wp_slash( $data ), true );
		return is_wp_error( $id ) ? $id : get_post( $id, ARRAY_A );
	}

	public static function terms( $input ) {
		$action = $input['action'];
		if ( 'describe' === $action ) {
			if ( isset( $input['taxonomy'] ) && ! taxonomy_exists( $input['taxonomy'] ) ) { return self::invalid( 'Unknown taxonomy.' ); }
			$taxonomies = array();
			foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
				if ( isset( $input['taxonomy'] ) && $input['taxonomy'] !== $taxonomy->name ) { continue; }
				$taxonomies[] = array( 'name' => $taxonomy->name, 'label' => $taxonomy->label, 'hierarchical' => $taxonomy->hierarchical,
					'object_types' => $taxonomy->object_type, 'show_in_rest' => $taxonomy->show_in_rest, 'rest_namespace' => $taxonomy->rest_namespace ?: 'wp/v2',
					'rest_base' => $taxonomy->rest_base ?: $taxonomy->name, 'capabilities' => (array) $taxonomy->cap,
					'can_manage' => current_user_can( $taxonomy->cap->manage_terms ), 'can_assign' => current_user_can( $taxonomy->cap->assign_terms ) );
			}
			return $taxonomies;
		}
		$taxonomy = get_taxonomy( $input['taxonomy'] ?? '' );
		if ( ! $taxonomy ) { return self::invalid( 'Provide a registered taxonomy.' ); }
		if ( 'assign' === $action ) {
			$post = get_post( $input['post_id'] ?? 0 );
			if ( ! $post || ! is_object_in_taxonomy( $post->post_type, $taxonomy->name ) || ! array_key_exists( 'term_ids', $input ) ) { return self::invalid( 'Provide a post using this taxonomy and an array of term IDs.' ); }
			if ( ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( $taxonomy->cap->assign_terms ) ) { return self::denied(); }
			foreach ( $input['term_ids'] as $id ) {
				$term = get_term( $id, $taxonomy->name );
				if ( ! $term || is_wp_error( $term ) ) { return self::invalid( 'Every term ID must exist in the requested taxonomy.' ); }
				if ( ! current_user_can( 'assign_term', $id ) ) { return self::denied(); }
			}
			$result = wp_set_object_terms( $post->ID, array_map( 'intval', $input['term_ids'] ), $taxonomy->name, ! empty( $input['append'] ) );
			return is_wp_error( $result ) ? $result : wp_get_object_terms( $post->ID, $taxonomy->name );
		}
		if ( 'list' === $action ) {
			if ( ! current_user_can( $taxonomy->cap->assign_terms ) && ! current_user_can( $taxonomy->cap->manage_terms ) ) { return self::denied(); }
			$args = array( 'taxonomy' => $taxonomy->name, 'hide_empty' => $input['hide_empty'] ?? false, 'search' => $input['search'] ?? '' );
			$total = wp_count_terms( $args );
			if ( is_wp_error( $total ) ) { return $total; }
			$args['number'] = $input['per_page'] ?? 20;
			$args['offset'] = ( ( $input['page'] ?? 1 ) - 1 ) * $args['number'];
			$args['orderby'] = 'term_id';
			$args['order'] = 'ASC';
			$items = get_terms( $args );
			return is_wp_error( $items ) ? $items : array( 'items' => $items, 'total' => (int) $total, 'total_pages' => (int) ceil( $total / $args['number'] ) );
		}
		$term = null;
		if ( 'create' !== $action ) {
			$term = get_term( $input['id'] ?? 0, $taxonomy->name );
			if ( ! $term || is_wp_error( $term ) ) { return self::invalid( 'Provide an existing term ID in this taxonomy.' ); }
		}
		if ( 'get' === $action ) {
			return current_user_can( $taxonomy->cap->assign_terms ) || current_user_can( $taxonomy->cap->manage_terms ) ? $term : self::denied();
		}
		$cap = 'create' === $action ? $taxonomy->cap->edit_terms : ( 'delete' === $action ? 'delete_term' : 'edit_term' );
		if ( ! current_user_can( $cap, $term ? $term->term_id : 0 ) ) { return self::denied(); }
		if ( 'delete' === $action ) {
			$result = wp_delete_term( $term->term_id, $taxonomy->name );
			return is_wp_error( $result ) ? $result : ( $result ? array( 'deleted' => true, 'id' => $term->term_id ) : self::invalid( 'WordPress did not delete the term; it may be a protected default term.' ) );
		}
		$fields = $input['fields'] ?? array();
		if ( ! $fields || ( 'create' === $action && empty( $fields['name'] ) ) ) { return self::invalid( 'Provide fields; creating a term requires a name.' ); }
		if ( ! empty( $fields['parent'] ) ) {
			$parent = get_term( $fields['parent'], $taxonomy->name );
			if ( ! $taxonomy->hierarchical || ! $parent || is_wp_error( $parent ) ) { return self::invalid( 'Parent must exist in the same hierarchical taxonomy.' ); }
		}
		$result = $term ? wp_update_term( $term->term_id, $taxonomy->name, wp_slash( $fields ) ) : wp_insert_term( wp_slash( $fields['name'] ), $taxonomy->name, wp_slash( $fields ) );
		return is_wp_error( $result ) ? $result : get_term( $result['term_id'], $taxonomy->name );
	}

	public static function post_meta( $input ) {
		$action = $input['action'];
		// Native post-meta mutations redirect revision IDs to the parent. Resolve
		// first so authorization and registered schemas use the actual target.
		$post = empty( $input['post_id'] ) ? null : get_post( wp_is_post_revision( $input['post_id'] ) ?: $input['post_id'] );
		if ( ! $post && ( 'describe' !== $action || empty( $input['post_type'] ) || ! empty( $input['post_id'] ) ) ) { return self::invalid( 'Provide an existing post ID, or post_type for describe.' ); }
		$type = get_post_type_object( $post ? $post->post_type : $input['post_type'] );
		if ( ! $type || ( $post && isset( $input['post_type'] ) && $input['post_type'] !== $type->name ) ) { return self::invalid( 'Provide a matching registered post type.' ); }
		if ( ! current_user_can( $post ? 'edit_post' : $type->cap->edit_posts, $post ? $post->ID : 0 ) ) { return self::denied(); }
		$registered = array_merge( get_registered_meta_keys( 'post' ), get_registered_meta_keys( 'post', $type->name ) );
		$key = $input['key'] ?? '';
		if ( 'describe' === $action ) {
			$fields = array();
			foreach ( $registered as $name => $args ) {
				if ( $key && $key !== $name ) { continue; }
				unset( $args['auth_callback'], $args['sanitize_callback'] );
				$fields[ $name ] = array( 'registration' => $args, 'protected' => is_protected_meta( $name, 'post' ) );
				if ( $post ) { $fields[ $name ]['can_edit'] = current_user_can( 'edit_post_meta', $post->ID, $name ); }
			}
			return $fields;
		}
		if ( 'list' === $action ) {
			$values = array();
			foreach ( get_post_meta( $post->ID ) as $name => $unused ) {
				if ( current_user_can( 'edit_post_meta', $post->ID, $name ) ) { $values[ $name ] = get_post_meta( $post->ID, $name, false ); }
			}
			return $values;
		}
		if ( ! $key ) { return self::invalid( 'Provide a nonempty meta key.' ); }
		$cap = 'delete' === $action ? 'delete_post_meta' : ( 'add' === $action ? 'add_post_meta' : 'edit_post_meta' );
		if ( ! current_user_can( $cap, $post->ID, $key ) ) { return self::denied(); }
		if ( 'get' === $action ) { return array( 'key' => $key, 'exists' => metadata_exists( 'post', $post->ID, $key ), 'values' => get_post_meta( $post->ID, $key, false ) ); }
		if ( in_array( $action, array( 'add', 'update' ), true ) ) {
			if ( ! array_key_exists( 'value', $input ) ) { return self::invalid( 'Provide a value to add or update.' ); }
		}
		$valid = self::validate_meta( $type->name, $key, $input['value'] ?? null, 'delete' !== $action );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$args = $registered[ $key ] ?? array();
		if ( in_array( $action, array( 'add', 'update' ), true ) ) {
			$value = wp_slash( $input['value'] );
			if ( 'add' === $action ) {
				$result = add_post_meta( $post->ID, wp_slash( $key ), $value, ! empty( $input['unique'] ) || ! empty( $args['single'] ) );
				if ( ! $result ) { return self::invalid( 'WordPress could not add the value; the key may already exist.' ); }
			} else { $result = update_post_meta( $post->ID, wp_slash( $key ), $value ); }
		} else {
			if ( array_key_exists( 'value', $input ) && in_array( $input['value'], array( '', null, false ), true ) ) { return self::invalid( 'WordPress treats an empty-string, null, or false deletion filter as all values. Omit value to delete all; otherwise provide a nonempty filter.' ); }
			// Native metadata APIs unslash their inputs. Preserve quoted/structured
			// values; an omitted filter requests all rows for this post/key.
			$result = delete_post_meta( $post->ID, wp_slash( $key ), wp_slash( $input['value'] ?? '' ) );
		}
		return array( 'key' => $key, 'changed' => (bool) $result, 'exists' => metadata_exists( 'post', $post->ID, $key ), 'values' => get_post_meta( $post->ID, $key, false ) );
	}

	private static function validate_meta( $post_type, $key, $value, $write = true ) {
		$registered = array_merge( get_registered_meta_keys( 'post' ), get_registered_meta_keys( 'post', $post_type ) );
		$args = $registered[ $key ] ?? array();
		$schema = is_array( $args['show_in_rest'] ?? false ) ? ( $args['show_in_rest']['schema'] ?? array() ) : array();
		if ( ! empty( $schema['readonly'] ) ) { return self::denied(); }
		return $write && $args ? rest_validate_value_from_schema( $value, array_merge( array( 'type' => $args['type'] ), $schema ), $key ) : true;
	}

	public static function save_post( $input ) {
		$post = isset( $input['id'] ) ? get_post( $input['id'] ) : null;
		if ( isset( $input['id'] ) && ( ! $post || wp_is_post_revision( $post->ID ) ) ) { return self::invalid( 'Provide an existing, non-revision post ID.' ); }
		$type = get_post_type_object( $post ? $post->post_type : ( $input['post_type'] ?? 'post' ) );
		if ( ! $type || ( isset( $input['post_type'] ) && $type->name !== $input['post_type'] ) ) { return self::invalid( 'Provide a matching registered post type.' ); }
		if ( ! current_user_can( $post ? 'edit_post' : $type->cap->create_posts, $post ? $post->ID : 0 ) ) { return self::denied(); }
		$fields = $input['fields'] ?? array();
		if ( ! $fields && ! $post ) { return self::invalid( 'Creating a post requires fields.' ); }
		// Validate known failures before creating/updating the post. Object-specific
		// meta permissions are checked again once a newly-created post has an ID.
		foreach ( $input['meta'] ?? array() as $key => $value ) {
			if ( '' === (string) $key ) { return self::invalid( 'Meta keys must be nonempty.' ); }
			$valid = self::validate_meta( $type->name, $key, $value );
			if ( is_wp_error( $valid ) ) { return $valid; }
			if ( $post && ! current_user_can( 'edit_post_meta', $post->ID, $key ) ) { return self::denied(); }
			if ( ! $post && is_protected_meta( $key, 'post' ) && ! isset( get_registered_meta_keys( 'post' )[ $key ] ) && ! isset( get_registered_meta_keys( 'post', $type->name )[ $key ] ) ) { return self::denied(); }
		}
		foreach ( $input['terms'] ?? array() as $name => $ids ) {
			$taxonomy = get_taxonomy( $name );
			if ( ! $taxonomy || ! is_object_in_taxonomy( $type->name, $name ) ) { return self::invalid( 'Taxonomy must be registered for this post type.' ); }
			if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) { return self::denied(); }
			foreach ( $ids as $id ) {
				$term = get_term( $id, $name );
				if ( ! $term || is_wp_error( $term ) ) { return self::invalid( 'Every term ID must exist in its requested taxonomy.' ); }
				if ( ! current_user_can( 'assign_term', $id ) ) { return self::denied(); }
			}
		}
		if ( isset( $input['featured_media'] ) ) {
			$id = $input['featured_media'];
			if ( ! post_type_supports( $type->name, 'thumbnail' ) ) { return self::invalid( 'This post type does not support featured images.' ); }
			if ( $id && ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) || ! wp_get_attachment_image( $id, 'thumbnail' ) ) ) { return self::invalid( 'Choose an existing image attachment.' ); }
			if ( $id && ! current_user_can( 'edit_post', $id ) ) { return self::denied(); }
		}
		$completed = array();
		if ( $fields ) {
			$args = array( 'action' => $post ? 'update' : 'create', 'post_type' => $type->name, 'fields' => $fields );
			if ( $post ) { $args['id'] = $post->ID; }
			$result = wp_get_ability( 'guest-key/manage-posts' )->execute( $args );
			if ( is_wp_error( $result ) ) { return $result; }
			$post = get_post( $result['ID'] );
			$completed[] = 'fields';
		}
		foreach ( $input['meta'] ?? array() as $key => $value ) {
			$result = wp_get_ability( 'guest-key/manage-post-meta' )->execute( array( 'action' => 'update', 'post_id' => $post->ID, 'key' => (string) $key, 'value' => $value ) );
			if ( is_wp_error( $result ) ) { return self::partial_save( $result, $post->ID, $completed ); }
			$completed[] = 'meta:' . $key;
		}
		foreach ( $input['terms'] ?? array() as $name => $ids ) {
			$result = wp_get_ability( 'guest-key/manage-terms' )->execute( array( 'action' => 'assign', 'post_id' => $post->ID, 'taxonomy' => $name, 'term_ids' => $ids ) );
			if ( is_wp_error( $result ) ) { return self::partial_save( $result, $post->ID, $completed ); }
			$completed[] = 'terms:' . $name;
		}
		if ( isset( $input['featured_media'] ) ) {
			// Recheck after save hooks, which may change the post's permissions.
			if ( ! current_user_can( 'edit_post', $post->ID ) ) { return self::partial_save( self::denied(), $post->ID, $completed ); }
			$id = $input['featured_media'];
			if ( $id ) { set_post_thumbnail( $post->ID, $id ); } else { delete_post_thumbnail( $post->ID ); }
			if ( (int) get_post_thumbnail_id( $post->ID ) !== $id ) { return self::partial_save( self::invalid( 'WordPress could not save the featured image.' ), $post->ID, $completed ); }
			$completed[] = 'featured_media';
		}
		return array( 'id' => $post->ID, 'status' => get_post_status( $post->ID ), 'completed' => $completed );
	}

	private static function partial_save( $error, $id, $completed ) {
		return new WP_Error( $error->get_error_code(), $error->get_error_message(), array( 'post_id' => $id, 'completed' => $completed, 'cause' => $error->get_error_data() ) );
	}
}
