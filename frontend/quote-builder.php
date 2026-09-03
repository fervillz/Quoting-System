<?php
/**
 * Frontend Quote Builder.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_builder_quote_is_editable( $quote_id ) {
	if ( ! $quote_id ) {
		return is_user_logged_in();
	}
	$quote = get_post( $quote_id );
	if ( ! $quote || 'quote' !== $quote->post_type ) {
		return false;
	}
	if ( current_user_can( 'edit_post', $quote_id ) ) {
		return true;
	}
	return 'draft' === get_post_status( $quote_id ) && (int) $quote->post_author === get_current_user_id();
}

/**
 * Return valid supporting-document attachment IDs saved against a quote.
 */
function qs_builder_supporting_document_ids( $quote_id ) {
	$ids = get_post_meta( $quote_id, '_supporting_documents', true );
	if ( ! is_array( $ids ) ) {
		return array();
	}

	return array_values(
		array_filter(
			array_map( 'absint', $ids ),
			static function ( $attachment_id ) {
				return $attachment_id && 'attachment' === get_post_type( $attachment_id );
			}
		)
	);
}

function qs_supporting_document_name( $attachment_id ) {
	$file_path = get_attached_file( $attachment_id );
	if ( $file_path ) {
		return wp_basename( $file_path );
	}

	$title = get_the_title( $attachment_id );
	return $title ? $title : __( 'Supporting document', 'quote-system' );
}

function qs_supporting_document_icon_url( $file_name ) {
	$extension = strtolower( (string) pathinfo( $file_name, PATHINFO_EXTENSION ) );

	return QS_URL . ( 'pdf' === $extension ? 'assets/images/icon-pdf.svg' : 'assets/images/icon-image.svg' );
}

/**
 * Save JPG, PNG and PDF supporting documents without making ACF a dependency.
 */
function qs_builder_save_supporting_documents( $quote_id ) {
	$attachment_ids = qs_builder_supporting_document_ids( $quote_id );
	$remove_ids     = isset( $_POST['remove_supporting_documents'] )
		? array_map( 'absint', (array) wp_unslash( $_POST['remove_supporting_documents'] ) )
		: array();

	if ( $remove_ids ) {
		$attachment_ids = array_values( array_diff( $attachment_ids, $remove_ids ) );
	}

	if ( empty( $_FILES['supporting_documents']['name'] ) || ! is_array( $_FILES['supporting_documents']['name'] ) ) {
		update_post_meta( $quote_id, '_supporting_documents', $attachment_ids );
		return true;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$files       = $_FILES['supporting_documents'];
	$allowed     = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'pdf'      => 'application/pdf',
	);
	$upload_name = 'qs_supporting_document';

	foreach ( $files['name'] as $index => $name ) {
		if ( empty( $name ) || ! empty( $files['error'][ $index ] ) && UPLOAD_ERR_NO_FILE === (int) $files['error'][ $index ] ) {
			continue;
		}

		if ( ! empty( $files['size'][ $index ] ) && (int) $files['size'][ $index ] > 10 * MB_IN_BYTES ) {
			return new WP_Error( 'supporting_document_too_large', __( 'Supporting documents must be 10MB or smaller.', 'quote-system' ) );
		}

		$_FILES[ $upload_name ] = array(
			'name'     => sanitize_file_name( wp_unslash( $files['name'][ $index ] ) ),
			'type'     => isset( $files['type'][ $index ] ) ? $files['type'][ $index ] : '',
			'tmp_name' => isset( $files['tmp_name'][ $index ] ) ? $files['tmp_name'][ $index ] : '',
			'error'    => isset( $files['error'][ $index ] ) ? $files['error'][ $index ] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $files['size'][ $index ] ) ? $files['size'][ $index ] : 0,
		);

		$attachment_id = media_handle_upload(
			$upload_name,
			$quote_id,
			array(),
			array(
				'test_form' => false,
				'mimes'     => $allowed,
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			unset( $_FILES[ $upload_name ] );
			return new WP_Error(
				'supporting_document_upload_failed',
				sprintf(
					/* translators: %s: uploaded filename. */
					__( 'Could not upload %s. Only JPG, PNG and PDF files up to 10MB are allowed.', 'quote-system' ),
					sanitize_file_name( wp_unslash( $name ) )
				)
			);
		}

		$attachment_ids[] = (int) $attachment_id;
		update_post_meta( $quote_id, '_supporting_documents', array_values( array_unique( $attachment_ids ) ) );
	}

	unset( $_FILES[ $upload_name ] );
	update_post_meta( $quote_id, '_supporting_documents', array_values( array_unique( $attachment_ids ) ) );

	return true;
}

function qs_builder_save_quote( $quote_id, $handle_uploads = true ) {
	if ( ! isset( $_POST['qs_builder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qs_builder_nonce'] ) ), 'qs_save_quote' ) ) {
		return new WP_Error( 'invalid_nonce', __( 'Your session expired. Please reload the page and try again.', 'quote-system' ) );
	}
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'not_logged_in', __( 'Please log in before saving a quote.', 'quote-system' ) );
	}
	$project_name = isset( $_POST['project_name'] ) ? sanitize_text_field( wp_unslash( $_POST['project_name'] ) ) : '';
	if ( '' === $project_name ) {
		return new WP_Error( 'missing_project_name', __( 'Project name is required.', 'quote-system' ) );
	}
	if ( $quote_id && ! qs_builder_quote_is_editable( $quote_id ) ) {
		return new WP_Error( 'forbidden', __( 'You cannot edit this quote.', 'quote-system' ) );
	}
	$post = array(
		'post_type'   => 'quote',
		'post_status' => 'draft',
		'post_title'  => $project_name,
		'post_author' => get_current_user_id(),
	);
	if ( $quote_id ) {
		$post['ID'] = $quote_id;
		$quote_id   = wp_update_post( $post, true );
	} else {
		$quote_id = wp_insert_post( $post, true );
	}
	if ( is_wp_error( $quote_id ) ) {
		return $quote_id;
	}
	$fields = array(
		'project_name'     => 'sanitize_text_field',
		'company_name'     => 'sanitize_text_field',
		'customer_name'    => 'sanitize_text_field',
		'customer_email'   => 'sanitize_email',
		'customer_phone'   => 'sanitize_text_field',
		'delivery_address' => 'sanitize_textarea_field',
		'door_profile'     => 'sanitize_text_field',
		'timber'           => 'sanitize_text_field',
		'finish'           => 'sanitize_text_field',
		'handle_profile'   => 'sanitize_text_field',
		'paint_colour'     => 'sanitize_text_field',
		'custom_requests'  => 'sanitize_textarea_field',
		'project_notes'    => 'sanitize_textarea_field',
	);
	foreach ( $fields as $field => $sanitiser ) {
		$value = isset( $_POST[ $field ] ) ? call_user_func( $sanitiser, wp_unslash( $_POST[ $field ] ) ) : '';
		update_post_meta( $quote_id, '_' . $field, $value );
	}

	$timber_label = qs_quote_product_label( get_post_meta( $quote_id, '_timber', true ) );
	$is_painted   = false !== stripos( $timber_label, 'paint' );
	if ( $is_painted ) {
		update_post_meta( $quote_id, '_finish', '' );
	} else {
		update_post_meta( $quote_id, '_paint_colour', '' );
	}

	qs_save_component_rows( $quote_id, isset( $_POST['components'] ) ? $_POST['components'] : array() );
	$pricing_validation = function_exists( 'qs_validate_quote_pricing_dimensions' )
		? qs_validate_quote_pricing_dimensions( $quote_id )
		: true;
	if ( is_wp_error( $pricing_validation ) ) {
		return $pricing_validation;
	}

	$pricing_type = isset( $_POST['pricing_type'] ) && 'retail' === $_POST['pricing_type'] ? 'retail' : 'trade';
	update_post_meta( $quote_id, '_pricing_type', $pricing_type );

	if ( $handle_uploads ) {
		$upload_result = qs_builder_save_supporting_documents( $quote_id );
		if ( is_wp_error( $upload_result ) ) {
			return $upload_result;
		}
	}

	$subtotal = qs_recalculate_quote_pricing( $quote_id );
	$has_items = false;
	foreach ( array( 'doors_drawers', 'end_panels', 'fillers', 'kickboards' ) as $component ) {
		if ( qs_component_rows( $quote_id, $component ) ) {
			$has_items = true;
			break;
		}
	}
	if ( $has_items && $subtotal <= 0 ) {
		return new WP_Error(
			'qs_zero_subtotal',
			__( 'The quote contains an item that does not match a pricing range. Please correct the highlighted measurements before continuing.', 'quote-system' )
		);
	}

	return $quote_id;
}

