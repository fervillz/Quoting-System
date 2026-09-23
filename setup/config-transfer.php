<?php
/**
 * Staging-to-live Quote System configuration transfer.
 *
 * Transfers configuration only:
 * - current Quote Product ACF field groups
 * - Quote Product types
 * - Quote Products and their pricing/configuration meta
 *
 * It intentionally does NOT transfer Quotes, WooCommerce orders, users,
 * quote-number sequences, or test/payment data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QS_CONFIG_EXPORT_SCHEMA' ) ) {
	define( 'QS_CONFIG_EXPORT_SCHEMA', 1 );
}

function qs_config_transfer_group_targets_quote_products( $group ) {
	foreach ( isset( $group['location'] ) ? (array) $group['location'] : array() as $and_group ) {
		foreach ( (array) $and_group as $rule ) {
			if (
				is_array( $rule ) &&
				'post_type' === ( isset( $rule['param'] ) ? $rule['param'] : '' ) &&
				'==' === ( isset( $rule['operator'] ) ? $rule['operator'] : '' ) &&
				'quote_products' === ( isset( $rule['value'] ) ? $rule['value'] : '' )
			) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Capture the ACF definition ACF is actually using now, rather than assuming
 * the original bundled JSON is still the source of truth.
 */
function qs_config_transfer_export_acf_groups() {
	$groups = array();

	if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
		foreach ( (array) acf_get_field_groups() as $group ) {
			if ( ! is_array( $group ) || ! qs_config_transfer_group_targets_quote_products( $group ) ) {
				continue;
			}

			$fields = acf_get_fields( $group );
			if ( is_array( $fields ) ) {
				$group['fields'] = $fields;
			}

			foreach ( array( 'ID', 'local', '_valid', 'modified', 'private' ) as $runtime_key ) {
				unset( $group[ $runtime_key ] );
			}

			$groups[] = $group;
		}
	}

	return $groups ? $groups : qs_setup_acf_field_groups();
}

function qs_config_transfer_export_terms() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'quote_product_type',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$by_id = array();
	foreach ( $terms as $term ) {
		$by_id[ (int) $term->term_id ] = $term->slug;
	}

	$output = array();
	foreach ( $terms as $term ) {
		$output[] = array(
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent_slug' => $term->parent && isset( $by_id[ (int) $term->parent ] ) ? $by_id[ (int) $term->parent ] : '',
		);
	}

	return $output;
}

function qs_config_transfer_meta_is_ephemeral( $key ) {
	return in_array(
		(string) $key,
		array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_thumbnail_id',
			'image',
			'_image',
		),
		true
	);
}

/**
 * Export post meta as decoded values so arrays/repeaters survive JSON cleanly.
 * Media IDs are omitted because attachment IDs are site-specific.
 */
function qs_config_transfer_export_product_meta( $post_id ) {
	$all_meta = get_post_meta( $post_id );
	$output   = array();

	foreach ( $all_meta as $key => $values ) {
		if ( qs_config_transfer_meta_is_ephemeral( $key ) ) {
			continue;
		}

		$output[ $key ] = array_map( 'maybe_unserialize', (array) $values );
	}

	return $output;
}

