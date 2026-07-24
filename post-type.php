<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Quote & Quote Product Post Types
 */
function qs_register_post_type() {

	/*
	|--------------------------------------------------------------------------
	| Quotes
	|--------------------------------------------------------------------------
	*/

	$quote_labels = array(
		'name'               => 'Quotes',
		'singular_name'      => 'Quote',
		'menu_name'          => 'Quote System',
		'name_admin_bar'     => 'Quote',
		'add_new'            => 'Add Quote',
		'add_new_item'       => 'Add New Quote',
		'edit_item'          => 'Edit Quote',
		'new_item'           => 'New Quote',
		'view_item'          => 'View Quote',
		'search_items'       => 'Search Quotes',
		'not_found'          => 'No quotes found',
		'not_found_in_trash' => 'No quotes found in Trash',
	);

	$quote_args = array(
		'labels'             => $quote_labels,
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'menu_position'      => 21,
		'menu_icon'          => 'dashicons-media-spreadsheet',
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => false,
		'supports'           => array(
			'title',
			'author',
		),
		'show_in_rest'       => false,
	);

	register_post_type( 'quote', $quote_args );

	/*
	|--------------------------------------------------------------------------
	| Quote Products
	|--------------------------------------------------------------------------
	*/

	$product_labels = array(
		'name'               => 'Quote Products',
		'singular_name'      => 'Quote Product',
		'menu_name'          => 'Products',
		'name_admin_bar'     => 'Quote Product',
		'add_new'            => 'Add Product',
		'add_new_item'       => 'Add New Product',
		'edit_item'          => 'Edit Product',
		'new_item'           => 'New Product',
		'view_item'          => 'View Product',
		'search_items'       => 'Search Products',
		'not_found'          => 'No products found',
		'not_found_in_trash' => 'No products found in Trash',
	);

	$product_args = array(
		'labels'             => $product_labels,
		'public'             => false,
		'show_ui'            => true,

		// Show under Quote System
		'show_in_menu'       => 'edit.php?post_type=quote',

		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => false,

		'supports'           => array(
			'title',
		),

		'show_in_rest'       => false,
	);

	register_post_type( 'quote_products', $product_args );
}

add_action( 'init', 'qs_register_post_type' );