<?php
/**
 * Customer quote review and summary page.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_quote_review_can_access( $quote_id ) {
	$quote = get_post( $quote_id );
	if ( ! $quote || 'quote' !== $quote->post_type || ! is_user_logged_in() ) {
		return false;
	}

	return current_user_can( 'edit_post', $quote_id ) || (int) $quote->post_author === get_current_user_id();
}

function qs_quote_review_handle_remove_item( $quote_id ) {
	if ( empty( $_POST['qs_review_remove_item'] ) ) {
		return '';
	}

	$nonce     = isset( $_POST['qs_review_item_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_review_item_nonce'] ) ) : '';
	$component = isset( $_POST['component'] ) ? sanitize_key( wp_unslash( $_POST['component'] ) ) : '';
	$row_index = isset( $_POST['row_index'] ) ? absint( $_POST['row_index'] ) : -1;

	if (
		! wp_verify_nonce( $nonce, 'qs_review_item_' . $quote_id ) ||
		! qs_quote_review_can_access( $quote_id ) ||
		'draft' !== get_post_status( $quote_id ) ||
		! array_key_exists( $component, qs_component_definitions() )
	) {
		return 'That item could not be removed.';
	}

	$rows = qs_component_rows( $quote_id, $component );
	if ( ! isset( $rows[ $row_index ] ) ) {
		return 'That item is no longer part of this quote.';
	}

	unset( $rows[ $row_index ] );
	update_post_meta( $quote_id, '_qs_' . $component, array_values( $rows ) );
	qs_recalculate_quote_pricing( $quote_id );

	return 'Item removed from the draft.';
}

function qs_review_specification_cards( $data ) {
	$specifications = array(
		array( 'label' => 'Profile', 'value' => $data['door_profile'], 'product' => $data['door_profile_id'], 'detail' => '' ),
		array( 'label' => 'Finish', 'value' => $data['finish'] ? $data['finish'] : '—', 'product' => $data['finish_id'], 'detail' => '' ),
		array( 'label' => 'Timber', 'value' => $data['timber'], 'product' => $data['timber_id'], 'detail' => $data['paint_colour'] ? 'Paint Colour: ' . $data['paint_colour'] : '' ),
		array( 'label' => 'Door / Drawer Handle Profile', 'value' => $data['handle_profile'], 'product' => $data['handle_profile_id'], 'detail' => '' ),
	);
	?>
	<div class="qs-review-spec-grid">
		<?php foreach ( $specifications as $specification ) :
			$image = qs_quote_product_image_url( $specification['product'] );
			?>
			<div class="qs-review-spec">
				<span class="qs-review-swatch"<?php echo $image ? ' style="' . esc_attr( 'background-image:url("' . $image . '")' ) . '"' : ''; ?>></span>
				<span>
					<strong><?php echo esc_html( $specification['label'] ); ?>:</strong>
					<?php echo esc_html( $specification['value'] ? $specification['value'] : '—' ); ?>
					<?php if ( $specification['detail'] ) : ?><small><?php echo esc_html( $specification['detail'] ); ?></small><?php endif; ?>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

function qs_review_component_sections( $quote_id, $data ) {
	$doors_drawers = $data['component_rows']['doors_drawers'];
	$fronts        = array_values(
		array_filter(
			$doors_drawers,
			static function ( $row ) {
				return empty( $row['type'] ) || 'Drawer Bank' !== $row['type'];
			}
		)
	);
	$drawer_banks  = qs_component_rows_by_type( $doors_drawers, 'Drawer Bank' );
	?>
	<section class="qs-review-section">
		<h3>Doors &amp; Drawers</h3>
		<p class="qs-review-note"><em>Grains run vertical (height)</em></p>
		<?php qs_render_quote_component_table( $fronts, array( 'type' => 'Item Type', 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
		<?php if ( $drawer_banks ) : ?>
			<h4>Drawer Banks</h4>
			<?php qs_render_quote_component_table( $drawer_banks, array( 'type' => 'Item Type', 'item_specifications' => 'Specifications', 'configuration' => 'Configuration', 'width' => 'Width', 'height_details' => 'Height', 'quantity' => 'Quantity' ), 'qs-review-table', 'No drawer banks supplied.', count( $fronts ) + 1 ); ?>
		<?php endif; ?>
	</section>

	<section class="qs-review-section">
		<h3>End Panels &amp; Fillers</h3>
		<h4>End Panels</h4>
		<?php qs_render_quote_component_table( $data['component_rows']['end_panels'], array( 'item_specifications' => 'Specifications', 'height' => 'Height', 'width' => 'Width', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
		<h4>Fillers</h4>
		<?php qs_render_quote_component_table( $data['component_rows']['fillers'], array( 'item_specifications' => 'Specifications', 'height' => 'Height', 'width' => 'Width', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
	</section>

	<section class="qs-review-section">
		<h3>Kickboards</h3>
		<p class="qs-review-note"><em>* Grain runs long / horizontal<br>* Max 2400mm per piece<br>* 1 face / no edges finished<br>* Cost at LM Rate - see kick tab</em></p>
		<?php qs_render_quote_component_table( $data['component_rows']['kickboards'], array( 'material' => 'Kick Material', 'item_specifications' => 'Specifications', 'height' => 'Kick Height', 'length' => 'Kick Length', 'quantity' => 'Quantity' ), 'qs-review-table' ); ?>
	</section>
	<?php
}

function qs_review_summary_items( $quote_id, $can_edit ) {
	$edit_url = add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) );
	foreach ( qs_quote_summary_groups( $quote_id ) as $group ) :
		if ( ! $group['rows'] ) {
			continue;
		}
		?>
		<div class="qs-review-summary-group">
			<strong><?php echo esc_html( $group['title'] . ' (' . count( $group['rows'] ) . ')' ); ?></strong>
			<?php foreach ( $group['rows'] as $row ) : ?>
				<div class="qs-review-summary-item">
					<span class="qs-review-summary-description">
						<span><?php echo esc_html( qs_quote_summary_primary( $group['component'], $row ) ); ?></span>
						<?php if ( qs_quote_summary_secondary( $group['component'], $row ) ) : ?><small><?php echo nl2br( esc_html( qs_quote_summary_secondary( $group['component'], $row ) ) ); ?></small><?php endif; ?>
					</span>
					<span>Qty. <?php echo esc_html( isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0 ); ?></span>
					<?php if ( $can_edit ) : ?>
						<span class="qs-review-summary-controls">
							<a href="<?php echo esc_url( $edit_url ); ?>" aria-label="Edit item"><img src="<?php echo esc_url( QS_URL . 'assets/images/icon-pen.svg' ); ?>" alt=""></a>
							<form method="post" data-confirm="Remove this item from the draft?">
								<?php wp_nonce_field( 'qs_review_item_' . $quote_id, 'qs_review_item_nonce' ); ?>
								<input type="hidden" name="component" value="<?php echo esc_attr( $group['component'] ); ?>">
								<input type="hidden" name="row_index" value="<?php echo esc_attr( isset( $row['_row_index'] ) ? $row['_row_index'] : 0 ); ?>">
								<button type="submit" name="qs_review_remove_item" aria-label="Remove item"><img src="<?php echo esc_url( QS_URL . 'assets/images/icon-trash.svg' ); ?>" alt=""></button>
							</form>
						</span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	endforeach;
}

/**
 * Shows workflow controls in the shared summary when an administrator opens
 * the quote. The quote itself remains owned by the trade account that created
 * it; administrators receive control through WordPress capabilities.
 */