function qs_config_transfer_export_products() {
	$posts = get_posts(
		array(
			'post_type'      => 'quote_products',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'order'          => 'ASC',
		)
	);

	$products = array();
	foreach ( $posts as $post ) {
		$terms = wp_get_object_terms( $post->ID, 'quote_product_type', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$products[] = array(
			'source_id'  => (int) $post->ID,
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'status'     => $post->post_status,
			'content'    => $post->post_content,
			'excerpt'    => $post->post_excerpt,
			'menu_order' => (int) $post->menu_order,
			'types'      => array_values( array_map( 'sanitize_title', (array) $terms ) ),
			'meta'       => qs_config_transfer_export_product_meta( $post->ID ),
		);
	}

	return $products;
}

function qs_config_transfer_build_export() {
	return array(
		'kind'          => 'loughlin-quote-system-config',
		'schema'        => QS_CONFIG_EXPORT_SCHEMA,
		'plugin_version'=> defined( 'QS_VERSION' ) ? QS_VERSION : '',
		'generated_at'  => current_time( 'mysql' ),
		'source_site'   => home_url( '/' ),
		'includes'      => array(
			'acf_groups'          => true,
			'quote_product_types' => true,
			'quote_products'      => true,
			'media_files'         => false,
			'quotes'              => false,
			'orders'              => false,
			'users'               => false,
		),
		'acf_groups'          => qs_config_transfer_export_acf_groups(),
		'quote_product_types' => qs_config_transfer_export_terms(),
		'quote_products'      => qs_config_transfer_export_products(),
	);
}

function qs_config_transfer_handle_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export Quote System configuration.', 'quote-system' ), 403 );
	}

	check_admin_referer( 'qs_export_configuration' );

	$data = qs_config_transfer_build_export();
	$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( false === $json ) {
		wp_die( esc_html__( 'Unable to encode Quote System configuration.', 'quote-system' ) );
	}

	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$host = $host ? sanitize_file_name( $host ) : 'site';
	$name = sprintf( 'quote-system-config-%s-%s.json', $host, gmdate( 'Y-m-d-His' ) );

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . strlen( $json ) );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'admin_post_qs_export_configuration', 'qs_config_transfer_handle_export' );

function qs_config_transfer_import_terms( $items ) {
	$term_ids = array();

	foreach ( (array) $items as $item ) {
		if ( ! is_array( $item ) || empty( $item['slug'] ) || empty( $item['name'] ) ) {
			continue;
		}

		$slug = sanitize_title( $item['slug'] );
		$term = get_term_by( 'slug', $slug, 'quote_product_type' );
		if ( ! $term ) {
			$result = wp_insert_term(
				sanitize_text_field( $item['name'] ),
				'quote_product_type',
				array(
					'slug'        => $slug,
					'description' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '',
				)
			);
			if ( is_wp_error( $result ) ) {
				continue;
			}
			$term_ids[ $slug ] = (int) $result['term_id'];
		} else {
			$term_ids[ $slug ] = (int) $term->term_id;
			wp_update_term(
				$term->term_id,
				'quote_product_type',
				array(
					'name'        => sanitize_text_field( $item['name'] ),
					'description' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '',
				)
			);
		}
	}

	foreach ( (array) $items as $item ) {
		$slug        = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
		$parent_slug = isset( $item['parent_slug'] ) ? sanitize_title( $item['parent_slug'] ) : '';
		if ( ! $slug || ! isset( $term_ids[ $slug ] ) || ! $parent_slug || ! isset( $term_ids[ $parent_slug ] ) ) {
			continue;
		}

		wp_update_term(
			$term_ids[ $slug ],
			'quote_product_type',
			array( 'parent' => $term_ids[ $parent_slug ] )
		);
	}

	return $term_ids;
}

function qs_config_transfer_first_meta_value( $meta, $key ) {
	if ( ! isset( $meta[ $key ] ) || ! is_array( $meta[ $key ] ) || ! array_key_exists( 0, $meta[ $key ] ) ) {
		return '';
	}

	return $meta[ $key ][0];
}

function qs_config_transfer_find_existing_product( $product ) {
	$meta = isset( $product['meta'] ) && is_array( $product['meta'] ) ? $product['meta'] : array();
	$code = trim( (string) qs_config_transfer_first_meta_value( $meta, 'quote_product_code' ) );

	if ( '' !== $code ) {
		$matches = get_posts(
			array(
				'post_type'      => 'quote_products',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 1,
				'meta_key'       => 'quote_product_code',
				'meta_value'     => $code,
				'fields'         => 'ids',
			)
		);
		if ( $matches ) {
			return (int) $matches[0];
		}
	}

	$title = isset( $product['title'] ) ? sanitize_text_field( $product['title'] ) : '';
	$types = isset( $product['types'] ) ? array_values( (array) $product['types'] ) : array();
	if ( $title && $types && function_exists( 'qs_setup_find_product' ) ) {
		$found = qs_setup_find_product( $title, sanitize_title( $types[0] ) );
		if ( $found ) {
			return $found;
		}
	}

	return 0;
}

