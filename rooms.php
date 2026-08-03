<?php
/**
 * Multi-room quote data and pricing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_room_specification_keys() {
	return array( 'door_profile', 'timber', 'finish', 'handle_profile', 'paint_colour' );
}

function qs_room_component_keys() {
	return array_keys( qs_component_definitions() );
}

function qs_new_room_id() {
	return 'room-' . wp_generate_uuid4();
}

function qs_blank_room( $position = 1 ) {
	$room = array(
		'id'         => qs_new_room_id(),
		'name'       => sprintf( 'Room %d', max( 1, absint( $position ) ) ),
		'components' => array(),
	);

	foreach ( qs_room_specification_keys() as $key ) {
		$room[ $key ] = '';
	}
	foreach ( qs_room_component_keys() as $component ) {
		$room['components'][ $component ] = array();
	}

	return $room;
}

function qs_sanitise_room( $raw_room, $position = 1 ) {
	$room     = qs_blank_room( $position );
	$raw_room = is_array( $raw_room ) ? $raw_room : array();
	$id       = isset( $raw_room['id'] ) ? sanitize_key( $raw_room['id'] ) : '';
	$name     = isset( $raw_room['name'] ) ? sanitize_text_field( $raw_room['name'] ) : '';

	$room['id']   = $id ? $id : qs_new_room_id();
	$room['name'] = $name ? $name : sprintf( 'Room %d', max( 1, absint( $position ) ) );

	foreach ( qs_room_specification_keys() as $key ) {
		$value        = isset( $raw_room[ $key ] ) ? $raw_room[ $key ] : '';
		$room[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	$components = isset( $raw_room['components'] ) && is_array( $raw_room['components'] )
		? $raw_room['components']
		: array();
	foreach ( qs_room_component_keys() as $component ) {
		$rows = isset( $components[ $component ] ) ? $components[ $component ] : array();
		$room['components'][ $component ] = qs_sanitise_component_rows( $component, $rows );
	}

	return $room;
}

function qs_sanitise_rooms( $raw_rooms ) {
	if ( ! is_array( $raw_rooms ) ) {
		return array();
	}

	$rooms = array();
	foreach ( array_values( $raw_rooms ) as $index => $raw_room ) {
		if ( ! is_array( $raw_room ) ) {
			continue;
		}
		$rooms[] = qs_sanitise_room( $raw_room, $index + 1 );
	}

	return $rooms;
}

function qs_legacy_room_from_quote( $quote_id ) {
	$room = qs_blank_room( 1 );
	$room['name'] = 'Room 1';

	foreach ( qs_room_specification_keys() as $key ) {
		$room[ $key ] = (string) get_post_meta( $quote_id, '_' . $key, true );
	}
	foreach ( qs_room_component_keys() as $component ) {
		$room['components'][ $component ] = qs_component_rows( $quote_id, $component );
	}

	return $room;
}

function qs_quote_rooms( $quote_id, $include_blank = true ) {
	$quote_id = absint( $quote_id );
	if ( $quote_id ) {
		$stored = get_post_meta( $quote_id, '_qs_rooms', true );
		$rooms  = qs_sanitise_rooms( $stored );
		if ( $rooms ) {
			return $rooms;
		}

		$legacy = qs_legacy_room_from_quote( $quote_id );
		$has_data = false;
		foreach ( qs_room_specification_keys() as $key ) {
			if ( '' !== trim( (string) $legacy[ $key ] ) ) {
				$has_data = true;
				break;
			}
		}
		if ( ! $has_data ) {
			foreach ( qs_room_component_keys() as $component ) {
				if ( ! empty( $legacy['components'][ $component ] ) ) {
					$has_data = true;
					break;
				}
			}
		}
		if ( $has_data || $include_blank ) {
			return array( $legacy );
		}
	}

	return $include_blank ? array( qs_blank_room( 1 ) ) : array();
}

function qs_posted_rooms() {
	if ( ! isset( $_POST['qs_rooms_json'] ) ) {
		return null;
	}

	$json = wp_unslash( $_POST['qs_rooms_json'] );
	$data = json_decode( $json, true );
	if ( ! is_array( $data ) ) {
		return array();
	}

	return qs_sanitise_rooms( $data );
}

function qs_save_posted_rooms( $post_id, $post = null ) {
	if ( wp_is_post_revision( $post_id ) || 'quote' !== get_post_type( $post_id ) ) {
		return;
	}

	$rooms = qs_posted_rooms();
	if ( null === $rooms ) {
		return;
	}
	if ( ! $rooms ) {
		$rooms = array( qs_blank_room( 1 ) );
	}

	update_post_meta( $post_id, '_qs_rooms', $rooms );
	update_post_meta( $post_id, '_qs_rooms_version', 1 );

	if ( isset( $_POST['qs_active_room_id'] ) ) {
		update_post_meta( $post_id, '_qs_active_room_id', sanitize_key( wp_unslash( $_POST['qs_active_room_id'] ) ) );
	}
}
add_action( 'save_post_quote', 'qs_save_posted_rooms', 20, 2 );

function qs_room_paint_product( $room ) {
	$paint_colour = isset( $room['paint_colour'] ) ? trim( (string) $room['paint_colour'] ) : '';
	$timber_title = isset( $room['timber'] ) ? qs_quote_product_label( $room['timber'] ) : '';
	if ( '' !== $paint_colour || false !== stripos( $timber_title, 'paint' ) ) {
		return qs_find_quote_product( 'Painted', 'paint' );
	}

	return 0;
}

function qs_calculate_room_trade_subtotal( $room, &$breakdown = null ) {
	$room = qs_sanitise_room( $room, 1 );

	$profile_id = qs_resolve_quote_product( $room['door_profile'], 'door-profile' );
	$timber_id  = qs_resolve_quote_product( $room['timber'], 'timber' );
	$finish_id  = qs_resolve_quote_product( $room['finish'], 'finish' );
	$handle_id  = qs_resolve_quote_product( $room['handle_profile'], 'accessory' );
	$paint_id   = qs_room_paint_product( $room );
	$evans_id   = qs_find_quote_product( 'Evans', 'door-profile' );

	$breakdown = array(
		'doors_drawers' => 0,
		'end_panels'    => 0,
		'fillers'       => 0,
		'kickboards'    => 0,
		'fixed'         => 0,
		'percentage'    => 0,
	);

	foreach ( $room['components']['doors_drawers'] as $row ) {
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
		} elseif ( 'profile end panel' === $type ) {
			$breakdown['doors_drawers'] += qs_price_panel( $profile_id, $width, $height, $paint_id, 0 ) * $quantity;
		} else {
			$breakdown['doors_drawers'] += qs_price_panel( $profile_id, $width, $height, $paint_id, $handle_id ) * $quantity;
		}
	}

	foreach ( $room['components']['end_panels'] as $row ) {
		$quantity = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		$breakdown['end_panels'] += qs_price_panel(
			$evans_id,
			isset( $row['width'] ) ? $row['width'] : 0,
			isset( $row['height'] ) ? $row['height'] : 0,
			$paint_id
		) * $quantity;
	}

	foreach ( $room['components']['fillers'] as $row ) {
		$quantity = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		$breakdown['fillers'] += qs_price_panel(
			$evans_id,
			isset( $row['width'] ) ? $row['width'] : 0,
			isset( $row['height'] ) ? $row['height'] : 0,
			$paint_id
		) * $quantity;
	}

	foreach ( $room['components']['kickboards'] as $row ) {
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

	return $subtotal;
}

function qs_calculate_rooms_pricing( $quote_id ) {
	$rooms            = qs_quote_rooms( $quote_id, false );
	$room_subtotals   = array();
	$room_breakdowns  = array();
	$aggregate        = array(
		'doors_drawers' => 0,
		'end_panels'    => 0,
		'fillers'       => 0,
		'kickboards'    => 0,
		'fixed'         => 0,
		'percentage'    => 0,
	);
	$trade_subtotal   = 0;

	foreach ( $rooms as $index => $room ) {
		$breakdown = array();
		$subtotal  = qs_calculate_room_trade_subtotal( $room, $breakdown );
		$room_id   = isset( $room['id'] ) ? $room['id'] : 'room-' . ( $index + 1 );
		$room_subtotals[ $room_id ]  = $subtotal;
		$room_breakdowns[ $room_id ] = $breakdown;
		$trade_subtotal += $subtotal;
		foreach ( $aggregate as $key => $amount ) {
			$aggregate[ $key ] += isset( $breakdown[ $key ] ) ? $breakdown[ $key ] : 0;
		}
	}

	$trade_subtotal = round( $trade_subtotal, 2 );
	$pricing_type   = get_post_meta( $quote_id, '_pricing_type', true );
	$subtotal       = 'retail' === $pricing_type ? qs_apply_retail_markup( $trade_subtotal ) : $trade_subtotal;

	return array(
		'trade_subtotal'  => $trade_subtotal,
		'subtotal'        => round( $subtotal, 2 ),
		'room_subtotals'  => $room_subtotals,
		'room_breakdowns' => $room_breakdowns,
		'breakdown'       => array_map( static function ( $value ) { return round( $value, 2 ); }, $aggregate ),
	);
}

function qs_store_rooms_pricing( $quote_id ) {
	static $updating = false;
	if ( $updating || 'quote' !== get_post_type( $quote_id ) ) {
		return 0;
	}

	$rooms = get_post_meta( $quote_id, '_qs_rooms', true );
	if ( ! is_array( $rooms ) || ! $rooms ) {
		return 0;
	}

	$updating = true;
	$result   = qs_calculate_rooms_pricing( $quote_id );
	update_post_meta( $quote_id, '_room_subtotals', $result['room_subtotals'] );
	update_post_meta( $quote_id, '_room_pricing_breakdowns', $result['room_breakdowns'] );
	update_post_meta( $quote_id, '_pricing_breakdown', $result['breakdown'] );
	update_post_meta( $quote_id, '_trade_subtotal', $result['trade_subtotal'] );
	update_post_meta( $quote_id, '_calculated_subtotal', $result['subtotal'] );
	update_post_meta( $quote_id, '_subtotal', $result['subtotal'] );
	$updating = false;

	return $result['subtotal'];
}

function qs_refresh_rooms_after_subtotal_meta( $meta_id, $quote_id, $meta_key, $meta_value ) {
	if ( '_subtotal' === $meta_key ) {
		qs_store_rooms_pricing( $quote_id );
	}
}
add_action( 'added_post_meta', 'qs_refresh_rooms_after_subtotal_meta', 10, 4 );
add_action( 'updated_post_meta', 'qs_refresh_rooms_after_subtotal_meta', 10, 4 );
add_action( 'save_post_quote', 'qs_store_rooms_pricing', 999 );

function qs_room_subtotal( $quote_id, $room_id ) {
	$subtotals = get_post_meta( $quote_id, '_room_subtotals', true );
	if ( ! is_array( $subtotals ) ) {
		$result    = qs_calculate_rooms_pricing( $quote_id );
		$subtotals = $result['room_subtotals'];
	}

	return isset( $subtotals[ $room_id ] ) ? (float) $subtotals[ $room_id ] : 0;
}

function qs_rooms_summary_markup( $quote_id, $show_prices = true ) {
	$rooms = qs_quote_rooms( $quote_id, false );
	if ( ! $rooms ) {
		return '';
	}

	ob_start();
	?>
	<section class="qs-room-summary-overview">
		<h3><?php echo esc_html__( 'Rooms', 'quote-system' ); ?></h3>
		<?php foreach ( $rooms as $room ) : ?>
			<article class="qs-room-summary-card">
				<header>
					<h4><?php echo esc_html( $room['name'] ); ?></h4>
					<?php if ( $show_prices ) : ?><strong>$<?php echo esc_html( number_format_i18n( qs_room_subtotal( $quote_id, $room['id'] ), 2 ) ); ?> AUD</strong><?php endif; ?>
				</header>
				<dl>
					<dt>Profile</dt><dd><?php echo esc_html( qs_quote_product_label( $room['door_profile'] ) ?: '—' ); ?></dd>
					<dt>Timber</dt><dd><?php echo esc_html( qs_quote_product_label( $room['timber'] ) ?: '—' ); ?></dd>
					<dt>Finish</dt><dd><?php echo esc_html( qs_quote_product_label( $room['finish'] ) ?: '—' ); ?></dd>
				</dl>
				<?php foreach ( $room['components'] as $component => $rows ) : ?>
					<?php if ( $rows ) : ?>
						<p><span><?php echo esc_html( ucwords( str_replace( '_', ' ', $component ) ) ); ?></span><strong><?php echo esc_html( count( $rows ) ); ?></strong></p>
					<?php endif; ?>
				<?php endforeach; ?>
			</article>
		<?php endforeach; ?>
	</section>
	<?php
	return ob_get_clean();
}