function qs_review_admin_summary_actions( $quote_id, $status ) {
	$dashboard_url = site_url( '/quote-admin-dashboard/' );
	$builder_url   = add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) );
	$pricing_url   = admin_url( 'post.php?post=' . $quote_id . '&action=edit' );
	$quotation_url = add_query_arg( 'download_quote_pdf', $quote_id, home_url( '/' ) );
	$jobsheet_url  = add_query_arg( 'download_jobsheet_pdf', $quote_id, home_url( '/' ) );
	?>
	<div class="qs-review-summary-actions qs-review-admin-actions">
		<h3>Admin Actions</h3>
		<a class="qs-btn qs-btn-outline" href="<?php echo esc_url( $dashboard_url ); ?>">Admin Dashboard</a>
		<a class="qs-btn qs-btn-outline" href="<?php echo esc_url( $pricing_url ); ?>">Edit Pricing</a>

		<?php if ( 'draft' === $status ) : ?>
			<a class="qs-btn" href="<?php echo esc_url( $builder_url ); ?>">Edit Quote</a>
		<?php endif; ?>

		<a class="qs-btn" href="<?php echo esc_url( $quotation_url ); ?>" target="_blank" rel="noopener">Quotation PDF</a>
		<a class="qs-btn" href="<?php echo esc_url( $jobsheet_url ); ?>" target="_blank" rel="noopener">Job Sheet</a>

		<?php if ( in_array( $status, array( 'pending', 'pending_review' ), true ) ) : ?>
			<?php qs_admin_dashboard_action_button( $quote_id, 'request_deposit', 'Request Deposit' ); ?>
			<?php qs_admin_dashboard_action_button( $quote_id, 'mark_approved', 'Mark as Approved' ); ?>
		<?php elseif ( 'awaiting_deposit' === $status ) : ?>
			<?php qs_admin_dashboard_action_button( $quote_id, 'resend_deposit', 'Resend Deposit' ); ?>
			<?php qs_admin_dashboard_action_button( $quote_id, 'mark_deposit_paid', 'Mark Deposit Paid', 'Confirm that this deposit was received outside WooCommerce?' ); ?>
		<?php elseif ( in_array( $status, array( 'deposit_paid', 'final_balance' ), true ) ) : ?>
			<?php qs_admin_dashboard_action_button( $quote_id, 'create_final_invoice', 'Create Final Invoice' ); ?>
			<?php qs_admin_dashboard_action_button( $quote_id, 'mark_in_production', 'Mark In Production' ); ?>
		<?php elseif ( 'paid_in_full' === $status ) : ?>
			<?php qs_admin_dashboard_action_button( $quote_id, 'duplicate_quote', 'Duplicate Quote' ); ?>
			<?php qs_admin_dashboard_action_button( $quote_id, 'archive_quote', 'Archive Quote', 'Archive this completed quote?' ); ?>
		<?php endif; ?>
	</div>
	<?php
}

