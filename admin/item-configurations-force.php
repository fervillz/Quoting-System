<?php
/**
 * Final wp-admin metabox routing for the per-item configuration editor.
 *
 * Some admin screens/plugins can register or restore metaboxes after the
 * standard add_meta_boxes callbacks run. Hooking do_meta_boxes gives Quote
 * System one final opportunity, immediately before WordPress renders each
 * context, to ensure the modern editor is the one shown.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_admin_item_config_force_metaboxes( $post_type, $context, $post ) {
	if ( 'quote' !== $post_type || ! ( $post instanceof WP_Post ) ) {
		return;
	}

	if ( 'normal' === $context ) {
		remove_meta_box( 'qs_components', 'quote', 'normal' );
		remove_meta_box( 'qs_cabinet_specifications', 'quote', 'normal' );

		add_meta_box(
			'qs_components',
			'Components & Item Configurations',
			'qs_admin_item_config_components_metabox',
			'quote',
			'normal',
			'default'
		);
	}

	if ( 'side' === $context ) {
		remove_meta_box( 'qs_configuration_summary', 'quote', 'side' );
		add_meta_box(
			'qs_configuration_summary',
			'Configuration Summary',
			'qs_admin_item_config_summary_metabox',
			'quote',
			'side',
			'default'
		);
	}
}
add_action( 'do_meta_boxes', 'qs_admin_item_config_force_metaboxes', 999, 3 );
