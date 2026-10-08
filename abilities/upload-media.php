<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/upload-media', array(
	'label' => 'Upload a media file',
	'description' => 'Upload a base64-encoded media file to the native media library. WordPress validates allowed file types and upload permissions. Optional attachment fields include title, caption, description, alt_text, and post. Does not permit writing to arbitrary paths.',
	'category' => 'site',
	'input_schema' => array(
		'type' => 'object',
		'properties' => array(
			'filename' => array( 'type' => 'string', 'minLength' => 1 ),
			'base64' => array( 'type' => 'string', 'minLength' => 1, 'description' => 'Base64 file bytes, without a data: URL prefix.' ),
			'fields' => array( 'type' => 'object', 'additionalProperties' => true ),
		), 'required' => array( 'filename', 'base64' ), 'additionalProperties' => false,
	),
	'execute_callback' => array( 'Guest_Key_Admin_Abilities', 'upload_media' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator() && current_user_can( 'upload_files' ); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) ),
) );
