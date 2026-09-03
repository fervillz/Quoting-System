<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quote Product pricing
 *
 * Quote Products are the source of truth. ACF can be used to edit their
 * fields, but calculations read normal post meta so the plugin does not
 * depend on ACF being active.
 */

/**
 * Return the first existing post-meta value from a list of compatible keys.
 */
function qs_pricing_meta( $post_id, $keys, $default = '' ) {
	foreach ( (array) $keys as $key ) {
		if ( metadata_exists( 'post', $post_id, $key ) ) {
			return get_post_meta( $post_id, $key, true );
		}
	}

	return $default;
}

/**
 * Read an ACF repeater directly from its raw post-meta rows.
 *
 * $fields maps the name used by this calculator to one or more field names
 * used by the current ACF export or the original pricing import.
 */
function qs_pricing_repeater_rows( $post_id, $bases, $fields ) {
	foreach ( (array) $bases as $base ) {
		$stored = qs_pricing_meta( $post_id, $base, null );

		// Also accept a normal array so imported/programmatic data remains easy
		// to use even when it was not saved by ACF.
		if ( is_array( $stored ) ) {
			$rows = array();
			foreach ( $stored as $stored_row ) {
				if ( ! is_array( $stored_row ) ) {
					continue;
				}
				$row = array();
				foreach ( $fields as $output_key => $aliases ) {
					foreach ( (array) $aliases as $alias ) {
						if ( array_key_exists( $alias, $stored_row ) ) {
							$row[ $output_key ] = $stored_row[ $alias ];
							break;
						}
					}
				}
				$rows[] = $row;
			}
			if ( $rows ) {
				return $rows;
			}
		}

		$count = is_numeric( $stored ) ? absint( $stored ) : 0;
		if ( ! $count ) {
			continue;
		}

		$rows = array();
		for ( $index = 0; $index < $count; $index++ ) {
			$row = array();
			foreach ( $fields as $output_key => $aliases ) {
				foreach ( (array) $aliases as $alias ) {
					$meta_key = $base . '_' . $index . '_' . $alias;
					if ( metadata_exists( 'post', $post_id, $meta_key ) ) {
						$row[ $output_key ] = get_post_meta( $post_id, $meta_key, true );
						break;
					}
				}
			}
			$rows[] = $row;
		}

		return $rows;
	}

	return array();
}

/** Convert an ACF relationship value into one Quote Product post ID. */
function qs_pricing_related_product_id( $value ) {
	if ( is_object( $value ) && isset( $value->ID ) ) {
		return absint( $value->ID );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $related ) {
			$product_id = qs_pricing_related_product_id( $related );
			if ( $product_id ) {
				return $product_id;
			}
		}
		return 0;
	}

	return is_numeric( $value ) ? absint( $value ) : 0;
}

/** Find a published Quote Product by its exact title and optional type. */
function qs_find_quote_product( $title, $type = '' ) {
	$title = trim( (string) $title );
	if ( '' === $title ) {
		return 0;
	}

	static $cache = array();
	$cache_key = strtolower( $type . '|' . $title );
	if ( array_key_exists( $cache_key, $cache ) ) {
		return $cache[ $cache_key ];
	}

	$args = array(
		'post_type'              => 'quote_products',
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
	);
	if ( $type ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'quote_product_type',
				'field'    => 'slug',
				'terms'    => sanitize_title( $type ),
			),
		);
	}

	$normalised_title = strtolower( $title );
	foreach ( get_posts( $args ) as $product ) {
		if ( strtolower( trim( $product->post_title ) ) === $normalised_title ) {
			$cache[ $cache_key ] = (int) $product->ID;
			return $cache[ $cache_key ];
		}
	}

	$cache[ $cache_key ] = 0;
	return 0;
}

/**
 * Resolve a saved product ID or an older saved product title.
 */
function qs_resolve_quote_product( $value, $type = '' ) {
	if ( is_object( $value ) && isset( $value->ID ) ) {
		$value = $value->ID;
	}
	if ( is_array( $value ) ) {
		$value = qs_pricing_related_product_id( $value );
	}
	if ( is_numeric( $value ) ) {
		$product_id = absint( $value );
		if ( $product_id && 'quote_products' === get_post_type( $product_id ) ) {
			return $product_id;
		}
	}

	return qs_find_quote_product( $value, $type );
}

