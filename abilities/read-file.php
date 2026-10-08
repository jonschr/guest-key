<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/read-file', Guest_Key_Files::args(
	'Read wp-content source text',
	'Read a UTF-8 text chunk without executing PHP or changing files. Paths are relative to wp-content; resolved symlinks must stay inside it and cannot point to excluded credentials/VCS. Files must be regular and at most 2 MiB. Defaults to 32 KiB, maximum 64 KiB per response. Offset is a byte offset aligned forward to a UTF-8 boundary; use next_offset to continue. line identifies the first line. Binary files are excluded.',
	'read', array(
		'path' => Guest_Key_Files::path_schema(),
		'offset' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => Guest_Key_Files::MAX_FILE_BYTES ),
		'length' => array( 'type' => 'integer', 'minimum' => 4, 'maximum' => 65536 ),
	), array( 'path' )
) );
