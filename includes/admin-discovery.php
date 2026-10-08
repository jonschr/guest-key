<?php
defined( 'ABSPATH' ) || exit;
if ( $discovery ) :
	$entries = $discovery;
	?>
	<section class="guest-key-discovery" data-guest-key-section>
		<h2><?php echo esc_html( __( 'Registered MCP servers', 'guest-key' ) . ' (' . count( $entries ) . ')' ); ?></h2>
		<p><?php esc_html_e( 'Automatically read from the official WordPress MCP Adapter registry. Only registered servers are shown. Inspect a server for its endpoint and tool, resource, and prompt definitions. Other servers may require different credentials.', 'guest-key' ); ?></p>
		<table class="widefat striped guest-key-table"><thead><tr>
			<th scope="col"><?php esc_html_e( 'Name', 'guest-key' ); ?></th><th scope="col"><?php esc_html_e( 'Source', 'guest-key' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'guest-key' ); ?></th><th scope="col"><?php esc_html_e( 'Details', 'guest-key' ); ?></th>
		</tr></thead><tbody>
		<?php foreach ( $entries as $entry ) : ?>
			<tr data-guest-key-item>
				<td><strong><?php echo esc_html( $entry['label'] ); ?></strong><?php if ( $entry['label'] !== $entry['name'] ) : ?><br><code><?php echo esc_html( $entry['name'] ); ?></code><?php endif; ?>
					<p><?php echo esc_html( $entry['description'] ); ?></p>
					<p class="guest-key-mobile-status"><?php echo esc_html( $entry['status'] ); ?></p>
					<?php if ( isset( $entry['details']['counts'] ) ) : ?><p class="description"><?php echo esc_html( sprintf( __( '%1$d tools · %2$d resources · %3$d prompts', 'guest-key' ), $entry['details']['counts']['tools'] ?? 0, $entry['details']['counts']['resources'] ?? 0, $entry['details']['counts']['prompts'] ?? 0 ) ); ?></p><?php endif; ?>
					<?php if ( isset( $entry['details']['endpoint'] ) ) : ?><p><code><?php echo esc_html( $entry['details']['endpoint'] ); ?></code></p><?php endif; ?>
				</td>
				<td><strong><?php echo esc_html( $entry['source']['name'] ); ?></strong><br><span class="description"><?php echo esc_html( $entry['source']['type'] ); ?></span></td>
				<td><?php echo esc_html( $entry['status'] ); ?></td>
				<td><button type="button" class="button" data-guest-key-inspect aria-haspopup="dialog" aria-label="<?php echo esc_attr( sprintf( __( 'Inspect: %s', 'guest-key' ), $entry['label'] ) ); ?>"><?php esc_html_e( 'Inspect', 'guest-key' ); ?></button>
					<template data-guest-key-ability-content>
						<h2><?php echo esc_html( $entry['label'] ); ?></h2>
						<p><code><?php echo esc_html( $entry['name'] ); ?></code></p>
						<p><?php echo esc_html( $entry['description'] ); ?></p>
						<p><strong><?php esc_html_e( 'Source:', 'guest-key' ); ?></strong> <?php echo esc_html( $entry['source']['name'] . ' (' . $entry['source']['type'] . ')' ); ?></p>
						<?php if ( $entry['source']['file'] ) : ?><p><code><?php echo esc_html( $entry['source']['file'] ); ?></code></p><?php endif; ?>
						<p><strong><?php esc_html_e( 'Status:', 'guest-key' ); ?></strong> <?php echo esc_html( $entry['status'] ); ?></p>
						<p><strong><?php esc_html_e( 'Type:', 'guest-key' ); ?></strong> <?php echo esc_html( $entry['kind'] ); ?></p>
						<?php foreach ( $entry['details'] as $detail => $value ) : ?>
							<h3><?php echo esc_html( ucwords( str_replace( '_', ' ', $detail ) ) ); ?></h3>
							<?php if ( is_string( $value ) ) : ?><p><code><?php echo esc_html( $value ); ?></code></p>
							<?php else : ?><pre><?php echo esc_html( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre><?php endif; ?>
						<?php endforeach; ?>
					</template>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
	</section>
<?php endif; ?>
