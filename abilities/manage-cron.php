<?php
defined( 'ABSPATH' ) || exit;
$args = Guest_Key_Content_Abilities::args(
	'Manage WordPress cron events',
	'List upcoming/overdue events, inspect recurrence schedules, schedule a single or recurring event, run an existing occurrence now, or delete one occurrence. Uses native WordPress scheduling APIs on the current site. Select existing events by timestamp, hook and signature from list (or exact args). Run executes registered callbacks synchronously, consumes the selected occurrence and reschedules recurring events like WP-Cron; effects are not rolled back on failure. Deleting one occurrence does not clear all events for its hook. Scheduling does not bypass WP-Cron traffic/host requirements.',
	'execute', array(
		'action' => array( 'type' => 'string', 'enum' => array( 'list', 'schedules', 'schedule', 'run', 'delete' ) ),
		'hook' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ),
		'timestamp' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Unix seconds. Required for schedule/run/delete; list returns the exact stored timestamp.' ),
		'signature' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{32}$', 'description' => 'Existing event identifier from cron list; use with hook and timestamp. Preferred over reconstructing args.' ),
		'args' => array( 'type' => 'array', 'description' => 'Callback arguments. Defaults to []. Existing events can instead be selected by signature.' ),
		'recurrence' => array( 'type' => 'string', 'description' => 'Schedule name from cron schedules; omit for a one-time event.' ),
		'due' => array( 'type' => 'boolean', 'description' => 'List only overdue/due events (true), or future events (false). Omit for both.' ),
		'page' => array( 'type' => 'integer', 'minimum' => 1 ), 'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
	), array( 'action' )
);
$args['execute_callback'] = array( 'Guest_Key_Cron', 'execute' );
wp_register_ability( 'guest-key/manage-cron', $args );
