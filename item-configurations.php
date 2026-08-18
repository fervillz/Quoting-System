<?php
/**
 * Per-item product configuration for quote components.
 *
 * Door Specifications used to be stored once at quote level. New quotes keep
 * Profile / Timber / Handle / Finish (as applicable) on each component row so
 * mixed configurations can be priced accurately while legacy quotes continue
 * to fall back to their original quote-level values.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Profile End Panel handle correction is now handled by the row calculator. */
remove_action( 'added_post_meta', 'qs_capture_profile_end_panel_raw_trade_subtotal', 900 );
remove_action( 'updated_post_meta', 'qs_capture_profile_end_panel_raw_trade_subtotal', 900 );
remove_action( 'added_post_meta', 'qs_apply_profile_end_panel_pricing_correction', 999 );
remove_action( 'updated_post_meta', 'qs_apply_profile_end_panel_pricing_correction', 999 );

function qs_item_config_row_value( $quote_id, $row, $row_key, $legacy_meta_key ) {
	if ( isset( $row[ $row_key ] ) && '' !== trim( (string) $row[ $row_key ] ) ) {
		return $row[ $row_key ];
	}

	return get_post_meta( $quote_id, $legacy_meta_key, true );
}

function qs_item_config_product_id( $quote_id, $row, $row_key, $legacy_meta_key, $type ) {
	return qs_resolve_quote_product(
		qs_item_config_row_value( $quote_id, $row, $row_key, $legacy_meta_key ),
		$type
	);
}

function qs_item_config_paint_product( $quote_id, $row, $timber_id ) {
	$paint_colour = isset( $row['paint_colour'] ) ? trim( (string) $row['paint_colour'] ) : '';
	$timber_title = $timber_id ? (string) get_the_title( $timber_id ) : '';

	if ( '' !== $paint_colour || false !== stripos( $timber_title, 'paint' ) ) {
		return qs_find_quote_product( 'Painted', 'paint' );
	}

	// Legacy rows may still rely on the quote-level paint selection.
	if ( empty( $row['timber'] ) && empty( $row['paint_colour'] ) ) {
		return qs_quote_paint_product( $quote_id );
	}

	return 0;
}

function qs_item_config_has_row_configuration( $quote_id ) {
	$configuration_fields = array(
		'doors_drawers' => array( 'door_profile', 'timber', 'handle_profile', 'finish', 'paint_colour' ),
		'end_panels'    => array( 'timber', 'finish', 'paint_colour' ),
		'fillers'       => array( 'timber', 'finish', 'paint_colour' ),
		'kickboards'    => array( 'timber', 'finish', 'paint_colour' ),
	);

	foreach ( $configuration_fields as $component => $fields ) {
		foreach ( qs_component_rows( $quote_id, $component ) as $row ) {
			foreach ( $fields as $field ) {
				if ( isset( $row[ $field ] ) && '' !== trim( (string) $row[ $field ] ) ) {
					return true;
				}
			}
		}
	}

	return false;
}

/** Apply Timber / Finish adjustments to one row amount. */
function qs_item_config_apply_row_modifiers( $amount, $timber_id, $finish_id, $quantity ) {
	$percentage = 0;
	$fixed      = 0;

	foreach ( array( $timber_id, $finish_id ) as $modifier_id ) {
		if ( ! $modifier_id ) {
			continue;
		}
		$method = qs_product_pricing_method( $modifier_id );
		if ( 'percentage' === $method ) {
			$percentage += qs_product_percentage( $modifier_id );
		} elseif ( 'fixed' === $method ) {
			$fixed += qs_product_fixed_price( $modifier_id ) * max( 1, absint( $quantity ) );
		}
	}

	return round( (float) $amount + ( (float) $amount * $percentage / 100 ) + $fixed, 2 );
}

