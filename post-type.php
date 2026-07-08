<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Quote Post Type
 */
function qs_register_post_type() {

	$labels = array(
		'name'               => 'Quotes',
		'singular_name'      => 'Quote',
		'menu_name'          => 'Quotes',
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

	$args = array(
		'labels'             => $labels,
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

	register_post_type( 'quote', $args );
}

add_action( 'init', 'qs_register_post_type' );