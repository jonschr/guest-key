<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/search-files', Guest_Key_Files::args(
	'Search wp-content source text',
	'Recursively search UTF-8 files for a literal query, returning one match per line with path, line and byte offsets. Default is ASCII case-insensitive; case_sensitive=true is exact. Optional filename is a basename glob such as *.php. Never follows symlinks. Offset paginates matching lines in filesystem order; changes can affect pagination. Check complete, scan.limited and skipped counters: at most 5,000 entries, 16 MiB of files and 3 seconds per scan; files over 2 MiB, binaries, known credentials and VCS are skipped. Narrow path after a scan limit; no regex, shell or PHP execution.',
	'search', array(
		'path' => Guest_Key_Files::path_schema(),
		'query' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ),
		'case_sensitive' => array( 'type' => 'boolean' ),
		'filename' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ),
		'offset' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => Guest_Key_Files::MAX_SCAN_BYTES ),
		'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
	), array( 'query' )
) );