function qs_builder_products( $type ) {
	return get_posts(
		array(
			'post_type'      => 'quote_products',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'tax_query'      => array(
				array(
					'taxonomy' => 'quote_product_type',
					'field'    => 'slug',
					'terms'    => $type,
				),
			),
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => 'active', 'value' => '1' ),
				array( 'key' => 'active', 'compare' => 'NOT EXISTS' ),
			),
		)
	);
}

function qs_builder_input( $name, $label, $value = '', $type = 'text', $required = false, $hint = '' ) {
	?>
	<div class="qs-field">
		<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></label>
		<input id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo $required ? 'required' : ''; ?>>
		<?php if ( $hint ) : ?><small><?php echo esc_html( $hint ); ?></small><?php endif; ?>
	</div>
	<?php
}

function qs_builder_product_picker( $name, $label, $type, $selected, $options = array() ) {
	$products         = qs_builder_products( $type );
	$description      = isset( $options['description'] ) ? $options['description'] : '';
	$paint_colour     = isset( $options['paint_colour'] ) ? $options['paint_colour'] : '';
	$selected_label   = $selected ? qs_quote_product_label( $selected ) : '';
	$selected_image   = '';
	$is_painted       = false !== stripos( $selected_label, 'paint' );
	$options_id       = 'qs-picker-options-' . sanitize_html_class( $name );
	$has_painted      = false;

	foreach ( $products as $product ) {
		if ( false !== stripos( $product->post_title, 'paint' ) ) {
			$has_painted = true;
		}
		if ( (string) $product->ID === (string) $selected || $product->post_title === $selected ) {
			$selected_label = $product->post_title;
			$selected_image = get_the_post_thumbnail_url( $product->ID, 'thumbnail' );
			$is_painted     = false !== stripos( $selected_label, 'paint' );
		}
	}

	if ( 'timber' === $name && $is_painted ) {
		$selected_label = 'Painted Oak';
	}
	?>
	<div class="qs-product-field" data-product-field="<?php echo esc_attr( $name ); ?>">
		<label class="qs-field-label"><?php echo esc_html( $label ); ?><sup>*</sup></label>
		<?php if ( $description ) : ?><small class="qs-product-help"><?php echo esc_html( $description ); ?></small><?php endif; ?>
		<div class="qs-product-picker" data-picker="<?php echo esc_attr( $name ); ?>">
			<button class="qs-product-selection" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $options_id ); ?>" data-picker-toggle>
				<span class="qs-product-swatch" data-picker-selected-swatch<?php echo $selected_image ? ' style="background-image:url(' . esc_url( $selected_image ) . ')"' : ''; ?>></span>
				<span data-picker-selected-label><?php echo esc_html( $selected_label ? $selected_label : 'Select an option' ); ?></span>
				<svg aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg>
			</button>
			<?php if ( 'timber' === $name ) : ?>
				<div class="qs-paint-colour-field" data-paint-colour-field <?php echo $is_painted ? '' : 'hidden'; ?>>
					<label for="paint_colour">Paint Colour<sup>*</sup></label>
					<input id="paint_colour" name="paint_colour" type="text" value="<?php echo esc_attr( $paint_colour ); ?>" placeholder="Dulux Snowy Mountains Half" <?php echo $is_painted ? 'required' : 'disabled'; ?>>
					<small>Enter the paint colour specification for your Painted Oak doors.</small>
				</div>
			<?php endif; ?>
			<div class="qs-product-options" id="<?php echo esc_attr( $options_id ); ?>">
				<?php if ( $products ) : foreach ( $products as $product ) :
					$value = (string) $product->ID;
					$is_selected = $value === (string) $selected || $product->post_title === $selected;
					$image = get_the_post_thumbnail_url( $product->ID, 'thumbnail' );
					$option_label = 'timber' === $name && false !== stripos( $product->post_title, 'paint' ) ? 'Painted Oak' : $product->post_title;
					?>
					<label class="qs-product-option<?php echo $is_selected ? ' is-selected' : ''; ?>" data-option-label="<?php echo esc_attr( $option_label ); ?>" data-option-image="<?php echo esc_url( $image ); ?>">
						<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $is_selected ); ?> required>
						<span class="qs-product-swatch"<?php echo $image ? ' style="background-image:url(' . esc_url( $image ) . ')"' : ''; ?>></span>
						<span class="qs-product-option-label"><?php echo esc_html( $option_label ); ?></span>
					</label>
				<?php endforeach; endif; ?>
				<?php if ( 'timber' === $name && ! $has_painted ) : ?>
					<label class="qs-product-option<?php echo $is_painted ? ' is-selected' : ''; ?>" data-option-label="Painted Oak" data-option-image="">
						<input type="radio" name="timber" value="Painted Oak" <?php checked( $is_painted ); ?> required>
						<span class="qs-product-swatch"></span>
						<span class="qs-product-option-label">Painted Oak</span>
					</label>
				<?php elseif ( ! $products ) : ?>
					<label class="qs-product-option is-selected" data-option-label="<?php echo esc_attr( $selected_label ? $selected_label : 'Select an option' ); ?>" data-option-image=""><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $selected ); ?>" checked required><span class="qs-product-swatch"></span><span class="qs-product-option-label"><?php echo esc_html( $selected_label ? $selected_label : 'Select an option' ); ?></span></label>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render the mockup-specific Door, Drawer and Drawer Bank item editor.
 *
 * Door and Drawer entries are added one at a time, so their stored quantity
 * starts at one. Adding the same size again increments that stored quantity.
 * Drawer Banks retain an explicit quantity field as shown in the design.
 */