function qs_quote_review_shortcode() {
	$quote_id = isset( $_GET['quote_id'] )
		? absint( $_GET['quote_id'] )
		: ( isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0 );
	if ( ! $quote_id || ! qs_quote_review_can_access( $quote_id ) ) {
		return '<p>Quote not found.</p>';
	}

	$is_admin = current_user_can( 'edit_others_posts' );
	$message  = '';

	if ( $is_admin && isset( $_POST['qs_dashboard_action'] ) ) {
		$message = qs_admin_dashboard_handle_action();
	}

	if ( isset( $_POST['qs_submit_quote'] ) ) {
		$nonce = isset( $_POST['qs_submit_quote_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_submit_quote_nonce'] ) ) : '';
		if ( $is_admin || ! wp_verify_nonce( $nonce, 'qs_submit_quote_' . $quote_id ) || 'draft' !== get_post_status( $quote_id ) ) {
			return '<p>Security check failed. Please try again.</p>';
		}
		qs_update_quote_status( $quote_id, 'pending_review' );
		qs_email_quote_submitted( $quote_id );
		wp_safe_redirect( add_query_arg( 'quote_id', $quote_id, site_url( '/quote-thank-you/' ) ) );
		exit;
	}

	$item_message = qs_quote_review_handle_remove_item( $quote_id );
	if ( $item_message ) {
		$message = $item_message;
	}

	$data     = qs_get_quote_data( $quote_id );
	$is_draft = 'draft' === get_post_status( $quote_id );
	$status   = get_post_status( $quote_id );
	$subtotal = (float) $data['subtotal'];

	ob_start();
	?>
	<div class="qs-container qs-review-page">
		<header class="qs-review-page-header">
			<h1>Quote Builder</h1>
			<nav aria-label="Quote account actions">
				<a class="qs-btn qs-btn-outline" href="<?php echo esc_url( site_url( $is_admin ? '/quote-admin-dashboard/' : '/my-quotes/' ) ); ?>"><?php echo esc_html( $is_admin ? 'Admin Dashboard' : 'My Quotes' ); ?></a>
				<a class="qs-btn" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Logout</a>
			</nav>
		</header>

		<?php if ( $message ) : ?><div class="qs-review-notice"><?php echo esc_html( $message ); ?></div><?php endif; ?>

		<div class="qs-review-layout">
			<main class="qs-review-main">
				<section class="qs-review-intro">
					<h2>Review Your Quote</h2>
					<p>Please review the details below before submitting your quote to Loughlin Furniture.<br>You can edit your selections if changes are required.</p>
				</section>

				<section class="qs-review-section qs-review-project">
					<h3>Project Details</h3>
					<dl>
						<dt>Company:</dt><dd><?php echo esc_html( $data['company_name'] ); ?></dd>
						<dt>Project Name:</dt><dd><?php echo esc_html( $data['project_name'] ); ?></dd>
						<dt>Pricing Mode:</dt><dd><?php echo esc_html( $data['pricing_type'] ); ?></dd>
						<dt>Delivery Address:</dt><dd><?php echo nl2br( esc_html( $data['delivery_address'] ) ); ?></dd>
					</dl>
				</section>

				<section class="qs-review-section">
					<h3>Selected Specifications</h3>
					<?php qs_review_specification_cards( $data ); ?>
				</section>

				<?php qs_review_component_sections( $quote_id, $data ); ?>

				<?php if ( $data['custom_requests'] ) : ?>
					<section class="qs-review-section"><h3>Custom Requests</h3><p><?php echo nl2br( esc_html( $data['custom_requests'] ) ); ?></p></section>
				<?php endif; ?>

				<section class="qs-review-section"><h3>Project Notes</h3><p><?php echo nl2br( esc_html( $data['project_notes'] ) ); ?></p></section>
			</main>

			<aside class="qs-review-summary">
				<h2>Quote Summary</h2>
				<h3>Selected Specifications</h3>
				<dl>
					<dt>Profile</dt><dd><?php echo esc_html( $data['door_profile'] ? $data['door_profile'] : '—' ); ?></dd>
					<dt>Timber</dt><dd><?php echo esc_html( $data['timber'] ? $data['timber'] : '—' ); ?></dd>
					<dt>Finish</dt><dd><?php echo esc_html( $data['finish'] ? $data['finish'] : '—' ); ?></dd>
					<dt>Door / Drawer Handle</dt><dd><?php echo esc_html( $data['handle_profile'] ? $data['handle_profile'] : '—' ); ?></dd>
					<dt>Paint Colour</dt><dd><?php echo esc_html( $data['paint_colour'] ? $data['paint_colour'] : '—' ); ?></dd>
				</dl>
				<h3>Items Breakdown</h3>
				<div class="qs-review-summary-items"><?php qs_review_summary_items( $quote_id, $is_draft ); ?></div>
				<div class="qs-review-lead-time"><strong>Estimated Lead Time</strong><span>4-6 Weeks</span></div>
				<div class="qs-review-subtotal"><span>Subtotal</span><strong>$<?php echo esc_html( number_format_i18n( $subtotal, 2 ) ); ?> AUD</strong></div>
				<?php if ( $is_admin ) : ?>
					<?php qs_review_admin_summary_actions( $quote_id, $status ); ?>
				<?php else : ?>
					<div class="qs-review-summary-actions">
						<?php if ( $is_draft ) : ?>
							<a class="qs-btn qs-btn-outline" href="<?php echo esc_url( add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) ) ); ?>">Edit Quote</a>
							<form method="post">
								<?php wp_nonce_field( 'qs_submit_quote_' . $quote_id, 'qs_submit_quote_nonce' ); ?>
								<input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>">
								<button class="qs-btn" type="submit" name="qs_submit_quote">Submit Quote</button>
							</form>
						<?php else : ?>
							<a class="qs-btn qs-btn-outline" href="<?php echo esc_url( site_url( '/my-quotes/' ) ); ?>">My Quotes</a>
							<a class="qs-btn" href="<?php echo esc_url( add_query_arg( 'download_quote_pdf', $quote_id, home_url( '/' ) ) ); ?>" target="_blank" rel="noopener">Download PDF</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
	<script>
	(function(){
		document.querySelectorAll('.qs-review-summary form[data-confirm]').forEach(function(form){
			form.addEventListener('submit',function(event){
				if(!window.confirm(form.getAttribute('data-confirm')))event.preventDefault();
			});
		});
	}());
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'quote_review', 'qs_quote_review_shortcode' );
add_shortcode( 'my_quote_review', 'qs_quote_review_shortcode' );
