<?php
/**
 * Quote editing rules shared by the Builder, Review and dashboards.
 *
 * Joiners may edit their own quote until the deposit is paid. Loughlin
 * administrators may continue editing through the final-balance stage so
 * production changes and additional costs can be captured before final
 * payment. Existing workflow status and ownership are never changed merely by
 * opening/saving the frontend Builder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Statuses where the Joiner may still change the quote. */
function qs_joiner_editable_quote_statuses() {
	return array( 'draft', 'pending', 'pending_review', 'awaiting_deposit' );
}

/** Statuses where LF admin may still change the job before final payment. */
function qs_admin_editable_quote_statuses() {
	return array( 'draft', 'pending', 'pending_review', 'awaiting_deposit', 'deposit_paid', 'final_balance' );
}

/**
 * Return whether a user should be offered the frontend Builder for a quote.
 */
function qs_workflow_user_can_edit_quote( $quote_id, $user_id = 0 ) {
	$quote = get_post( $quote_id );
	if ( ! $quote || 'quote' !== $quote->post_type ) {
		return false;
	}

	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	$user    = $user_id ? get_user_by( 'id', $user_id ) : false;
	if ( ! $user instanceof WP_User ) {
		return false;
	}

	$status = get_post_status( $quote_id );

	if ( user_can( $user, 'edit_others_posts' ) ) {
		return in_array( $status, qs_admin_editable_quote_statuses(), true );
	}

	return
		(int) $quote->post_author === $user_id &&
		qs_user_is_joiner( $user ) &&
		in_array( $status, qs_joiner_editable_quote_statuses(), true );
}

/**
 * WordPress normally requires post-editing capabilities that Joiners do not
 * have. Grant only the single meta capability needed for their own editable
 * Quote CPT records. The wp-admin block in roles.php remains unchanged.
 */
function qs_workflow_joiner_edit_meta_cap( $caps, $cap, $user_id, $args ) {
	if ( 'edit_post' !== $cap || empty( $args[0] ) || ! qs_user_is_joiner( $user_id ) ) {
		return $caps;
	}

	$quote_id = absint( $args[0] );
	$quote    = get_post( $quote_id );
	if (
		$quote &&
		'quote' === $quote->post_type &&
		(int) $quote->post_author === (int) $user_id &&
		in_array( get_post_status( $quote_id ), qs_joiner_editable_quote_statuses(), true )
	) {
		return array( 'read' );
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'qs_workflow_joiner_edit_meta_cap', 20, 4 );

/**
 * The Builder sends post_status=draft on every save. Preserve the workflow
 * status for existing quotes so an edit cannot move a submitted/approved job
 * backwards to Draft. customer-details.php separately preserves post_author.
 */
function qs_workflow_preserve_builder_quote_status( $data, $postarr ) {
	if (
		empty( $postarr['ID'] ) ||
		empty( $_POST['qs_builder_nonce'] ) ||
		! isset( $data['post_type'] ) ||
		'quote' !== $data['post_type']
	) {
		return $data;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['qs_builder_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'qs_save_quote' ) ) {
		return $data;
	}

	$existing = get_post( absint( $postarr['ID'] ) );
	if ( $existing && 'quote' === $existing->post_type ) {
		$data['post_status'] = $existing->post_status;
	}

	return $data;
}
add_filter( 'wp_insert_post_data', 'qs_workflow_preserve_builder_quote_status', 25, 2 );

/** Record when the final invoice stage was first entered for display ordering. */
function qs_workflow_track_final_invoice_stage( $new_status, $old_status, $post ) {
	if (
		$post instanceof WP_Post &&
		'quote' === $post->post_type &&
		'final_balance' === $new_status &&
		'final_balance' !== $old_status
	) {
		update_post_meta( $post->ID, '_qs_final_invoice_created_at', current_time( 'mysql' ) );
	}
}
add_action( 'transition_post_status', 'qs_workflow_track_final_invoice_stage', 20, 3 );

/**
 * Dashboard status label. Production and final-payment requests are display
 * states layered on top of the canonical Quote post status.
 */
function qs_workflow_quote_status_label( $quote_id ) {
	$status        = get_post_status( $quote_id );
	$production_at = (string) get_post_meta( $quote_id, '_qs_in_production', true );
	$final_at      = (string) get_post_meta( $quote_id, '_qs_final_invoice_created_at', true );

	if ( 'final_balance' === $status ) {
		$production_time = $production_at ? strtotime( $production_at ) : 0;
		$final_time      = $final_at ? strtotime( $final_at ) : 0;

		// If production was marked after the final invoice, the latest admin
		// action is In Production. If the invoice came later, payment is now the
		// customer-facing action required.
		if ( $production_time && ( ! $final_time || $production_time > $final_time ) ) {
			return 'In Production';
		}

		return 'Final payment required';
	}

	if ( 'deposit_paid' === $status && $production_at ) {
		return 'In Production';
	}

	$labels = array(
		'draft'            => 'Draft',
		'pending'          => 'Pending Review',
		'pending_review'   => 'Pending Review',
		'awaiting_deposit' => 'Deposit Requested',
		'deposit_paid'     => 'Approved',
		'paid_in_full'     => 'Completed',
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '_', ' ', (string) $status ) );
}

/** Add Edit Quote to Review for every workflow stage where that user may edit. */
function qs_workflow_add_review_edit_action( $output, $tag ) {
	if ( ! in_array( $tag, array( 'quote_review', 'my_quote_review' ), true ) || false !== strpos( $output, '>Edit Quote</a>' ) ) {
		return $output;
	}

	$quote_id = isset( $_GET['quote_id'] )
		? absint( $_GET['quote_id'] )
		: ( isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0 );

	if ( ! $quote_id || ! qs_workflow_user_can_edit_quote( $quote_id ) ) {
		return $output;
	}

	$builder_url = add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) );
	$link        = '<a class="qs-btn qs-btn-outline qs-workflow-edit-quote" href="' . esc_url( $builder_url ) . '">Edit Quote</a>';

	return preg_replace(
		'/(<div class="qs-review-summary-actions(?: qs-review-admin-actions)?">)/',
		'$1' . $link,
		$output,
		1
	);
}
add_filter( 'do_shortcode_tag', 'qs_workflow_add_review_edit_action', 50, 2 );

