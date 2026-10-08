<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/read-admin-info', Guest_Key_Admin_Abilities::rest_args(
	'read-admin-info',
	'Read admin API information',
	'Read post types, statuses, taxonomies, and native site search results.',
	array( '/wp/v2/types', '/wp/v2/statuses', '/wp/v2/taxonomies', '/wp/v2/search' ), true
) );