/** Return the lower-case pricing method saved on a Quote Product. */
function qs_product_pricing_method( $product_id ) {
	return strtolower( trim( (string) qs_pricing_meta( $product_id, 'pricing_method', '' ) ) );
}

/** Return the product linked through either version of the ACF source field. */
function qs_product_pricing_source( $product_id ) {
	$value = qs_pricing_meta( $product_id, array( 'pricing_matrix_source', 'pricing_matrix_copy' ), 0 );
	return qs_pricing_related_product_id( $value );
}

/** Return matrix rows using the imported and current ACF field names. */
function qs_product_matrix_rows( $product_id ) {
	return qs_pricing_repeater_rows(
		$product_id,
		'pricing_matrix',
		array(
			'height_min' => 'height_min',
			'height_max' => 'height_max',
			'width_min'  => 'width_min',
			'width_max'  => 'width_max',
			'price'      => 'price',
		)
	);
}

/**
 * Get the matrix price for one panel at the supplied millimetre dimensions.
 */
function qs_product_matrix_price( $product_id, $width, $height, $visited = array() ) {
	$product_id = absint( $product_id );
	$width      = max( 0, (float) $width );
	$height     = max( 0, (float) $height );
	if ( ! $product_id || ! $width || ! $height || isset( $visited[ $product_id ] ) ) {
		return 0;
	}
	$visited[ $product_id ] = true;

	$rows = qs_product_matrix_rows( $product_id );
	if ( ! $rows ) {
		$source_id = qs_product_pricing_source( $product_id );
		return $source_id ? qs_product_matrix_price( $source_id, $width, $height, $visited ) : 0;
	}

	foreach ( $rows as $row ) {
		$height_min = isset( $row['height_min'] ) && '' !== $row['height_min'] ? (float) $row['height_min'] : 0;
		$height_max = isset( $row['height_max'] ) && '' !== $row['height_max'] ? (float) $row['height_max'] : PHP_FLOAT_MAX;
		$width_min  = isset( $row['width_min'] ) && '' !== $row['width_min'] ? (float) $row['width_min'] : 0;
		$width_max  = isset( $row['width_max'] ) && '' !== $row['width_max'] ? (float) $row['width_max'] : PHP_FLOAT_MAX;

		if ( $height >= $height_min && $height <= $height_max && $width >= $width_min && $width <= $width_max ) {
			return round( isset( $row['price'] ) ? (float) $row['price'] : 0, 2 );
		}
	}

	return 0;
}

/** Fixed product value, compatible with both ACF exports. */
function qs_product_fixed_price( $product_id ) {
	return (float) qs_pricing_meta( $product_id, array( 'fixed_price', 'price' ), 0 );
}

/** Percentage product adjustment, for example Walnut +10 or Raw -10. */
function qs_product_percentage( $product_id ) {
	return (float) qs_pricing_meta( $product_id, 'percentage', 0 );
}

/** Price one linear item using length and the matching height band. */
function qs_product_linear_price( $product_id, $length, $height ) {
	$rows = qs_pricing_repeater_rows(
		$product_id,
		'linear_pricing',
		array(
			'min'   => array( 'height_min', 'min' ),
			'max'   => array( 'height_max', 'max' ),
			'price' => array( 'price_per_lm', 'price' ),
		)
	);
	if ( ! $rows ) {
		$source_id = qs_product_pricing_source( $product_id );
		return $source_id ? qs_product_linear_price( $source_id, $length, $height ) : 0;
	}

	$height = max( 0, (float) $height );
	$metres = max( 0, (float) $length ) / 1000;
	foreach ( $rows as $row ) {
		$minimum = isset( $row['min'] ) && '' !== $row['min'] ? (float) $row['min'] : 0;
		$maximum = isset( $row['max'] ) && '' !== $row['max'] ? (float) $row['max'] : PHP_FLOAT_MAX;
		if ( $height >= $minimum && $height <= $maximum ) {
			return round( $metres * ( isset( $row['price'] ) ? (float) $row['price'] : 0 ), 2 );
		}
	}

	return 0;
}

