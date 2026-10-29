<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/list-files', Guest_Key_Files::args(
	'Browse wp-content files',
	'List wp-content-relative paths, types and sizes without reading file contents. Defaults to one directory; recursive=true walks descendants; depth bounds directory levels. Search filters filenames literally. Pass next_cursor as cursor with the same filters to resume after any result, entry or time limit; limit may change. Legacy offset skips matching entries from the start. Filesystem changes may affect pagination; check scan.skipped on every page. No symlink traversal. Known credentials and VCS directories are excluded.',
	'listing', array(
		'path' => Guest_Key_Files::path_schema(), 'recursive' => array( 'type' => 'boolean' ),
		'depth' => Guest_Key_Files::depth_schema(), 'cursor' => Guest_Key_Files::cursor_schema(),
		'search' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ),
		'offset' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => Guest_Key_Files::MAX_ENTRIES ),
		'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
	)
) );
