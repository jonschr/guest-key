<?php
defined( 'ABSPATH' ) || exit;

$args = Guest_Key_Content_Abilities::args(
	'Manage ACF post fields',
	'List applicable ACF fields, read supported values, validate or update one field using ACF form authorization, validation and save hooks. Requires ACF 6.8.11+. Supports top-level text fields without conditional logic; rejects other types, hidden, disabled and readonly fields. Uses stable field keys. Does not require or change public REST exposure. Each update is a separate write; command batches stop at the failing operation and retain prior results. Custom plugin hooks may transform values or have side effects, as in the WordPress editor.',
	'fields',
	array(
		'action' => array( 'type' => 'string', 'enum' => array( 'list', 'get', 'validate', 'update' ) ),
		'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
		'field_key' => array( 'type' => 'string', 'pattern' => '^field_', 'description' => 'Stable ACF field key from list; required except for list.' ),
		'value' => array( 'type' => 'string', 'description' => 'Required for validate/update. Empty string is allowed unless ACF validation rejects it.' ),
	),
	array( 'action', 'post_id' )
);
$args['execute_callback'] = array( 'Guest_Key_ACF_Abilities', 'fields' );
wp_register_ability( 'guest-key/manage-acf-fields', $args );