/** Price an area item from a square-metre price band. */
function qs_product_square_price( $product_id, $width, $height ) {
	$rows = qs_pricing_repeater_rows(
		$product_id,
		array( 'square_metre', 'square_metre_pricing' ),
		array(
			'min'   => 'min',
			'max'   => 'max',
			'price' => array( 'price_per_sqm', 'price' ),
		)
	);
	if ( ! $rows ) {
		$source_id = qs_product_pricing_source( $product_id );
		return $source_id ? qs_product_square_price( $source_id, $width, $height ) : 0;
	}

	$area = ( max( 0, (float) $width ) / 1000 ) * ( max( 0, (float) $height ) / 1000 );
	foreach ( $rows as $row ) {
		$minimum = isset( $row['min'] ) && '' !== $row['min'] ? (float) $row['min'] : 0;
		$maximum = isset( $row['max'] ) && '' !== $row['max'] ? (float) $row['max'] : PHP_FLOAT_MAX;
		if ( $area >= $minimum && $area <= $maximum ) {
			return round( $area * ( isset( $row['price'] ) ? (float) $row['price'] : 0 ), 2 );
		}
	}

	return 0;
}

/** Price one dimensional product using its configured method. */
function qs_product_dimension_price( $product_id, $width, $height, $length = 0 ) {
	switch ( qs_product_pricing_method( $product_id ) ) {
		case 'matrix':
			return qs_product_matrix_price( $product_id, $width, $height );
		case 'fixed':
			return round( qs_product_fixed_price( $product_id ), 2 );
		case 'linear':
			return qs_product_linear_price( $product_id, $length ? $length : $width, $height );
		case 'square':
			return qs_product_square_price( $product_id, $width, $height );
		default:
			// A linked matrix can still provide the dimensional base price.
			$source_id = qs_product_pricing_source( $product_id );
			return $source_id ? qs_product_matrix_price( $source_id, $width, $height ) : 0;
	}
}

/** Find the Paint product used when a paint colour has been supplied. */
function qs_quote_paint_product( $quote_id ) {
	foreach ( array( '_paint_product', '_paint' ) as $meta_key ) {
		$product_id = qs_resolve_quote_product( get_post_meta( $quote_id, $meta_key, true ), 'paint' );
		if ( $product_id ) {
			return $product_id;
		}
	}

	$paint_colour = trim( (string) get_post_meta( $quote_id, '_paint_colour', true ) );
	$timber_title = qs_quote_product_label( get_post_meta( $quote_id, '_timber', true ) );
	if ( '' !== $paint_colour || false !== stripos( $timber_title, 'paint' ) ) {
		return qs_find_quote_product( 'Painted', 'paint' );
	}

	return 0;
}

/** Finger Pull is only chargeable on the three supported door profiles. */
function qs_handle_applies_to_profile( $handle_id, $profile_id ) {
	$handle_title  = strtolower( trim( (string) get_the_title( $handle_id ) ) );
	$profile_title = strtolower( trim( (string) get_the_title( $profile_id ) ) );
	if ( 'finger pull' !== $handle_title ) {
		return true;
	}

	return in_array( $profile_title, array( 'evans', 'valley', '30 shaker' ), true );
}

/** Price one door, drawer front, end panel or filler. */
function qs_price_panel( $profile_id, $width, $height, $paint_id = 0, $handle_id = 0 ) {
	$price = qs_product_dimension_price( $profile_id, $width, $height );
	if ( $paint_id ) {
		$price += qs_product_dimension_price( $paint_id, $width, $height );
	}
	if ( $handle_id && qs_handle_applies_to_profile( $handle_id, $profile_id ) ) {
		$price += qs_product_fixed_price( $handle_id );
	}

	return round( $price, 2 );
}

/**
 * Return each drawer-front height for a drawer bank.
 *
 * The current form stores top/middle/bottom. Optional top-middle and
 * bottom-middle values are also supported for four/five drawer banks.
 */
