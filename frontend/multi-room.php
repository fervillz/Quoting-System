<?php
/**
 * Frontend integration for multi-room quotes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_multi_room_quote_id() {
	if ( isset( $_GET['quote_id'] ) ) {
		return absint( $_GET['quote_id'] );
	}
	if ( isset( $_POST['quote_id'] ) ) {
		return absint( $_POST['quote_id'] );
	}
	return 0;
}

function qs_multi_room_builder_config( $quote_id ) {
	$rooms          = qs_quote_rooms( $quote_id, true );
	$active_room_id = $quote_id ? sanitize_key( get_post_meta( $quote_id, '_qs_active_room_id', true ) ) : '';
	$query_room_id  = isset( $_GET['room_id'] ) ? sanitize_key( wp_unslash( $_GET['room_id'] ) ) : '';
	$room_subtotals = array();

	if ( $quote_id ) {
		$result         = qs_calculate_rooms_pricing( $quote_id );
		$room_subtotals = $result['room_subtotals'];
	}
	if ( $query_room_id && wp_list_filter( $rooms, array( 'id' => $query_room_id ) ) ) {
		$active_room_id = $query_room_id;
	}
	if ( ! $active_room_id || ! wp_list_filter( $rooms, array( 'id' => $active_room_id ) ) ) {
		$active_room_id = $rooms[0]['id'];
	}

	return array(
		'quoteId'             => $quote_id,
		'rooms'               => array_values( $rooms ),
		'activeRoomId'        => $active_room_id,
		'roomSubtotals'       => $room_subtotals,
		'leadTime'            => qs_quote_lead_time( $quote_id ),
		'canEditLeadTime'     => $quote_id ? current_user_can( 'edit_post', $quote_id ) && current_user_can( 'edit_others_posts' ) : current_user_can( 'edit_others_posts' ),
		'editIcon'            => QS_URL . 'assets/images/icon-pen.svg',
		'removeIcon'          => QS_URL . 'assets/images/icon-trash.svg',
		'profileEndPanelType' => 'Profile End Panel',
	);
}

function qs_multi_room_builder_shortcode() {
	$html = qs_quote_builder_shortcode();
	if ( false === strpos( $html, 'qs-builder-form' ) ) {
		return $html;
	}

	$quote_id = qs_multi_room_quote_id();
	$config   = qs_multi_room_builder_config( $quote_id );
	wp_localize_script( 'qs-multi-room', 'QSMultiRoom', $config );

	$lead_time = esc_html( $config['leadTime'] );
	$html = preg_replace(
		'/(<div class="qs-lead-time"><strong>Estimated Lead Time<\/strong><span>).*?(<\/span>)/s',
		'$1' . $lead_time . '$2',
		$html,
		1
	);

	if ( $quote_id ) {
		$pricing = qs_calculate_rooms_pricing( $quote_id );
		$total   = '$' . number_format_i18n( $pricing['subtotal'], 2 ) . ' AUD';
		$html    = preg_replace(
			'/(<strong data-qs-subtotal>).*?(<\/strong>)/s',
			'$1' . esc_html( $total ) . '$2',
			$html,
			1
		);
	}

	return str_replace( 'class="qs-builder-shell"', 'class="qs-builder-shell qs-multi-room-enabled"', $html );
}

function qs_multi_room_review_markup( $quote_id, $rooms ) {
	$can_edit = 'draft' === get_post_status( $quote_id ) && qs_quote_review_can_access( $quote_id );
	ob_start();
	?>
	<section class="qs-room-review-overview">
		<div class="qs-room-review-heading">
			<div>
				<h3><?php echo esc_html__( 'Rooms', 'quote-system' ); ?></h3>
				<p><?php echo esc_html__( 'Specifications and modifications are grouped by room.', 'quote-system' ); ?></p>
			</div>
			<?php if ( $can_edit ) : ?>
				<a class="qs-btn qs-btn-outline" href="<?php echo esc_url( add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) ) ); ?>"><?php echo esc_html__( 'Manage Rooms', 'quote-system' ); ?></a>
			<?php endif; ?>
		</div>

		<?php foreach ( $rooms as $room ) :
			$components   = isset( $room['components'] ) && is_array( $room['components'] ) ? $room['components'] : array();
			$fronts       = isset( $components['doors_drawers'] ) ? array_values(
				array_filter(
					$components['doors_drawers'],
					static function ( $row ) {
						return empty( $row['type'] ) || 'Drawer Bank' !== $row['type'];
					}
				)
			) : array();
			$drawer_banks = isset( $components['doors_drawers'] ) ? qs_component_rows_by_type( $components['doors_drawers'], 'Drawer Bank' ) : array();
			$edit_url     = add_query_arg(
				array(
					'quote_id' => $quote_id,
					'room_id'  => $room['id'],
				),
				site_url( '/quote-builder/' )
			);
			?>
			<article class="qs-room-review-card" id="<?php echo esc_attr( $room['id'] ); ?>">
				<header class="qs-room-review-card-header">
					<div>
						<h4><?php echo esc_html( $room['name'] ); ?></h4>
						<span><?php echo esc_html__( 'Room configuration', 'quote-system' ); ?></span>
					</div>
					<div class="qs-room-review-card-actions">
						<strong>$<?php echo esc_html( number_format_i18n( qs_room_subtotal( $quote_id, $room['id'] ), 2 ) ); ?> AUD</strong>
						<?php if ( $can_edit ) : ?><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit Room', 'quote-system' ); ?></a><?php endif; ?>
					</div>
				</header>

				<dl class="qs-room-review-specifications">
					<dt><?php echo esc_html__( 'Profile', 'quote-system' ); ?></dt><dd><?php echo esc_html( qs_quote_product_label( $room['door_profile'] ) ?: '—' ); ?></dd>
					<dt><?php echo esc_html__( 'Timber', 'quote-system' ); ?></dt><dd><?php echo esc_html( qs_quote_product_label( $room['timber'] ) ?: '—' ); ?></dd>
					<dt><?php echo esc_html__( 'Finish', 'quote-system' ); ?></dt><dd><?php echo esc_html( qs_quote_product_label( $room['finish'] ) ?: '—' ); ?></dd>
					<dt><?php echo esc_html__( 'Door / Drawer Handle', 'quote-system' ); ?></dt><dd><?php echo esc_html( qs_quote_product_label( $room['handle_profile'] ) ?: '—' ); ?></dd>
					<?php if ( ! empty( $room['paint_colour'] ) ) : ?><dt><?php echo esc_html__( 'Paint Colour', 'quote-system' ); ?></dt><dd><?php echo esc_html( $room['paint_colour'] ); ?></dd><?php endif; ?>
				</dl>

				<?php if ( $fronts ) : ?>
					<div class="qs-room-review-component">
						<h5><?php echo esc_html__( 'Doors, Drawers & Profile End Panels', 'quote-system' ); ?></h5>
						<?php qs_render_quote_component_table( $fronts, array( 'type' => 'Item Type', 'width' => 'Width', 'height' => 'Height', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $drawer_banks ) : ?>
					<div class="qs-room-review-component">
						<h5><?php echo esc_html__( 'Drawer Banks', 'quote-system' ); ?></h5>
						<?php qs_render_quote_component_table( $drawer_banks, array( 'type' => 'Item Type', 'configuration' => 'Configuration', 'width' => 'Width', 'height_details' => 'Height', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $components['end_panels'] ) ) : ?>
					<div class="qs-room-review-component">
						<h5><?php echo esc_html__( 'End Panels', 'quote-system' ); ?></h5>
						<?php qs_render_quote_component_table( $components['end_panels'], array( 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $components['fillers'] ) ) : ?>
					<div class="qs-room-review-component">
						<h5><?php echo esc_html__( 'Fillers', 'quote-system' ); ?></h5>
						<?php qs_render_quote_component_table( $components['fillers'], array( 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $components['kickboards'] ) ) : ?>
					<div class="qs-room-review-component">
						<h5><?php echo esc_html__( 'Kickboards', 'quote-system' ); ?></h5>
						<?php qs_render_quote_component_table( $components['kickboards'], array( 'material' => 'Kick Material', 'height' => 'Kick Height', 'length' => 'Kick Length', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! $fronts && ! $drawer_banks && empty( $components['end_panels'] ) && empty( $components['fillers'] ) && empty( $components['kickboards'] ) ) : ?>
					<p class="qs-room-review-empty"><?php echo esc_html__( 'No items have been added to this room.', 'quote-system' ); ?></p>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</section>
	<?php
	return ob_get_clean();
}

function qs_multi_room_remove_legacy_review_sections( $html ) {
	$headings = array(
		'Selected Specifications',
		'Doors &amp; Drawers',
		'End Panels &amp; Fillers',
		'Kickboards',
	);

	foreach ( $headings as $heading ) {
		$html = preg_replace(
			'#<section class="qs-review-section">\s*<h3>' . preg_quote( $heading, '#' ) . '</h3>.*?</section>#s',
			'',
			$html,
			1
		);
	}

	return $html;
}

function qs_multi_room_review_shortcode() {
	$html     = qs_quote_review_shortcode();
	$quote_id = qs_multi_room_quote_id();
	if ( ! $quote_id || false === strpos( $html, 'qs-review-page' ) ) {
		return $html;
	}

	$rooms = qs_quote_rooms( $quote_id, false );
	if ( $rooms ) {
		$html     = qs_multi_room_remove_legacy_review_sections( $html );
		$overview = qs_multi_room_review_markup( $quote_id, $rooms );
		$html     = preg_replace( '/<\/main>/', $overview . '</main>', $html, 1 );
	}

	$html = preg_replace(
		'/(<div class="qs-review-lead-time"><strong>Estimated Lead Time<\/strong><span>).*?(<\/span>)/s',
		'$1' . esc_html( qs_quote_lead_time( $quote_id ) ) . '$2',
		$html,
		1
	);

	$pricing = qs_calculate_rooms_pricing( $quote_id );
	$html = preg_replace(
		'/(<div class="qs-review-subtotal"><span>Subtotal<\/span><strong>\$).*?( AUD<\/strong><\/div>)/s',
		'$1' . esc_html( number_format_i18n( $pricing['subtotal'], 2 ) ) . '$2',
		$html,
		1
	);

	$html = str_replace(
		'class="qs-container qs-review-page"',
		'class="qs-container qs-review-page qs-room-aware-review' . ( count( $rooms ) > 1 ? ' qs-has-multiple-rooms' : '' ) . '"',
		$html
	);

	return $html;
}

function qs_multi_room_ajax_recalculate() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please log in before calculating a quote.' ), 403 );
	}

	$quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
	$saved_id = qs_builder_save_quote( $quote_id, false );
	if ( is_wp_error( $saved_id ) ) {
		wp_send_json_error( array( 'message' => $saved_id->get_error_message() ), 400 );
	}

	$pricing = qs_calculate_rooms_pricing( $saved_id );
	qs_store_rooms_pricing( $saved_id );

	wp_send_json_success(
		array(
			'quote_id'           => (int) $saved_id,
			'subtotal'           => $pricing['subtotal'],
			'formatted_subtotal' => number_format_i18n( $pricing['subtotal'], 2 ),
			'room_subtotals'     => $pricing['room_subtotals'],
		)
	);
}

function qs_register_multi_room_ajax() {
	remove_action( 'wp_ajax_qs_builder_recalculate', 'qs_builder_ajax_recalculate' );
	add_action( 'wp_ajax_qs_builder_recalculate', 'qs_multi_room_ajax_recalculate' );
}
add_action( 'init', 'qs_register_multi_room_ajax', 100 );

function qs_register_multi_room_shortcodes() {
	remove_shortcode( 'quote_builder' );
	add_shortcode( 'quote_builder', 'qs_multi_room_builder_shortcode' );

	remove_shortcode( 'quote_review' );
	remove_shortcode( 'my_quote_review' );
	add_shortcode( 'quote_review', 'qs_multi_room_review_shortcode' );
	add_shortcode( 'my_quote_review', 'qs_multi_room_review_shortcode' );
}
add_action( 'init', 'qs_register_multi_room_shortcodes', 100 );
