<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/run', array(
	'label' => 'Run WordPress commands',
	'description' => 'Run 1–20 commands sequentially in one request: {commands:[{command:"plugin install",input:{slug:"example",activate:true}}]}. Use help for schemas. post save combines fields, meta, terms, featured_media. rest request calls native APIs; ability run calls registered plugin abilities. Stops on failure: inspect ok, results, failed_index and error; completed writes remain. Never blindly retry a batch. No shell or arbitrary PHP.',
	'category' => 'site', 'input_schema' => Guest_Key_Commands::run_schema(),
	'execute_callback' => array( 'Guest_Key_Commands', 'run' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
) );
