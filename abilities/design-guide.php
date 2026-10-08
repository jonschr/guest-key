<?php
defined( 'ABSPATH' ) || exit;

$guest_key_design_sections = array(
	'overview' => 'Start here: design workflow',
	'brief' => 'Audience, content and visual direction',
	'system' => 'Typography, color and reusable design decisions',
	'layout' => 'Page composition, components and responsive behavior',
	'motion' => 'Purposeful animation and interaction feedback',
	'images' => 'Image selection and portfolio art direction',
	'wordpress' => 'Editable WordPress implementation',
	'review' => 'Rendered-page review and acceptance checks',
);

wp_register_ability( 'guest-key/design-guide', array(
	'label' => 'Website design guide',
	'description' => 'Load original, builder-independent design guidance before creating or redesigning a page. Omit section for a short workflow and topic index; request a focused section when needed. This is advice, not an automated audit or a site mutation.',
	'category' => 'site',
	'input_schema' => array( 'type' => 'object', 'properties' => array(
		'section' => array( 'type' => 'string', 'enum' => array_keys( $guest_key_design_sections ), 'description' => 'Default: overview. Load only the topic needed for the current task.' ),
	), 'additionalProperties' => false ),
	'execute_callback' => static function ( $input ) use ( $guest_key_design_sections ) {
		$section = $input['section'] ?? 'overview';
		if ( ! isset( $guest_key_design_sections[ $section ] ) ) { return new WP_Error( 'guest_key_design_section', 'Unknown design section.' ); }
		$content = @file_get_contents( GUEST_KEY_DIR . '/guides/design/' . $section . '.md' );
		if ( false === $content ) { return new WP_Error( 'guest_key_design_missing', 'The bundled design guide is unavailable. Reinstall the complete plugin package.' ); }
		$result = array( 'section' => $section, 'title' => $guest_key_design_sections[ $section ], 'content' => $content );
		if ( 'overview' === $section ) { $result['sections'] = $guest_key_design_sections; }
		return $result;
	},
	'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
) );
