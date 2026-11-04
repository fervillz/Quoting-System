<?php
/**
 * Internal production job-sheet PDF.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quote_id     = isset( $quote_id ) ? absint( $quote_id ) : 0;
$data         = isset( $data ) && is_array( $data ) ? $data : qs_get_quote_data( $quote_id );
$rows         = $data['component_rows'];
$fronts       = array_values(
	array_filter(
		$rows['doors_drawers'],
		static function ( $row ) {
			return empty( $row['type'] ) || 'Drawer Bank' !== $row['type'];
		}
	)
);
$drawer_banks = qs_component_rows_by_type( $rows['doors_drawers'], 'Drawer Bank' );

// Preserve the item numbers shown on the master Job Sheet. Sorted production
// pages may move rows, but manufacturing references must keep these numbers.
foreach ( $fronts as $index => &$front_row ) {
	$front_row['_display_index'] = $index + 1;
}
unset( $front_row );

$drawer_start = count( $fronts ) + 1;
foreach ( $drawer_banks as $index => &$drawer_row ) {
	$drawer_row['_display_index'] = $drawer_start + $index;
}
unset( $drawer_row );

$created_by   = $data['created_by'] ? $data['created_by'] : $data['customer_name'];
$primary_specs = function_exists( 'qs_quote_primary_specifications' ) ? qs_quote_primary_specifications( $quote_id, $data ) : array();
$finish_image = ! empty( $primary_specs['finish']['id'] ) && function_exists( 'qs_quote_product_image_url' )
	? qs_quote_product_image_url( $primary_specs['finish']['id'] )
	: '';
$handle_image = ! empty( $primary_specs['handle_profile']['id'] ) && function_exists( 'qs_quote_product_image_url' )
	? qs_quote_product_image_url( $primary_specs['handle_profile']['id'] )
	: '';
$specs        = array(
	array( 'Timber', $primary_specs['timber']['label'] ?? $data['timber'], ! empty( $primary_specs['paint_colour'] ) ? 'Paint Colour: ' . $primary_specs['paint_colour'] : '', $primary_specs['timber']['image'] ?? '' ),
	array( 'Profile', $primary_specs['door_profile']['label'] ?? $data['door_profile'], '', $primary_specs['door_profile']['image'] ?? '' ),
	array( 'Finish', $primary_specs['finish']['label'] ?? $data['finish'], '', $finish_image ),
	array( 'Door / Drawer Handle Profile', $primary_specs['handle_profile']['label'] ?? $data['handle_profile'], '', $handle_image ),
);
$has_legacy_specs = (bool) array_filter(
	array(
		$primary_specs['timber']['label'] ?? '',
		$primary_specs['finish']['label'] ?? '',
		$primary_specs['door_profile']['label'] ?? '',
		$primary_specs['handle_profile']['label'] ?? '',
		$primary_specs['paint_colour'] ?? '',
	)
);

/**
 * Production-order pages use the exact same rows as the normal Job Sheet,
 * but present them in descending cutting order.
 *
 * Drawer Banks do not store one overall height, so their sortable height is
 * the sum of the configured drawer-front heights.
 */
$jobsheet_row_height = static function ( $row ) {
	if ( isset( $row['type'] ) && 'Drawer Bank' === $row['type'] ) {
		$total = 0;
		foreach ( array( 'top_height', 'top_middle_height', 'middle_height', 'bottom_middle_height', 'bottom_height' ) as $key ) {
			$total += isset( $row[ $key ] ) ? absint( $row[ $key ] ) : 0;
		}
		return $total;
	}

	return isset( $row['height'] ) ? absint( $row['height'] ) : 0;
};

$jobsheet_sort_rows = static function ( $items, $dimension ) use ( $jobsheet_row_height ) {
	$items = array_values( is_array( $items ) ? $items : array() );

	usort(
		$items,
		static function ( $left, $right ) use ( $dimension, $jobsheet_row_height ) {
			$left_value  = 'height' === $dimension
				? $jobsheet_row_height( $left )
				: ( isset( $left['width'] ) ? absint( $left['width'] ) : 0 );
			$right_value = 'height' === $dimension
				? $jobsheet_row_height( $right )
				: ( isset( $right['width'] ) ? absint( $right['width'] ) : 0 );

			if ( $left_value === $right_value ) {
				// Deterministic secondary sort keeps repeated PDF renders stable.
				$left_secondary  = 'height' === $dimension
					? ( isset( $left['width'] ) ? absint( $left['width'] ) : 0 )
					: $jobsheet_row_height( $left );
				$right_secondary = 'height' === $dimension
					? ( isset( $right['width'] ) ? absint( $right['width'] ) : 0 )
					: $jobsheet_row_height( $right );

				if ( $left_secondary === $right_secondary ) {
					return absint( $left['_display_index'] ?? 0 ) <=> absint( $right['_display_index'] ?? 0 );
				}

				return $right_secondary <=> $left_secondary;
			}

			return $right_value <=> $left_value;
		}
	);

	return $items;
};