function qs_config_transfer_status( $status ) {
	return in_array( $status, array( 'publish', 'draft', 'private', 'pending' ), true ) ? $status : 'publish';
}

function qs_config_transfer_remap_relationship_value( $value, $id_map ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $child ) {
			$value[ $key ] = qs_config_transfer_remap_relationship_value( $child, $id_map );
		}
		return $value;
	}

	if ( is_numeric( $value ) ) {
		$source_id = absint( $value );
		if ( $source_id && isset( $id_map[ $source_id ] ) ) {
			return (int) $id_map[ $source_id ];
		}
	}

	return $value;
}

function qs_config_transfer_is_relationship_meta( $key ) {
	return in_array( $key, array( 'pricing_matrix_source', 'pricing_matrix_copy' ), true )
		|| (bool) preg_match( '/^pricing_adjustments_[0-9]+_product$/', (string) $key );
}

/**
 * Clear only Quote Product configuration meta. WordPress/system-private meta
 * is preserved, including thumbnails/media, because those belong to this site.
 */
function qs_config_transfer_clear_product_configuration_meta( $post_id ) {
	$meta = get_post_meta( $post_id );

	foreach ( array_keys( $meta ) as $key ) {
		if ( qs_config_transfer_meta_is_ephemeral( $key ) ) {
			continue;
		}

		if ( 0 !== strpos( $key, '_' ) ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		$public_key = substr( $key, 1 );
		if ( $public_key && array_key_exists( $public_key, $meta ) ) {
			delete_post_meta( $post_id, $key );
		}
	}
}

function qs_config_transfer_import_products( $products ) {
	$id_map  = array();
	$records = array();
	$created = 0;
	$updated = 0;

	// Pass 1: create/update posts and terms so relationship IDs can be remapped.
	foreach ( (array) $products as $product ) {
		if ( ! is_array( $product ) || empty( $product['title'] ) ) {
			continue;
		}

		$product_id = qs_config_transfer_find_existing_product( $product );
		$post_data  = array(
			'post_type'    => 'quote_products',
			'post_status'  => qs_config_transfer_status( isset( $product['status'] ) ? $product['status'] : 'publish' ),
			'post_title'   => sanitize_text_field( $product['title'] ),
			'post_name'    => isset( $product['slug'] ) ? sanitize_title( $product['slug'] ) : '',
			'post_content' => isset( $product['content'] ) ? wp_kses_post( $product['content'] ) : '',
			'post_excerpt' => isset( $product['excerpt'] ) ? sanitize_textarea_field( $product['excerpt'] ) : '',
			'menu_order'   => isset( $product['menu_order'] ) ? intval( $product['menu_order'] ) : 0,
		);

		if ( $product_id ) {
			$post_data['ID'] = $product_id;
			$result = wp_update_post( $post_data, true );
			$updated++;
		} else {
			$result = wp_insert_post( $post_data, true );
			$created++;
		}

		if ( is_wp_error( $result ) || ! $result ) {
			continue;
		}

		$product_id = (int) $result;
		$types      = array_values( array_filter( array_map( 'sanitize_title', isset( $product['types'] ) ? (array) $product['types'] : array() ) ) );
		if ( $types ) {
			wp_set_object_terms( $product_id, $types, 'quote_product_type', false );
		}

		$source_id = isset( $product['source_id'] ) ? absint( $product['source_id'] ) : 0;
		if ( $source_id ) {
			$id_map[ $source_id ] = $product_id;
		}
		$records[] = array( 'post_id' => $product_id, 'product' => $product );
	}

	// Pass 2: replace configuration meta and remap product relationships.
	foreach ( $records as $record ) {
		$product_id = (int) $record['post_id'];
		$product    = $record['product'];
		$meta       = isset( $product['meta'] ) && is_array( $product['meta'] ) ? $product['meta'] : array();

		qs_config_transfer_clear_product_configuration_meta( $product_id );

		foreach ( $meta as $key => $values ) {
			$key = sanitize_key( $key );
			if ( ! $key || qs_config_transfer_meta_is_ephemeral( $key ) ) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				if ( qs_config_transfer_is_relationship_meta( $key ) ) {
					$value = qs_config_transfer_remap_relationship_value( $value, $id_map );
				}
				add_post_meta( $product_id, $key, $value );
			}
		}
	}

	return array(
		'created' => $created,
		'updated' => $updated,
		'total'   => count( $records ),
	);
}

