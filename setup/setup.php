<?php
/**
 * First-run setup and system-status screen.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QS_SETUP_VERSION' ) ) {
	define( 'QS_SETUP_VERSION', '1.0' );
}
if ( ! defined( 'QS_DEFAULT_DATA_VERSION' ) ) {
	define( 'QS_DEFAULT_DATA_VERSION', '1.0' );
}

function qs_setup_page_definitions() {
	return array(
		'login' => array(
			'title'     => 'Quote Login',
			'slug'      => 'quote-login',
			'shortcode' => 'quote_login',
		),
		'my_quotes' => array(
			'title'     => 'My Quotes',
			'slug'      => 'my-quotes',
			'shortcode' => 'my_quotes',
		),
		'quote_builder' => array(
			'title'     => 'Quote Builder',
			'slug'      => 'quote-builder',
			'shortcode' => 'quote_builder',
		),
		'quote_review' => array(
			'title'     => 'Quote Review',
			'slug'      => 'quote-review',
			'shortcode' => 'quote_review',
		),
		'quote_submitted' => array(
			'title'     => 'Quote Submitted',
			'slug'      => 'quote-submitted',
			'shortcode' => 'quote_submitted',
		),
		'admin_dashboard' => array(
			'title'     => 'Quote Admin Dashboard',
			'slug'      => 'quote-admin-dashboard',
			'shortcode' => 'quote_admin_dashboard',
		),
	);
}

function qs_setup_page_option_name( $key ) {
	return 'qs_page_' . sanitize_key( $key );
}

function qs_setup_get_page_id( $key ) {
	$definitions = qs_setup_page_definitions();
	if ( ! isset( $definitions[ $key ] ) ) {
		return 0;
	}

	$page_id = absint( get_option( qs_setup_page_option_name( $key ), 0 ) );
	if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
		return $page_id;
	}

	$page = get_page_by_path( $definitions[ $key ]['slug'], OBJECT, 'page' );
	if ( $page && 'trash' !== $page->post_status ) {
		update_option( qs_setup_page_option_name( $key ), (int) $page->ID, false );
		return (int) $page->ID;
	}

	return 0;
}

/**
 * Use this helper for new links instead of hard-coding a page slug.
 */
function qs_page_url( $key, $args = array() ) {
	$page_id = qs_setup_get_page_id( $key );
	$url     = $page_id ? get_permalink( $page_id ) : '';

	if ( ! $url ) {
		$definitions = qs_setup_page_definitions();
		$url = isset( $definitions[ $key ] ) ? site_url( '/' . $definitions[ $key ]['slug'] . '/' ) : home_url( '/' );
	}

	return $args ? add_query_arg( $args, $url ) : $url;
}

function qs_setup_acf_pro_active() {
	return function_exists( 'acf_add_local_field_group' ) && defined( 'ACF_PRO' ) && ACF_PRO;
}

function qs_setup_woocommerce_active() {
	return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
}

function qs_setup_acf_fields_ready() {
	if ( get_option( 'qs_acf_fields_installed' ) ) {
		return true;
	}

	if ( function_exists( 'acf_get_field_group' ) ) {
		$group = acf_get_field_group( 'group_6a5da19d89d02' );
		return is_array( $group ) && ! empty( $group['key'] );
	}

	return false;
}

function qs_setup_pages_ready() {
	foreach ( qs_setup_page_definitions() as $key => $definition ) {
		$page_id = qs_setup_get_page_id( $key );
		if ( ! $page_id ) {
			return false;
		}
		$page = get_post( $page_id );
		if ( ! $page || ! has_shortcode( (string) $page->post_content, $definition['shortcode'] ) ) {
			return false;
		}
	}
	return true;
}

function qs_setup_status() {
	$status = array(
		'acf_pro'      => qs_setup_acf_pro_active(),
		'acf_fields'   => qs_setup_acf_fields_ready(),
		'woocommerce'  => qs_setup_woocommerce_active(),
		'pages'        => qs_setup_pages_ready(),
		'default_data' => QS_DEFAULT_DATA_VERSION === (string) get_option( 'qs_default_data_version', '' ),
	);
	$status['complete'] = ! in_array( false, $status, true );
	return $status;
}

