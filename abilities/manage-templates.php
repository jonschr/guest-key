<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-templates', Guest_Key_Admin_Abilities::rest_args(
	'manage-templates',
	'Manage templates and patterns',
	'Manage database-backed block templates, template parts, and reusable blocks/patterns, including revisions. These operations do not edit theme PHP or template files.',
	array( '/wp/v2/templates', '/wp/v2/template-parts', '/wp/v2/blocks' )
) );