/** Legacy overall modifiers are retained for quotes made before row settings. */
function qs_item_config_apply_legacy_modifiers( $base, $quote_id, &$breakdown ) {
	$timber_id = qs_resolve_quote_product( get_post_meta( $quote_id, '_timber', true ), 'timber' );
	$finish_id = qs_resolve_quote_product( get_post_meta( $quote_id, '_finish', true ), 'finish' );
	$percentage = 0;

	foreach ( array( $timber_id, $finish_id ) as $modifier_id ) {
		if ( ! $modifier_id ) {
			continue;
		}
		$method = qs_product_pricing_method( $modifier_id );
		if ( 'percentage' === $method ) {
			$percentage += qs_product_percentage( $modifier_id );
		} elseif ( 'fixed' === $method ) {
			$breakdown['fixed'] += qs_product_fixed_price( $modifier_id );
		}
	}

	$adjusted_base           = (float) $base + (float) $breakdown['fixed'];
	$breakdown['percentage'] = round( $adjusted_base * ( $percentage / 100 ), 2 );

	return round( $adjusted_base + $breakdown['percentage'], 2 );
}

/**
 * Calculate pricing using either row-level configuration or legacy quote-level
 * configuration. Profile End Panels deliberately never receive handle charges.
 */
function qs_item_config_calculate_trade_subtotal( $quote_id ) {
	$row_mode = qs_item_config_has_row_configuration( $quote_id );
	$evans_id = qs_find_quote_product( 'Evans', 'door-profile' );
	$legacy_profile = qs_resolve_quote_product( get_post_meta( $quote_id, '_door_profile', true ), 'door-profile' );
	$legacy_handle  = qs_resolve_quote_product( get_post_meta( $quote_id, '_handle_profile', true ), 'accessory' );
	$legacy_paint   = qs_quote_paint_product( $quote_id );

	$breakdown = array(
		'doors_drawers' => 0,
		'end_panels'    => 0,
		'fillers'       => 0,
		'kickboards'    => 0,
		'fixed'         => 0,
		'percentage'    => 0,
	);

	foreach ( qs_component_rows( $quote_id, 'doors_drawers' ) as $row ) {
		$type     = isset( $row['type'] ) ? strtolower( trim( (string) $row['type'] ) ) : 'door';
		$width    = isset( $row['width'] ) ? absint( $row['width'] ) : 0;
		$height   = isset( $row['height'] ) ? absint( $row['height'] ) : 0;
		$quantity = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		if ( ! $width || ! $quantity ) {
			continue;
		}

		$profile_id = $row_mode ? qs_item_config_product_id( $quote_id, $row, 'door_profile', '_door_profile', 'door-profile' ) : $legacy_profile;
		$timber_id  = $row_mode ? qs_item_config_product_id( $quote_id, $row, 'timber', '_timber', 'timber' ) : 0;
		$finish_id  = $row_mode ? qs_item_config_product_id( $quote_id, $row, 'finish', '_finish', 'finish' ) : 0;
		$handle_id  = $row_mode ? qs_item_config_product_id( $quote_id, $row, 'handle_profile', '_handle_profile', 'accessory' ) : $legacy_handle;
		$paint_id   = $row_mode ? qs_item_config_paint_product( $quote_id, $row, $timber_id ) : $legacy_paint;

		if ( ! $profile_id ) {
			continue;
		}
		if ( 'profile end panel' === $type ) {
			$handle_id = 0;
		}

		$row_amount = 0;
		if ( 'drawer bank' === $type ) {
			foreach ( qs_drawer_bank_heights( $row ) as $drawer_height ) {
				$row_amount += qs_price_panel( $profile_id, $width, $drawer_height, $paint_id, $handle_id ) * $quantity;
			}
		} elseif ( $height ) {
			$row_amount = qs_price_panel( $profile_id, $width, $height, $paint_id, $handle_id ) * $quantity;
		}

		if ( $row_mode ) {
			$row_amount = qs_item_config_apply_row_modifiers( $row_amount, $timber_id, $finish_id, $quantity );
		}
		$breakdown['doors_drawers'] += $row_amount;
	}

	foreach ( array( 'end_panels', 'fillers' ) as $component ) {
		foreach ( qs_component_rows( $quote_id, $component ) as $row ) {
			$quantity = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
			$width    = isset( $row['width'] ) ? absint( $row['width'] ) : 0;
			$height   = isset( $row['height'] ) ? absint( $row['height'] ) : 0;
			if ( ! $quantity || ! $width || ! $height || ! $evans_id ) {
				continue;
			}

			$timber_id = $row_mode ? qs_item_config_product_id( $quote_id, $row, 'timber', '_timber', 'timber' ) : 0;
			$finish_id = $row_mode ? qs_item_config_product_id( $quote_id, $row, 'finish', '_finish', 'finish' ) : 0;
			$paint_id  = $row_mode ? qs_item_config_paint_product( $quote_id, $row, $timber_id ) : $legacy_paint;
			$row_amount = qs_price_panel( $evans_id, $width, $height, $paint_id ) * $quantity;

			if ( $row_mode ) {
				$row_amount = qs_item_config_apply_row_modifiers( $row_amount, $timber_id, $finish_id, $quantity );
			}
			$breakdown[ $component ] += $row_amount;
		}
	}

	foreach ( qs_component_rows( $quote_id, 'kickboards' ) as $row ) {
		$product_id = qs_kickboard_product( isset( $row['material'] ) ? $row['material'] : '' );
		$quantity   = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		if ( ! $product_id || ! $quantity ) {
			continue;
		}

		$row_amount = qs_product_linear_price(
			$product_id,
			isset( $row['length'] ) ? $row['length'] : 0,
			isset( $row['height'] ) ? $row['height'] : 0
		) * $quantity;

		if ( $row_mode ) {
			$timber_id = qs_item_config_product_id( $quote_id, $row, 'timber', '_timber', 'timber' );
			$finish_id = qs_item_config_product_id( $quote_id, $row, 'finish', '_finish', 'finish' );
			$row_amount = qs_item_config_apply_row_modifiers( $row_amount, $timber_id, $finish_id, $quantity );
		}
		$breakdown['kickboards'] += $row_amount;
	}

	if ( $row_mode ) {
		$subtotal = array_sum( $breakdown );
	} else {
		$base     = $breakdown['doors_drawers'] + $breakdown['end_panels'] + $breakdown['fillers'] + $breakdown['kickboards'];
		$subtotal = qs_item_config_apply_legacy_modifiers( $base, $quote_id, $breakdown );
	}

	foreach ( $breakdown as $key => $amount ) {
		$breakdown[ $key ] = round( (float) $amount, 2 );
	}
	update_post_meta( $quote_id, '_pricing_breakdown', $breakdown );

	return round( (float) $subtotal, 2 );
}