/** Register the bundled ACF group after the setup button has enabled it. */
function qs_setup_register_acf_fields() {
	if ( ! get_option( 'qs_acf_fields_installed' ) || ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	foreach ( qs_setup_acf_field_groups() as $group ) {
		if ( is_array( $group ) && ! empty( $group['key'] ) ) {
			acf_add_local_field_group( $group );
		}
	}
}
add_action( 'acf/init', 'qs_setup_register_acf_fields', 5 );

/**
 * Map field names to ACF field keys so seeded normal post meta also displays in
 * the ACF editor. Repeater row references are derived from their parent field.
 */
function qs_setup_acf_reference_maps() {
	$maps = array( 'top' => array(), 'repeaters' => array() );
	foreach ( qs_setup_acf_field_groups() as $group ) {
		foreach ( isset( $group['fields'] ) ? (array) $group['fields'] : array() as $field ) {
			if ( empty( $field['name'] ) || empty( $field['key'] ) ) {
				continue;
			}
			$maps['top'][ $field['name'] ] = $field['key'];
			if ( 'repeater' === ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
				$maps['repeaters'][ $field['name'] ] = array();
				foreach ( isset( $field['sub_fields'] ) ? (array) $field['sub_fields'] : array() as $sub_field ) {
					if ( ! empty( $sub_field['name'] ) && ! empty( $sub_field['key'] ) ) {
						$maps['repeaters'][ $field['name'] ][ $sub_field['name'] ] = $sub_field['key'];
					}
				}
			}
		}
	}
	return $maps;
}

function qs_setup_acf_reference_for_meta( $meta_key, $maps ) {
	if ( isset( $maps['top'][ $meta_key ] ) ) {
		return $maps['top'][ $meta_key ];
	}

	if ( preg_match( '/^([a-z0-9_]+)_([0-9]+)_([a-z0-9_]+)$/i', $meta_key, $matches ) ) {
		$repeater = $matches[1];
		$sub      = $matches[3];
		if ( isset( $maps['repeaters'][ $repeater ][ $sub ] ) ) {
			return $maps['repeaters'][ $repeater ][ $sub ];
		}
	}

	return '';
}

function qs_setup_install_pages() {
	$created = 0;
	$reused  = 0;

	foreach ( qs_setup_page_definitions() as $key => $definition ) {
		$page_id = qs_setup_get_page_id( $key );
		$content = '[' . $definition['shortcode'] . ']';

		if ( ! $page_id ) {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $definition['title'],
					'post_name'    => $definition['slug'],
					'post_content' => $content,
				),
				true
			);
			if ( is_wp_error( $page_id ) ) {
				return $page_id;
			}
			$created++;
		} else {
			$page = get_post( $page_id );
			if ( $page && ! has_shortcode( (string) $page->post_content, $definition['shortcode'] ) ) {
				$updated = wp_update_post(
					array(
						'ID'           => $page_id,
						'post_content' => trim( (string) $page->post_content ) . "\n\n" . $content,
					),
					true
				);
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}
			$reused++;
		}

		update_option( qs_setup_page_option_name( $key ), (int) $page_id, false );
	}

	flush_rewrite_rules();
	update_option( 'qs_pages_installed_version', QS_SETUP_VERSION, false );
	return sprintf( 'Pages ready: %d created, %d reused.', $created, $reused );
}

function qs_setup_install_acf_fields() {
	if ( ! qs_setup_acf_pro_active() ) {
		return new WP_Error( 'qs_acf_missing', 'ACF Pro must be installed and active before the Quote Product fields can be enabled.' );
	}

	update_option( 'qs_acf_fields_installed', '1', false );
	qs_setup_register_acf_fields();
	return 'Quote Product ACF fields enabled.';
}

function qs_setup_find_product( $title, $type_slug ) {
	$posts = get_posts(
		array(
			'post_type'              => 'quote_products',
			'post_status'            => array( 'publish', 'draft', 'private' ),
			'posts_per_page'         => -1,
			's'                      => $title,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy' => 'quote_product_type',
					'field'    => 'slug',
					'terms'    => $type_slug,
				),
			),
		)
	);

	foreach ( $posts as $post ) {
		if ( 0 === strcasecmp( trim( $post->post_title ), trim( $title ) ) ) {
			return (int) $post->ID;
		}
	}
	return 0;
}