function qs_drawer_bank_heights( $row ) {
	$count = isset( $row['drawer_count'] ) ? absint( $row['drawer_count'] ) : 0;
	if ( ! $count ) {
		$count = count( array_filter( array(
			isset( $row['top_height'] ) ? $row['top_height'] : 0,
			isset( $row['top_middle_height'] ) ? $row['top_middle_height'] : 0,
			isset( $row['middle_height'] ) ? $row['middle_height'] : 0,
			isset( $row['bottom_middle_height'] ) ? $row['bottom_middle_height'] : 0,
			isset( $row['bottom_height'] ) ? $row['bottom_height'] : 0,
		) ) );
	}
	$count    = max( 1, $count );
	$fallback = isset( $row['height'] ) ? absint( $row['height'] ) : 0;
	$get      = static function ( $key ) use ( $row, $fallback ) {
		return ! empty( $row[ $key ] ) ? absint( $row[ $key ] ) : $fallback;
	};

	if ( 1 === $count ) {
		return array( $get( 'top_height' ) );
	}
	if ( 2 === $count ) {
		return array( $get( 'top_height' ), $get( 'bottom_height' ) );
	}

	$heights   = array( $get( 'top_height' ) );
	$middle    = $get( 'middle_height' );
	$middle_no = $count - 2;
	for ( $index = 0; $index < $middle_no; $index++ ) {
		if ( 0 === $index && ! empty( $row['top_middle_height'] ) ) {
			$heights[] = absint( $row['top_middle_height'] );
		} elseif ( $index === $middle_no - 1 && ! empty( $row['bottom_middle_height'] ) ) {
			$heights[] = absint( $row['bottom_middle_height'] );
		} else {
			$heights[] = $middle;
		}
	}
	$heights[] = $get( 'bottom_height' );

	return $heights;
}

/** Resolve the material saved on a kickboard row to its Quote Product. */
function qs_kickboard_product( $material ) {
	$product_id = qs_resolve_quote_product( $material, 'kickboard' );
	if ( $product_id ) {
		return $product_id;
	}

	$material = trim( (string) $material );
	if ( '' !== $material && false === stripos( $material, 'kickboard' ) ) {
		$product_id = qs_find_quote_product( $material . ' Kickboard', 'kickboard' );
	}

	return $product_id;
}

/**
 * Calculate the trade subtotal from the selected products and component rows.
 */
function qs_calculate_component_subtotal( $quote_id ) {
	$profile_id = qs_resolve_quote_product( get_post_meta( $quote_id, '_door_profile', true ), 'door-profile' );
	$timber_id  = qs_resolve_quote_product( get_post_meta( $quote_id, '_timber', true ), 'timber' );
	$finish_id  = qs_resolve_quote_product( get_post_meta( $quote_id, '_finish', true ), 'finish' );
	$handle_id  = qs_resolve_quote_product( get_post_meta( $quote_id, '_handle_profile', true ), 'accessory' );
	$paint_id   = qs_quote_paint_product( $quote_id );
	$evans_id   = qs_find_quote_product( 'Evans', 'door-profile' );

	$breakdown = array(
		'doors_drawers' => 0,
		'end_panels'    => 0,
		'fillers'       => 0,
		'kickboards'    => 0,
		'fixed'         => 0,
		'percentage'    => 0,
	);

	foreach ( qs_component_rows( $quote_id, 'doors_drawers' ) as $row ) {
		$type     = isset( $row['type'] ) ? strtolower( trim( $row['type'] ) ) : 'door';
		$width    = isset( $row['width'] ) ? absint( $row['width'] ) : 0;
		$height   = isset( $row['height'] ) ? absint( $row['height'] ) : 0;
		$quantity = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		if ( ! $profile_id || ! $width || ! $quantity ) {
			continue;
		}

		if ( 'drawer bank' === $type ) {
			foreach ( qs_drawer_bank_heights( $row ) as $drawer_height ) {
				$breakdown['doors_drawers'] += qs_price_panel( $profile_id, $width, $drawer_height, $paint_id, $handle_id ) * $quantity;
			}
		} else {
			$breakdown['doors_drawers'] += qs_price_panel( $profile_id, $width, $height, $paint_id, $handle_id ) * $quantity;
		}
	}

	foreach ( qs_component_rows( $quote_id, 'end_panels' ) as $row ) {
		$quantity = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		$breakdown['end_panels'] += qs_price_panel(
			$evans_id,
			isset( $row['width'] ) ? $row['width'] : 0,
			isset( $row['height'] ) ? $row['height'] : 0,
			$paint_id
		) * $quantity;
	}

	foreach ( qs_component_rows( $quote_id, 'fillers' ) as $row ) {
		$quantity = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		$breakdown['fillers'] += qs_price_panel(
			$evans_id,
			isset( $row['width'] ) ? $row['width'] : 0,
			isset( $row['height'] ) ? $row['height'] : 0,
			$paint_id
		) * $quantity;
	}

	foreach ( qs_component_rows( $quote_id, 'kickboards' ) as $row ) {
		$product_id = qs_kickboard_product( isset( $row['material'] ) ? $row['material'] : '' );
		$quantity   = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		if ( $product_id && $quantity ) {
			$breakdown['kickboards'] += qs_product_linear_price(
				$product_id,
				isset( $row['length'] ) ? $row['length'] : 0,
				isset( $row['height'] ) ? $row['height'] : 0
			) * $quantity;
		}
	}

	// Timber and finish are overall quote modifiers. Their imported fixed
	// values are currently zero, while Walnut (+10%) and Raw (-10%) use the
	// percentage field.
	$percentage = 0;
	foreach ( array( $timber_id, $finish_id ) as $modifier_id ) {
		if ( ! $modifier_id ) {
			continue;
		}
		if ( 'percentage' === qs_product_pricing_method( $modifier_id ) ) {
			$percentage += qs_product_percentage( $modifier_id );
		} elseif ( 'fixed' === qs_product_pricing_method( $modifier_id ) ) {
			$breakdown['fixed'] += qs_product_fixed_price( $modifier_id );
		}
	}

	$base                    = array_sum( $breakdown );
	$breakdown['percentage'] = round( $base * ( $percentage / 100 ), 2 );
	$subtotal                = round( $base + $breakdown['percentage'], 2 );

	foreach ( $breakdown as $key => $amount ) {
		$breakdown[ $key ] = round( $amount, 2 );
	}
	update_post_meta( $quote_id, '_pricing_breakdown', $breakdown );

	return $subtotal;
}