/** Replace the standard stored subtotal after every normal recalculation. */
function qs_item_config_sync_pricing( $meta_id, $quote_id, $meta_key, $meta_value ) {
	if ( '_subtotal' !== $meta_key || 'quote' !== get_post_type( $quote_id ) || ! empty( $GLOBALS['qs_item_config_pricing_sync'] ) ) {
		return;
	}

	$GLOBALS['qs_item_config_pricing_sync'] = true;
	$trade_subtotal = qs_item_config_calculate_trade_subtotal( $quote_id );
	$subtotal       = 'retail' === get_post_meta( $quote_id, '_pricing_type', true )
		? qs_apply_retail_markup( $trade_subtotal )
		: $trade_subtotal;

	update_post_meta( $quote_id, '_trade_subtotal', $trade_subtotal );
	update_post_meta( $quote_id, '_calculated_subtotal', $subtotal );
	update_post_meta( $quote_id, '_subtotal', $subtotal );
	$GLOBALS['qs_item_config_pricing_sync'] = false;
}
add_action( 'added_post_meta', 'qs_item_config_sync_pricing', 1200, 4 );
add_action( 'updated_post_meta', 'qs_item_config_sync_pricing', 1200, 4 );

/**
 * wp-admin's old component tables do not yet expose the new item-level fields.
 * Preserve those values by row index whenever an administrator saves a quote.
 */