function qs_config_transfer_import_data( $data ) {
	if ( ! is_array( $data ) || 'loughlin-quote-system-config' !== ( isset( $data['kind'] ) ? $data['kind'] : '' ) ) {
		return new WP_Error( 'qs_config_invalid', 'That file is not a Quote System configuration export.' );
	}

	$schema = isset( $data['schema'] ) ? absint( $data['schema'] ) : 0;
	if ( ! $schema || $schema > QS_CONFIG_EXPORT_SCHEMA ) {
		return new WP_Error( 'qs_config_schema', 'This configuration file uses an unsupported schema version.' );
	}

	$pages = qs_setup_install_pages();
	if ( is_wp_error( $pages ) ) {
		return $pages;
	}

	if ( ! empty( $data['acf_groups'] ) && is_array( $data['acf_groups'] ) ) {
		update_option( 'qs_imported_acf_field_groups', $data['acf_groups'], false );
		update_option( 'qs_acf_fields_installed', '1', false );
		if ( function_exists( 'acf_add_local_field_group' ) ) {
			qs_setup_register_acf_fields();
		}
	}

	qs_config_transfer_import_terms( isset( $data['quote_product_types'] ) ? $data['quote_product_types'] : array() );
	$products = qs_config_transfer_import_products( isset( $data['quote_products'] ) ? $data['quote_products'] : array() );

	update_option( 'qs_default_data_version', QS_DEFAULT_DATA_VERSION, false );
	update_option( 'qs_configuration_source', 'import', false );
	update_option( 'qs_configuration_imported_at', current_time( 'mysql' ), false );
	update_option( 'qs_configuration_imported_from', isset( $data['source_site'] ) ? esc_url_raw( $data['source_site'] ) : '', false );

	$status = qs_setup_status();
	if ( $status['complete'] ) {
		update_option( 'qs_setup_completed_version', QS_SETUP_VERSION, false );
	}

	return array(
		'pages'    => $pages,
		'products' => $products,
		'complete' => $status['complete'],
	);
}

function qs_config_transfer_notice_key() {
	return 'qs_config_transfer_notice_' . get_current_user_id();
}

