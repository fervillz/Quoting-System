<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quote Builder Shortcode
 */
function qs_quote_builder_shortcode() {

	$quote_id = isset( $_GET['quote_id'] )
	? absint( $_GET['quote_id'] ) 
	: 0;

	$project_name = '';
	$company_name = '';
	$customer_name = '';
	$customer_email = '';
	$customer_phone = '';
	$delivery_address = '';
	$door_profile = '';
	$timber = '';
	$finish = '';
	$handle_profile = '';
	$project_notes = '';

	if ( $quote_id ) {

		$delivery_address = get_post_meta(
			$quote_id,
			'_delivery_address',
			true
		);

		$door_profile = get_post_meta(
			$quote_id,
			'_door_profile',
			true
		);

		$timber = get_post_meta(
			$quote_id,
			'_timber',
			true
		);

		$finish = get_post_meta(
			$quote_id,
			'_finish',
			true
		);

		$handle_profile = get_post_meta(
			$quote_id,
			'_handle_profile',
			true
		);

		$project_notes = get_post_meta(
			$quote_id,
			'_project_notes',
			true
		);

		$project_name = get_post_meta(
			$quote_id,
			'_project_name',
			true
		);

		$company_name = get_post_meta(
			$quote_id,
			'_company_name',
			true
		);

		$customer_name = get_post_meta(
			$quote_id,
			'_customer_name',
			true
		);

		$customer_email = get_post_meta(
			$quote_id,
			'_customer_email',
			true
		);

		$customer_phone = get_post_meta(
			$quote_id,
			'_customer_phone',
			true
		);

		$doors_drawers = get_post_meta(
			$quote_id,
			'_doors_drawers',
			true
		);

		if ( ! empty( $doors_drawers ) ) {

			$parts = explode(
				'|',
				$doors_drawers
			);

			$item_type         = $parts[0] ?? '';
			$item_width        = $parts[1] ?? '';
			$item_height       = $parts[2] ?? '';
			$item_quantity     = $parts[3] ?? '';
			$item_edge_profile = $parts[4] ?? '';

		}

		$end_panels = get_post_meta(
			$quote_id,
			'_end_panels',
			true
		);

		if ( ! empty( $end_panels ) ) {

			$parts = explode(
				'|',
				$end_panels
			);

			$end_panel_height   = $parts[0] ?? '';
			$end_panel_width    = $parts[1] ?? '';
			$end_panel_quantity = $parts[2] ?? '';

		}

		$fillers = get_post_meta(
			$quote_id,
			'_fillers',
			true
		);

		if ( ! empty( $fillers ) ) {

			$parts = explode(
				'|',
				$fillers
			);

			$filler_width    = $parts[0] ?? '';
			$filler_quantity = $parts[1] ?? '';

		}

		$kickboards = get_post_meta(
			$quote_id,
			'_kickboards',
			true
		);

		if ( ! empty( $kickboards ) ) {

			$parts = explode(
				'|',
				$kickboards
			);

			$kick_height   = $parts[0] ?? '';
			$kick_length   = $parts[1] ?? '';
			$kick_quantity = $parts[2] ?? '';

		}

	}

	if ( isset( $_POST['qs_save_draft'] ) ) {

		$project_name = sanitize_text_field(
			$_POST['project_name']
		);

		if ( $quote_id ) {

			wp_update_post(
				array(
					'ID'         => $quote_id,
					'post_title' => $project_name,
				)
			);

		} else {

			$quote_id = wp_insert_post(
				array(
					'post_type'   => 'quote',
					'post_status' => 'draft',
					'post_title'  => $project_name,
				)
			);

		}

		if ( $quote_id ) {

			update_post_meta(
				$quote_id,
				'_project_name',
				$project_name
			);

			update_post_meta(
				$quote_id,
				'_customer_name',
				sanitize_text_field(
					$_POST['customer_name']
				)
			);

			update_post_meta(
				$quote_id,
				'_customer_email',
				sanitize_email(
					$_POST['customer_email']
				)
			);

			update_post_meta(
				$quote_id,
				'_company_name',
				sanitize_text_field(
					$_POST['company_name']
				)
			);

			update_post_meta(
				$quote_id,
				'_customer_phone',
				sanitize_text_field(
					$_POST['customer_phone']
				)
			);

			update_post_meta(
				$quote_id,
				'_delivery_address',
				sanitize_textarea_field(
					$_POST['delivery_address']
				)
			);

			update_post_meta(
				$quote_id,
				'_door_profile',
				sanitize_text_field(
					$_POST['door_profile']
				)
			);

			update_post_meta(
				$quote_id,
				'_timber',
				sanitize_text_field(
					$_POST['timber']
				)
			);

			update_post_meta(
				$quote_id,
				'_finish',
				sanitize_text_field(
					$_POST['finish']
				)
			);

			update_post_meta(
				$quote_id,
				'_handle_profile',
				sanitize_text_field(
					$_POST['handle_profile']
				)
			);

			update_post_meta(
				$quote_id,
				'_project_notes',
				sanitize_textarea_field(
					$_POST['project_notes']
				)
			);

			$doors_drawers = implode(
				'|',
				array(
					sanitize_text_field(
						$_POST['item_type']
					),
					absint(
						$_POST['item_width']
					),
					absint(
						$_POST['item_height']
					),
					absint(
						$_POST['item_quantity']
					),
					sanitize_text_field(
						$_POST['item_edge_profile']
					),
				)
			);

			update_post_meta(
				$quote_id,
				'_doors_drawers',
				$doors_drawers
			);	

			$end_panels = implode(
				'|',
				array(
					absint(
						$_POST['end_panel_height']
					),
					absint(
						$_POST['end_panel_width']
					),
					absint(
						$_POST['end_panel_quantity']
					),
				)
			);

			update_post_meta(
				$quote_id,
				'_end_panels',
				$end_panels
			);

			$fillers = implode(
				'|',
				array(
					absint(
						$_POST['filler_width']
					),
					absint(
						$_POST['filler_quantity']
					),
				)
			);

			update_post_meta(
				$quote_id,
				'_fillers',
				$fillers
			);

			$kickboards = implode(
				'|',
				array(
					absint(
						$_POST['kick_height']
					),
					absint(
						$_POST['kick_length']
					),
					absint(
						$_POST['kick_quantity']
					),
				)
			);

			update_post_meta(
				$quote_id,
				'_kickboards',
				$kickboards
			);

			/**
			* Save Pricing
			*/
			update_post_meta(
				$quote_id,
				'_subtotal',
				isset( $_POST['subtotal'] )
					? (float) $_POST['subtotal']
					: 0
			);

			update_post_meta(
				$quote_id,
				'_discount',
				0
			);

			update_post_meta(
				$quote_id,
				'_additional_charges',
				0
			);

			wp_redirect(
				add_query_arg(
					'quote_id',
					$quote_id,
					site_url( '/quote-builder/' )
				)
			);

			exit;

		}

	}

	if ( isset( $_POST['qs_review_quote'] ) ) {

		if ( ! $quote_id ) {

			return;

		}

		wp_redirect(
			add_query_arg(
				'quote_id',
				$quote_id,
				site_url(
					'/quote-review/'
				)
			)
		);

		exit;

	}
	
	$total = 0;

	$door_profile_summary = '';
	$timber_summary       = '';
	$finish_summary       = '';

	$doors_count      = 0;
	$drawers_count    = 0;
	$end_panel_count  = 0;
	$kickboard_count  = 0;

	if ( $quote_id ) {

		$door_profile_summary = get_post_meta(
			$quote_id,
			'_door_profile',
			true
		);

		$timber_summary = get_post_meta(
			$quote_id,
			'_timber',
			true
		);

		$finish_summary = get_post_meta(
			$quote_id,
			'_finish',
			true
		);

		$doors_drawers = get_post_meta(
			$quote_id,
			'_doors_drawers',
			true
		);

		if ( ! empty( $doors_drawers ) ) {

			$parts = explode(
				'|',
				$doors_drawers
			);

			if ( isset( $parts[0] ) ) {

				if ( 'Door' === $parts[0] ) {

					$doors_count = isset( $parts[3] )
						? absint( $parts[3] )
						: 0;

				}

				if ( 'Drawer' === $parts[0] ) {

					$drawers_count = isset( $parts[3] )
						? absint( $parts[3] )
						: 0;

				}

			}

		}

		$end_panels = get_post_meta(
			$quote_id,
			'_end_panels',
			true
		);

		if ( ! empty( $end_panels ) ) {

			$parts = explode(
				'|',
				$end_panels
			);

			$end_panel_count = isset( $parts[2] )
				? absint( $parts[2] )
				: 0;

		}

		$kickboards = get_post_meta(
			$quote_id,
			'_kickboards',
			true
		);

		if ( ! empty( $kickboards ) ) {

			$parts = explode(
				'|',
				$kickboards
			);

			$kickboard_count = isset( $parts[2] )
				? absint( $parts[2] )
				: 0;

		}

		if ( function_exists(
			'qs_calculate_total'
		) ) {

			$total = qs_calculate_total(
				$quote_id
			);

		}

	}
		

	ob_start();

	?>

	<h2>Quote Builder</h2>

	<form method="post" class="qs-container qs-flex qs-wrapper">
		<div class="form">

			<h3>Customer Details</h3>

			<p>
				<label>Project Name</label><br>
				<input
					type="text"
					name="project_name"
					required
					value="<?php echo esc_attr( $project_name ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Company Name</label><br>
				<input
					type="text"
					name="company_name"
					value="<?php echo esc_attr( $company_name ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Customer Name</label><br>
				<input
					type="text"
					name="customer_name"
					value="<?php echo esc_attr( $customer_name ); ?>"
					required
					class="qs-input"
				>
			</p>

			<p>
				<label>Email</label><br>
				<input
					type="email"
					name="customer_email"
					required
					value="<?php echo esc_attr( $customer_email ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Phone</label><br>
				<input
					type="text"
					name="customer_phone"
					value="<?php echo esc_attr( $customer_phone ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Delivery Address</label><br>
				<textarea
					class="qs-textarea"
					name="delivery_address"
					rows="4"
					class="qs-input"
				><?php echo esc_textarea( $delivery_address ); ?></textarea>
			</p>

			<hr>

			<h3>Cabinet Specifications</h3>

			<p>
				<label>Door Profile</label><br>
				<input
					type="text"
					name="door_profile"
					value="<?php echo esc_attr( $door_profile ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Timber</label><br>
				<input
					type="text"
					name="timber"
					value="<?php echo esc_attr( $timber ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Finish</label><br>
				<input
					type="text"
					name="finish"
					value="<?php echo esc_attr( $finish ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Handle Profile</label><br>
				<input
					type="text"
					name="handle_profile"
					value="<?php echo esc_attr( $handle_profile ); ?>"
					class="qs-input"
				>
			</p>

			<hr>

			<h3>Doors & Drawers</h3>

			<p>

				<label>Type</label><br>

				<select name="item_type">

					<option
						value="Door"
						<?php selected(
							$item_type,
							'Door'
						); ?>
					>
						Door
					</option>

					<option
						value="Drawer"
						<?php selected(
							$item_type,
							'Drawer'
						); ?>
					>
						Drawer
					</option>

				</select>

			</p>

			<p>

				<label>Width (mm)</label><br>

				<input
					type="number"
					name="item_width"
					value="<?php echo esc_attr( $item_width ); ?>"
					class="qs-input"
				>

			</p>

			<p>

				<label>Height (mm)</label><br>

				<input
					type="number"
					name="item_height"
					value="<?php echo esc_attr( $item_height ); ?>"
					class="qs-input"
				>

			</p>

			<p>

				<label>Quantity</label><br>

				<input
					type="number"
					name="item_quantity"
					value="<?php echo esc_attr( $item_quantity ); ?>"
					class="qs-input"
				>

			</p>

			<p>

				<label>Edge Profile</label><br>

				<input
					type="text"
					name="item_edge_profile"
					value="<?php echo esc_attr( $item_edge_profile ); ?>"
					class="qs-input"
				>

			</p>

			<hr>

			<h3>End Panels</h3>

			<p>
				<label>Height (mm)</label><br>
				<input
					type="number"
					name="end_panel_height"
					value="<?php echo esc_attr( $end_panel_height ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Width (mm)</label><br>
				<input
					type="number"
					name="end_panel_width"
					value="<?php echo esc_attr( $end_panel_width ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Quantity</label><br>
				<input
					type="number"
					name="end_panel_quantity"
					value="<?php echo esc_attr( $end_panel_quantity ); ?>"
					class="qs-input"
				>
			</p>

			<hr>

			<h3>Fillers</h3>

			<p>
				<label>Width (mm)</label><br>
				<input
					type="number"
					name="filler_width"
					value="<?php echo esc_attr( $filler_width ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Quantity</label><br>
				<input
					type="number"
					name="filler_quantity"
					value="<?php echo esc_attr( $filler_quantity ); ?>"
					class="qs-input"
				>
			</p>

			<hr>

			<h3>Kickboards</h3>

			<p>
				<label>Kick Height (mm)</label><br>
				<input
					type="number"
					name="kick_height"
					value="<?php echo esc_attr( $kick_height ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Kick Length (mm)</label><br>
				<input
					type="number"
					name="kick_length"
					value="<?php echo esc_attr( $kick_length ); ?>"
					class="qs-input"
				>
			</p>

			<p>
				<label>Quantity</label><br>
				<input
					type="number"
					name="kick_quantity"
					value="<?php echo esc_attr( $kick_quantity ); ?>"
					class="qs-input"
				>
			</p>

			<hr>

			<h3>Project Notes</h3>

			<p>
				<textarea
					class="qs-textarea"
					name="project_notes"
					rows="6"					
				><?php echo esc_textarea( $project_notes ); ?></textarea>
			</p>

		</div>

		<div class="qs-summary">

			<h3>
				Quote Summary
			</h3>

			<h4>
				Selected Specifications
			</h4>

			<p>
				Door Profile:
				<?php echo esc_html(
					$door_profile_summary
				); ?>
			</p>

			<p>
				Timber:
				<?php echo esc_html(
					$timber_summary
				); ?>
			</p>

			<p>
				Finish:
				<?php echo esc_html(
					$finish_summary
				); ?>
			</p>

			<hr>

			<h4>
				Items Breakdown
			</h4>

			<p>
				Doors:
				<?php echo esc_html(
					$doors_count
				); ?>
			</p>

			<p>
				Drawers:
				<?php echo esc_html(
					$drawers_count
				); ?>
			</p>

			<p>
				End Panels:
				<?php echo esc_html(
					$end_panel_count
				); ?>
			</p>

			<p>
				Kickboards:
				<?php echo esc_html(
					$kickboard_count
				); ?>
			</p>

			<hr>

			<p>
				Estimated Lead Time:
				4-6 Weeks
			</p>

			<hr>

			<h3>

				$
				<?php echo esc_html(
					number_format(
						$total,
						2
					)
				); ?>

			</h3>

			<input
				type="hidden"
				name="subtotal"
				id="qs-subtotal"
				value="<?php echo esc_attr( $total ); ?>"
			>

			<p>	

				<button
					class="qs-btn"
					type="submit"
					name="qs_save_draft"
				>
					Save Draft
				</button>

				<button
					class="qs-btn"
					type="submit"
					name="qs_review_quote"
				>
					Review Quote
				</button>

			</p>

		</div>
	</form>

	<?php

	return ob_get_clean();

}


add_shortcode(
	'quote_builder',
	'qs_quote_builder_shortcode'
);