function qs_setup_install_default_data() {
	qs_create_default_product_types();
	$products = qs_setup_default_products();
	if ( ! $products ) {
		return new WP_Error( 'qs_defaults_missing', 'The bundled default pricing data could not be read.' );
	}

	$acf_maps = qs_setup_acf_reference_maps();
	$created  = 0;
	$updated  = 0;

	foreach ( $products as $product ) {
		$title = isset( $product['title'] ) ? sanitize_text_field( $product['title'] ) : '';
		$type  = isset( $product['type'] ) ? sanitize_title( $product['type'] ) : '';
		if ( ! $title || ! $type ) {
			continue;
		}

		$product_id = qs_setup_find_product( $title, $type );
		if ( ! $product_id ) {
			$product_id = wp_insert_post(
				array(
					'post_type'   => 'quote_products',
					'post_status' => 'publish',
					'post_title'  => $title,
				),
				true
			);
			if ( is_wp_error( $product_id ) ) {
				return $product_id;
			}
			wp_set_object_terms( $product_id, $type, 'quote_product_type', false );
			$created++;
		} else {
			$updated++;
		}

		foreach ( isset( $product['meta'] ) ? (array) $product['meta'] : array() as $meta_key => $meta_value ) {
			$meta_key = sanitize_key( $meta_key );
			if ( ! $meta_key || metadata_exists( 'post', $product_id, $meta_key ) ) {
				continue;
			}
			update_post_meta( $product_id, $meta_key, $meta_value );

			$acf_key = qs_setup_acf_reference_for_meta( $meta_key, $acf_maps );
			if ( $acf_key && ! metadata_exists( 'post', $product_id, '_' . $meta_key ) ) {
				update_post_meta( $product_id, '_' . $meta_key, $acf_key );
			}
		}
	}

	update_option( 'qs_default_data_version', QS_DEFAULT_DATA_VERSION, false );
	return sprintf( 'Default Quote Products ready: %d created, %d existing products preserved.', $created, $updated );
}

function qs_setup_run_action( $action ) {
	$messages = array();

	if ( in_array( $action, array( 'install_pages', 'install_all' ), true ) ) {
		$result = qs_setup_install_pages();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$messages[] = $result;
	}

	if ( in_array( $action, array( 'install_acf', 'install_all' ), true ) ) {
		$result = qs_setup_install_acf_fields();
		if ( is_wp_error( $result ) ) {
			if ( 'install_acf' === $action ) {
				return $result;
			}
			$messages[] = $result->get_error_message();
		} else {
			$messages[] = $result;
		}
	}

	if ( in_array( $action, array( 'install_data', 'install_all' ), true ) ) {
		$result = qs_setup_install_default_data();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$messages[] = $result;
	}

	$status = qs_setup_status();
	if ( $status['complete'] ) {
		update_option( 'qs_setup_completed_version', QS_SETUP_VERSION, false );
		$messages[] = 'Quote System setup is complete.';
	}

	return implode( ' ', array_filter( $messages ) );
}

function qs_setup_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=quote',
		'Quote System Setup',
		'Setup',
		'manage_options',
		'qs-setup',
		'qs_setup_admin_page'
	);
}
add_action( 'admin_menu', 'qs_setup_admin_menu', 30 );

function qs_setup_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$status = qs_setup_status();
	if ( $status['complete'] ) {
		return;
	}
	$url = admin_url( 'edit.php?post_type=quote&page=qs-setup' );
	?>
	<div class="notice notice-warning qs-setup-notice">
		<p><strong>Quote System requires initial setup.</strong> Required pages, pricing fields, default Quote Products or dependencies are not ready yet. <a class="button button-primary" href="<?php echo esc_url( $url ); ?>">Run Quote System Setup</a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'qs_setup_admin_notice' );

function qs_setup_status_badge( $ready, $ready_label = 'Ready', $missing_label = 'Needs setup' ) {
	$label = $ready ? $ready_label : $missing_label;
	$class = $ready ? 'is-ready' : 'is-missing';
	return '<span class="qs-setup-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
}

