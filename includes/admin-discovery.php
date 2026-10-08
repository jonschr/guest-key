<?php
defined( 'ABSPATH' ) || exit;
if ( $discovery ) :
	$entries = $discovery;
	?>
		<?php foreach ( $entries as $entry ) : ?>
			<li data-guest-key-item>
				<button type="button" class="button-link" data-guest-key-inspect aria-haspopup="dialog" aria-label="<?php echo esc_attr( sprintf( __( 'Inspect MCP server: %s', 'guest-key' ), $entry['label'] ) ); ?>"><?php echo esc_html( $entry['name'] ); ?></button>
				<span class="description"><?php echo esc_html( $entry['kind'] . ' · ' . $entry['source']['name'] ); ?></span>
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
			</li>
		<?php endforeach; ?>
<?php endif; ?>
