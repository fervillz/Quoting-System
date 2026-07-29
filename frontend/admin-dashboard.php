<?php
/**
 * Frontend quote-management dashboard for Loughlin administrators.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_admin_dashboard_status_display( $status ) {
	$labels = array(
		'draft'            => 'Draft',
		'pending_review'   => 'Pending Review',
		'awaiting_deposit' => 'Deposit Requested',
		'deposit_paid'     => 'Approved',
		'final_balance'    => 'Approved',
		'paid_in_full'     => 'Completed',
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '_', ' ', $status ) );
}

function qs_admin_dashboard_status_bucket( $status ) {
	if ( in_array( $status, array( 'deposit_paid', 'final_balance' ), true ) ) {
		return 'approved';
	}
	if ( 'paid_in_full' === $status ) {
		return 'completed';
	}

	return $status;
}

function qs_admin_dashboard_filter_values( $quotes, $meta_key ) {
	$values = array();
	foreach ( $quotes as $quote ) {
		$value = trim( (string) get_post_meta( $quote->ID, $meta_key, true ) );
		if ( '' !== $value ) {
			$values[] = $value;
		}
	}
	$values = array_values( array_unique( $values ) );
	natcasesort( $values );

	return array_values( $values );
}

function qs_admin_dashboard_order_url( $quote_id, $payment_type ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return '';
	}

	$order_id = absint( get_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', true ) );
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		return '';
	}

	$url = method_exists( $order, 'get_edit_order_url' )
		? $order->get_edit_order_url()
		: admin_url( 'post.php?post=' . $order_id . '&action=edit' );

	return apply_filters( 'qs_admin_dashboard_invoice_url', $url, $order, $payment_type, $quote_id );
}

function qs_admin_dashboard_duplicate_quote( $quote_id ) {
	$source = get_post( $quote_id );
	if ( ! $source || 'quote' !== $source->post_type ) {
		return new WP_Error( 'invalid_quote', 'The quote could not be duplicated.' );
	}

	$new_quote_id = wp_insert_post(
		array(
			'post_type'    => 'quote',
			'post_status'  => 'draft',
			'post_author'  => (int) $source->post_author,
			'post_title'   => wp_slash( $source->post_title . ' (Copy)' ),
			'post_content' => wp_slash( $source->post_content ),
		),
		true
	);

	if ( is_wp_error( $new_quote_id ) ) {
		return $new_quote_id;
	}

	$reset_keys = array(
		'_quote_number',
		'_shipping',
		'_discount',
		'_additional_charges',
		'_total',
		'_deposit_amount',
		'_balance_amount',
		'_internal_notes',
		'_qs_locked_deposit_amount',
		'_qs_in_production',
		'_qs_archived',
		'_qs_archived_at',
		'_qs_archived_by',
	);

	foreach ( get_post_meta( $quote_id ) as $meta_key => $values ) {
		if (
			in_array( $meta_key, $reset_keys, true ) ||
			0 === strpos( $meta_key, '_qs_deposit_' ) ||
			0 === strpos( $meta_key, '_qs_balance_' )
		) {
			continue;
		}

		foreach ( $values as $value ) {
			add_post_meta( $new_quote_id, $meta_key, maybe_unserialize( $value ) );
		}
	}

	qs_recalculate_quote_pricing( $new_quote_id );

	return $new_quote_id;
}

function qs_admin_dashboard_action_button( $quote_id, $action, $label, $confirm = '' ) {
	?>
	<form method="post" class="qs-admin-action-form"<?php echo $confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : ''; ?>>
		<?php wp_nonce_field( 'qs_admin_quote_action_' . $quote_id, 'qs_dashboard_action_nonce' ); ?>
		<input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>">
		<input type="hidden" name="qs_dashboard_action" value="<?php echo esc_attr( $action ); ?>">
		<button type="submit"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

function qs_admin_dashboard_visible_meta_query() {
	return array(
		'relation' => 'OR',
		array(
			'key'     => '_qs_archived',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => '_qs_archived',
			'value'   => '1',
			'compare' => '!=',
		),
	);
}

function qs_admin_dashboard_handle_action() {
	if ( empty( $_POST['qs_dashboard_action'] ) ) {
		return '';
	}

	$action   = sanitize_key( wp_unslash( $_POST['qs_dashboard_action'] ) );
	$quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
	$nonce    = isset( $_POST['qs_dashboard_action_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_dashboard_action_nonce'] ) ) : '';
	$quote    = get_post( $quote_id );

	if ( ! $quote || 'quote' !== $quote->post_type || ! wp_verify_nonce( $nonce, 'qs_admin_quote_action_' . $quote_id ) || ! current_user_can( 'edit_post', $quote_id ) ) {
		return 'The quote action could not be completed.';
	}

	$status = get_post_status( $quote_id );

	switch ( $action ) {
		case 'delete_draft':
			if ( 'draft' !== $status ) {
				return 'Only saved drafts can be deleted.';
			}
			return wp_trash_post( $quote_id ) ? 'Draft deleted.' : 'The draft could not be deleted.';

		case 'mark_approved':
			if ( 'pending_review' !== $status ) {
				return 'Only a quote pending review can be marked as approved.';
			}
			qs_update_quote_status( $quote_id, 'awaiting_deposit' );
			return 'Quote approved and ready for its deposit request.';

		case 'request_deposit':
		case 'resend_deposit':
			if (
				( 'request_deposit' === $action && 'pending_review' !== $status ) ||
				( 'resend_deposit' === $action && 'awaiting_deposit' !== $status )
			) {
				return 'The deposit request is not available for this quote status.';
			}
			$order_id = qs_create_payment_order( $quote_id, 'deposit' );
			if ( is_wp_error( $order_id ) ) {
				return 'The deposit request could not be created: ' . $order_id->get_error_message();
			}
			qs_update_quote_status( $quote_id, 'awaiting_deposit' );
			qs_email_quote_approved( $quote_id );
			return ( 'resend_deposit' === $action ? 'Deposit request resent for order #' : 'Deposit order #' ) . $order_id . '.';

		case 'mark_deposit_paid':
			if ( 'awaiting_deposit' !== $status ) {
				return 'Only a requested deposit can be manually marked as paid.';
			}
			qs_update_quote_status( $quote_id, 'deposit_paid' );
			update_post_meta( $quote_id, '_qs_deposit_manually_paid_by', get_current_user_id() );
			update_post_meta( $quote_id, '_qs_deposit_manually_paid_at', current_time( 'mysql' ) );
			return 'Deposit marked as paid.';

		case 'create_final_invoice':
			if ( ! in_array( $status, array( 'deposit_paid', 'final_balance' ), true ) ) {
				return 'The final invoice is only available after the deposit is paid.';
			}
			$order_id = qs_create_payment_order( $quote_id, 'balance' );
			if ( is_wp_error( $order_id ) ) {
				return 'The final invoice could not be created: ' . $order_id->get_error_message();
			}
			qs_update_quote_status( $quote_id, 'final_balance' );
			return 'Final-balance order #' . $order_id . ' is ready.';

		case 'mark_in_production':
			if ( ! in_array( $status, array( 'deposit_paid', 'final_balance' ), true ) ) {
				return 'This quote is not ready for production.';
			}
			update_post_meta( $quote_id, '_qs_in_production', current_time( 'mysql' ) );
			update_post_meta( $quote_id, '_qs_in_production_by', get_current_user_id() );
			return 'Quote marked as in production.';

		case 'duplicate_quote':
			if ( 'paid_in_full' !== $status ) {
				return 'Only a completed quote can be duplicated from this menu.';
			}
			$new_quote_id = qs_admin_dashboard_duplicate_quote( $quote_id );
			if ( is_wp_error( $new_quote_id ) ) {
				return $new_quote_id->get_error_message();
			}
			return 'Draft duplicate created: ' . get_post_meta( $new_quote_id, '_quote_number', true ) . '.';

		case 'archive_quote':
			if ( 'paid_in_full' !== $status ) {
				return 'Only a completed quote can be archived.';
			}
			update_post_meta( $quote_id, '_qs_archived', '1' );
			update_post_meta( $quote_id, '_qs_archived_at', current_time( 'mysql' ) );
			update_post_meta( $quote_id, '_qs_archived_by', get_current_user_id() );
			return 'Quote archived.';
	}

	return 'Unknown quote action.';
}

function qs_admin_dashboard_quote_actions( $quote ) {
	$status       = get_post_status( $quote->ID );
	$review_url   = add_query_arg( 'quote_id', $quote->ID, site_url( '/quote-review/' ) );
	$admin_url    = $review_url;
	$builder_url  = add_query_arg( 'quote_id', $quote->ID, site_url( '/quote-builder/' ) );
	$pdf_url      = add_query_arg( 'download_quote_pdf', $quote->ID, home_url( '/' ) );
	$jobsheet_url = add_query_arg( 'download_jobsheet_pdf', $quote->ID, home_url( '/' ) );
	$edit_url     = admin_url( 'post.php?post=' . $quote->ID . '&action=edit' );
	$deposit_url  = qs_admin_dashboard_order_url( $quote->ID, 'deposit' );
	$balance_url  = qs_admin_dashboard_order_url( $quote->ID, 'balance' );
	$actions      = array();

	switch ( $status ) {
		case 'draft':
			$actions = array(
				array( 'link', 'Edit Quote', $builder_url ),
				array( 'link', 'View Draft', $review_url ),
				array( 'form', 'Delete Draft', 'delete_draft', 'Delete this saved draft?' ),
			);
			break;

		case 'pending_review':
			$actions = array(
				array( 'link', 'Review', $admin_url ),
				array( 'link', 'View Quote', $review_url ),
				array( 'link', 'Edit Pricing', $edit_url ),
				array( 'link', 'Generate Quotation PDF', $pdf_url, true ),
				array( 'link', 'Generate Job Sheet', $jobsheet_url, true ),
				array( 'form', 'Request Deposit', 'request_deposit' ),
				array( 'link', 'Add Internal Notes', $edit_url ),
				array( 'form', 'Mark as Approved', 'mark_approved' ),
			);
			break;

		case 'awaiting_deposit':
			$actions = array(
				array( 'link', 'View', $admin_url ),
				array( 'link', 'View Quote', $review_url ),
				array( 'link', 'View Deposit Invoice', $deposit_url, true ),
				array( 'form', 'Resend Deposit Request', 'resend_deposit' ),
				array( 'link', 'Generate Quotation PDF', $pdf_url, true ),
				array( 'link', 'Generate Job Sheet', $jobsheet_url, true ),
				array( 'form', 'Mark Deposit as Paid', 'mark_deposit_paid', 'Confirm that this deposit was received outside WooCommerce?' ),
				array( 'link', 'Add Internal Notes', $edit_url ),
			);
			break;

		case 'deposit_paid':
		case 'final_balance':
			$actions = array(
				array( 'link', 'Open', $admin_url ),
				array( 'link', 'View Quote', $review_url ),
				array( 'link', 'Generate Job Sheet', $jobsheet_url, true ),
				array( 'link', 'Download Quotation PDF', $pdf_url, true ),
				array( 'form', 'Create Final Invoice', 'create_final_invoice' ),
				array( 'form', 'Mark as In Production', 'mark_in_production' ),
				array( 'link', 'Add Internal Notes', $edit_url ),
			);
			break;

		case 'paid_in_full':
			$actions = array(
				array( 'link', 'View', $admin_url ),
				array( 'link', 'View Quote', $review_url ),
				array( 'link', 'Download Final Invoice', $balance_url, true ),
				array( 'form', 'Duplicate Quote', 'duplicate_quote' ),
				array( 'link', 'Download Job Sheet', $jobsheet_url, true ),
				array( 'form', 'Archive Quote', 'archive_quote', 'Archive this completed quote?' ),
			);
			break;
	}
	?>
	<div class="qs-admin-row-actions qs-action-count-<?php echo esc_attr( count( $actions ) ); ?>">
		<?php foreach ( $actions as $action_item ) : ?>
			<?php if ( 'form' === $action_item[0] ) : ?>
				<?php qs_admin_dashboard_action_button( $quote->ID, $action_item[2], $action_item[1], isset( $action_item[3] ) ? $action_item[3] : '' ); ?>
			<?php elseif ( empty( $action_item[2] ) ) : ?>
				<span class="qs-admin-action-disabled" aria-disabled="true" title="The related WooCommerce order is not available yet."><?php echo esc_html( $action_item[1] ); ?></span>
			<?php else : ?>
				<a href="<?php echo esc_url( $action_item[2] ); ?>"<?php echo ! empty( $action_item[3] ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $action_item[1] ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php
}

function qs_admin_dashboard_shortcode() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return '<p>You are not allowed to manage quotes.</p>';
	}

	$success_message = qs_admin_dashboard_handle_action();
	$statuses        = array( 'draft', 'pending_review', 'awaiting_deposit', 'deposit_paid', 'final_balance', 'paid_in_full' );
	$visible_quotes  = qs_admin_dashboard_visible_meta_query();
	$all_quotes      = get_posts(
		array(
			'post_type'      => 'quote',
			'post_status'    => $statuses,
			'posts_per_page' => -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_query'     => $visible_quotes,
		)
	);

	$company_filter = isset( $_GET['company'] ) ? sanitize_text_field( wp_unslash( $_GET['company'] ) ) : '';
	$status_filter  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$project_filter = isset( $_GET['project'] ) ? sanitize_text_field( wp_unslash( $_GET['project'] ) ) : '';
	$date_filter    = isset( $_GET['date'] ) ? sanitize_key( wp_unslash( $_GET['date'] ) ) : '';

	$args = array(
		'post_type'      => 'quote',
		'post_status'    => $status_filter && in_array( $status_filter, $statuses, true ) ? array( $status_filter ) : $statuses,
		'posts_per_page' => -1,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);

	$meta_query = array(
		'relation' => 'AND',
		$visible_quotes,
	);
	if ( $company_filter ) {
		$meta_query[] = array(
			'key'   => '_company_name',
			'value' => $company_filter,
		);
	}
	if ( $project_filter ) {
		$meta_query[] = array(
			'key'   => '_project_name',
			'value' => $project_filter,
		);
	}
	$args['meta_query'] = $meta_query;

	switch ( $date_filter ) {
		case 'today':
			$args['date_query'] = array( array( 'after' => 'today', 'inclusive' => true ) );
			break;
		case 'week':
			$args['date_query'] = array( array( 'after' => '7 days ago', 'inclusive' => true ) );
			break;
		case 'month':
			$args['date_query'] = array( array( 'after' => 'first day of this month', 'inclusive' => true ) );
			break;
		case 'last_month':
			$args['date_query'] = array(
				array(
					'year'  => (int) gmdate( 'Y', strtotime( 'last month' ) ),
					'month' => (int) gmdate( 'n', strtotime( 'last month' ) ),
				),
			);
			break;
		case 'year':
			$args['date_query'] = array( array( 'after' => 'first day of January ' . gmdate( 'Y' ), 'inclusive' => true ) );
			break;
	}

	$quotes          = get_posts( $args );
	$company_list    = qs_admin_dashboard_filter_values( $all_quotes, '_company_name' );
	$project_list    = qs_admin_dashboard_filter_values( $all_quotes, '_project_name' );
	$grouped_quotes  = array();
	$counts          = array(
		'draft'            => 0,
		'pending_review'   => 0,
		'awaiting_deposit' => 0,
		'approved'         => 0,
		'completed'        => 0,
	);

	foreach ( $all_quotes as $quote ) {
		$bucket = qs_admin_dashboard_status_bucket( get_post_status( $quote->ID ) );
		if ( isset( $counts[ $bucket ] ) ) {
			$counts[ $bucket ]++;
		}
	}

	foreach ( $quotes as $quote ) {
		$company = trim( (string) get_post_meta( $quote->ID, '_company_name', true ) );
		$company = $company ? $company : 'Unassigned';
		$grouped_quotes[ $company ][] = $quote;
	}

	$stat_labels = array(
		'draft'            => 'Draft Quotes',
		'pending_review'   => 'Pending Review',
		'awaiting_deposit' => 'Deposit Requested',
		'approved'         => 'Approved Quotes',
		'completed'        => 'Completed',
	);

	ob_start();
	?>
	<div class="qs-container qs-admin-dashboard">
		<header class="qs-dashboard-page-header">
			<h1>Loughlin Furniture<br>Admin Dashboard</h1>
			<nav aria-label="Admin quote account actions">
				<a class="qs-btn qs-btn-outline" href="<?php echo esc_url( site_url( '/my-quotes/' ) ); ?>">My Quotes</a>
				<a class="qs-btn" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Logout</a>
			</nav>
		</header>

		<?php if ( $success_message ) : ?><div class="qs-admin-notice"><?php echo esc_html( $success_message ); ?></div><?php endif; ?>

		<section class="qs-admin-intro">
			<h2>Quote Management</h2>
			<p>Manage submitted quotes, review project details, and generate quotation documents.</p>
		</section>

		<div class="qs-admin-stats">
			<?php foreach ( $stat_labels as $key => $label ) : ?>
				<div class="qs-stat-card"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $counts[ $key ] ); ?></strong></div>
			<?php endforeach; ?>
		</div>

		<section class="qs-admin-panel">
			<form method="get" class="qs-admin-search">
				<span class="qs-admin-search-label">Search Quotes</span>
				<select name="company" aria-label="Filter by company">
					<option value="">Company</option>
					<?php foreach ( $company_list as $company_name ) : ?>
						<option value="<?php echo esc_attr( $company_name ); ?>" <?php selected( $company_filter, $company_name ); ?>><?php echo esc_html( $company_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="status" aria-label="Filter by quote status">
					<option value="">All Statuses</option>
					<?php foreach ( qs_get_quote_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="project" aria-label="Filter by project">
					<option value="">All Projects</option>
					<?php foreach ( $project_list as $project_name ) : ?>
						<option value="<?php echo esc_attr( $project_name ); ?>" <?php selected( $project_filter, $project_name ); ?>><?php echo esc_html( $project_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="date" aria-label="Filter by date range">
					<option value="">Date Range</option>
					<option value="today" <?php selected( $date_filter, 'today' ); ?>>Today</option>
					<option value="week" <?php selected( $date_filter, 'week' ); ?>>Last 7 Days</option>
					<option value="month" <?php selected( $date_filter, 'month' ); ?>>This Month</option>
					<option value="last_month" <?php selected( $date_filter, 'last_month' ); ?>>Last Month</option>
					<option value="year" <?php selected( $date_filter, 'year' ); ?>>This Year</option>
				</select>
				<button type="submit" class="qs-btn">Search</button>
			</form>

			<?php if ( ! $grouped_quotes ) : ?><p class="qs-admin-empty">No quotes match these filters.</p><?php endif; ?>

			<?php foreach ( $grouped_quotes as $company => $company_quotes ) :
				$company_contact = '';
				foreach ( $company_quotes as $company_quote ) {
					$company_contact = get_post_meta( $company_quote->ID, '_customer_name', true );
					if ( $company_contact ) {
						break;
					}
				}
				?>
				<section class="qs-company-group">
					<header class="qs-company-heading">
						<h3><?php echo esc_html( $company ); ?></h3>
						<p><strong>Company Contact:</strong> <?php echo esc_html( $company_contact ? $company_contact : '—' ); ?></p>
					</header>

					<div class="qs-admin-table-wrap">
						<table class="qs-admin-table">
							<thead>
								<tr>
									<th>Quote Ref</th>
									<th>Company</th>
									<th>Created By</th>
									<th>Last Updated</th>
									<th>Status</th>
									<th>Total</th>
									<th><span class="screen-reader-text">Expand actions</span></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $company_quotes as $quote ) :
									$status       = get_post_status( $quote->ID );
									$quote_number = get_post_meta( $quote->ID, '_quote_number', true );
									$created_by   = get_the_author_meta( 'display_name', $quote->post_author );
									$total        = qs_calculate_total( $quote->ID );
									$expand_id    = 'qs-admin-actions-' . $quote->ID;
									?>
									<tr class="qs-admin-quote-row" data-admin-quote-row>
										<td><?php echo esc_html( $quote_number ? $quote_number : 'LF-' . $quote->ID ); ?></td>
										<td><?php echo esc_html( $company ); ?></td>
										<td><?php echo esc_html( $created_by ); ?></td>
										<td><?php echo esc_html( get_the_modified_date( 'd M Y', $quote->ID ) ); ?></td>
										<td><span class="qs-status qs-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( qs_admin_dashboard_status_display( $status ) ); ?></span></td>
										<td class="qs-admin-total">$<?php echo esc_html( number_format_i18n( $total, 0 ) ); ?></td>
										<td class="qs-expand-cell">
											<button type="button" class="qs-expand-btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $expand_id ); ?>" aria-label="<?php echo esc_attr( 'Show actions for ' . $quote_number ); ?>">
												<svg aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg>
											</button>
										</td>
									</tr>
									<tr class="qs-admin-expand-row" id="<?php echo esc_attr( $expand_id ); ?>" hidden>
										<td colspan="7"><?php qs_admin_dashboard_quote_actions( $quote ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
			<?php endforeach; ?>
		</section>
	</div>

	<script>
	(function(){
		document.querySelectorAll('.qs-admin-dashboard').forEach(function(dashboard){
			const closeRow=function(row){
				if(!row)return;
				const button=row.querySelector('.qs-expand-btn');
				const expansion=row.nextElementSibling;
				row.classList.remove('is-expanded');
				if(button)button.setAttribute('aria-expanded','false');
				if(expansion&&expansion.classList.contains('qs-admin-expand-row'))expansion.hidden=true;
			};
			dashboard.querySelectorAll('.qs-admin-action-form[data-confirm]').forEach(function(form){
				form.addEventListener('submit',function(event){
					if(!window.confirm(form.getAttribute('data-confirm')))event.preventDefault();
				});
			});
			dashboard.querySelectorAll('.qs-expand-btn').forEach(function(button){
				button.addEventListener('click',function(){
					const row=button.closest('[data-admin-quote-row]');
					const opening=button.getAttribute('aria-expanded')!=='true';
					dashboard.querySelectorAll('[data-admin-quote-row].is-expanded').forEach(closeRow);
					if(!opening)return;
					const expansion=row.nextElementSibling;
					row.classList.add('is-expanded');
					button.setAttribute('aria-expanded','true');
					if(expansion&&expansion.classList.contains('qs-admin-expand-row'))expansion.hidden=false;
				});
			});
		});
	}());
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'quote_admin_dashboard', 'qs_admin_dashboard_shortcode' );
add_shortcode( 'admin_dashboard', 'qs_admin_dashboard_shortcode' );
