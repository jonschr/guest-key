<?php
defined( 'ABSPATH' ) || exit;

/** Native scheduling APIs; event signatures preserve exact stored argument identity. */
final class Guest_Key_Cron {
	public static function execute( $input ) {
		$action = $input['action'];
		if ( 'schedules' === $action ) { return wp_get_schedules(); }
		if ( 'list' === $action ) {
			$items = array(); $now = time();
			foreach ( _get_cron_array() ?: array() as $timestamp => $hooks ) {
				foreach ( $hooks as $hook => $events ) {
					if ( isset( $input['hook'] ) && $hook !== $input['hook'] ) { continue; }
					if ( isset( $input['due'] ) && ( $timestamp <= $now ) !== $input['due'] ) { continue; }
					foreach ( $events as $signature => $event ) {
						$items[] = array( 'timestamp' => (int) $timestamp, 'utc' => gmdate( 'c', $timestamp ), 'hook' => $hook, 'signature' => $signature, 'args' => $event['args'], 'schedule' => $event['schedule'], 'interval' => $event['interval'] ?? null, 'due' => $timestamp <= $now );
					}
				}
			}
			$page = $input['page'] ?? 1; $per_page = $input['per_page'] ?? 50;
			return array( 'items' => array_slice( $items, ( $page - 1 ) * $per_page, $per_page ), 'total' => count( $items ), 'total_pages' => (int) ceil( count( $items ) / $per_page ), 'running' => (bool) get_transient( 'doing_cron' ) );
		}
		if ( empty( $input['hook'] ) || empty( $input['timestamp'] ) ) { return new WP_Error( 'guest_key_cron_input', 'Provide hook and timestamp. Use cron list to discover existing events.' ); }
		$hook = $input['hook']; $timestamp = $input['timestamp'];
		if ( 'schedule' === $action ) {
			$args = $input['args'] ?? array(); $recurrence = $input['recurrence'] ?? '';
			$result = $recurrence ? wp_schedule_event( $timestamp, $recurrence, $hook, $args, true ) : wp_schedule_single_event( $timestamp, $hook, $args, true );
			return is_wp_error( $result ) ? $result : array( 'scheduled' => (bool) $result, 'timestamp' => $timestamp, 'hook' => $hook, 'signature' => md5( serialize( $args ) ) );
		}
		$signature = $input['signature'] ?? md5( serialize( $input['args'] ?? array() ) );
		$event = ( _get_cron_array() ?: array() )[ $timestamp ][ $hook ][ $signature ] ?? null;
		if ( ! $event ) { return new WP_Error( 'guest_key_cron_missing', 'Event not found. Refresh cron list and use its timestamp, hook and signature.' ); }
		if ( 'run' === $action && ! has_action( $hook ) ) { return new WP_Error( 'guest_key_cron_callback', 'No callback is registered for this event. Nothing was run or removed.' ); }
		if ( 'run' === $action && $event['schedule'] ) {
			$result = wp_reschedule_event( $timestamp, $event['schedule'], $hook, $event['args'], true );
			if ( is_wp_error( $result ) ) { return $result; }
		}
		$result = wp_unschedule_event( $timestamp, $hook, $event['args'], true );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( ! $result ) { return new WP_Error( 'guest_key_cron_remove', 'WordPress could not remove the selected occurrence.' ); }
		if ( 'run' === $action ) {
			try { do_action_ref_array( $hook, $event['args'] ); }
			catch ( Throwable $error ) { error_log( 'Guest Key cron callback failed: ' . wp_json_encode( array( 'hook' => $hook, 'message' => $error->getMessage() ) ) ); return new WP_Error( 'guest_key_cron_failed', 'The callback failed after the occurrence was consumed. Recurring events were rescheduled. Check site logs before retrying.' ); }
		}
		return array( 'hook' => $hook, 'timestamp' => $timestamp, 'signature' => $signature, 'ran' => 'run' === $action, 'removed' => true );
	}
}