function qs_setup_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage Quote System setup.', 'quote-system' ) );
	}

	$message = '';
	$error   = '';
	if ( isset( $_POST['qs_setup_action'] ) ) {
		check_admin_referer( 'qs_setup_action', 'qs_setup_nonce' );
		$action = sanitize_key( wp_unslash( $_POST['qs_setup_action'] ) );
		$result = qs_setup_run_action( $action );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$message = $result;
		}
	}

	$status = qs_setup_status();
	$pages  = qs_setup_page_definitions();
	?>
	<div class="wrap qs-setup-wrap">
		<h1>Quote System Setup</h1>
		<p class="description">Run this once after installing Quote System. The installer is safe to run again: existing pages, products and customised pricing values are preserved.</p>

		<?php if ( $message ) : ?><div class="notice notice-success inline"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>
		<?php if ( $error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

		<div class="qs-setup-overall <?php echo $status['complete'] ? 'is-ready' : ''; ?>">
			<strong><?php echo $status['complete'] ? 'Quote System is ready.' : 'Preliminary installation is still required.'; ?></strong>
			<?php echo qs_setup_status_badge( $status['complete'], 'Setup complete', 'Setup incomplete' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="qs-setup-grid">
			<section class="qs-setup-card">
				<h2>Dependencies <?php echo qs_setup_status_badge( $status['acf_pro'] && $status['woocommerce'] ); // phpcs:ignore ?></h2>
				<p><?php echo qs_setup_status_badge( $status['acf_pro'], 'ACF Pro active', 'ACF Pro missing' ); // phpcs:ignore ?> ACF Pro is used to edit pricing matrices.</p>
				<p><?php echo qs_setup_status_badge( $status['woocommerce'], 'WooCommerce active', 'WooCommerce missing' ); // phpcs:ignore ?> WooCommerce handles deposit and balance orders.</p>
			</section>

			<section class="qs-setup-card">
				<h2>Required Pages <?php echo qs_setup_status_badge( $status['pages'] ); // phpcs:ignore ?></h2>
				<p>Creates or reuses each required page and inserts its shortcode automatically.</p>
				<ul class="qs-setup-page-list">
					<?php foreach ( $pages as $key => $definition ) : $page_id = qs_setup_get_page_id( $key ); ?>
						<li><code>/<?php echo esc_html( $definition['slug'] ); ?>/</code> <span>[<?php echo esc_html( $definition['shortcode'] ); ?>]</span> <?php echo $page_id ? '<a href="' . esc_url( get_edit_post_link( $page_id ) ) . '">Edit</a>' : ''; // phpcs:ignore ?></li>
					<?php endforeach; ?>
				</ul>
				<?php qs_setup_action_button( 'install_pages', $status['pages'] ? 'Repair / Check Pages' : 'Install Pages' ); ?>
			</section>

			<section class="qs-setup-card">
				<h2>ACF Pricing Fields <?php echo qs_setup_status_badge( $status['acf_fields'] ); // phpcs:ignore ?></h2>
				<p>Enables the bundled Quote Product ACF field group, including matrix, percentage, fixed, linear and square-metre pricing fields.</p>
				<?php qs_setup_action_button( 'install_acf', $status['acf_fields'] ? 'Re-enable Fields' : 'Install ACF Fields', ! $status['acf_pro'] ); ?>
			</section>

			<section class="qs-setup-card">
				<h2>Default Pricing Data <?php echo qs_setup_status_badge( $status['default_data'] ); // phpcs:ignore ?></h2>
				<p>Seeds the approved starter Quote Products and pricing matrices. Existing products and existing meta values are never overwritten.</p>
				<?php qs_setup_action_button( 'install_data', $status['default_data'] ? 'Check / Fill Missing Data' : 'Install Default Data' ); ?>
			</section>
		</div>

		<form method="post" class="qs-setup-all-form">
			<?php wp_nonce_field( 'qs_setup_action', 'qs_setup_nonce' ); ?>
			<input type="hidden" name="qs_setup_action" value="install_all">
			<button type="submit" class="button button-primary button-hero">Install Everything</button>
			<p class="description">If ACF Pro or WooCommerce is missing, the other setup steps can still run and the notice will remain until the dependency is installed.</p>
		</form>
	</div>
	<style>
		.qs-setup-wrap{max-width:1100px}.qs-setup-overall{display:flex;align-items:center;justify-content:space-between;background:#fff;border-left:4px solid #dba617;padding:16px 18px;margin:20px 0}.qs-setup-overall.is-ready{border-left-color:#16803c}.qs-setup-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.qs-setup-card{background:#fff;border:1px solid #dcdcde;padding:20px}.qs-setup-card h2{display:flex;align-items:center;justify-content:space-between;margin-top:0}.qs-setup-card p{line-height:1.55}.qs-setup-badge{display:inline-block;border-radius:999px;padding:3px 9px;font-size:12px;font-weight:600;background:#f0f0f1;color:#50575e}.qs-setup-badge.is-ready{background:#e6f4ea;color:#146c2e}.qs-setup-badge.is-missing{background:#fff3cd;color:#7a5b00}.qs-setup-page-list{margin:12px 0 18px}.qs-setup-page-list li{margin:7px 0}.qs-setup-page-list span{color:#646970;margin:0 8px}.qs-setup-all-form{margin-top:22px;padding:22px;background:#f6f7f7;border:1px solid #dcdcde}@media(max-width:782px){.qs-setup-grid{grid-template-columns:1fr}.qs-setup-overall{align-items:flex-start;gap:10px;flex-direction:column}}
	</style>
	<?php
}

function qs_setup_action_button( $action, $label, $disabled = false ) {
	?>
	<form method="post">
		<?php wp_nonce_field( 'qs_setup_action', 'qs_setup_nonce' ); ?>
		<input type="hidden" name="qs_setup_action" value="<?php echo esc_attr( $action ); ?>">
		<button type="submit" class="button button-secondary" <?php disabled( $disabled ); ?>><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}
