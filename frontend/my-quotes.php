<?php
/**
 * Tradesperson dashboard.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_my_quotes_status_display( $status ) {
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

function qs_my_quotes_action( $quote, $is_draft = false ) {
	$status = get_post_status( $quote->ID );
	$url    = add_query_arg( 'quote_id', $quote->ID, site_url( $is_draft ? '/quote-builder/' : '/quote-review/' ) );
	$label  = $is_draft ? 'Continue Quote' : 'View';

	if ( 'awaiting_deposit' === $status ) {
		$payment_url = qs_get_quote_payment_url( $quote->ID, 'deposit' );
		if ( $payment_url ) {
			$url   = $payment_url;
			$label = 'Pay Deposit';
		}
	} elseif ( 'final_balance' === $status ) {
		$payment_url = qs_get_quote_payment_url( $quote->ID, 'balance' );
		if ( $payment_url ) {
			$url   = $payment_url;
			$label = 'Pay Balance';
		}
	} elseif ( in_array( $status, array( 'deposit_paid', 'paid_in_full' ), true ) ) {
		$url   = add_query_arg( 'download_quote_pdf', $quote->ID, home_url( '/' ) );
		$label = 'Download PDF';
	}

	return array(
		'url'   => $url,
		'label' => $label,
	);
}

function qs_my_quotes_table( $quotes, $drafts = false ) {
	?>
	<div class="qs-my-quotes-table-wrap">
		<table class="qs-my-quotes-table">
			<thead>
				<tr>
					<th>Quote Ref</th>
					<th>Project Name</th>
					<th>Created By</th>
					<th>Last Updated</th>
					<th>Status</th>
					<th><span class="screen-reader-text">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $quotes ) : ?>
					<tr class="qs-empty-row"><td colspan="6">No quotes found.</td></tr>
				<?php endif; ?>
				<?php foreach ( $quotes as $quote ) :
					$status       = get_post_status( $quote->ID );
					$quote_number = get_post_meta( $quote->ID, '_quote_number', true );
					$action       = qs_my_quotes_action( $quote, $drafts );
					?>
					<tr>
						<td><?php echo esc_html( $quote_number ? $quote_number : 'LF-' . $quote->ID ); ?></td>
						<td class="qs-project-name"><?php echo esc_html( $quote->post_title ); ?></td>
						<td><?php echo esc_html( get_the_author_meta( 'display_name', $quote->post_author ) ); ?></td>
						<td><?php echo esc_html( get_the_modified_date( 'd M Y', $quote->ID ) ); ?></td>
						<td><span class="qs-status qs-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( qs_my_quotes_status_display( $status ) ); ?></span></td>
						<td class="qs-my-quotes-actions">
							<a class="qs-table-action" href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
							<?php if ( $drafts ) : ?>
								<form method="post" class="qs-delete-draft-form" onsubmit="return window.confirm('Delete this saved draft?');">
									<?php wp_nonce_field( 'qs_delete_draft_' . $quote->ID, 'qs_delete_draft_nonce' ); ?>
									<input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote->ID ); ?>">
									<button type="submit" name="qs_delete_draft" aria-label="<?php echo esc_attr( 'Delete draft ' . $quote_number ); ?>">
										<svg aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/></svg>
									</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function qs_my_quotes_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<p>Please log in to view your quotes.</p>';
	}

	$message = '';
	if ( isset( $_POST['qs_delete_draft'] ) ) {
		$quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
		$nonce    = isset( $_POST['qs_delete_draft_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_delete_draft_nonce'] ) ) : '';
		$quote    = get_post( $quote_id );
		$allowed  = $quote && 'quote' === $quote->post_type && 'draft' === get_post_status( $quote_id ) &&
			( (int) $quote->post_author === get_current_user_id() || current_user_can( 'delete_post', $quote_id ) );

		if ( ! $quote_id || ! wp_verify_nonce( $nonce, 'qs_delete_draft_' . $quote_id ) || ! $allowed ) {
			$message = 'The draft could not be deleted.';
		} else {
			wp_trash_post( $quote_id );
			$message = 'Draft deleted.';
		}
	}

	$args = array(
		'post_type'      => 'quote',
		'post_status'    => array( 'draft', 'pending_review', 'awaiting_deposit', 'deposit_paid', 'final_balance', 'paid_in_full' ),
		'posts_per_page' => -1,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		$args['author'] = get_current_user_id();
	}

	$quotes = get_posts( $args );
	$groups = array(
		'draft'              => array(),
		'pending_review'     => array(),
		'awaiting_deposit'   => array(),
		'approved'           => array(),
		'completed'          => array(),
	);

	foreach ( $quotes as $quote ) {
		$status = get_post_status( $quote->ID );
		if ( isset( $groups[ $status ] ) ) {
			$groups[ $status ][] = $quote;
		} elseif ( in_array( $status, array( 'deposit_paid', 'final_balance' ), true ) ) {
			$groups['approved'][] = $quote;
		} elseif ( 'paid_in_full' === $status ) {
			$groups['completed'][] = $quote;
		}
	}

	$user    = wp_get_current_user();
	$company = get_user_meta( $user->ID, 'company_name', true );
	$labels  = array(
		'draft'            => 'Draft Quotes',
		'pending_review'   => 'Pending Review',
		'awaiting_deposit' => 'Deposit Requested',
		'approved'         => 'Approved Quotes',
		'completed'        => 'Completed',
	);

	ob_start();
	?>
	<div class="qs-my-quotes">
		<header class="qs-dashboard-page-header">
			<div>
				<h1>Loughlin Furniture</h1>
				<p><strong>Logged in as:</strong> <span><?php echo esc_html( $company ? $company : $user->display_name ); ?></span><br><span class="qs-user-name"><?php echo esc_html( $user->display_name ); ?></span></p>
			</div>
			<nav aria-label="Quote account actions">
				<a class="qs-btn qs-btn-outline" href="<?php echo esc_url( site_url( '/my-quotes/' ) ); ?>">My Quotes</a>
				<a class="qs-btn" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Logout</a>
			</nav>
		</header>

		<?php if ( $message ) : ?><div class="qs-dashboard-notice"><?php echo esc_html( $message ); ?></div><?php endif; ?>

		<section class="qs-my-quotes-intro">
			<h2>My Quotes</h2>
			<p>Manage your quote requests, continue saved drafts, or start a new quote.</p>
			<a class="qs-btn qs-start-quote" href="<?php echo esc_url( site_url( '/quote-builder/' ) ); ?>">
				<svg aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8.5 4.5a.5.5 0 0 0-1 0v3h-3a.5.5 0 0 0 0 1h3v3a.5.5 0 0 0 1 0v-3h3a.5.5 0 0 0 0-1h-3z"/></svg>
				Start New Quote
			</a>
		</section>

		<div class="qs-my-quotes-stats">
			<?php foreach ( $labels as $key => $label ) : ?>
				<div class="qs-my-quotes-stat"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( count( $groups[ $key ] ) ); ?></strong></div>
			<?php endforeach; ?>
		</div>

		<section class="qs-my-quotes-panel">
			<div class="qs-dashboard-section-heading">
				<h3>Saved Drafts</h3>
				<p>Continue working on your unfinished quote requests.</p>
			</div>
			<?php qs_my_quotes_table( $groups['draft'], true ); ?>

			<div class="qs-dashboard-section-heading qs-submitted-heading">
				<h3>Submitted Quotes</h3>
				<p>Track submitted quote requests and their current status.</p>
			</div>
			<?php qs_my_quotes_table( array_merge( $groups['pending_review'], $groups['awaiting_deposit'], $groups['approved'], $groups['completed'] ) ); ?>
		</section>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'my_quotes', 'qs_my_quotes_shortcode' );
