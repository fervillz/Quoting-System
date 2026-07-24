<?php

/*
|--------------------------------------------------------------------------
| Quote Product Types
|--------------------------------------------------------------------------
*/

$product_type_labels = array(
	'name'              => 'Product Types',
	'singular_name'     => 'Product Type',
	'search_items'      => 'Search Product Types',
	'all_items'         => 'All Product Types',
	'parent_item'       => 'Parent Product Type',
	'parent_item_colon' => 'Parent Product Type:',
	'edit_item'         => 'Edit Product Type',
	'update_item'       => 'Update Product Type',
	'add_new_item'      => 'Add New Product Type',
	'new_item_name'     => 'New Product Type',
	'menu_name'         => 'Product Types',
);

$product_type_args = array(
	'hierarchical'      => true,
	'labels'            => $product_type_labels,
	'show_ui'           => true,
	'show_admin_column' => true,
	'query_var'         => true,
	'rewrite'           => false,
	'show_in_rest'      => false,
);

register_taxonomy(
	'quote_product_type',
	array( 'quote_products' ),
	$product_type_args
);

/**
 * Create default Quote Product Types
 */
function qs_create_default_product_types() {

	$terms = array(
		'Door Profile',
		'Timber',
		'Finish',
		'Modifier',
		'Kickboard',
		'Paint',
		'Accessory',
	);

	foreach ( $terms as $term ) {

		if ( ! term_exists( $term, 'quote_product_type' ) ) {

			wp_insert_term(
				$term,
				'quote_product_type'
			);

		}

	}

}