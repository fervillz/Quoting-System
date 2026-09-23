<?php
/**
 * Joiner operational overview.
 *
 * This is intentionally separate from WordPress Users: it combines each
 * Joiner's account/contact data with their Quote System history and payments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_joiners_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=quote',
		'Joiners',
		'Joiners',
		'manage_options',
		'qs-joiners',
		'qs_joiners_admin_page'
	);
}
add_action( 'admin_menu', 'qs_joiners_admin_menu', 31 );

function qs_joiners_quote_statuses() {
	return array( 'draft', 'pending', 'pending_review', 'awaiting_deposit', 'deposit_paid', 'final_balance', 'paid_in_full' );
}

function qs_joiners_status_label( $quote_id ) {
	if ( function_exists( 'qs_workflow_quote_status_label' ) ) {
		return qs_workflow_quote_status_label( $quote_id );
	}

	$status = get_post_status( $quote_id );
	$labels = array(
		'draft'            => 'Draft',
		'pending'          => 'Pending Review',
		'pending_review'   => 'Pending Review',
		'awaiting_deposit' => 'Deposit Requested',
		'deposit_paid'     => 'Deposit Paid',
		'final_balance'    => 'Final Payment Required',
		'paid_in_full'     => 'Completed',
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '_', ' ', (string) $status ) );
}

function qs_joiners_status_key( $label ) {
	$key = sanitize_title( strtolower( (string) $label ) );
	return $key ? $key : 'unknown';
}

function qs_joiners_company_name( $user_id ) {
	foreach ( array( 'company_name', 'billing_company', 'qs_customer_company' ) as $key ) {
		$value = trim( (string) get_user_meta( $user_id, $key, true ) );
		if ( '' !== $value ) {
			return $value;
		}
	}
	return '';
}

function qs_joiners_phone( $user_id ) {
	foreach ( array( 'qs_customer_phone', 'billing_phone' ) as $key ) {
		$value = trim( (string) get_user_meta( $user_id, $key, true ) );
		if ( '' !== $value ) {
			return $value;
		}
	}
	return '';
}

/**
 * Return money actually received against a Quote.
 *
 * Paid WooCommerce orders are authoritative. A manually-confirmed deposit is
 * also counted when its Woo order is not itself paid, so the overview matches
 * the office workflow without double counting the same payment.
 */
