<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Add a clear entry point to the Quote Product price records. */
function qs_add_pricing_settings_page() {
	add_submenu_page(
		'edit.php?post_type=quote',
		'Quote Product Pricing',
		'Quote Pricing',
		'manage_options',
		'quote-pricing',
		'qs_render_pricing_settings_page'
	);
}
add_action( 'admin_menu', 'qs_add_pricing_settings_page' );

/**
 * Render an overview instead of the obsolete global-rate form.
 *
 * Every editable value now lives on its Quote Product. This page deliberately
 * performs no writes so it cannot override the product matrices.
 */
function qs_render_pricing_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$types = get_terms(
		array(
			'taxonomy'   => 'quote_product_type',
			'hide_empty' => false,
		)
	);
	?>
	<div class="wrap">
		<h1>Quote Product Pricing</h1>
		<p>
			Quote totals now use the pricing method and values saved on each
			<strong>Quote Product</strong>. The old global door, drawer and
			surcharge rates are no longer used.
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=quote_products' ) ); ?>">Manage Quote Products</a>
		</p>

		<h2>Pricing rules in use</h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th>Component</th><th>Price source</th></tr></thead>
			<tbody>
				<tr><td>Doors and drawer fronts</td><td>The selected Door Profile matrix</td></tr>
				<tr><td>Drawer banks</td><td>The selected Door Profile matrix for each drawer front</td></tr>
				<tr><td>End panels and fillers</td><td>The Evans pricing matrix</td></tr>
				<tr><td>Painted items</td><td>The Painted matrix when a paint colour is supplied</td></tr>
				<tr><td>Finger Pull</td><td>Fixed price per panel for Evans, Valley and 30 Shaker</td></tr>
				<tr><td>Kickboards</td><td>The selected kickboard material and height band, per linear metre</td></tr>
				<tr><td>Timber and finish</td><td>Fixed or percentage adjustment, including Walnut +10% and Raw -10%</td></tr>
				<tr><td>Retail quotes</td><td>Trade subtotal + 22.22%</td></tr>
			</tbody>
		</table>

		<?php if ( ! is_wp_error( $types ) && $types ) : ?>
			<h2>Product types</h2>
			<ul>
				<?php foreach ( $types as $type ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'quote_products', 'quote_product_type' => $type->slug ), admin_url( 'edit.php' ) ) ); ?>">
							<?php echo esc_html( $type->name ); ?>
						</a>
						(<?php echo esc_html( $type->count ); ?>)
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
