<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/list-files', Guest_Key_Files::args(
	'Browse wp-content files',
	'List wp-content-relative paths, types and sizes without reading file contents. Defaults to one directory; recursive=true walks descendants without following symlinks. Search filters filenames literally. Offset paginates matching entries in filesystem order; changes can affect pagination. Check complete and scan.limited; narrow path after a scan limit. Known credentials and VCS directories are excluded.',
	'listing', array(
		'path' => Guest_Key_Files::path_schema(), 'recursive' => array( 'type' => 'boolean' ),
		'search' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ),
		'offset' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => Guest_Key_Files::MAX_ENTRIES ),
		'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
	)
) );