$fronts_by_width       = $jobsheet_sort_rows( $fronts, 'width' );
$drawer_banks_by_width = $jobsheet_sort_rows( $drawer_banks, 'width' );
$fronts_by_height      = $jobsheet_sort_rows( $fronts, 'height' );
$drawer_banks_by_height = $jobsheet_sort_rows( $drawer_banks, 'height' );
?>
<div class="qs-pdf qs-jobsheet-pdf">
	<header class="qs-pdf-header">
		<table class="qs-pdf-header-table">
			<tr><td><strong>Loughlin Furniture</strong></td><td><span>Job Sheet</span></td></tr>
		</table>
	</header>

	<main class="qs-pdf-page">
		<table class="qs-pdf-project-header">
			<tr>
				<td>
					<p><strong>Project Name:</strong> <?php echo esc_html( $data['project_name'] ); ?></p>
					<p><strong>Company:</strong> <?php echo esc_html( $data['company_name'] ); ?></p>
					<p><strong>Created By:</strong> <?php echo esc_html( $created_by ); ?></p>
					<p><strong>Pricing Type:</strong> <?php echo esc_html( $data['pricing_type'] ); ?></p>
					<p><strong>Date Created:</strong> <?php echo esc_html( $data['date_created'] ); ?></p>
					<p><strong>Delivery Address:</strong> <?php echo nl2br( esc_html( $data['delivery_address'] ) ); ?></p>
				</td>
				<td class="qs-pdf-quote-number">
					<strong>Quote Number</strong>
					<span><?php echo esc_html( $data['quote_number'] ); ?></span>
				</td>
			</tr>
		</table>

		<section class="qs-pdf-section qs-pdf-project-details">
			<h2>Project Details</h2>
			<?php if ( $has_legacy_specs ) : ?>
				<table class="qs-pdf-specifications">
					<tr>
						<?php foreach ( $specs as $spec ) : ?>
							<td class="qs-pdf-spec-group<?php echo ! empty( $spec[3] ) ? ' has-image' : ''; ?>">
								<table class="qs-pdf-spec-item">
									<tr>
										<td class="qs-pdf-swatch-cell">
											<?php if ( ! empty( $spec[3] ) ) : ?>
												<img class="qs-pdf-swatch-image" src="<?php echo esc_url( $spec[3] ); ?>" alt="">
											<?php else : ?>
												<span class="qs-pdf-swatch-placeholder" aria-hidden="true"></span>
											<?php endif; ?>
										</td>
										<td class="qs-pdf-spec-copy">
											<strong><?php echo esc_html( $spec[0] ); ?>:</strong>
											<span><?php echo esc_html( $spec[1] ? $spec[1] : '-' ); ?></span>
											<?php if ( $spec[2] ) : ?><small><?php echo esc_html( $spec[2] ); ?></small><?php endif; ?>
										</td>
									</tr>
								</table>
							</td>
						<?php endforeach; ?>
					</tr>
				</table>
			<?php endif; ?>
		</section>

		<section class="qs-pdf-section">
			<h2>Doors &amp; Drawers</h2>
			<?php qs_render_quote_component_table( $fronts, array( 'type' => 'Item Type', 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table qs-jobsheet-doors-table' ); ?>
			<?php if ( $drawer_banks ) : ?>
				<h3>Drawer Banks</h3>
				<?php qs_render_quote_component_table( $drawer_banks, array( 'type' => 'Item Type', 'item_specifications' => 'Specifications', 'configuration' => 'Configuration', 'width' => 'Width', 'height_details' => 'Height', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table qs-jobsheet-drawer-bank-table', 'No drawer banks supplied.', count( $fronts ) + 1 ); ?>
			<?php endif; ?>
		</section>

		<section class="qs-pdf-section">
			<h2>End Panels &amp; Fillers</h2>
			<h3>End Panels</h3>
			<p><em>Flat panels / no profile</em></p>
			<?php qs_render_quote_component_table( $rows['end_panels'], array( 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
			<h3>Fillers</h3>
			<?php qs_render_quote_component_table( $rows['fillers'], array( 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
		</section>

		<section class="qs-pdf-section">
			<h2>Kickboards</h2>
			<?php qs_render_quote_component_table( $rows['kickboards'], array( 'material' => 'Kick Material', 'item_specifications' => 'Specifications', 'height' => 'Kick Height', 'length' => 'Kick Length', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
		</section>

		<?php if ( $data['custom_requests'] ) : ?>
			<section class="qs-pdf-section"><h2>Custom Requests</h2><p><?php echo nl2br( esc_html( $data['custom_requests'] ) ); ?></p></section>
		<?php endif; ?>

		<section class="qs-pdf-section qs-pdf-notes">
			<h2>Project Notes</h2>
			<p><?php echo nl2br( esc_html( $data['project_notes'] ? $data['project_notes'] : '-' ) ); ?></p>
		</section>
	</main>

	<?php
	$production_pages = array(
		array(
			'label'        => 'Width',
			'description'  => 'Largest to Smallest',
			'fronts'       => $fronts_by_width,
			'drawer_banks' => $drawer_banks_by_width,
		),
		array(
			'label'        => 'Height',
			'description'  => 'Largest to Smallest',
			'fronts'       => $fronts_by_height,
			'drawer_banks' => $drawer_banks_by_height,
		),
	);
	foreach ( $production_pages as $production_page ) :
		?>
		<div class="qs-jobsheet-sort-page">
			<header class="qs-pdf-header">
				<table class="qs-pdf-header-table">
					<tr>
						<td><strong>Loughlin Furniture</strong></td>
						<td><span>Job Sheet</span></td>
					</tr>
				</table>
			</header>

			<main class="qs-pdf-page">
				<table class="qs-pdf-project-header qs-jobsheet-sort-project-header">
					<tr>
						<td>
							<p><strong>Project Name:</strong> <?php echo esc_html( $data['project_name'] ); ?></p>
							<p><strong>Company:</strong> <?php echo esc_html( $data['company_name'] ); ?></p>
						</td>
						<td class="qs-pdf-quote-number">
							<strong>Quote Number</strong>
							<span><?php echo esc_html( $data['quote_number'] ); ?></span>
						</td>
					</tr>
				</table>

				<section class="qs-pdf-section qs-jobsheet-production-section">
					<h2>Doors &amp; Drawers + Drawer Banks — Sorted by <?php echo esc_html( $production_page['label'] ); ?></h2>
					<p class="qs-jobsheet-sort-note"><?php echo esc_html( $production_page['label'] . ' order: ' . $production_page['description'] ); ?></p>

					<h3>Doors &amp; Drawers</h3>
					<?php
					$doors_columns = 'Width' === $production_page['label']
						? array(
							'type'                => 'Item Type',
							'item_specifications' => 'Specifications',
							'width'               => 'Width',
							'shoulder_length'     => 'Shoulder Length',
							'rail_length'         => 'Rail Length',
							'quantity'            => 'Quantity',
							'notes'               => 'Notes',
						)
						: array(
							'type'                => 'Item Type',
							'item_specifications' => 'Specifications',
							'width'               => 'Width',
							'height'              => 'Height',
							'quantity'            => 'Quantity',
							'notes'               => 'Notes',
						);

					qs_render_quote_component_table(
						$production_page['fronts'],
						$doors_columns,
						'qs-pdf-table qs-jobsheet-doors-table qs-jobsheet-production-table ' . ( 'Width' === $production_page['label'] ? 'qs-jobsheet-production-width-table' : 'qs-jobsheet-production-height-table' )
					);
					?>

					<h3>Drawer Banks</h3>
					<?php
					$drawer_columns = 'Width' === $production_page['label']
						? array(
							'type'                => 'Item Type',
							'item_specifications' => 'Specifications',
							'configuration'       => 'Configuration',
							'width'               => 'Width',
							'shoulder_length'     => 'Shoulder Length',
							'rail_length'         => 'Rail Length',
							'quantity'            => 'Quantity',
							'notes'               => 'Notes',
						)
						: array(
							'type'                => 'Item Type',
							'item_specifications' => 'Specifications',
							'configuration'       => 'Configuration',
							'width'               => 'Width',
							'height_details'      => 'Height',
							'quantity'            => 'Quantity',
							'notes'               => 'Notes',
						);

					qs_render_quote_component_table(
						$production_page['drawer_banks'],
						$drawer_columns,
						'qs-pdf-table qs-jobsheet-drawer-bank-table qs-jobsheet-production-table ' . ( 'Width' === $production_page['label'] ? 'qs-jobsheet-production-width-table' : 'qs-jobsheet-production-height-table' ),
						'No drawer banks supplied.',
						count( $production_page['fronts'] ) + 1
					);
					?>
				</section>
			</main>
		</div>
	<?php endforeach; ?>
</div>