function qs_config_transfer_handle_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to import Quote System configuration.', 'quote-system' ), 403 );
	}

	check_admin_referer( 'qs_import_configuration' );

	$redirect = admin_url( 'edit.php?post_type=quote&page=qs-setup' );
	if ( empty( $_FILES['qs_configuration_file'] ) || ! is_array( $_FILES['qs_configuration_file'] ) ) {
		set_transient( qs_config_transfer_notice_key(), array( 'type' => 'error', 'message' => 'Choose a Quote System configuration JSON file first.' ), 60 );
		wp_safe_redirect( $redirect );
		exit;
	}

	$file = $_FILES['qs_configuration_file'];
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		set_transient( qs_config_transfer_notice_key(), array( 'type' => 'error', 'message' => 'The configuration upload failed.' ), 60 );
		wp_safe_redirect( $redirect );
		exit;
	}

	if ( (int) $file['size'] > 20 * MB_IN_BYTES ) {
		set_transient( qs_config_transfer_notice_key(), array( 'type' => 'error', 'message' => 'The configuration file is larger than the 20 MB safety limit.' ), 60 );
		wp_safe_redirect( $redirect );
		exit;
	}

	$json = file_get_contents( $file['tmp_name'] );
	$data = is_string( $json ) ? json_decode( $json, true ) : null;
	if ( ! is_array( $data ) ) {
		set_transient( qs_config_transfer_notice_key(), array( 'type' => 'error', 'message' => 'The uploaded file is not valid JSON.' ), 60 );
		wp_safe_redirect( $redirect );
		exit;
	}

	$result = qs_config_transfer_import_data( $data );
	if ( is_wp_error( $result ) ) {
		set_transient( qs_config_transfer_notice_key(), array( 'type' => 'error', 'message' => $result->get_error_message() ), 60 );
		wp_safe_redirect( $redirect );
		exit;
	}

	$product_counts = $result['products'];
	$message = sprintf(
		'Configuration imported. %d Quote Products created, %d updated. %s',
		(int) $product_counts['created'],
		(int) $product_counts['updated'],
		$result['complete'] ? 'Quote System setup is complete.' : 'Configuration is installed, but a required dependency still needs attention.'
	);

	set_transient( qs_config_transfer_notice_key(), array( 'type' => 'success', 'message' => $message ), 60 );
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_qs_import_configuration', 'qs_config_transfer_handle_import' );

function qs_config_transfer_render_setup_panel() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice = get_transient( qs_config_transfer_notice_key() );
	if ( $notice ) {
		delete_transient( qs_config_transfer_notice_key() );
		$class = 'success' === ( isset( $notice['type'] ) ? $notice['type'] : '' ) ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' inline"><p>' . esc_html( isset( $notice['message'] ) ? $notice['message'] : '' ) . '</p></div>';
	}

	$source       = (string) get_option( 'qs_configuration_source', '' );
	$imported_at  = (string) get_option( 'qs_configuration_imported_at', '' );
	$imported_from= (string) get_option( 'qs_configuration_imported_from', '' );
	?>
	<section class="qs-setup-transfer">
		<h2>Staging → Live Configuration</h2>
		<p><strong>Use this for the live launch.</strong> Export the configuration from the approved staging site, then import that JSON on live. This uses the current working Quote Products and ACF structure as the source of truth instead of the older bundled starter data.</p>

		<div class="qs-setup-transfer-grid">
			<div>
				<h3>1. Export Current Configuration</h3>
				<p>Exports current Quote Product types, Quote Products, pricing/meta values and the current Quote Product ACF field-group definition.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="qs_export_configuration">
					<?php wp_nonce_field( 'qs_export_configuration' ); ?>
					<button type="submit" class="button button-secondary">Export Current Configuration</button>
				</form>
			</div>

			<div>
				<h3>2. Import &amp; Configure Live</h3>
				<p>Creates/checks required Quote System pages, installs the exported ACF snapshot and syncs Quote Product configuration. Existing Quotes, WooCommerce orders, users and quote-number history are untouched.</p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="qs_import_configuration">
					<?php wp_nonce_field( 'qs_import_configuration' ); ?>
					<input type="file" name="qs_configuration_file" accept=".json,application/json" required>
					<button type="submit" class="button button-primary" onclick="return confirm('Import this Quote System configuration? Quote Product configuration/pricing will be updated to match the file. Existing quotes, orders and users will not be changed.');">Import &amp; Configure</button>
				</form>
			</div>
		</div>

		<p class="description"><strong>Not transferred:</strong> Quotes, drafts, WooCommerce orders, Joiner/admin users, quote-number sequence and WordPress media files. Product media already present on live is preserved.</p>
		<?php if ( 'import' === $source && $imported_at ) : ?>
			<p class="description"><strong>Last imported:</strong> <?php echo esc_html( $imported_at ); ?><?php echo $imported_from ? ' from ' . esc_html( $imported_from ) : ''; ?></p>
		<?php endif; ?>
	</section>
	<?php
}
add_action( 'qs_setup_after_grid', 'qs_config_transfer_render_setup_panel' );
