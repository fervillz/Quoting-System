<?php
/**
 * Bundled starter configuration for Quote System.
 *
 * Setup data lives in setup/data as normal JSON so it is easy to inspect,
 * update and validate. No compression or zlib extension is required.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read one bundled JSON data file. */
function qs_setup_read_json_file( $filename ) {
	$filename = basename( (string) $filename );
	$path     = QS_PATH . 'setup/data/' . $filename;

	if ( ! is_readable( $path ) ) {
		return array();
	}

	$json = file_get_contents( $path );
	if ( false === $json || '' === trim( $json ) ) {
		return array();
	}

	$data = json_decode( $json, true );
	return is_array( $data ) ? $data : array();
}

/**
 * Return the starter Quote Products in the flat post-meta shape expected by
 * the installer. The JSON keeps the repeated matrix grid only once to avoid
 * duplicating thousands of height/width values in the plugin package.
 */
function qs_setup_default_products() {
	$bundle = qs_setup_read_json_file( 'quote-products.json' );
	if ( empty( $bundle['products'] ) || ! is_array( $bundle['products'] ) ) {
		return array();
	}

	$global_defaults = isset( $bundle['meta_defaults'] ) && is_array( $bundle['meta_defaults'] )
		? $bundle['meta_defaults']
		: array();
	$type_defaults   = isset( $bundle['type_defaults'] ) && is_array( $bundle['type_defaults'] )
		? $bundle['type_defaults']
		: array();
	$matrix_grid     = isset( $bundle['matrix_grid'] ) && is_array( $bundle['matrix_grid'] )
		? $bundle['matrix_grid']
		: array();
	$products        = array();

	foreach ( $bundle['products'] as $product ) {
		if ( ! is_array( $product ) || empty( $product['title'] ) || empty( $product['type'] ) ) {
			continue;
		}

		$type = sanitize_title( $product['type'] );
		$meta = $global_defaults;
		if ( isset( $type_defaults[ $type ] ) && is_array( $type_defaults[ $type ] ) ) {
			$meta = array_merge( $meta, $type_defaults[ $type ] );
		}
		if ( isset( $product['meta'] ) && is_array( $product['meta'] ) ) {
			$meta = array_merge( $meta, $product['meta'] );
		}

		if ( isset( $product['matrix_prices'] ) && is_array( $product['matrix_prices'] ) ) {
			$prices = array_values( $product['matrix_prices'] );
			$count  = min( count( $matrix_grid ), count( $prices ) );
			$meta['pricing_matrix'] = (string) $count;

			for ( $index = 0; $index < $count; $index++ ) {
				$range = isset( $matrix_grid[ $index ] ) && is_array( $matrix_grid[ $index ] )
					? array_values( $matrix_grid[ $index ] )
					: array();
				if ( count( $range ) < 4 ) {
					continue;
				}

				$prefix = 'pricing_matrix_' . $index . '_';
				$meta[ $prefix . 'height_min' ] = $range[0];
				$meta[ $prefix . 'height_max' ] = $range[1];
				$meta[ $prefix . 'width_min' ]  = $range[2];
				$meta[ $prefix . 'width_max' ]  = $range[3];
				$meta[ $prefix . 'price' ]      = $prices[ $index ];
			}
		}

		if ( isset( $product['linear_pricing'] ) && is_array( $product['linear_pricing'] ) ) {
			$rows = array_values( $product['linear_pricing'] );
			$meta['linear_pricing'] = (string) count( $rows );
			foreach ( $rows as $index => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$prefix = 'linear_pricing_' . $index . '_';
				foreach ( array( 'height_min', 'height_max', 'price_per_lm' ) as $field ) {
					if ( array_key_exists( $field, $row ) ) {
						$meta[ $prefix . $field ] = $row[ $field ];
					}
				}
			}
		}

		$products[] = array(
			'title' => sanitize_text_field( $product['title'] ),
			'type'  => $type,
			'meta'  => $meta,
		);
	}

	return $products;
}

/** Return the bundled ACF field group used to edit Quote Product pricing. */
function qs_setup_acf_field_groups() {
	$data = qs_setup_read_json_file( 'acf-quote-product-pricing.json' );
	return is_array( $data ) ? $data : array();
}