function qs_joiners_quote_paid_amount( $quote_id ) {
	$paid     = 0.0;
	$seen     = array();
	$deposit_paid_via_woo = false;

	if ( function_exists( 'wc_get_order' ) ) {
		foreach ( array( 'deposit', 'balance' ) as $payment_type ) {
			$order_id = absint( get_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', true ) );
			if ( ! $order_id || isset( $seen[ $order_id ] ) ) {
				continue;
			}
			$seen[ $order_id ] = true;

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$is_paid = method_exists( $order, 'is_paid' ) && $order->is_paid();
			if ( ! $is_paid && method_exists( $order, 'has_status' ) ) {
				$is_paid = $order->has_status( array( 'processing', 'completed' ) );
			}

			if ( $is_paid ) {
				$paid += (float) $order->get_total();
				if ( 'deposit' === $payment_type ) {
					$deposit_paid_via_woo = true;
				}
			}
		}
	}

	if ( ! $deposit_paid_via_woo && get_post_meta( $quote_id, '_qs_deposit_manually_paid_at', true ) ) {
		$manual_deposit = get_post_meta( $quote_id, '_qs_locked_deposit_amount', true );
		if ( '' === $manual_deposit || false === $manual_deposit ) {
			$manual_deposit = function_exists( 'qs_calculate_deposit' ) ? qs_calculate_deposit( $quote_id ) : 0;
		}
		$paid += (float) $manual_deposit;
	}

	return round( $paid, 2 );
}

function qs_joiners_build_data() {
	$users = get_users(
		array(
			'role'    => 'joiner',
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => -1,
		)
	);

	$rows = array();
	foreach ( $users as $user ) {
		$rows[ $user->ID ] = array(
			'user'          => $user,
			'company'       => qs_joiners_company_name( $user->ID ),
			'phone'         => qs_joiners_phone( $user->ID ),
			'quotes'        => array(),
			'status_counts' => array(),
			'latest_quote'  => null,
			'quoted_value'  => 0.0,
			'paid_value'    => 0.0,
			'open_quotes'   => 0,
			'completed'     => 0,
		);
	}

	if ( ! $rows ) {
		return $rows;
	}

	$quotes = get_posts(
		array(
			'post_type'      => 'quote',
			'post_status'    => qs_joiners_quote_statuses(),
			'posts_per_page' => -1,
			'author__in'     => array_map( 'absint', array_keys( $rows ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);

	foreach ( $quotes as $quote ) {
		$user_id = (int) $quote->post_author;
		if ( ! isset( $rows[ $user_id ] ) ) {
			continue;
		}

		$label = qs_joiners_status_label( $quote->ID );
		if ( ! isset( $rows[ $user_id ]['status_counts'][ $label ] ) ) {
			$rows[ $user_id ]['status_counts'][ $label ] = 0;
		}
		$rows[ $user_id ]['status_counts'][ $label ]++;
		$rows[ $user_id ]['quotes'][] = $quote;

		if ( ! $rows[ $user_id ]['latest_quote'] ) {
			$rows[ $user_id ]['latest_quote'] = $quote;
		}

		$status = get_post_status( $quote->ID );
		if ( 'draft' !== $status && function_exists( 'qs_calculate_total' ) ) {
			$rows[ $user_id ]['quoted_value'] += (float) qs_calculate_total( $quote->ID );
		}

		$rows[ $user_id ]['paid_value'] += qs_joiners_quote_paid_amount( $quote->ID );

		if ( 'paid_in_full' === $status ) {
			$rows[ $user_id ]['completed']++;
		} else {
			$rows[ $user_id ]['open_quotes']++;
		}
	}

	return $rows;
}

function qs_joiners_matches_search( $row, $search ) {
	$search = strtolower( trim( (string) $search ) );
	if ( '' === $search ) {
		return true;
	}

	$user = $row['user'];
	$haystack = implode(
		' ',
		array(
			$user->display_name,
			$user->user_login,
			$user->user_email,
			$row['company'],
			$row['phone'],
		)
	);

	return false !== strpos( strtolower( $haystack ), $search );
}

function qs_joiners_status_tooltip( $counts ) {
	if ( ! $counts ) {
		return 'No quotes yet';
	}

	$order = array(
		'Draft',
		'Pending Review',
		'Deposit Requested',
		'Approved',
		'Deposit Paid',
		'In Production',
		'Final payment required',
		'Final Payment Required',
		'Completed',
	);
	$lines = array();

	foreach ( $order as $label ) {
		if ( ! empty( $counts[ $label ] ) ) {
			$lines[] = $label . ': ' . (int) $counts[ $label ];
			unset( $counts[ $label ] );
		}
	}
	foreach ( $counts as $label => $count ) {
		$lines[] = $label . ': ' . (int) $count;
	}

	return implode( "\n", $lines );
}

function qs_joiners_money( $amount ) {
	if ( function_exists( 'wc_price' ) ) {
		return wp_strip_all_tags( wc_price( (float) $amount ) );
	}
	return '$' . number_format_i18n( (float) $amount, 2 );
}

function qs_joiners_quote_review_url( $quote_id ) {
	return function_exists( 'qs_page_url' )
		? qs_page_url( 'quote_review', array( 'quote_id' => absint( $quote_id ) ) )
		: add_query_arg( 'quote_id', absint( $quote_id ), site_url( '/quote-review/' ) );
}

function qs_joiners_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view Joiners.', 'quote-system' ) );
	}

	$all_rows = qs_joiners_build_data();
	$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$filtered = array();

	foreach ( $all_rows as $row ) {
		if ( qs_joiners_matches_search( $row, $search ) ) {
			$filtered[] = $row;
		}
	}

	$per_page    = 20;
	$current_page= max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
	$total_items = count( $filtered );
	$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
	if ( $current_page > $total_pages ) {
		$current_page = $total_pages;
	}
	$visible_rows = array_slice( $filtered, ( $current_page - 1 ) * $per_page, $per_page );

	$total_joiners   = count( $all_rows );
	$total_open      = 0;
	$total_completed = 0;
	$total_paid      = 0.0;
	foreach ( $all_rows as $row ) {
		$total_open      += (int) $row['open_quotes'];
		$total_completed += (int) $row['completed'];
		$total_paid      += (float) $row['paid_value'];
	}

	$base_url = str_replace(
		'999999999',
		'%#%',
		add_query_arg(
			array(
				'post_type' => 'quote',
				'page'      => 'qs-joiners',
				's'         => $search,
				'paged'     => 999999999,
			),
			admin_url( 'edit.php' )
		)
	);
	?>
	<div class="wrap qs-joiners-wrap">
		<h1 class="wp-heading-inline">Joiners</h1>
		<a href="<?php echo esc_url( add_query_arg( 'qs_role', 'joiner', admin_url( 'user-new.php' ) ) ); ?>" class="page-title-action">Add New Joiner</a>
		<hr class="wp-header-end">

		<p class="description qs-joiners-intro">A Quote System view of each Joiner account, their quote activity and payments received.</p>

		<form method="get" class="search-form qs-joiners-search">
			<input type="hidden" name="post_type" value="quote">
			<input type="hidden" name="page" value="qs-joiners">
			<p class="search-box">
				<label class="screen-reader-text" for="qs-joiner-search-input">Search Joiners:</label>
				<input type="search" id="qs-joiner-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Name, company, email, phone">
				<input type="submit" class="button" value="Search Joiners">
			</p>
		</form>

		<div class="tablenav top qs-joiners-toolbar">
			<div class="alignleft actions">
				<div class="qs-joiner-summary">
					<span><strong>Joiners:</strong> <?php echo esc_html( $total_joiners ); ?></span>
					<span class="qs-joiner-summary-divider" aria-hidden="true">|</span>
					<span><strong>Open Quotes:</strong> <?php echo esc_html( $total_open ); ?></span>
					<span class="qs-joiner-summary-divider" aria-hidden="true">|</span>
					<span><strong>Completed Quotes:</strong> <?php echo esc_html( $total_completed ); ?></span>
					<span class="qs-joiner-summary-divider" aria-hidden="true">|</span>
					<span><strong>Total Paid:</strong> <?php echo esc_html( qs_joiners_money( $total_paid ) ); ?></span>
				</div>
			</div>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav-pages">
					<?php echo wp_kses_post( paginate_links( array( 'base' => $base_url, 'format' => '', 'current' => $current_page, 'total' => $total_pages, 'prev_text' => '‹', 'next_text' => '›' ) ) ); ?>
				</div>
			<?php endif; ?>
			<br class="clear">
		</div>

		<table class="wp-list-table widefat fixed striped table-view-list users qs-joiners-table">
			<thead>
				<tr>
					<th class="column-primary">Joiner</th>
					<th>Contact</th>
					<th class="qs-col-quotes">Quotes</th>
					<th>Latest Quote</th>
					<th class="qs-col-money">Quoted Value</th>
					<th class="qs-col-money">Paid</th>
					<th>Last Activity</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $visible_rows ) : ?>
				<tr class="no-items"><td colspan="7">No Joiners found.</td></tr>
			<?php endif; ?>

			<?php foreach ( $visible_rows as $row ) :
				$user         = $row['user'];
				$latest       = $row['latest_quote'];
				$quote_count  = count( $row['quotes'] );
				$tooltip      = qs_joiners_status_tooltip( $row['status_counts'] );
				$company      = $row['company'] ? $row['company'] : 'No company set';
				$quotes_url   = add_query_arg( array( 'post_type' => 'quote', 'author' => $user->ID ), admin_url( 'edit.php' ) );
				$edit_url     = admin_url( 'user-edit.php?user_id=' . $user->ID );
				$latest_label = $latest ? qs_joiners_status_label( $latest->ID ) : '';
				$latest_key   = $latest ? qs_joiners_status_key( $latest_label ) : '';
				?>
				<tr>
					<td class="column-primary" data-colname="Joiner">
						<div class="qs-joiner-identity">
							<?php echo get_avatar( $user->ID, 40 ); ?>
							<div>
								<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $user->display_name ); ?></a></strong>
								<span><?php echo esc_html( $company ); ?></span>
								<?php if ( function_exists( 'qs_portal_user_has_test_access' ) && qs_portal_user_has_test_access( $user ) ) : ?>
									<small class="qs-joiner-test-badge">TEST ACCESS</small>
								<?php endif; ?>
							</div>
						</div>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>">Edit User</a> | </span>
							<span class="view"><a href="<?php echo esc_url( $quotes_url ); ?>">View Quotes</a></span>
						</div>
						<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
					</td>

					<td data-colname="Contact">
						<a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a>
						<?php if ( $row['phone'] ) : ?><br><span class="qs-muted"><?php echo esc_html( $row['phone'] ); ?></span><?php endif; ?>
					</td>

					<td data-colname="Quotes" class="qs-col-quotes">
						<a href="<?php echo esc_url( $quotes_url ); ?>" class="qs-joiner-quote-count" aria-label="<?php echo esc_attr( $quote_count . ' quotes. ' . str_replace( "\n", ', ', $tooltip ) ); ?>">
							<?php echo esc_html( $quote_count ); ?>
							<span class="qs-joiner-tooltip" role="tooltip"><?php echo nl2br( esc_html( $tooltip ) ); ?></span>
						</a>
					</td>

					<td data-colname="Latest Quote">
						<?php if ( $latest ) :
							$number = (string) get_post_meta( $latest->ID, '_quote_number', true );
							?>
							<a class="qs-latest-quote" href="<?php echo esc_url( qs_joiners_quote_review_url( $latest->ID ) ); ?>" target="_blank" rel="noopener">
								<strong><?php echo esc_html( $number ? $number : 'Quote #' . $latest->ID ); ?></strong>
								<span><?php echo esc_html( $latest->post_title ); ?></span>
							</a>
							<span class="qs-joiner-status qs-joiner-status-<?php echo esc_attr( $latest_key ); ?>"><?php echo esc_html( $latest_label ); ?></span>
						<?php else : ?>
							<span class="qs-muted">No quotes yet</span>
						<?php endif; ?>
					</td>

					<td data-colname="Quoted Value" class="qs-col-money">
						<strong><?php echo esc_html( qs_joiners_money( $row['quoted_value'] ) ); ?></strong>
						<small>Drafts excluded</small>
					</td>

					<td data-colname="Paid" class="qs-col-money">
						<strong><?php echo esc_html( qs_joiners_money( $row['paid_value'] ) ); ?></strong>
						<small>Received payments</small>
					</td>

					<td data-colname="Last Activity">
						<?php if ( $latest ) : ?>
							<strong><?php echo esc_html( get_the_modified_date( 'd M Y', $latest->ID ) ); ?></strong>
							<small><?php echo esc_html( get_the_modified_time( 'g:i a', $latest->ID ) ); ?></small>
						<?php else : ?>
							<strong><?php echo esc_html( mysql2date( 'd M Y', $user->user_registered ) ); ?></strong>
							<small>Joined</small>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="tablenav bottom">
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav-pages">
					<?php echo wp_kses_post( paginate_links( array( 'base' => $base_url, 'format' => '', 'current' => $current_page, 'total' => $total_pages, 'prev_text' => '‹', 'next_text' => '›' ) ) ); ?>
				</div>
			<?php endif; ?>
			<br class="clear">
		</div>
	</div>

	<style>
		.qs-joiners-wrap{max-width:1500px}
		.qs-joiners-intro{margin:8px 0 18px}
		.qs-joiner-summary{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0;color:#50575e;line-height:30px}
		.qs-joiner-summary span{display:inline}
		.qs-joiner-summary strong{color:#1d2327;font-weight:600}
		.qs-joiner-summary-divider{color:#a7aaad;margin:0 2px}
		.qs-joiners-search{min-height:38px;margin-bottom:-38px;position:relative;z-index:2}
		.qs-joiners-search .search-box{float:right;margin:0 0 8px}
		.qs-joiners-table th{font-weight:600}
		.qs-joiners-table td{vertical-align:middle}
		.qs-joiner-identity{display:flex;align-items:center;gap:10px}
		.qs-joiner-identity img{border-radius:50%}
		.qs-joiner-identity strong,.qs-joiner-identity span{display:block}
		.qs-joiner-identity span,.qs-muted,.qs-col-money small,.qs-joiners-table td>small{color:#646970}
		.qs-joiner-test-badge{display:inline-block!important;width:max-content;margin-top:4px;padding:2px 6px;border-radius:999px;background:#fff3cd;color:#6f5200!important;font-size:9px;font-weight:700;letter-spacing:.04em}
		.qs-col-quotes{width:80px;text-align:center}
		.qs-col-money{width:125px}
		.qs-col-money small{display:block;margin-top:2px;font-size:11px}
		.qs-joiner-quote-count{position:relative;display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;border-radius:999px;background:#f0f0f1;color:#1d2327;font-weight:700;text-decoration:none}
		.qs-joiner-quote-count:hover,.qs-joiner-quote-count:focus{background:#dce8f3;color:#135e96;outline:none}
		.qs-joiner-tooltip{position:absolute;z-index:20;left:50%;bottom:calc(100% + 9px);transform:translateX(-50%);display:none;min-width:190px;padding:10px 12px;background:#1d2327;color:#fff;border-radius:4px;text-align:left;font-size:12px;font-weight:400;line-height:1.55;white-space:nowrap;box-shadow:0 4px 14px rgba(0,0,0,.18)}
		.qs-joiner-tooltip:after{content:"";position:absolute;left:50%;top:100%;margin-left:-5px;border:5px solid transparent;border-top-color:#1d2327}
		.qs-joiner-quote-count:hover .qs-joiner-tooltip,.qs-joiner-quote-count:focus .qs-joiner-tooltip{display:block}
		.qs-latest-quote{display:block;text-decoration:none;margin-bottom:5px}
		.qs-latest-quote strong,.qs-latest-quote span{display:block}
		.qs-latest-quote span{margin-top:2px;color:#646970;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px}
		.qs-joiner-status{display:inline-flex;align-items:center;min-height:20px;padding:2px 7px;border-radius:999px;background:#e5e7eb;color:#4b5563;font-size:10px;font-weight:700;white-space:nowrap}
		.qs-joiner-status-in-production,.qs-joiner-status-completed{background:#def3e6;color:#1f6f3d}
		.qs-joiner-status-final-payment-required,.qs-joiner-status-deposit-requested{background:#fff0c9;color:#7b5400}
		.qs-joiner-status-pending-review{background:#fff1d6;color:#8a5a00}
		.qs-joiner-status-approved,.qs-joiner-status-deposit-paid{background:#e6eef9;color:#275a8e}
		.qs-joiners-table td[data-colname="Last Activity"] strong,.qs-joiners-table td[data-colname="Last Activity"] small{display:block}
		@media(max-width:1100px){.qs-col-money{width:auto}}
		@media(max-width:782px){.qs-joiners-search{margin-bottom:0}.qs-joiners-search .search-box{float:none}.qs-col-quotes{text-align:left;width:auto}.qs-joiner-tooltip{left:0;transform:none}.qs-joiner-tooltip:after{left:18px}}
	</style>
	<?php
}


/** Preselect Joiner when the Joiners screen opens WordPress's Add New User form. */
function qs_joiners_preselect_new_user_role() {
	if ( ! current_user_can( 'create_users' ) || empty( $_GET['qs_role'] ) || 'joiner' !== sanitize_key( wp_unslash( $_GET['qs_role'] ) ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded',function(){
		var role=document.getElementById('role');
		if(role&&role.querySelector('option[value="joiner"]'))role.value='joiner';
	});
	</script>
	<?php
}
add_action( 'admin_footer-user-new.php', 'qs_joiners_preselect_new_user_role' );
