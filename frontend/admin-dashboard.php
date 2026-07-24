Exit code: 0
Wall time: 0.5 seconds
Output:
<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Dashboard Shortcode
 */
function qs_admin_dashboard_shortcode() {

	$success_message = '';
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return '<p>You are not allowed to manage quotes.</p>';
	}

	if (
		isset( $_POST['qs_approve_quote'] ) &&
		isset( $_POST['qs_approve_quote_nonce'] ) &&
		wp_verify_nonce(
			$_POST['qs_approve_quote_nonce'],
			'qs_approve_quote'
		)
	) {

		$quote_id = absint(
			$_POST['quote_id']
		);
		if ( ! current_user_can( 'edit_post', $quote_id ) ) {
			return '<p>You are not allowed to approve this quote.</p>';
		}

		qs_update_quote_status(
			$quote_id,
			'awaiting_deposit'
		);

		qs_email_quote_approved(
			$quote_id
		);

		$order_id = qs_create_payment_order( $quote_id, 'deposit' );
		$success_message = is_wp_error( $order_id ) ? 'Quote approved, but the deposit order could not be created: ' . $order_id->get_error_message() : 'Quote approved and deposit order #' . $order_id . ' created.';
	}

	$search  = isset( $_GET['search'] )
		? sanitize_text_field(
			$_GET['search']
		)
		: '';

	$company = isset( $_GET['company'] )
		? sanitize_text_field(
			$_GET['company']
		)
		: '';

	$status = isset( $_GET['status'] )
		? sanitize_text_field(
			$_GET['status']
		)
		: '';

	$project = isset( $_GET['project'] )
		? sanitize_text_field(
			$_GET['project']
		)
		: '';

	$date = isset( $_GET['date'] )
		? sanitize_text_field(
			$_GET['date']
		)
		: '';

	$args = array(

		'post_type'      => 'quote',

		'post_status'    => array(
			'draft',
			'pending_review',
			'awaiting_deposit',
			'deposit_paid',
			'final_balance',
			'paid_in_full',
		),

		'posts_per_page' => -1,

		'orderby'        => 'date',

		'order'          => 'DESC',

	);


	if ( ! empty( $company ) ) {

		$args['meta_query'][] = array(
			'key'   => '_company_name',
			'value' => $company,
		);

	}

	if ( ! empty( $status ) ) {

		$args['post_status'] = $status;

	}

	if ( ! empty( $project ) ) {

		$args['meta_query'][] = array(
			'key'   => '_project_name',
			'value' => $project,
		);

	}

	if ( ! empty( $date ) ) {

		switch ( $date ) {

			case 'today':

				$args['date_query'] = array(
					array(
						'after' => 'today',
					)
				);

				break;

			case 'week':

				$args['date_query'] = array(
					array(
						'after' => '7 days ago',
					)
				);

				break;

			case 'month':

				$args['date_query'] = array(
					array(
						'after' => 'first day of this month',
					)
				);

				break;

			case 'last_month':

				$args['date_query'] = array(
					array(
						'year'  => date( 'Y', strtotime( 'last month' ) ),
						'month' => date( 'n', strtotime( 'last month' ) ),
					)
				);

				break;

			case 'year':

				$args['date_query'] = array(
					array(
						'after' => 'first day of January ' . date( 'Y' ),
					)
				);

				break;

		}

	}

	$quotes = get_posts(
		$args
	);

	if ( ! empty( $search ) ) {

	$quotes = array_filter(

		$quotes,

		function( $quote ) use ( $search ) {

			$search = strtolower(
				$search
			);

			$quote_number = strtolower(
				get_post_meta(
					$quote->ID,
					'_quote_number',
					true
				)
			);

			$project = strtolower(
				get_post_meta(
					$quote->ID,
					'_project_name',
					true
				)
			);

			$company = strtolower(
				get_post_meta(
					$quote->ID,
					'_company_name',
					true
				)
			);

			$customer = strtolower(
				get_post_meta(
					$quote->ID,
					'_customer_name',
					true
				)
			);

			$email = strtolower(
				get_post_meta(
					$quote->ID,
					'_customer_email',
					true
				)
			);

			return

				false !== strpos(
					$quote_number,
					$search
				)

				||

				false !== strpos(
					$project,
					$search
				)

				||

				false !== strpos(
					$company,
					$search
				)

				||

				false !== strpos(
					$customer,
					$search
				)

				||

				false !== strpos(
					$email,
					$search
				);

			}

		);

	}

	$draft_count     = 0;
	$pending_count   = 0;
	$deposit_count   = 0;
	$approved_count  = 0;
	$completed_count = 0;

	$grouped_quotes = array();

	foreach ( $quotes as $quote ) {

		$status = get_post_status(
			$quote->ID
		);

		switch ( $status ) {

			case 'draft':
				$draft_count++;
				break;

			case 'pending_review':
				$pending_count++;
				break;

			case 'awaiting_deposit':
				$deposit_count++;
				break;

			case 'deposit_paid':
			case 'final_balance':
				$approved_count++;
				break;

			case 'paid_in_full':
				$completed_count++;
				break;

		}

		$company = get_post_meta(
			$quote->ID,
			'_company_name',
			true
		);

		if ( empty( $company ) ) {
			$company = 'Unassigned';
		}

		$grouped_quotes[ $company ][] = $quote;

	}

	$companies = get_posts(
		array(
			'post_type'      => 'quote',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	$company_list = array();

	foreach ( $companies as $quote_id ) {

		$company_name = get_post_meta(
			$quote_id,
			'_company_name',
			true
		);

		if ( ! empty( $company_name ) ) {

			$company_list[] = $company_name;

		}

	}

	$company_list = array_unique(
		$company_list
	);

	sort(
		$company_list
	);

	// Project Filter
	$projects = get_posts(
		array(
			'post_type'      => 'quote',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	$project_list = array();

	foreach ( $projects as $quote_id ) {

		$project_name = get_post_meta(
			$quote_id,
			'_project_name',
			true
		);

		if ( ! empty( $project_name ) ) {

			$project_list[] = $project_name;

		}

	}

	$project_list = array_unique(
		$project_list
	);

	sort(
		$project_list
	);

	ob_start();

	?>

	<div class="qs-container qs-admin-dashboard">

	<h2>Quote Management</h2>

	<form
		method="get"
		class="qs-admin-search"
	>

	<input
		type="text"
		name="search"
		placeholder="Search Quotes..."
		value="<?php echo esc_attr( $search ); ?>"
	>

	<select name="company">

		<option value="">
			All Companies
		</option>

		<?php foreach ( $company_list as $company_name ) : ?>

			<option
				value="<?php echo esc_attr( $company_name ); ?>"
				<?php selected(
					$company,
					$company_name
				); ?>
			>

				<?php echo esc_html(
					$company_name
				); ?>

			</option>

		<?php endforeach; ?>

	</select>

	<select name="status">

		<option value="">
			All Statuses
		</option>

		<?php foreach ( qs_get_quote_statuses() as $key => $label ) : ?>

			<option
				value="<?php echo esc_attr( $key ); ?>"
				<?php selected(
					$status,
					$key
				); ?>
			>
				<?php echo esc_html( $label ); ?>
			</option>

		<?php endforeach; ?>

	</select>

	<select name="project">

		<option value="">
			All Projects
		</option>

		<?php foreach ( $project_list as $project_name ) : ?>

			<option
				value="<?php echo esc_attr( $project_name ); ?>"
				<?php selected(
					$project,
					$project_name
				); ?>
			>

				<?php echo esc_html(
					$project_name
				); ?>

			</option>

		<?php endforeach; ?>

	</select>

	<select name="date">

		<option value="">
			Date Range
		</option>

		<option
			value="today"
			<?php selected(
				$date,
				'today'
			); ?>
		>
			Today
		</option>

		<option
			value="week"
			<?php selected(
				$date,
				'week'
			); ?>
		>
			Last 7 Days
		</option>

		<option
			value="month"
			<?php selected(
				$date,
				'month'
			); ?>
		>
			This Month
		</option>

		<option
			value="last_month"
			<?php selected(
				$date,
				'last_month'
			); ?>
		>
			Last Month
		</option>

		<option
			value="year"
			<?php selected(
				$date,
				'year'
			); ?>
		>
			This Year
		</option>

	</select>

	<button
		type="submit"
		class="qs-btn"
	>
		Search
	</button>

	<a
		href="<?php echo esc_url(
			get_permalink()
		); ?>"
		class="qs-btn qs-btn-secondary"
	>
		Reset
	</a>

</form>

	<div
		style="
			margin-bottom:25px;
		"
	>

		<button type="button">
			Export Quotes
		</button>

		<button type="button">
			Generate Report
		</button>

	</div>

	<?php if ( ! empty( $success_message ) ) : ?>

		<div
			style="
				padding:10px;
				margin-bottom:20px;
				background:#dff0d8;
				border:1px solid #d6e9c6;
			"
		>
			<?php echo esc_html( $success_message ); ?>
		</div>

	<?php endif; ?>

	<div class="qs-admin-stats">

		<div style="qs-stat-card ">
			<strong>Draft Quotes</strong><br>
			<?php echo esc_html( $draft_count ); ?>
		</div>

		<div style="qs-stat-card">
			<strong>Pending Review</strong><br>
			<?php echo esc_html( $pending_count ); ?>
		</div>

		<div style="qs-stat-card">
			<strong>Deposit Requested</strong><br>
			<?php echo esc_html( $deposit_count ); ?>
		</div>

		<div style="qs-stat-card">
			<strong>Approved Quotes</strong><br>
			<?php echo esc_html( $approved_count ); ?>
		</div>

		<div style="qs-stat-card">
			<strong>Completed</strong><br>
			<?php echo esc_html( $completed_count ); ?>
		</div>

	</div>

	<?php foreach ( $grouped_quotes as $company => $company_quotes ) : ?>

		<?php

		$active_quotes = count(
			$company_quotes
		);

		$company_contact = '';

		foreach ( $company_quotes as $company_quote ) {

			$company_contact = get_post_meta(
				$company_quote->ID,
				'_customer_name',
				true
			);

			if ( ! empty( $company_contact ) ) {
				break;
			}
		}  

		?>

		<details
			class="qs-company-card"
			open
		>

			<summary class="qs-company-summary qs-company-info">

				<div class="qs-company-header">

					<div>

						<h3>
							<?php echo esc_html( $company ); ?>
						</h3>

						<p>

							<strong>Company Contact:</strong>

							<?php echo esc_html(
								$company_contact
							); ?>

						</p>

					</div>

					<div>

						<strong>
							<?php echo esc_html(
								$active_quotes
							); ?>
						</strong>

						Quotes

					</div>

				</div>

			</summary>

			<table class="qs-admin-table">
				<thead>

					<tr>

						<th align="left">Quote Ref</th>

						<th align="left">Project</th>

						<th align="left">Created By</th>

						<th align="left">Last Updated</th>

						<th align="left">Status</th>

						<th align="left">Total</th>

						<th width="60"></th>

					</tr>

				</thead>

				<tbody>

				<?php foreach ( $company_quotes as $quote ) : ?>

					<?php

					$quote_number = get_post_meta(
						$quote->ID,
						'_quote_number',
						true
					);

					$status = get_post_status(
						$quote->ID
					);

					$statuses = qs_get_quote_statuses();

					$status_label = isset(
						$statuses[ $status ]
					)
						? $statuses[ $status ]
						: ucfirst( $status );

					$created_by = get_the_author_meta(
						'display_name',
						$quote->post_author
					);

					$total = qs_calculate_total(
						$quote->ID
					);

					?>

					<tr class="qs-company-summary-row">

						<td>
							<?php echo esc_html(
								$quote_number
							); ?>
						</td>

						<td>
							<?php echo esc_html(
								$quote->post_title
							); ?>
						</td>

						<td>
							<?php echo esc_html(
								$created_by
							); ?>
						</td>

						<td>
							<?php echo esc_html(
								get_the_modified_date(
									'd M Y',
									$quote->ID
								)
							); ?>
						</td>

						<td>

							<?php

							$background = '#ccc';

							switch ( $status ) {

								case 'draft':
									$background = '#bdbdbd';
									break;

								case 'pending_review':
									$background = '#e7cfcf';
									break;

								case 'awaiting_deposit':
									$background = '#d9b2a7';
									break;

								case 'deposit_paid':
								case 'final_balance':
									$background = '#29b35a';
									break;

								case 'paid_in_full':
									$background = '#157f3b';
									break;

							}

							?>

							<span
								style="
									display:inline-block;
									padding:6px 14px;
									border-radius:20px;
									background:<?php echo esc_attr( $background ); ?>;
									color:#fff;
									font-size:12px;
									min-width:120px;
									text-align:center;
								"
							>
								<?php echo esc_html( $status_label ); ?>
							</span>

						</td>

						<td>
							$
							<?php echo esc_html(
								number_format(
									$total,
									2
								)
							); ?>
						</td>

						<td class="qs-expand-cell">

							<button
								type="button"
								class="qs-expand-btn"
							>
								â–¼
							</button>

						</td>
					</tr>

					<tr class="qs-admin-expand-row">

						<td colspan="7">

							<div class="qs-admin-expand">

								<?php
								include QS_PATH .
									'frontend/admin-quote-expanded.php';
								?>

							</div>

						</td>

					</tr>

				<?php endforeach; ?>

				</tbody>

			</table>

		</details>

	<?php endforeach; ?>

	</div>

	<script>

	document.querySelectorAll(
	'.qs-expand-btn'
	).forEach(function(btn){

		btn.addEventListener(
		'click',
		function(e){

			e.stopPropagation();

			const row =
				btn.closest('tr');

			const expand =
				row.nextElementSibling;

			if(
				expand.style.display ===
				'table-row'
			){

				expand.style.display='none';

				btn.innerHTML='â–¼';

			}else{

				document.querySelectorAll(
				'.qs-admin-expand-row'
				).forEach(function(r){

					r.style.display='none';

				});

				document.querySelectorAll(
				'.qs-expand-btn'
				).forEach(function(b){

					b.innerHTML='â–¼';

				});

				expand.style.display='table-row';

				btn.innerHTML='â–²';

			}

		});

	});

	</script>

	<?php

	return ob_get_clean();

}

add_shortcode(
	'quote_admin_dashboard',
	'qs_admin_dashboard_shortcode'
);

