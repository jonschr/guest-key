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

	private static function walk( $path, $recursive, &$state, $deadline ) {
		$stack = array( array( rtrim( $path, '/' ), 0 ) );
		while ( $stack ) {
			list( $directory, $depth ) = array_pop( $stack );
			$absolute = self::resolve( $directory );
			if ( is_wp_error( $absolute ) || ! is_dir( $absolute ) ) { $state['skipped']['unreadable']++; continue; }
			try { $iterator = new FilesystemIterator( $absolute, FilesystemIterator::SKIP_DOTS ); }
			catch ( UnexpectedValueException $error ) { $state['skipped']['unreadable']++; continue; }
			foreach ( $iterator as $entry ) {
				if ( microtime( true ) >= $deadline ) { $state['limited'] = 'time'; return; }
				if ( $state['entries_scanned'] >= self::MAX_ENTRIES ) { $state['limited'] = 'entries'; return; }
				$state['entries_scanned']++;
				$relative = ( '' === $directory ? '' : $directory . '/' ) . $entry->getFilename();
				if ( self::excluded( $relative ) ) { $state['skipped']['excluded']++; continue; }
				yield array( $relative, $entry );
				if ( $recursive && ! $entry->isLink() && $entry->isDir() ) {
					if ( $depth >= self::MAX_DEPTH ) { $state['skipped']['depth']++; }
					else { $stack[] = array( $relative, $depth + 1 ); }
				}
			}
		}
	}

	public static function listing( $input ) {
		$path = $input['path'] ?? '';
		$absolute = self::resolve( $path );
		if ( is_wp_error( $absolute ) ) { return $absolute; }
		if ( ! is_dir( $absolute ) || ! is_readable( $absolute ) ) { return self::error( 'directory', 'Choose a readable directory.', 400 ); }
		$offset = (int) ( $input['offset'] ?? 0 ); $limit = (int) ( $input['limit'] ?? 100 );
		$state = self::state(); $items = array(); $position = 0;
		foreach ( self::walk( $path, rest_sanitize_boolean( $input['recursive'] ?? false ), $state, microtime( true ) + self::SCAN_SECONDS ) as $item ) {
			list( $relative, $entry ) = $item;
			if ( isset( $input['search'] ) && false === stripos( $entry->getFilename(), $input['search'] ) ) { continue; }
			if ( $position++ < $offset ) { continue; }
			$resolved = self::resolve( $relative );
			$row = array( 'path' => $relative, 'type' => $entry->isLink() ? 'symlink' : ( $entry->isDir() ? 'directory' : ( $entry->isFile() ? 'file' : 'other' ) ), 'readable' => ! is_wp_error( $resolved ) && is_readable( $resolved ) );
			if ( ! $entry->isLink() && $entry->isFile() ) {
				try { $row['size'] = $entry->getSize(); }
				catch ( RuntimeException $error ) { $row['readable'] = false; $state['skipped']['unreadable']++; }
			}
			$items[] = $row;
			if ( count( $items ) >= $limit ) { $state['limited'] = 'results'; break; }
		}
		return array( 'path' => $path, 'items' => $items, 'next_offset' => 'results' === $state['limited'] ? $offset + count( $items ) : null, 'complete' => self::complete( $state ), 'scan' => $state );
	}

	private static function lines( $text ) {
		$offset = 0; $number = 1; $size = strlen( $text );
		while ( $offset < $size ) {
			$length = strcspn( $text, "\r\n", $offset );
			yield array( $number++, $offset, substr( $text, $offset, $length ) );
			$offset += $length;
			if ( $offset < $size && "\r" === $text[ $offset ] && $offset + 1 < $size && "\n" === $text[ $offset + 1 ] ) { $offset += 2; }
			elseif ( $offset < $size ) { $offset++; }
		}
	}

	public static function search( $input ) {
		$path = $input['path'] ?? '';
		$absolute = self::resolve( $path );
		if ( is_wp_error( $absolute ) ) { return $absolute; }
		if ( ! is_dir( $absolute ) || ! is_readable( $absolute ) ) { return self::error( 'directory', 'Choose a readable search directory.', 400 ); }
		$state = self::state(); $matches = array(); $position = 0;
		$offset = (int) ( $input['offset'] ?? 0 ); $limit = (int) ( $input['limit'] ?? 30 );
		$case_sensitive = rest_sanitize_boolean( $input['case_sensitive'] ?? false );
		$deadline = microtime( true ) + self::SCAN_SECONDS;
		foreach ( self::walk( $path, true, $state, $deadline ) as $item ) {
			list( $relative, $entry ) = $item;
			if ( $entry->isLink() ) { $state['skipped']['symlink']++; continue; }
			if ( ! $entry->isFile() || ( isset( $input['filename'] ) && ! fnmatch( $input['filename'], $entry->getFilename() ) ) ) { continue; }
			try { $size = $entry->getSize(); }
			catch ( RuntimeException $error ) { $state['skipped']['unreadable']++; continue; }
			if ( $size > self::MAX_FILE_BYTES ) { $state['skipped']['size']++; continue; }
			if ( $state['bytes_scanned'] + $size > self::MAX_SCAN_BYTES ) { $state['limited'] = 'bytes'; break; }
			$text = self::text( $relative );
			$state['files_scanned']++; $state['bytes_scanned'] += is_wp_error( $text ) ? $size : strlen( $text );
			if ( $state['bytes_scanned'] > self::MAX_SCAN_BYTES ) { $state['limited'] = 'bytes'; break; }
			if ( is_wp_error( $text ) ) {
				$reason = substr( $text->get_error_code(), strlen( 'guest_key_file_' ) );
				$state['skipped'][ isset( $state['skipped'][ $reason ] ) ? $reason : 'unreadable' ]++; continue;
			}
			foreach ( self::lines( $text ) as $line ) {
				if ( microtime( true ) >= $deadline ) { $state['limited'] = 'time'; break 2; }
				list( $number, $line_offset, $content ) = $line;
				$match = $case_sensitive ? strpos( $content, $input['query'] ) : stripos( $content, $input['query'] );
				if ( false === $match || $position++ < $offset ) { continue; }
				list( $start, $snippet ) = self::slice( $content, max( 0, $match - 120 ), 600 );
				$matches[] = array( 'path' => $relative, 'line' => $number, 'offset' => $line_offset + $match, 'line_offset' => $line_offset, 'text' => $snippet, 'truncated' => 0 !== $start || strlen( $snippet ) < strlen( $content ) );
				if ( count( $matches ) >= $limit ) { $state['limited'] = 'results'; break 2; }
			}
		}
		return array( 'path' => $path, 'matches' => $matches, 'next_offset' => 'results' === $state['limited'] ? $offset + count( $matches ) : null, 'complete' => self::complete( $state ), 'scan' => $state );
	}
}
