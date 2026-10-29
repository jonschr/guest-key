<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/search-files', Guest_Key_Files::args(
	'Search wp-content source text',
	'Recursively search UTF-8 files for a literal query, returning one match per line with path, line and byte offsets. Default is ASCII case-insensitive; case_sensitive=true is exact. filename is a basename glob such as *.php; depth bounds directory levels. Pass next_cursor as cursor with the same filters after any result, byte, entry or time limit; limit may change. Legacy offset skips matching lines from the start. Each page scans at most 5,000 entries, 16 MiB and 3 seconds; files over 2 MiB, binaries, credentials, VCS and symlinks are skipped. Check scan.skipped on every page; filesystem changes may affect pagination. No regex, shell or PHP execution.',
	'search', array(
		'path' => Guest_Key_Files::path_schema(),
		'depth' => Guest_Key_Files::depth_schema(), 'cursor' => Guest_Key_Files::cursor_schema(),
		'query' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ),
		'case_sensitive' => array( 'type' => 'boolean' ),
		'filename' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ),
		'offset' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => Guest_Key_Files::MAX_SCAN_BYTES ),
		'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
	), array( 'query' )
) );