function qs_builder_doors_drawers_editor( $rows ) {
	$rows        = is_array( $rows ) ? $rows : array();
	$active_type = ! empty( $rows[0]['type'] ) && in_array( $rows[0]['type'], array( 'Door', 'Drawer', 'Drawer Bank' ), true )
		? $rows[0]['type']
		: 'Door';
	$fields      = array_keys( qs_component_definitions()['doors_drawers'] );
	?>
	<section class="qs-component qs-doors-drawers" data-component="doors_drawers" data-active-type="<?php echo esc_attr( $active_type ); ?>">
		<h3>Doors &amp; Drawers</h3>
		<p class="qs-section-help">Enter the size and quantity for each door or drawer required.</p>
		<p class="qs-grain-note">Grains run vertical (height)</p>

		<div class="qs-type-actions" role="group" aria-label="Select item type">
			<?php foreach ( array( 'Door', 'Drawer', 'Drawer Bank' ) as $type ) : ?>
				<button
					type="button"
					data-select-type="<?php echo esc_attr( $type ); ?>"
					class="<?php echo $active_type === $type ? 'is-active' : ''; ?>"
					aria-pressed="<?php echo $active_type === $type ? 'true' : 'false'; ?>"
				>+ Add <?php echo esc_html( $type ); ?></button>
			<?php endforeach; ?>
		</div>

		<div class="qs-door-entry-editor">
			<div class="qs-item-editor" data-editor-type="Door"<?php echo 'Door' === $active_type ? '' : ' hidden'; ?>>
				<div class="qs-editor-fields qs-front-editor-fields">
					<input type="number" min="1" step="1" data-editor-field="width" placeholder="Width mm" aria-label="Door width in millimetres">
					<input type="number" min="1" step="1" data-editor-field="height" placeholder="Height mm" aria-label="Door height in millimetres">
				</div>
				<p class="qs-item-instruction">Enter the size and quantity for each door or drawer required.</p>
			</div>

			<div class="qs-item-editor" data-editor-type="Drawer"<?php echo 'Drawer' === $active_type ? '' : ' hidden'; ?>>
				<div class="qs-editor-fields qs-front-editor-fields">
					<input type="number" min="1" step="1" data-editor-field="width" placeholder="Width mm" aria-label="Drawer width in millimetres">
					<input type="number" min="1" step="1" data-editor-field="height" placeholder="Height mm" aria-label="Drawer height in millimetres">
				</div>
				<p class="qs-item-instruction">Enter the size and quantity for each door or drawer required.</p>
			</div>

			<div class="qs-item-editor" data-editor-type="Drawer Bank"<?php echo 'Drawer Bank' === $active_type ? '' : ' hidden'; ?>>
				<label class="qs-bank-type-label" for="qs-drawer-bank-type">Drawer Bank Type<sup>*</sup></label>
				<select id="qs-drawer-bank-type" data-editor-field="drawer_count" aria-label="Drawer bank type">
					<option value="2">2 Drawers</option>
					<option value="3" selected>3 Drawers</option>
					<option value="4">4 Drawers</option>
				</select>
				<div class="qs-editor-fields qs-bank-editor-fields" data-bank-count="3">
					<input class="qs-bank-top" type="number" min="1" step="1" data-editor-field="top_height" data-bank-counts="2 3 4" placeholder="Top Drawer Height (mm)" aria-label="Top drawer height in millimetres">
					<input class="qs-bank-top-middle" type="number" min="1" step="1" data-editor-field="top_middle_height" data-bank-counts="4" placeholder="Top Middle Drawer Height (mm)" aria-label="Top middle drawer height in millimetres" hidden>
					<input class="qs-bank-middle" type="number" min="1" step="1" data-editor-field="middle_height" data-bank-counts="3" placeholder="Middle Drawer Height (mm)" aria-label="Middle drawer height in millimetres">
					<input class="qs-bank-bottom-middle" type="number" min="1" step="1" data-editor-field="bottom_middle_height" data-bank-counts="4" placeholder="Bottom Middle Drawer Height (mm)" aria-label="Bottom middle drawer height in millimetres" hidden>
					<input class="qs-bank-bottom" type="number" min="1" step="1" data-editor-field="bottom_height" data-bank-counts="2 3 4" placeholder="Bottom Drawer Height (mm)" aria-label="Bottom drawer height in millimetres">
					<input class="qs-bank-width" type="number" min="1" step="1" data-editor-field="width" placeholder="Width (mm)" aria-label="Drawer bank width in millimetres">
					<input class="qs-bank-quantity" type="number" min="1" step="1" value="1" data-editor-field="quantity" placeholder="Quantity" aria-label="Drawer bank quantity">
				</div>
				<p class="qs-item-instruction">Enter the size and quantity for each door or drawer required.</p>
			</div>

			<button class="qs-commit-item" type="button">Add Item</button>
		</div>

		<div class="qs-repeater-list" aria-live="polite">
			<?php foreach ( $rows as $index => $row ) : ?>
				<div class="qs-repeater-row qs-stored-item" data-item-type="<?php echo esc_attr( isset( $row['type'] ) ? $row['type'] : 'Door' ); ?>">
					<?php foreach ( $fields as $field ) : ?>
						<input
							type="hidden"
							name="components[doors_drawers][<?php echo esc_attr( $index ); ?>][<?php echo esc_attr( $field ); ?>]"
							value="<?php echo esc_attr( isset( $row[ $field ] ) ? $row[ $field ] : '' ); ?>"
						>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * Render component rows as hidden Quote CPT fields. The visible editor is
 * deliberately separate so each component can match its own mockup.
 */
function qs_builder_stored_component_rows( $component, $rows ) {
	$definitions = qs_component_definitions();
	$fields      = isset( $definitions[ $component ] ) ? array_keys( $definitions[ $component ] ) : array();
	?>
	<div class="qs-repeater-list" aria-live="polite">
		<?php foreach ( (array) $rows as $index => $row ) : ?>
			<div class="qs-repeater-row qs-stored-item">
				<?php foreach ( $fields as $field ) :
					$value         = isset( $row[ $field ] ) ? $row[ $field ] : '';
					$display_value = 'kickboards' === $component && 'material' === $field ? qs_quote_product_label( $value ) : '';
					?>
					<input
						type="hidden"
						name="components[<?php echo esc_attr( $component ); ?>][<?php echo esc_attr( $index ); ?>][<?php echo esc_attr( $field ); ?>]"
						value="<?php echo esc_attr( $value ); ?>"
						<?php echo $display_value ? 'data-display-value="' . esc_attr( $display_value ) . '"' : ''; ?>
					>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

function qs_builder_end_panels_editor( $rows ) {
	?>
	<section class="qs-component qs-configured-component qs-end-panels" data-component="end_panels">
		<h3>End Panels</h3>
		<div class="qs-component-editor">
			<div class="qs-editor-fields qs-two-column-fields">
				<input type="number" min="1" step="1" data-component-field="width" placeholder="Width mm" aria-label="End panel width in millimetres">
				<input type="number" min="1" step="1" data-component-field="height" placeholder="Height mm" aria-label="End panel height in millimetres">
			</div>

			<label class="qs-editor-label" for="qs-end-panel-faces">Face Seen<sup>*</sup></label>
			<select id="qs-end-panel-faces" data-component-field="faces_seen" data-default-value="2 Faces">
				<option value="">Select faces seen</option>
				<option value="1 Face Only">1 Face Only</option>
				<option value="1 Face / 1 Return (100mm)">1 Face / 1 Return (100mm)</option>
				<option value="1 Face / 2 Returns (100mm)">1 Face / 2 Returns (100mm)</option>
				<option value="2 Faces" selected>2 Faces</option>
			</select>

			<label class="qs-editor-label">Edge/s Seen<sup>*</sup></label>
			<div class="qs-edge-selector" data-edge-selector>
				<label class="qs-edge-choice qs-edge-top"><input type="checkbox" value="Top" data-edge-position="Top"><span></span><em>Top edge</em></label>
				<label class="qs-edge-choice qs-edge-right"><input type="checkbox" value="Right" data-edge-position="Right"><span></span><em>Right edge</em></label>
				<label class="qs-edge-choice qs-edge-bottom"><input type="checkbox" value="Bottom" data-edge-position="Bottom"><span></span><em>Bottom edge</em></label>
				<label class="qs-edge-choice qs-edge-left"><input type="checkbox" value="Left" data-edge-position="Left"><span></span><em>Left edge</em></label>
				<div class="qs-edge-face">Face</div>
				<button type="button" class="qs-save-edges" data-save-edges>Save</button>
			</div>

			<button class="qs-commit-component" type="button">Add Item</button>
		</div>
		<?php qs_builder_stored_component_rows( 'end_panels', $rows ); ?>
	</section>
	<?php
}

function qs_builder_fillers_editor( $rows ) {
	?>
	<section class="qs-component qs-configured-component qs-fillers" data-component="fillers">
		<h3>Fillers</h3>
		<div class="qs-component-editor">
			<div class="qs-editor-fields qs-two-column-fields">
				<input type="number" min="1" step="1" data-component-field="width" placeholder="Width mm" aria-label="Filler width in millimetres">
				<input type="number" min="1" step="1" data-component-field="height" placeholder="Height mm" aria-label="Filler height in millimetres">
			</div>

			<label class="qs-editor-label" for="qs-filler-faces">Face Seen<sup>*</sup></label>
			<select id="qs-filler-faces" data-component-field="faces_seen" data-default-value="2 Faces">
				<option value="">Select faces seen</option>
				<option value="1 Face">1 Face</option>
				<option value="2 Faces" selected>2 Faces</option>
			</select>

			<label class="qs-editor-label" for="qs-filler-edges">Edge/s Seen<sup>*</sup></label>
			<select id="qs-filler-edges" data-component-field="edges_seen" data-default-value="1 Long / 2 Short">
				<option value="">Select edges seen</option>
				<option value="1 Long / 2 Short" selected>1 Long / 2 Short</option>
				<option value="1 Long / 1 Short">1 Long / 1 Short</option>
				<option value="1 Long / No Short">1 Long / No Short</option>
			</select>

			<button class="qs-commit-component" type="button">Add Item</button>
		</div>
		<?php qs_builder_stored_component_rows( 'fillers', $rows ); ?>
	</section>
	<?php
}

function qs_builder_kickboards_editor( $rows ) {
	$products = qs_builder_products( 'kickboard' );
	?>
	<section class="qs-component qs-configured-component qs-kickboards" data-component="kickboards">
		<h3>Kickboards</h3>
		<ul class="qs-kickboard-notes">
			<li>Grain runs long / horizontal</li>
			<li>Max length 2400mm per piece</li>
			<li>Max height 200mm</li>
			<li>1 face / no edges finished</li>
		</ul>

		<div class="qs-component-editor">
			<label class="qs-editor-label" for="qs-kickboard-material">Kick Material<sup>*</sup></label>
			<p class="qs-editor-help">Add kickboards if required.</p>
			<select id="qs-kickboard-material" data-component-field="material"<?php echo $products ? ' data-default-value="' . esc_attr( $products[0]->ID ) . '"' : ''; ?>>
				<?php if ( ! $products ) : ?><option value="">Select kick material</option><?php endif; ?>
				<?php foreach ( $products as $product_index => $product ) : ?>
					<option value="<?php echo esc_attr( $product->ID ); ?>" <?php selected( 0, $product_index ); ?>><?php echo esc_html( $product->post_title ); ?></option>
				<?php endforeach; ?>
			</select>

			<div class="qs-editor-fields qs-two-column-fields qs-kickboard-fields">
				<input type="number" min="1" max="200" step="1" data-component-field="height" placeholder="Kick Height" aria-label="Kickboard height in millimetres">
				<input type="number" min="1" step="1" value="1" data-component-field="quantity" placeholder="Quantity" aria-label="Kickboard quantity">
				<input type="number" min="1" max="2400" step="1" data-component-field="length" placeholder="Kick Length" aria-label="Kickboard length in millimetres">
			</div>

			<button class="qs-commit-component" type="button">Add Item</button>
		</div>
		<?php qs_builder_stored_component_rows( 'kickboards', $rows ); ?>
	</section>
	<?php
}

function qs_builder_component_table( $component, $rows ) {
	switch ( $component ) {
		case 'doors_drawers':
			qs_builder_doors_drawers_editor( $rows );
			break;
		case 'end_panels':
			qs_builder_end_panels_editor( $rows );
			break;
		case 'fillers':
			qs_builder_fillers_editor( $rows );
			break;
		case 'kickboards':
			qs_builder_kickboards_editor( $rows );
			break;
	}
}

/**
 * Save the current Builder values and return the same calculated total used
 * by the Review page.
 */
function qs_builder_ajax_recalculate() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please log in before calculating a quote.' ), 403 );
	}

	$quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
	$saved_id = qs_builder_save_quote( $quote_id, false );

	if ( is_wp_error( $saved_id ) ) {
		wp_send_json_error( array( 'message' => $saved_id->get_error_message() ), 400 );
	}

	$subtotal = (float) get_post_meta( $saved_id, '_subtotal', true );

	wp_send_json_success(
		array(
			'quote_id'           => (int) $saved_id,
			'subtotal'           => $subtotal,
			'formatted_subtotal' => number_format_i18n( $subtotal, 2 ),
		)
	);
}
add_action( 'wp_ajax_qs_builder_recalculate', 'qs_builder_ajax_recalculate' );