/** Return true when the current page contains one of the Quote dashboards. */
function qs_workflow_dashboard_page_type() {
	if ( ! is_singular() ) {
		return '';
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	if ( has_shortcode( $post->post_content, 'quote_admin_dashboard' ) || has_shortcode( $post->post_content, 'admin_dashboard' ) ) {
		return 'admin';
	}
	if ( has_shortcode( $post->post_content, 'my_quotes' ) ) {
		return 'joiner';
	}

	return '';
}

/**
 * Enhance existing dashboard rows without duplicating dashboard query/render
 * logic: status text is kept consistent and an Edit Quote link is added where
 * the current user is allowed to use the Builder.
 */
function qs_workflow_dashboard_editing_script() {
	$page_type = qs_workflow_dashboard_page_type();
	if ( ! $page_type || ! is_user_logged_in() ) {
		return;
	}

	$statuses = array( 'draft', 'pending', 'pending_review', 'awaiting_deposit', 'deposit_paid', 'final_balance', 'paid_in_full' );
	$args     = array(
		'post_type'      => 'quote',
		'post_status'    => $statuses,
		'posts_per_page' => -1,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);

	if ( 'admin' === $page_type ) {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		if ( function_exists( 'qs_admin_dashboard_visible_meta_query' ) ) {
			$args['meta_query'] = qs_admin_dashboard_visible_meta_query();
		}
	} else {
		if ( ! qs_user_is_joiner() ) {
			return;
		}
		$args['author'] = get_current_user_id();
	}

	$rows = array();
	foreach ( get_posts( $args ) as $quote ) {
		$quote_number = get_post_meta( $quote->ID, '_quote_number', true );
		$reference    = $quote_number ? $quote_number : 'LF-' . $quote->ID;
		$rows[ (string) $quote->ID ] = array(
			'reference' => $reference,
			'label'     => qs_workflow_quote_status_label( $quote->ID ),
			'editable'  => qs_workflow_user_can_edit_quote( $quote->ID ),
			'editUrl'   => add_query_arg( 'quote_id', $quote->ID, site_url( '/quote-builder/' ) ),
			'status'    => get_post_status( $quote->ID ),
		);
	}
	?>
	<script>
	(function(){
		var pageType=<?php echo wp_json_encode( $page_type ); ?>;
		var rows=<?php echo wp_json_encode( $rows ); ?>;

		function updateActionCount(container){
			if(!container)return;
			Array.prototype.slice.call(container.classList).forEach(function(name){
				if(/^qs-action-count-\d+$/.test(name))container.classList.remove(name);
			});
			container.classList.add('qs-action-count-'+container.children.length);
		}

		function hasEditLink(container){
			return Array.prototype.some.call(container.querySelectorAll('a'),function(link){
				return (link.textContent||'').trim()==='Edit Quote';
			});
		}

		if(pageType==='admin'){
			document.querySelectorAll('.qs-admin-quote-row').forEach(function(row){
				var button=row.querySelector('.qs-expand-btn');
				var match=button&&String(button.getAttribute('aria-controls')||'').match(/(\d+)$/);
				var info=match?rows[match[1]]:null;
				if(!info)return;
				var badge=row.querySelector('.qs-status');
				if(badge)badge.textContent=info.label;
				if(!info.editable)return;
				var expansion=row.nextElementSibling;
				var actions=expansion&&expansion.querySelector('.qs-admin-row-actions');
				if(!actions||hasEditLink(actions))return;
				var link=document.createElement('a');
				link.href=info.editUrl;
				link.textContent='Edit Quote';
				actions.appendChild(link);
				updateActionCount(actions);
			});
			return;
		}

		var byReference={};
		Object.keys(rows).forEach(function(id){byReference[rows[id].reference]=rows[id];});
		document.querySelectorAll('.qs-my-quotes-table tbody tr').forEach(function(row){
			if(!row.cells||!row.cells.length)return;
			var reference=(row.cells[0].textContent||'').trim();
			var info=byReference[reference];
			if(!info)return;
			var badge=row.querySelector('.qs-status');
			if(badge)badge.textContent=info.label;
			if(!info.editable||info.status==='draft')return;
			var actions=row.querySelector('.qs-my-quotes-actions');
			if(!actions||hasEditLink(actions))return;
			var link=document.createElement('a');
			link.className='qs-table-action qs-workflow-edit-quote';
			link.href=info.editUrl;
			link.textContent='Edit Quote';
			actions.appendChild(link);
		});
	}());
	</script>
	<?php
}
add_action( 'wp_footer', 'qs_workflow_dashboard_editing_script', 110 );
