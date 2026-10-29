<?php
defined( 'ABSPATH' ) || exit;

/** Read-only source inspection, confined to the canonical wp-content directory. */
final class Guest_Key_Files {

	const MAX_FILE_BYTES = 2097152;
	const MAX_SCAN_BYTES = 16777216;
	const MAX_ENTRIES = 5000;
	const MAX_DEPTH = 20;
	const SCAN_SECONDS = 3;

	public static function init() { add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) ); }

	public static function register_routes() {
		foreach ( array( '/files' => 'list-files', '/files/read' => 'read-file', '/files/search' => 'search-files' ) as $path => $ability ) {
			Guest_Key_Commands::register_ability_route( $path, 'guest-key/' . $ability, 'GET' );
		}
	}

	public static function args( $label, $description, $method, $properties, $required = array() ) {
		return array(
			'label' => $label, 'description' => $description, 'category' => 'site',
			'input_schema' => array( 'type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false ),
			'execute_callback' => array( __CLASS__, $method ),
			'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
			'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
		);
	}

	public static function path_schema() {
		return array( 'type' => 'string', 'maxLength' => 1024, 'description' => 'Path relative to wp-content, e.g. plugins/example/includes. Omit for the content root when listing/searching. No absolute paths or dot segments.' );
	}

	public static function cursor_schema() {
		return array( 'type' => 'string', 'maxLength' => 65536, 'description' => 'Opaque next_cursor from the previous response. Repeat the same filters; limit may change. Resumes after result, byte, entry or time limits without rescanning completed files. Expires after one hour; scoped to this user and site.' );
	}

	public static function depth_schema() {
		return array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_DEPTH + 1, 'description' => 'Maximum directory levels to inspect: 1 lists/searches only immediate children, 2 includes their children. Overrides recursive for listing. Default: 1 for listing, 21 with recursive=true or search.' );
	}

	private static function error( $code, $message, $status = 400 ) { return new WP_Error( 'guest_key_file_' . $code, $message, array( 'status' => $status ) ); }

	private static function excluded( $path ) {
		foreach ( explode( '/', $path ) as $part ) {
			if ( in_array( strtolower( $part ), array( '.git', '.svn', '.hg', '.htpasswd' ), true ) || preg_match( '/^(?:\.env(?:\..*)?|wp-config\.php(?:\..*)?)$/i', $part ) ) { return true; }
		}
		return false;
	}

	private static function resolve( $path ) {
		if ( preg_match( '~[\\\\%\x00-\x1f\x7f]|^/|^[a-zA-Z]:|://|(?:^|/)\.{1,2}(?:/|$)~', $path ) ) { return self::error( 'path', 'Use a relative wp-content path without encoded segments, backslashes, streams or dot segments.' ); }
		if ( self::excluded( $path ) ) { return self::error( 'denied', 'Credential files and version-control directories are excluded.', 403 ); }
		clearstatcache( true );
		$root = realpath( WP_CONTENT_DIR );
		if ( false === $root ) { return self::error( 'root', 'The content directory is unavailable.', 503 ); }
		$root = wp_normalize_path( $root );
		$absolute = realpath( $root . '/' . $path );
		if ( false === $absolute ) { return self::error( 'missing', 'The requested content path does not exist.', 404 ); }
		$absolute = wp_normalize_path( $absolute );
		if ( $absolute !== $root && 0 !== strpos( $absolute, $root . '/' ) ) { return self::error( 'outside', 'The resolved path is outside wp-content.', 403 ); }
		if ( self::excluded( ltrim( substr( $absolute, strlen( $root ) ), '/' ) ) ) { return self::error( 'denied', 'The resolved path points to an excluded file or directory.', 403 ); }
		return $absolute;
	}

	private static function text( $path ) {
		$absolute = self::resolve( $path );
		if ( is_wp_error( $absolute ) ) { return $absolute; }
		if ( ! is_file( $absolute ) ) { return self::error( 'type', 'Choose a regular text file.', 415 ); }
		$handle = @fopen( $absolute, 'rb' );
		if ( false === $handle ) { return self::error( 'unreadable', 'The file is not readable.', 403 ); }
		try {
			$info = fstat( $handle );
			clearstatcache( true, $absolute );
			$verified = self::resolve( $path );
			$check = is_wp_error( $verified ) ? false : @stat( $verified );
			if ( ! $info || ! $check || $info['dev'] !== $check['dev'] || $info['ino'] !== $check['ino'] || ( $info['mode'] & 0170000 ) !== 0100000 ) { return self::error( 'changed', 'The path changed while opening the file; retry.', 409 ); }
			if ( $info['size'] > self::MAX_FILE_BYTES ) { return self::error( 'size', 'Text inspection is limited to files of 2 MiB or less.', 413 ); }
			$content = stream_get_contents( $handle, self::MAX_FILE_BYTES + 1 );
			if ( false === $content ) { return self::error( 'unreadable', 'The file could not be read.', 403 ); }
			if ( strlen( $content ) > self::MAX_FILE_BYTES ) { return self::error( 'size', 'The file grew beyond the 2 MiB limit.', 413 ); }
			if ( false !== strpos( $content, "\0" ) || 1 !== preg_match( '//u', $content ) ) { return self::error( 'binary', 'Only UTF-8 text files are supported; binary files are excluded.', 415 ); }
			return $content;
		} finally { fclose( $handle ); }
	}

	private static function slice( $text, $offset, $length ) {
		$size = strlen( $text ); $offset = min( $size, $offset );
		while ( $offset < $size && ( ord( $text[ $offset ] ) & 0xc0 ) === 0x80 ) { $offset++; }
		$chunk = substr( $text, $offset, $length );
		while ( '' !== $chunk && 1 !== preg_match( '//u', $chunk ) ) { $chunk = substr( $chunk, 0, -1 ); }
		return array( $offset, $chunk );
	}

	public static function read( $input ) {
		$content = self::text( $input['path'] );
		if ( is_wp_error( $content ) ) { return $content; }
		list( $offset, $chunk ) = self::slice( $content, (int) ( $input['offset'] ?? 0 ), (int) ( $input['length'] ?? 32768 ) );
		$prefix = substr( $content, 0, $offset );
		$next = $offset + strlen( $chunk );
		return array( 'path' => $input['path'], 'size' => strlen( $content ), 'offset' => $offset,
			'line' => 1 + substr_count( $prefix, "\n" ) + substr_count( $prefix, "\r" ) - substr_count( $prefix, "\r\n" ),
			'content' => $chunk, 'next_offset' => $next < strlen( $content ) ? $next : null );
	}

	private static function state() {
		return array( 'entries_scanned' => 0, 'files_scanned' => 0, 'bytes_scanned' => 0, 'skipped' => array( 'excluded' => 0, 'symlink' => 0, 'unreadable' => 0, 'size' => 0, 'binary' => 0, 'depth' => 0 ), 'limited' => null );
	}

	private static function complete( $state ) {
		return null === $state['limited'] && 0 === $state['skipped']['unreadable'] && 0 === $state['skipped']['size'] && 0 === $state['skipped']['depth'] && 0 === $state['skipped']['symlink'];
	}

	private static function checkpoint( $input, $mode ) {
		$options = array_merge( 'list' === $mode ? array( 'path' => '', 'recursive' => false, 'offset' => 0 ) : array( 'path' => '', 'case_sensitive' => false, 'offset' => 0 ), $input );
		unset( $options['cursor'], $options['limit'] ); ksort( $options );
		$context = hash( 'sha256', $mode . ':' . get_current_blog_id() . ':' . get_current_user_id() . ':' . wp_json_encode( $options ) );
		if ( empty( $input['cursor'] ) ) {
			return array( 'context' => $context, 'expires' => time() + HOUR_IN_SECONDS, 'stack' => array( array( rtrim( $input['path'] ?? '', '/' ), 0, 0 ) ), 'skip' => (int) ( $input['offset'] ?? 0 ) );
		}
		$parts = explode( '.', $input['cursor'], 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) { return self::error( 'cursor', 'Invalid scan cursor. Start a new scan.' ); }
		$checkpoint = json_decode( base64_decode( $parts[0], true ), true );
		if ( ! is_array( $checkpoint ) || ( $checkpoint['context'] ?? '' ) !== $context || ( $checkpoint['expires'] ?? 0 ) <= time() ) { return self::error( 'cursor', 'Scan cursor expired or belongs to different filters, user or site. Start a new scan.' ); }
		return $checkpoint;
	}

	private static function cursor( $checkpoint, $state ) {
		if ( null === $state['limited'] ) { return null; }
		$payload = base64_encode( wp_json_encode( $checkpoint ) );
		return $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	private static function walk( &$checkpoint, $max_depth, &$state, $deadline ) {
		while ( $checkpoint['stack'] ) {
			$frame = count( $checkpoint['stack'] ) - 1;
			list( $directory, $depth, $index ) = $checkpoint['stack'][ $frame ];
			$absolute = self::resolve( $directory );
			if ( is_wp_error( $absolute ) || ! is_dir( $absolute ) || is_link( WP_CONTENT_DIR . '/' . $directory ) ) { array_pop( $checkpoint['stack'] ); $state['skipped']['unreadable']++; continue; }
			try {
				$iterator = new FilesystemIterator( $absolute, FilesystemIterator::SKIP_DOTS );
				if ( $index ) { $iterator->seek( $index ); }
			} catch ( OutOfBoundsException $error ) { array_pop( $checkpoint['stack'] ); continue; }
			catch ( UnexpectedValueException $error ) { array_pop( $checkpoint['stack'] ); $state['skipped']['unreadable']++; continue; }
			while ( $iterator->valid() ) {
				if ( microtime( true ) >= $deadline ) { $state['limited'] = 'time'; return; }
				if ( $state['entries_scanned'] >= self::MAX_ENTRIES ) { $state['limited'] = 'entries'; return; }
				$entry = $iterator->current();
				$state['entries_scanned']++;
				$relative = ( '' === $directory ? '' : $directory . '/' ) . $entry->getFilename();
				$checkpoint['stack'][ $frame ][2] = ++$index;
				$iterator->next();
				if ( self::excluded( $relative ) ) { $state['skipped']['excluded']++; continue; }
				$descend = ! $entry->isLink() && $entry->isDir() && $depth + 1 < $max_depth;
				if ( $descend ) { $checkpoint['stack'][] = array( $relative, $depth + 1, 0 ); }
				elseif ( ! $entry->isLink() && $entry->isDir() && self::MAX_DEPTH + 1 === $max_depth ) { $state['skipped']['depth']++; }
				yield array( $relative, $entry );
				if ( $descend ) { continue 2; }
			}
			array_pop( $checkpoint['stack'] );
		}
	}

	public static function listing( $input ) {
		$path = $input['path'] ?? '';
		$absolute = self::resolve( $path );
		if ( is_wp_error( $absolute ) ) { return $absolute; }
		if ( ! is_dir( $absolute ) || ! is_readable( $absolute ) ) { return self::error( 'directory', 'Choose a readable directory.', 400 ); }
		$offset = (int) ( $input['offset'] ?? 0 ); $limit = (int) ( $input['limit'] ?? 100 );
		$checkpoint = self::checkpoint( $input, 'list' );
		if ( is_wp_error( $checkpoint ) ) { return $checkpoint; }
		$state = self::state(); $items = array();
		$depth = $input['depth'] ?? ( rest_sanitize_boolean( $input['recursive'] ?? false ) ? self::MAX_DEPTH + 1 : 1 );
		foreach ( self::walk( $checkpoint, $depth, $state, microtime( true ) + self::SCAN_SECONDS ) as $item ) {
			list( $relative, $entry ) = $item;
			if ( isset( $input['search'] ) && false === stripos( $entry->getFilename(), $input['search'] ) ) { continue; }
			if ( $checkpoint['skip'] > 0 ) { $checkpoint['skip']--; continue; }
			$resolved = self::resolve( $relative );
			$row = array( 'path' => $relative, 'type' => $entry->isLink() ? 'symlink' : ( $entry->isDir() ? 'directory' : ( $entry->isFile() ? 'file' : 'other' ) ), 'readable' => ! is_wp_error( $resolved ) && is_readable( $resolved ) );
			if ( ! $entry->isLink() && $entry->isFile() ) {
				try { $row['size'] = $entry->getSize(); }
				catch ( RuntimeException $error ) { $row['readable'] = false; $state['skipped']['unreadable']++; }
			}
			$items[] = $row;
			if ( count( $items ) >= $limit ) { $state['limited'] = 'results'; break; }
		}
		return array( 'path' => $path, 'items' => $items, 'next_offset' => 'results' === $state['limited'] && empty( $input['cursor'] ) ? $offset + count( $items ) : null, 'next_cursor' => self::cursor( $checkpoint, $state ), 'complete' => self::complete( $state ), 'scan' => $state );
	}

	private static function lines( $text, $offset = 0, $number = 1 ) {
		$size = strlen( $text );
		while ( $offset < $size ) {
			$length = strcspn( $text, "\r\n", $offset );
			$start = $offset;
			$offset += $length;
			if ( $offset < $size && "\r" === $text[ $offset ] && $offset + 1 < $size && "\n" === $text[ $offset + 1 ] ) { $offset += 2; }
			elseif ( $offset < $size ) { $offset++; }
			yield array( $number++, $start, substr( $text, $start, $length ), $offset );
		}
	}

	public static function search( $input ) {
		$path = $input['path'] ?? '';
		$absolute = self::resolve( $path );
		if ( is_wp_error( $absolute ) ) { return $absolute; }
		if ( ! is_dir( $absolute ) || ! is_readable( $absolute ) ) { return self::error( 'directory', 'Choose a readable search directory.', 400 ); }
		$checkpoint = self::checkpoint( $input, 'search' );
		if ( is_wp_error( $checkpoint ) ) { return $checkpoint; }
		$state = self::state(); $matches = array();
		$offset = (int) ( $input['offset'] ?? 0 ); $limit = (int) ( $input['limit'] ?? 30 );
		$case_sensitive = rest_sanitize_boolean( $input['case_sensitive'] ?? false );
		$deadline = microtime( true ) + self::SCAN_SECONDS;
		$pending = $checkpoint['file'] ?? null;
		$files = static function () use ( &$checkpoint, &$state, $deadline, $input, $pending ) {
			if ( $pending ) {
				$resolved = self::resolve( $pending[0] );
				if ( ! is_wp_error( $resolved ) ) { yield array( $pending[0], new SplFileInfo( WP_CONTENT_DIR . '/' . $pending[0] ) ); }
				else { $state['skipped']['unreadable']++; }
			}
			unset( $checkpoint['file'] );
			yield from self::walk( $checkpoint, $input['depth'] ?? self::MAX_DEPTH + 1, $state, $deadline );
		};
		foreach ( $files() as $item ) {
			list( $relative, $entry ) = $item;
			$resume = $pending && $pending[0] === $relative ? $pending : array( $relative, 0, 1 );
			unset( $checkpoint['file'] );
			$pending = null;
			if ( $entry->isLink() ) { $state['skipped']['symlink']++; continue; }
			if ( ! $entry->isFile() || ( isset( $input['filename'] ) && ! fnmatch( $input['filename'], $entry->getFilename() ) ) ) { continue; }
			try { $size = $entry->getSize(); }
			catch ( RuntimeException $error ) { $state['skipped']['unreadable']++; continue; }
			if ( $size > self::MAX_FILE_BYTES ) { $state['skipped']['size']++; continue; }
			$checkpoint['file'] = $resume;
			if ( $state['bytes_scanned'] + $size > self::MAX_SCAN_BYTES ) { $state['limited'] = 'bytes'; break; }
			$text = self::text( $relative );
			$state['files_scanned']++; $state['bytes_scanned'] += is_wp_error( $text ) ? $size : strlen( $text );
			if ( $state['bytes_scanned'] > self::MAX_SCAN_BYTES ) { $state['limited'] = 'bytes'; break; }
			if ( is_wp_error( $text ) ) {
				$reason = substr( $text->get_error_code(), strlen( 'guest_key_file_' ) );
				$state['skipped'][ isset( $state['skipped'][ $reason ] ) ? $reason : 'unreadable' ]++; unset( $checkpoint['file'] ); continue;
			}
			foreach ( self::lines( $text, $checkpoint['file'][1], $checkpoint['file'][2] ) as $line ) {
				if ( microtime( true ) >= $deadline ) { $state['limited'] = 'time'; break 2; }
				list( $number, $line_offset, $content, $next ) = $line;
				$checkpoint['file'] = array( $relative, $next, $number + 1 );
				$match = $case_sensitive ? strpos( $content, $input['query'] ) : stripos( $content, $input['query'] );
				if ( false === $match ) { continue; }
				if ( $checkpoint['skip'] > 0 ) { $checkpoint['skip']--; continue; }
				list( $start, $snippet ) = self::slice( $content, max( 0, $match - 120 ), 600 );
				$matches[] = array( 'path' => $relative, 'line' => $number, 'offset' => $line_offset + $match, 'line_offset' => $line_offset, 'text' => $snippet, 'truncated' => 0 !== $start || strlen( $snippet ) < strlen( $content ) );
				if ( count( $matches ) >= $limit ) { $state['limited'] = 'results'; break 2; }
			}
			unset( $checkpoint['file'] );
		}
		return array( 'path' => $path, 'matches' => $matches, 'next_offset' => 'results' === $state['limited'] && empty( $input['cursor'] ) ? $offset + count( $matches ) : null, 'next_cursor' => self::cursor( $checkpoint, $state ), 'complete' => self::complete( $state ), 'scan' => $state );
	}
}