/** Save the current product calculation so every screen and PDF agrees. */
function qs_recalculate_quote_pricing( $quote_id ) {
	// The supplied pricing matrices are RRP / Retail prices, Ex GST.
	// Trade pricing is 18.18% less than those spreadsheet values.
	$retail_subtotal = function_exists( 'qs_item_config_calculate_trade_subtotal' )
		? qs_item_config_calculate_trade_subtotal( $quote_id )
		: qs_calculate_component_subtotal( $quote_id );
	$pricing_type   = get_post_meta( $quote_id, '_pricing_type', true );
	$trade_subtotal = qs_apply_trade_discount( $retail_subtotal );
	$subtotal       = 'retail' === $pricing_type ? $retail_subtotal : $trade_subtotal;

	update_post_meta( $quote_id, '_retail_subtotal', $retail_subtotal );
	update_post_meta( $quote_id, '_trade_subtotal', $trade_subtotal );
	update_post_meta( $quote_id, '_calculated_subtotal', $subtotal );
	update_post_meta( $quote_id, '_subtotal', $subtotal );

	return $subtotal;
}

/** Trade pricing is 18.18% less than the RRP / Retail Ex GST subtotal. */
function qs_apply_trade_discount( $amount ) {
	return round( (float) $amount * 0.8182, 2 );
}

/**
 * Legacy compatibility for any older code that still asks for a retail markup.
 * The supplied matrix is already Retail/RRP, so no markup should be applied.
 */
function qs_apply_retail_markup( $amount ) {
	return round( (float) $amount, 2 );
}

/** Calculate the customer total after office adjustments. */
function qs_calculate_total( $quote_id ) {
	$subtotal          = (float) get_post_meta( $quote_id, '_subtotal', true );
	$discount          = (float) get_post_meta( $quote_id, '_discount', true );
	$additional_charge = (float) get_post_meta( $quote_id, '_additional_charges', true );
	$shipping          = (float) get_post_meta( $quote_id, '_shipping', true );

	return round( $subtotal + $shipping - $discount + $additional_charge, 2 );
}

/** Deposit is 30% until the office creates the order and locks that amount. */
function qs_calculate_deposit( $quote_id ) {
	$locked_deposit = get_post_meta( $quote_id, '_qs_locked_deposit_amount', true );
	if ( '' !== $locked_deposit && false !== $locked_deposit ) {
		return round( (float) $locked_deposit, 2 );
	}

	return round( qs_calculate_total( $quote_id ) * 0.30, 2 );
}

/** The balance is the current total less the already requested deposit. */
function qs_calculate_balance( $quote_id ) {
	return round( qs_calculate_total( $quote_id ) - qs_calculate_deposit( $quote_id ), 2 );
}