function qs_quote_builder_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<div class="qs-notice">Please log in to create a quote.</div>';
	}
	$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
	$error    = '';
	if ( isset( $_POST['qs_save_draft'] ) || isset( $_POST['qs_review_quote'] ) ) {
		$saved = qs_builder_save_quote( $quote_id );
		if ( is_wp_error( $saved ) ) {
			$error = $saved->get_error_message();
		} else {
			$destination = isset( $_POST['qs_review_quote'] ) ? '/quote-review/' : '/quote-builder/';
			wp_safe_redirect( add_query_arg( array( 'quote_id' => $saved, 'saved' => 1 ), site_url( $destination ) ) );
			exit;
		}
	}
	if ( ! qs_builder_quote_is_editable( $quote_id ) ) {
		return '<p>' . esc_html__( 'You cannot view this quote.', 'quote-system' ) . '</p>';
	}
	$keys = array( 'project_name', 'company_name', 'customer_name', 'customer_email', 'customer_phone', 'delivery_address', 'door_profile', 'timber', 'finish', 'handle_profile', 'paint_colour', 'custom_requests', 'project_notes' );
	$meta = array();
	foreach ( $keys as $key ) {
		$meta[ $key ] = $quote_id ? get_post_meta( $quote_id, '_' . $key, true ) : '';
	}
	$pricing_type        = $quote_id && 'retail' === get_post_meta( $quote_id, '_pricing_type', true ) ? 'retail' : 'trade';
	$builder_subtotal    = $quote_id ? qs_recalculate_quote_pricing( $quote_id ) : 0;
	$supporting_document_ids = $quote_id ? qs_builder_supporting_document_ids( $quote_id ) : array();
	ob_start();
	?>
	<div class="qs-builder-shell">
		<header class="qs-page-header"><h1>Quote Builder</h1><div><span class="qs-save-state"><?php echo isset( $_GET['saved'] ) ? 'Saved' : ''; ?></span><a class="qs-btn qs-btn-outline" href="<?php echo esc_url( site_url( '/my-quotes/' ) ); ?>">My Quotes</a><?php if ( function_exists( 'wp_logout_url' ) ) : ?><a class="qs-btn" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Logout</a><?php endif; ?></div></header>
		<?php if ( $error ) : ?><div class="qs-notice qs-notice-error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
		<form method="post" enctype="multipart/form-data" class="qs-builder-form">
			<?php wp_nonce_field( 'qs_save_quote', 'qs_builder_nonce' ); ?>
			<main class="qs-builder-main">
				<section class="qs-builder-intro"><h2>Create a New Quote</h2><p>Select your cabinet profile, timber, finishes and quantities to generate a quote. <br>