function qs_item_config_capture_admin_rows( $post_id ) {
	if ( ! is_admin() || empty( $_POST['components'] ) ) {
		return;
	}

	$GLOBALS['qs_item_config_admin_rows'][ $post_id ] = array();
	foreach ( array_keys( qs_component_definitions() ) as $component ) {
		$GLOBALS['qs_item_config_admin_rows'][ $post_id ][ $component ] = get_post_meta( $post_id, '_qs_' . $component, true );
	}
}
add_action( 'save_post_quote', 'qs_item_config_capture_admin_rows', 1 );

function qs_item_config_restore_admin_rows( $post_id ) {
	if ( ! is_admin() || empty( $GLOBALS['qs_item_config_admin_rows'][ $post_id ] ) || empty( $_POST['components'] ) ) {
		return;
	}

	$preserve_fields = array( 'door_profile', 'timber', 'handle_profile', 'finish', 'paint_colour', 'notes' );
	$captured        = $GLOBALS['qs_item_config_admin_rows'][ $post_id ];

	foreach ( array_keys( qs_component_definitions() ) as $component ) {
		$current = get_post_meta( $post_id, '_qs_' . $component, true );
		$current = is_array( $current ) ? $current : array();
		$old     = isset( $captured[ $component ] ) && is_array( $captured[ $component ] ) ? $captured[ $component ] : array();

		foreach ( $current as $index => &$row ) {
			if ( ! isset( $old[ $index ] ) || ! is_array( $old[ $index ] ) ) {
				continue;
			}
			foreach ( $preserve_fields as $field ) {
				if ( array_key_exists( $field, $old[ $index ] ) && ( ! isset( $row[ $field ] ) || '' === (string) $row[ $field ] ) ) {
					$row[ $field ] = $old[ $index ][ $field ];
				}
			}
		}
		unset( $row );

		update_post_meta( $post_id, '_qs_' . $component, qs_sanitise_component_rows( $component, $current ) );
	}

	unset( $GLOBALS['qs_item_config_admin_rows'][ $post_id ] );
	qs_recalculate_quote_pricing( $post_id );
}
add_action( 'save_post_quote', 'qs_item_config_restore_admin_rows', 30 );

function qs_item_config_product_options( $type ) {
	$products = get_posts(
		array(
			'post_type'      => 'quote_products',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'tax_query'      => array(
				array(
					'taxonomy' => 'quote_product_type',
					'field'    => 'slug',
					'terms'    => $type,
				),
			),
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => 'active', 'value' => '1' ),
				array( 'key' => 'active', 'compare' => 'NOT EXISTS' ),
			),
		)
	);

	return array_values(
		array_map(
			static function ( $product ) use ( $type ) {
				$label = $product->post_title;
				if ( 'timber' === $type && false !== stripos( $label, 'paint' ) ) {
					$label = 'Painted Oak';
				}
				return array( 'id' => (string) $product->ID, 'label' => $label );
			},
			$products
		)
	);
}

function qs_enqueue_item_configuration_assets() {
	$script = 'assets/js/item-configurations.js';
	$style  = 'assets/css/item-configurations.css';

	wp_enqueue_style(
		'qs-item-configurations',
		QS_URL . $style,
		array( 'qs-quote-builder' ),
		file_exists( QS_PATH . $style ) ? (string) filemtime( QS_PATH . $style ) : QS_VERSION
	);

	wp_enqueue_script(
		'qs-item-configurations',
		QS_URL . $script,
		array( 'qs-quantity-fields', 'qs-quote-builder-ux', 'qs-profile-end-panels' ),
		file_exists( QS_PATH . $script ) ? (string) filemtime( QS_PATH . $script ) : QS_VERSION,
		true
	);

	wp_localize_script(
		'qs-item-configurations',
		'QSItemConfigurations',
		array(
			'profiles' => qs_item_config_product_options( 'door-profile' ),
			'timbers'  => qs_item_config_product_options( 'timber' ),
			'handles'  => qs_item_config_product_options( 'accessory' ),
			'finishes' => qs_item_config_product_options( 'finish' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'qs_enqueue_item_configuration_assets', 10002 );