Pricing updates automatically as you configure your selections.</p><div class="qs-pricing-mode"><span>Pricing Mode*</span><label>Trade Pricing <input type="checkbox" name="pricing_type" value="retail" <?php checked( $pricing_type, 'retail' ); ?>><i></i> Retail Pricing</label></div><small>Select the pricing structure for this quote.</small></section>
				<section class="qs-form-section"><h3>Project Details</h3>
					<?php qs_builder_input( 'company_name', 'Company', $meta['company_name'], 'text', true, 'This is your business name.' ); ?>
					<?php qs_builder_input( 'project_name', 'Project Name', $meta['project_name'], 'text', true, 'This name will be used to identify the quote.' ); ?>
					<?php qs_builder_input( 'customer_name', 'Contact Name', $meta['customer_name'], 'text', true ); ?>
					<?php qs_builder_input( 'customer_email', 'Email', $meta['customer_email'], 'email', true ); ?>
					<?php qs_builder_input( 'customer_phone', 'Phone', $meta['customer_phone'] ); ?>
					<div class="qs-field"><label for="delivery_address">Delivery Address</label><textarea id="delivery_address" name="delivery_address"><?php echo esc_textarea( $meta['delivery_address'] ); ?></textarea></div>
				</section>
				<section class="qs-form-section"><h3>Door Specifications</h3><p class="qs-section-help">Select the door style and timber material for your cabinetry.</p>
					<?php qs_builder_product_picker( 'door_profile', 'Profile', 'door-profile', $meta['door_profile'] ); ?>
					<?php qs_builder_product_picker( 'timber', 'Timber', 'timber', $meta['timber'], array( 'paint_colour' => $meta['paint_colour'] ) ); ?>
					<?php qs_builder_product_picker( 'handle_profile', 'Door / Drawer Handle Profile', 'accessory', $meta['handle_profile'] ); ?>
					<?php qs_builder_product_picker( 'finish', 'Finish', 'finish', $meta['finish'], array( 'description' => 'Choose the finish applied to the doors and panels.' ) ); ?>
				</section>
				<?php qs_builder_component_table( 'doors_drawers', qs_component_rows( $quote_id, 'doors_drawers' ) ); ?>
				<?php qs_builder_component_table( 'end_panels', qs_component_rows( $quote_id, 'end_panels' ) ); ?>
				<?php qs_builder_component_table( 'fillers', qs_component_rows( $quote_id, 'fillers' ) ); ?>
				<?php qs_builder_component_table( 'kickboards', qs_component_rows( $quote_id, 'kickboards' ) ); ?>
				<section class="qs-form-section qs-custom-requests">
					<h3>Custom Requests</h3>
					<p class="qs-section-help">Need something outside our standard range?</p>
					<textarea name="custom_requests" rows="6" placeholder="Describe any custom items, modifications, or special requirements below."><?php echo esc_textarea( $meta['custom_requests'] ); ?></textarea>
					<p class="qs-custom-request-note">Custom items will be reviewed by our team and priced separately if required. Please upload additional supporting documents below.</p>
				</section>

				<section class="qs-form-section qs-supporting-documents">
					<h3>Upload Supporting Documents</h3>
					<label class="qs-upload-dropzone" for="qs-supporting-documents">
						<span class="qs-upload-icon" aria-hidden="true">
							<svg viewBox="0 0 16 16" focusable="false"><path fill-rule="evenodd" d="M7.646 5.146a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 6.707V10.5a.5.5 0 0 1-1 0V6.707L6.354 7.854a.5.5 0 1 1-.708-.708z"/><path d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383m.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z"/></svg>
						</span>
						<span class="qs-upload-copy"><strong>Select a file or drag and drop here</strong><small>JPG, PNG or PDF, file size no more than 10MB</small></span>
						<span class="qs-upload-select">Select File</span>
						<input id="qs-supporting-documents" type="file" name="supporting_documents[]" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" multiple>
					</label>

					<div class="qs-upload-file-list" data-upload-file-list>
						<?php if ( $supporting_document_ids ) : ?><p class="qs-upload-list-heading">Files added</p><?php endif; ?>
						<?php foreach ( $supporting_document_ids as $attachment_id ) :
							$file_path = get_attached_file( $attachment_id );
							$file_url  = wp_get_attachment_url( $attachment_id );
							$file_name = qs_supporting_document_name( $attachment_id );
							$file_size = $file_path && file_exists( $file_path ) ? size_format( filesize( $file_path ), 2 ) : '';
							?>
							<div class="qs-upload-file" data-existing-upload="<?php echo esc_attr( $attachment_id ); ?>">
								<span class="qs-upload-file-icon" aria-hidden="true"><img src="<?php echo esc_url( qs_supporting_document_icon_url( $file_name ) ); ?>" alt=""></span>
								<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $file_name ); ?></a>
								<small><?php echo esc_html( $file_size ); ?></small>
								<button type="button" data-remove-upload aria-label="<?php echo esc_attr( sprintf( 'Remove %s', $file_name ) ); ?>"><svg aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/></svg></button>
							</div>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="qs-form-section qs-project-notes">
					<h3>Project Notes</h3>
					<textarea name="project_notes" rows="6" placeholder="Include any additional information or special requirements for this order."><?php echo esc_textarea( $meta['project_notes'] ); ?></textarea>
				</section>
			</main>
			<aside class="qs-builder-summary">
				<h2>Quote Summary</h2>
				<h4>Selected Specifications</h4>
				<dl><dt>Profile</dt><dd data-summary="door_profile">—</dd><dt>Timber</dt><dd data-summary="timber">—</dd><dt>Finish</dt><dd data-summary="finish">—</dd><dt>Door / Drawer Handle</dt><dd data-summary="handle_profile">—</dd><dt>Paint Colour</dt><dd data-summary="paint_colour">—</dd></dl>
				<h4>Items Breakdown</h4>
				<div class="qs-summary-items"></div>
				<div class="qs-lead-time"><strong>Estimated Lead Time</strong><span>4–6 Weeks</span></div>
				<div class="qs-subtotal"><span>Subtotal (Ex GST)</span><strong data-qs-subtotal>$<?php echo esc_html( number_format_i18n( $builder_subtotal, 2 ) ); ?> AUD</strong></div>
				<div class="qs-summary-actions"><button class="qs-btn qs-btn-outline" type="submit" name="qs_save_draft">Save Draft</button><button class="qs-btn" type="submit" name="qs_review_quote">Review Quote</button></div>
			</aside>
		</form>
	</div>
	<script>
	(function(){
		const form=document.querySelector('.qs-builder-form'); if(!form)return;
		const subtotal=document.querySelector('[data-qs-subtotal]');
		const summaryItems=document.querySelector('.qs-summary-items');
		const ajaxUrl=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const summaryIcons={edit:<?php echo wp_json_encode( QS_URL . 'assets/images/icon-pen.svg' ); ?>,remove:<?php echo wp_json_encode( QS_URL . 'assets/images/icon-trash.svg' ); ?>};
		const uploadIcons={image:<?php echo wp_json_encode( QS_URL . 'assets/images/icon-image.svg' ); ?>,pdf:<?php echo wp_json_encode( QS_URL . 'assets/images/icon-pdf.svg' ); ?>};
		let quoteId=<?php echo (int) $quote_id; ?>;
		let calculateTimer;
		let calculateRequest;
		let activeSummaryEdit=null;
		let activeSummaryEditTimer=null;
		function syncRowType(row){const select=row.querySelector('[name$="[type]"]');row.classList.toggle('is-drawer-bank',!!select&&select.value==='Drawer Bank');}
		function reindex(section){section.querySelectorAll('.qs-repeater-row').forEach((row,i)=>{row.querySelectorAll('[name]').forEach(el=>el.name=el.name.replace(/\[\d+\]/,'['+i+']'));syncRowType(row);});}
		function addRow(section,type){const list=section.querySelector('.qs-repeater-list'),source=list.querySelector('.qs-repeater-row'),row=source.cloneNode(true);row.querySelectorAll('input').forEach(el=>el.value='');if(type){const select=row.querySelector('select');if(select)select.value=type;}list.appendChild(row);reindex(section);refresh();scheduleCalculation();}
		function removeRow(section,row){const rows=section.querySelectorAll('.qs-repeater-row');if(section.dataset.component==='doors_drawers'||section.classList.contains('qs-configured-component')||rows.length>1)row.remove();else row.querySelectorAll('input').forEach(el=>el.value='');reindex(section);refresh();scheduleCalculation();}
		function editorPanel(section,type){return section.querySelector('.qs-item-editor[data-editor-type="'+type+'"]');}
		function editorField(section,type,key){const panel=editorPanel(section,type);return panel&&panel.querySelector('[data-editor-field="'+key+'"]');}
		function selectEditorType(section,type){
			section.dataset.activeType=type;
			section.querySelectorAll('[data-select-type]').forEach(button=>{const active=button.dataset.selectType===type;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',active?'true':'false');});
			section.querySelectorAll('.qs-item-editor').forEach(panel=>panel.hidden=panel.dataset.editorType!==type);
			const first=editorPanel(section,type)?.querySelector('input,select');
			if(first)first.focus();
		}
		function updateBankHeightFields(section){
			const panel=editorPanel(section,'Drawer Bank');if(!panel)return;
			const count=String(editorField(section,'Drawer Bank','drawer_count')?.value||'3');
			const fields=panel.querySelector('.qs-bank-editor-fields');
			if(fields)fields.dataset.bankCount=count;
			panel.querySelectorAll('[data-bank-counts]').forEach(input=>{input.hidden=!input.dataset.bankCounts.split(' ').includes(count);if(input.hidden)input.value='';});
		}
		function storedValues(row){const values={};row.querySelectorAll('[name]').forEach(input=>{const match=input.name.match(/\[([^\]]+)\]$/);if(match)values[match[1]]=input.value;});return values;}
		const storedFieldMap={
			doors_drawers:['type','width','height','quantity','edge_profile','drawer_count','top_height','top_middle_height','middle_height','bottom_middle_height','bottom_height'],
			end_panels:['height','width','quantity','faces_seen','edges_seen'],
			fillers:['height','width','quantity','faces_seen','edges_seen'],
			kickboards:['material','height','length','quantity']
		};
		function setStoredValues(row,component,values,displayValues={}){
			const fields=storedFieldMap[component]||[];
			fields.forEach(key=>{let input=field(row,key);if(!input){input=document.createElement('input');input.type='hidden';input.name='components[doors_drawers][0]['+key+']';row.appendChild(input);}input.value=values[key]||'';});
			row.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(/^components\[[^\]]+\]/,'components['+component+']');const key=input.name.match(/\[([^\]]+)\]$/)?.[1];if(key&&displayValues[key])input.dataset.displayValue=displayValues[key];else if(key)delete input.dataset.displayValue;});
			if(component==='doors_drawers')row.dataset.itemType=values.type||'Door';
		}
		function createStoredRow(section,values,displayValues={}){const row=document.createElement('div');row.className='qs-repeater-row qs-stored-item';setStoredValues(row,section.dataset.component,values,displayValues);section.querySelector('.qs-repeater-list').appendChild(row);return row;}
		function clearDoorEditor(section){
			section.dataset.editingIndex='';
			section.querySelectorAll('[data-editor-field]').forEach(input=>{if(input.dataset.editorField==='drawer_count')input.value='3';else if(input.dataset.editorField==='quantity')input.value='1';else input.value='';});
			const button=section.querySelector('.qs-commit-item');if(button)button.textContent='Add Item';
			updateBankHeightFields(section);
		}
		function loadDoorEditor(section,row,index){
			const values=storedValues(row),type=values.type||'Door';
			selectEditorType(section,type);
			section.dataset.editingIndex=String(index);
			const panel=editorPanel(section,type);
			if(panel)panel.querySelectorAll('[data-editor-field]').forEach(input=>{const key=input.dataset.editorField;input.value=values[key]||(key==='quantity'?'1':key==='drawer_count'?'3':'');});
			updateBankHeightFields(section);
			const button=section.querySelector('.qs-commit-item');if(button)button.textContent='Update Item';
			section.scrollIntoView({behavior:'smooth',block:'center'});
		}
		function commitDoorItem(section){
			const type=section.dataset.activeType||'Door';
			const panel=editorPanel(section,type);if(!panel)return;
			const read=key=>editorField(section,type,key)?.value.trim()||'';
			const required=type==='Drawer Bank'
				?[editorField(section,type,'width'),editorField(section,type,'quantity'),...panel.querySelectorAll('[data-bank-counts]:not([hidden])')]
				:[editorField(section,type,'width'),editorField(section,type,'height')];
			const invalid=required.find(input=>!input||Number(input.value)<=0);
			if(invalid){if(invalid){invalid.setCustomValidity('Please enter this measurement.');invalid.reportValidity();window.setTimeout(()=>invalid.setCustomValidity(''),0);}return;}
			const values={type,width:read('width'),height:type==='Drawer Bank'?'':read('height'),quantity:type==='Drawer Bank'?(read('quantity')||'1'):'1',edge_profile:'',drawer_count:type==='Drawer Bank'?(read('drawer_count')||'3'):'',top_height:type==='Drawer Bank'?read('top_height'):'',top_middle_height:type==='Drawer Bank'?read('top_middle_height'):'',middle_height:type==='Drawer Bank'?read('middle_height'):'',bottom_middle_height:type==='Drawer Bank'?read('bottom_middle_height'):'',bottom_height:type==='Drawer Bank'?read('bottom_height'):''};
			const rows=[...section.querySelectorAll('.qs-repeater-row')];
			const editing=section.dataset.editingIndex;
			let row=editing!==''?rows[Number(editing)]:null;
			if(row&&type!=='Drawer Bank')values.quantity=storedValues(row).quantity||'1';
			if(!row&&type!=='Drawer Bank'){
				row=rows.find(candidate=>{const existing=storedValues(candidate);return existing.type===type&&String(existing.width)===String(values.width)&&String(existing.height)===String(values.height);});
				if(row){values.quantity=String((Number(storedValues(row).quantity)||0)+1);}
			}
			if(row)setStoredValues(row,'doors_drawers',values);else createStoredRow(section,values);
			reindex(section);refresh();scheduleCalculation();clearDoorEditor(section);
		}
		function componentEditorField(section,key){return section.querySelector('[data-component-field="'+key+'"]');}
		function componentEditorValue(section,key){const input=componentEditorField(section,key);return input?input.value.trim():'';}
		function selectedEdges(section){return [...section.querySelectorAll('[data-edge-position]:checked')].map(input=>input.value);}
		function componentEditorValues(section){
			const component=section.dataset.component;
			if(component==='end_panels')return {height:componentEditorValue(section,'height'),width:componentEditorValue(section,'width'),quantity:'1',faces_seen:componentEditorValue(section,'faces_seen'),edges_seen:selectedEdges(section).join(' + ')};
			if(component==='fillers')return {height:componentEditorValue(section,'height'),width:componentEditorValue(section,'width'),quantity:'1',faces_seen:componentEditorValue(section,'faces_seen'),edges_seen:componentEditorValue(section,'edges_seen')};
			if(component==='kickboards')return {material:componentEditorValue(section,'material'),height:componentEditorValue(section,'height'),length:componentEditorValue(section,'length'),quantity:componentEditorValue(section,'quantity')||'1'};
			return {};
		}
		function requiredComponentFields(section){
			const component=section.dataset.component;
			if(component==='end_panels')return ['width','height','faces_seen'];
			if(component==='fillers')return ['width','height','faces_seen','edges_seen'];
			if(component==='kickboards')return ['material','height','length','quantity'];
			return [];
		}
		function clearComponentEditor(section){
			section.dataset.editingIndex='';
			section.querySelectorAll('[data-component-field]').forEach(input=>{input.value=input.dataset.componentField==='quantity'?'1':(input.dataset.defaultValue||'');});
			section.querySelectorAll('[data-edge-position]').forEach(input=>input.checked=false);
			const edgeSelector=section.querySelector('[data-edge-selector]');if(edgeSelector)edgeSelector.classList.remove('is-saved');
			const button=section.querySelector('.qs-commit-component');if(button)button.textContent='Add Item';
		}
		function componentDisplayValues(section){
			if(section.dataset.component!=='kickboards')return {};
			const select=componentEditorField(section,'material');
			return {material:select?.selectedOptions[0]?.textContent.trim()||''};
		}
		function componentSignature(component,values){return (storedFieldMap[component]||[]).filter(key=>key!=='quantity').map(key=>String(values[key]||'')).join('|');}
		function commitComponentItem(section){
			const values=componentEditorValues(section);
			for(const key of requiredComponentFields(section)){const input=componentEditorField(section,key);const invalid=!input||!input.value||(input&&input.type==='number'&&Number(input.value)<=0);if(invalid){if(input){input.setCustomValidity('Please complete this field.');input.reportValidity();window.setTimeout(()=>input.setCustomValidity(''),0);}return;}}
			if(section.dataset.component==='end_panels'&&!values.edges_seen){const firstEdge=section.querySelector('[data-edge-position]');if(firstEdge){firstEdge.setCustomValidity('Select at least one seen edge.');firstEdge.reportValidity();window.setTimeout(()=>firstEdge.setCustomValidity(''),0);}return;}
			const rows=[...section.querySelectorAll('.qs-repeater-row')];
			const editing=section.dataset.editingIndex;
			let row=editing!==''?rows[Number(editing)]:null;
			if(row&&section.dataset.component!=='kickboards')values.quantity=storedValues(row).quantity||'1';
			if(!row){const signature=componentSignature(section.dataset.component,values);row=rows.find(candidate=>componentSignature(section.dataset.component,storedValues(candidate))===signature);if(row)values.quantity=String((Number(storedValues(row).quantity)||0)+(Number(values.quantity)||1));}
			const displays=componentDisplayValues(section);
			if(row)setStoredValues(row,section.dataset.component,values,displays);else createStoredRow(section,values,displays);
			reindex(section);refresh();scheduleCalculation();clearComponentEditor(section);
		}
		function loadComponentEditor(section,row,index){
			const values=storedValues(row);
			section.dataset.editingIndex=String(index);
			section.querySelectorAll('[data-component-field]').forEach(input=>{const key=input.dataset.componentField;input.value=values[key]||(key==='quantity'?'1':'');});
			if(section.dataset.component==='end_panels'){const edges=String(values.edges_seen||'').toLowerCase();section.querySelectorAll('[data-edge-position]').forEach(input=>input.checked=edges.includes(input.value.toLowerCase()));}
			const button=section.querySelector('.qs-commit-component');if(button)button.textContent='Update Item';
			section.scrollIntoView({behavior:'smooth',block:'center'});
			const first=section.querySelector('[data-component-field]');if(first)first.focus({preventScroll:true});
		}
		document.querySelectorAll('.qs-component').forEach(section=>{section.addEventListener('click',e=>{const button=e.target.closest('button');if(!button)return;if(button.matches('.qs-add-row'))addRow(section);if(button.matches('[data-select-type]'))selectEditorType(section,button.dataset.selectType);if(button.matches('.qs-commit-item'))commitDoorItem(section);if(button.matches('.qs-commit-component'))commitComponentItem(section);if(button.matches('[data-save-edges]')){const edges=selectedEdges(section);if(!edges.length){const first=section.querySelector('[data-edge-position]');if(first){first.setCustomValidity('Select at least one seen edge.');first.reportValidity();window.setTimeout(()=>first.setCustomValidity(''),0);}return;}section.querySelector('[data-edge-selector]')?.classList.add('is-saved');button.textContent='Saved';window.setTimeout(()=>button.textContent='Save',900);}if(button.matches('.qs-remove-row'))removeRow(section,button.closest('.qs-repeater-row'));});});
		document.querySelectorAll('.qs-doors-drawers [data-editor-field="drawer_count"]').forEach(select=>select.addEventListener('change',()=>updateBankHeightFields(select.closest('.qs-doors-drawers'))));
		function closeProductPicker(picker){if(!picker)return;picker.classList.remove('is-open');picker.querySelector('[data-picker-toggle]')?.setAttribute('aria-expanded','false');}
		function selectedProductLabel(name){const checked=form.querySelector('[name="'+name+'"]:checked');return checked?.closest('.qs-product-option')?.dataset.optionLabel||'';}
		function updateSpecificationAvailability(){
			const painted=/paint/i.test(selectedProductLabel('timber'));
			const paintField=form.querySelector('[data-paint-colour-field]');
			const paintInput=paintField?.querySelector('[name="paint_colour"]');
			if(paintField)paintField.hidden=!painted;
			if(paintInput){paintInput.disabled=!painted;paintInput.required=painted;}
			const finishField=form.querySelector('[data-product-field="finish"]');
			if(finishField){finishField.hidden=painted;finishField.querySelectorAll('input').forEach(input=>input.disabled=painted);}
		}
		document.querySelectorAll('[data-picker-toggle]').forEach(toggle=>toggle.addEventListener('click',()=>{
			const picker=toggle.closest('.qs-product-picker'),opening=!picker.classList.contains('is-open');
			document.querySelectorAll('.qs-product-picker.is-open').forEach(closeProductPicker);
			picker.classList.toggle('is-open',opening);
			toggle.setAttribute('aria-expanded',opening?'true':'false');
		}));
		document.addEventListener('click',event=>{if(!event.target.closest('.qs-product-picker'))document.querySelectorAll('.qs-product-picker.is-open').forEach(closeProductPicker);});
		document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('.qs-product-picker.is-open').forEach(closeProductPicker);});
		document.querySelectorAll('.qs-product-option input').forEach(input=>input.addEventListener('change',()=>{
			const picker=input.closest('.qs-product-picker'),option=input.closest('.qs-product-option');
			picker.querySelectorAll('.qs-product-option').forEach(row=>row.classList.toggle('is-selected',row===option));
			const selectedLabel=picker.querySelector('[data-picker-selected-label]');
			const selectedSwatch=picker.querySelector('[data-picker-selected-swatch]');
			if(selectedLabel)selectedLabel.textContent=option.dataset.optionLabel||'Select an option';
			if(selectedSwatch)selectedSwatch.style.backgroundImage=option.dataset.optionImage?'url("'+option.dataset.optionImage.replace(/"/g,'\\"')+'")':'';
			closeProductPicker(picker);
			updateSpecificationAvailability();
			refresh();
			scheduleCalculation();
		}));
		function labelFor(name){const input=form.querySelector('[name="'+name+'"]:not(:disabled)');if(!input)return '—';if(input.type==='radio'){const checked=form.querySelector('[name="'+name+'"]:checked:not(:disabled)');return checked?.closest('.qs-product-option')?.dataset.optionLabel||'—';}return input.value.trim()||'—';}
		function field(row,key){return row.querySelector('[name$="['+key+']"]');}
		function fieldValue(row,key){const input=field(row,key);if(!input)return '';if(input.dataset.displayValue)return input.dataset.displayValue;return input.tagName==='SELECT'&&input.selectedOptions[0]?input.selectedOptions[0].textContent.trim():input.value.trim();}
		function itemDetails(component,row){const width=fieldValue(row,'width'),height=fieldValue(row,'height'),type=fieldValue(row,'type');if(component==='doors_drawers'){if(type==='Drawer Bank'){const rawHeights=[['Top',fieldValue(row,'top_height')],['Top Middle',fieldValue(row,'top_middle_height')],['Middle',fieldValue(row,'middle_height')],['Bottom Middle',fieldValue(row,'bottom_middle_height')],['Bottom',fieldValue(row,'bottom_height')]].filter(([,value])=>value);const totalHeight=rawHeights.reduce((sum,[,value])=>sum+(Number(value)||0),0);const heights=rawHeights.map(([label,value])=>label+': '+value+'mm');return {primary:[width,totalHeight||''].filter(Boolean).join(' × ')||'Drawer Bank',extra:heights};}return {primary:[width,height].filter(Boolean).join(' × '),extra:[]};}if(component==='kickboards')return {primary:fieldValue(row,'material'),extra:[[height,fieldValue(row,'length')].filter(Boolean).join(' × ')]};return {primary:[height,width].filter(Boolean).join(' × '),extra:[]};}
		function addSummaryAction(actions,label,icon,action,component,index){const button=document.createElement('button');button.type='button';button.className='qs-summary-action';button.dataset.summaryAction=action;button.dataset.component=component;button.dataset.rowIndex=index;button.setAttribute('aria-label',label);const image=document.createElement('img');image.src=icon;image.alt='';button.appendChild(image);actions.appendChild(button);}
		function addSummaryRow(group,component,item){const details=itemDetails(component,item.row);const entry=document.createElement('div');entry.className='qs-summary-item';entry.dataset.summaryComponent=component;entry.dataset.summaryRowIndex=String(item.index);if(activeSummaryEdit&&activeSummaryEdit.component===component&&activeSummaryEdit.rowIndex===item.index)entry.classList.add('is-summary-editing');const content=document.createElement('div');content.className='qs-summary-item-content';const primary=document.createElement('span');primary.className='qs-summary-item-primary';primary.textContent=details.primary||'Item';content.appendChild(primary);details.extra.filter(Boolean).forEach(text=>{const extra=document.createElement('span');extra.className='qs-summary-item-extra';extra.textContent=text;content.appendChild(extra);});const quantity=document.createElement('span');quantity.className='qs-summary-item-quantity';quantity.textContent='Qty. '+fieldValue(item.row,'quantity');const actions=document.createElement('div');actions.className='qs-summary-item-actions';addSummaryAction(actions,'Edit item',summaryIcons.edit,'edit',component,item.index);addSummaryAction(actions,'Remove item',summaryIcons.remove,'remove',component,item.index);entry.append(content,quantity,actions);group.appendChild(entry);}
		function addSummaryGroup(title,component,items){if(!items.length)return;const group=document.createElement('div');group.className='qs-summary-group';const heading=document.createElement('strong');heading.className='qs-summary-group-title';heading.textContent=title+' ('+items.length+')';group.appendChild(heading);items.forEach(item=>addSummaryRow(group,component,item));summaryItems.appendChild(group);}
		function refresh(){document.querySelectorAll('.qs-repeater-row').forEach(syncRowType);['door_profile','timber','finish','handle_profile','paint_colour'].forEach(name=>{const target=document.querySelector('[data-summary="'+name+'"]');if(target)target.textContent=labelFor(name)||'—';});summaryItems.innerHTML='';document.querySelectorAll('.qs-component').forEach(section=>{const component=section.dataset.component;const items=[...section.querySelectorAll('.qs-repeater-row')].map((row,index)=>({row,index})).filter(item=>Number(fieldValue(item.row,'quantity'))>0);if(!items.length)return;if(component==='doors_drawers'){addSummaryGroup('Doors',component,items.filter(item=>fieldValue(item.row,'type')==='Door'));addSummaryGroup('Drawers',component,items.filter(item=>fieldValue(item.row,'type')==='Drawer'));addSummaryGroup('Drawer Banks',component,items.filter(item=>fieldValue(item.row,'type')==='Drawer Bank'));}else{addSummaryGroup(section.querySelector('h3').textContent,component,items);}});}
		if(summaryItems)summaryItems.addEventListener('click',event=>{const button=event.target.closest('[data-summary-action]');if(!button)return;const section=document.querySelector('.qs-component[data-component="'+button.dataset.component+'"]');const row=section&&section.querySelectorAll('.qs-repeater-row')[Number(button.dataset.rowIndex)];if(!row)return;if(button.dataset.summaryAction==='remove'){if(activeSummaryEdit&&activeSummaryEdit.component===button.dataset.component&&activeSummaryEdit.rowIndex===Number(button.dataset.rowIndex))activeSummaryEdit=null;removeRow(section,row);return;}activeSummaryEdit={component:button.dataset.component,rowIndex:Number(button.dataset.rowIndex)};if(activeSummaryEditTimer)window.clearTimeout(activeSummaryEditTimer);summaryItems.querySelectorAll('.qs-summary-item.is-summary-editing,.qs-summary-item.is-summary-edit-fading').forEach(item=>item.classList.remove('is-summary-editing','is-summary-edit-fading'));const activeItem=button.closest('.qs-summary-item');activeItem?.classList.add('is-summary-editing');activeSummaryEditTimer=window.setTimeout(()=>{if(!activeItem)return;activeItem.classList.add('is-summary-edit-fading');window.setTimeout(()=>{activeItem.classList.remove('is-summary-editing','is-summary-edit-fading');activeSummaryEdit=null;},500);},3000);if(section.dataset.component==='doors_drawers'){loadDoorEditor(section,row,Number(button.dataset.rowIndex));return;}if(section.classList.contains('qs-configured-component')){loadComponentEditor(section,row,Number(button.dataset.rowIndex));return;}row.classList.add('qs-summary-editing');row.scrollIntoView({behavior:'smooth',block:'center'});const focusTarget=row.querySelector('input,select,textarea');if(focusTarget)focusTarget.focus({preventScroll:true});window.setTimeout(()=>row.classList.remove('qs-summary-editing'),1300);});
		function scheduleCalculation(){
			clearTimeout(calculateTimer);
			calculateTimer=setTimeout(recalculateSubtotal,700);
		}
		function recalculateSubtotal(){
			const projectName=form.querySelector('[name="project_name"]');
			if(!projectName||!projectName.value.trim())return;
			if(calculateRequest)calculateRequest.abort();
			calculateRequest=new AbortController();
			const data=new FormData(form);
			data.delete('supporting_documents[]');
			data.delete('remove_supporting_documents[]');
			data.append('action','qs_builder_recalculate');
			data.append('quote_id',String(quoteId));
			if(subtotal)subtotal.classList.add('is-calculating');
			fetch(ajaxUrl,{method:'POST',body:data,credentials:'same-origin',signal:calculateRequest.signal})
				.then(response=>response.json())
				.then(result=>{
					if(!result.success)throw new Error(result.data&&result.data.message?result.data.message:'Unable to calculate subtotal.');
					quoteId=Number(result.data.quote_id)||quoteId;
					if(subtotal)subtotal.textContent='$'+result.data.formatted_subtotal+' AUD';
					const url=new URL(window.location.href);
					url.searchParams.set('quote_id',String(quoteId));
					url.searchParams.set('saved','1');
					window.history.replaceState({},'',url.toString());
				})
				.catch(error=>{if(error.name!=='AbortError'&&window.console)console.error('Quote calculation:',error);})
				.finally(()=>{if(subtotal)subtotal.classList.remove('is-calculating');});
		}
		const uploadInput=form.querySelector('#qs-supporting-documents');
		const uploadList=form.querySelector('[data-upload-file-list]');
		const uploadDropzone=form.querySelector('.qs-upload-dropzone');
		let stagedUploads=[];
		function formatBytes(bytes){if(!bytes)return '';const units=['B','KB','MB'];let value=bytes,index=0;while(value>=1024&&index<units.length-1){value/=1024;index++;}return value.toFixed(index?2:0)+' '+units[index];}
		function syncUploadInput(){if(!uploadInput||typeof DataTransfer==='undefined')return;const transfer=new DataTransfer();stagedUploads.forEach(entry=>transfer.items.add(entry.file));uploadInput.files=transfer.files;}
		function uploadIconFor(file){return file.type==='application/pdf'||/\.pdf$/i.test(file.name)?uploadIcons.pdf:uploadIcons.image;}
		function updateUploadProgress(entry){
			const row=uploadList?.querySelector('[data-staged-upload="'+entry.id+'"]');
			const progress=row?.querySelector('.qs-upload-progress-value');
			if(progress)progress.style.width=Math.max(2,Math.min(100,entry.progress||0))+'%';
			if(row)row.classList.toggle('is-read',entry.progress>=100);
		}
		function readStagedUpload(entry){
			if(entry.reader||entry.progress>=100)return;
			entry.reader=new FileReader();
			entry.reader.onprogress=event=>{entry.progress=event.lengthComputable?Math.round((event.loaded/event.total)*100):20;updateUploadProgress(entry);};
			entry.reader.onload=()=>{entry.progress=100;updateUploadProgress(entry);};
			entry.reader.onerror=()=>{entry.progress=100;updateUploadProgress(entry);};
			entry.reader.readAsArrayBuffer(entry.file);
		}
		function renderStagedUploads(){
			if(!uploadList)return;
			uploadList.querySelectorAll('[data-staged-upload]').forEach(row=>row.remove());
			if(stagedUploads.length&&!uploadList.querySelector('.qs-upload-list-heading')){const heading=document.createElement('p');heading.className='qs-upload-list-heading';heading.textContent='Files added';uploadList.prepend(heading);}
			stagedUploads.forEach(entry=>{const file=entry.file,row=document.createElement('div');row.className='qs-upload-file';row.dataset.stagedUpload=entry.id;const icon=document.createElement('span');icon.className='qs-upload-file-icon';const iconImage=document.createElement('img');iconImage.src=uploadIconFor(file);iconImage.alt='';icon.appendChild(iconImage);const name=document.createElement('span');name.className='qs-upload-file-name';name.textContent=file.name;const size=document.createElement('small');size.textContent=formatBytes(file.size);const remove=document.createElement('button');remove.type='button';remove.dataset.removeStagedUpload=entry.id;remove.setAttribute('aria-label','Remove '+file.name);remove.innerHTML='<svg aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/></svg>';const track=document.createElement('span');track.className='qs-upload-progress';track.setAttribute('aria-hidden','true');const progress=document.createElement('span');progress.className='qs-upload-progress-value';progress.style.width=Math.max(2,entry.progress||0)+'%';track.appendChild(progress);row.append(icon,name,size,remove,track);uploadList.appendChild(row);readStagedUpload(entry);});
			if(!uploadList.querySelector('.qs-upload-file'))uploadList.querySelector('.qs-upload-list-heading')?.remove();
		}
		function stageUploads(files){const incoming=[...files];const invalid=incoming.find(file=>file.size>10*1024*1024||!(['image/jpeg','image/png','application/pdf'].includes(file.type)||/\.(jpe?g|png|pdf)$/i.test(file.name)));if(invalid){window.alert('Only JPG, PNG and PDF files up to 10MB are allowed.');if(uploadInput)uploadInput.value='';return;}stagedUploads=stagedUploads.concat(incoming.map((file,index)=>({file,progress:0,reader:null,id:'upload-'+Date.now()+'-'+index+'-'+Math.random().toString(36).slice(2,8)})));syncUploadInput();renderStagedUploads();}
		if(uploadInput)uploadInput.addEventListener('change',()=>stageUploads(uploadInput.files));
		if(uploadDropzone){uploadDropzone.addEventListener('dragover',event=>{event.preventDefault();uploadDropzone.classList.add('is-dragging');});uploadDropzone.addEventListener('dragleave',()=>uploadDropzone.classList.remove('is-dragging'));uploadDropzone.addEventListener('drop',event=>{event.preventDefault();uploadDropzone.classList.remove('is-dragging');if(event.dataTransfer?.files?.length)stageUploads(event.dataTransfer.files);});}
		if(uploadList)uploadList.addEventListener('click',event=>{const removeExisting=event.target.closest('[data-remove-upload]');if(removeExisting){const row=removeExisting.closest('[data-existing-upload]');if(row){const hidden=document.createElement('input');hidden.type='hidden';hidden.name='remove_supporting_documents[]';hidden.value=row.dataset.existingUpload;form.appendChild(hidden);row.remove();if(!uploadList.querySelector('.qs-upload-file'))uploadList.querySelector('.qs-upload-list-heading')?.remove();}return;}const removeStaged=event.target.closest('[data-remove-staged-upload]');if(removeStaged){const index=stagedUploads.findIndex(entry=>entry.id===removeStaged.dataset.removeStagedUpload);if(index>=0)stagedUploads.splice(index,1);syncUploadInput();renderStagedUploads();}});
		form.addEventListener('submit',()=>uploadList?.querySelectorAll('[data-staged-upload]').forEach(row=>row.classList.add('is-submitting')));
		form.addEventListener('input',event=>{if(event.target.closest('.qs-repeater-row'))refresh();if(!event.target.classList.contains('qs-product-search')&&!event.target.matches('[data-editor-field],[data-component-field],[data-edge-position],#qs-supporting-documents'))scheduleCalculation();});
		form.addEventListener('change',event=>{if(!event.target.classList.contains('qs-product-search')&&!event.target.matches('[data-editor-field],[data-component-field],[data-edge-position],#qs-supporting-documents'))scheduleCalculation();});
		document.querySelectorAll('.qs-doors-drawers').forEach(section=>updateBankHeightFields(section));
		updateSpecificationAvailability();
		refresh();
		if(quoteId)scheduleCalculation();
	}());
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'quote_builder', 'qs_quote_builder_shortcode' );
