<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Quote metaboxes.
 *
 * This file contains all Quote metaboxes.
 * Additional metaboxes can be added later:
 *
 * - Project Details
 * - Cabinet Specifications
 * - Pricing & Workflow
 */
function qs_register_metaboxes() {

	add_meta_box(
		'qs_project_details',
		'Project Details',
		'qs_project_details_metabox',
		'quote',
		'normal',
		'high'
	);

	add_meta_box(
		'qs_cabinet_specifications',
		'Cabinet Specifications',
		'qs_cabinet_specifications_metabox',
		'quote',
		'normal',
		'default'
	);

	add_meta_box(
		'qs_components',
		'Components',
		'qs_components_metabox',
		'quote',
		'normal',
		'default'
	);

	add_meta_box(
		'qs_pricing_workflow',
		'Pricing & Workflow',
		'qs_pricing_workflow_metabox',
		'quote',
		'side',
		'high'
	);
}

add_action( 'add_meta_boxes', 'qs_register_metaboxes' );

/**

* Cabinet Specifications Metabox
  */
  function qs_cabinet_specifications_metabox( $post ) {

  $door_profile   = get_post_meta( $post->ID, '_door_profile', true );
  $timber         = get_post_meta( $post->ID, '_timber', true );
  $finish         = get_post_meta( $post->ID, '_finish', true );
  $handle_profile = get_post_meta( $post->ID, '_handle_profile', true );

  ?>

<table class="form-table">
   <tr>
   	<th>
   		<label for="door_profile">Door Profile</label>
   	</th>
   	<td>
   		<input
   			type="text"
   			id="door_profile"
   			name="door_profile"
   			value="<?php echo esc_attr( $door_profile ); ?>"
   			class="regular-text"
   		/>
   	</td>
   </tr>

   <tr>
   	<th>
   		<label for="timber">Timber</label>
   	</th>
   	<td>
   		<input
   			type="text"
   			id="timber"
   			name="timber"
   			value="<?php echo esc_attr( $timber ); ?>"
   			class="regular-text"
   		/>
   	</td>
   </tr>

   <tr>
   	<th>
   		<label for="finish">Finish</label>
   	</th>
   	<td>
   		<input
   			type="text"
   			id="finish"
   			name="finish"
   			value="<?php echo esc_attr( $finish ); ?>"
   			class="regular-text"
   		/>
   	</td>
   </tr>

   <tr>
   	<th>
   		<label for="handle_profile">Handle Profile</label>
   	</th>
   	<td>
   		<input
   			type="text"
   			id="handle_profile"
   			name="handle_profile"
   			value="<?php echo esc_attr( $handle_profile ); ?>"
   			class="regular-text"
   		/>
   	</td>
   </tr>
   </table>

   <?php

}

/**

* Components Metabox
*
* Stores manufacturing components used
* by the Quote PDF and Job Sheet PDF.
  */
  function qs_components_metabox( $post ) {

  $doors_drawers = get_post_meta( $post->ID, '_doors_drawers', true );
  $end_panels    = get_post_meta( $post->ID, '_end_panels', true );
  $fillers       = get_post_meta( $post->ID, '_fillers', true );
  $kickboards    = get_post_meta( $post->ID, '_kickboards', true );

  ?>

   <p>
   	<strong>Doors & Drawers</strong>
   </p>

   <textarea
   	name="doors_drawers"
   	rows="8"
   	style="width:100%;font-family:monospace;"
   ><?php echo esc_textarea( $doors_drawers ); ?></textarea>

   <p>
   	<small>
   		One item per line.
   		Example:
   		Door | 450 | 720 | 2 | Pencil Round
   	</small>
   </p>

   <hr>

   <p>
   	<strong>End Panels</strong>
   </p>

   <textarea
   	name="end_panels"
   	rows="5"
   	style="width:100%;font-family:monospace;"
   ><?php echo esc_textarea( $end_panels ); ?></textarea>

   <hr>

   <p>
   	<strong>Fillers</strong>
   </p>

   <textarea
   	name="fillers"
   	rows="5"
   	style="width:100%;font-family:monospace;"
   ><?php echo esc_textarea( $fillers ); ?></textarea>

   <hr>

   <p>
   	<strong>Kickboards</strong>
   </p>

   <textarea
   	name="kickboards"
   	rows="5"
   	style="width:100%;font-family:monospace;"
   ><?php echo esc_textarea( $kickboards ); ?></textarea>

   <?php


}

/**
 * Pricing & Workflow Metabox
 */
function qs_pricing_workflow_metabox( $post ) {

	$pricing_type      = get_post_meta( $post->ID, '_pricing_type', true );

	$subtotal          = get_post_meta( $post->ID, '_subtotal', true );
	$discount          = get_post_meta( $post->ID, '_discount', true );
	$additional_charges = get_post_meta( $post->ID, '_additional_charges', true );
	$total             = get_post_meta( $post->ID, '_total', true );

	$deposit_amount    = get_post_meta( $post->ID, '_deposit_amount', true );
	$balance_amount    = get_post_meta( $post->ID, '_balance_amount', true );

	$internal_notes    = get_post_meta( $post->ID, '_internal_notes', true );

	$total   = qs_calculate_total( $post->ID );
	$deposit = qs_calculate_deposit( $post->ID );
	$balance = qs_calculate_balance( $post->ID );
	

	?>

	<p>
	<strong>Total:</strong>
		$<?php echo number_format( $total, 2 ); ?>
	</p>

	<p>
		<strong>Deposit (30%):</strong>
		$<?php echo number_format( $deposit, 2 ); ?>
	</p>

	<p>
		<strong>Balance:</strong>
		$<?php echo number_format( $balance, 2 ); ?>
	</p>

	<p>
		<label for="pricing_type"><strong>Pricing Type</strong></label>
		<input
			type="text"
			id="pricing_type"
			name="pricing_type"
			value="<?php echo esc_attr( $pricing_type ); ?>"
			class="widefat"
		/>
	</p>

	<hr>

	<p>
		<label for="subtotal"><strong>Subtotal</strong></label>
		<input
			type="number"
			step="0.01"
			id="subtotal"
			name="subtotal"
			value="<?php echo esc_attr( $subtotal ); ?>"
			class="widefat"
		/>
	</p>

	<p>
		<label for="discount"><strong>Discount</strong></label>
		<input
			type="number"
			step="0.01"
			id="discount"
			name="discount"
			value="<?php echo esc_attr( $discount ); ?>"
			class="widefat"
		/>
	</p>

	<p>
		<label for="additional_charges"><strong>Additional Charges</strong></label>
		<input
			type="number"
			step="0.01"
			id="additional_charges"
			name="additional_charges"
			value="<?php echo esc_attr( $additional_charges ); ?>"
			class="widefat"
		/>
	</p>

	<p>
		<label for="total"><strong>Total</strong></label>
		<input
			type="number"
			step="0.01"
			id="total"
			name="total"
			value="<?php echo esc_attr( $total ); ?>"
			class="widefat"
		/>
	</p>

	<hr>

	<p>
		<label for="deposit_amount"><strong>Deposit Amount</strong></label>
		<input
			type="number"
			step="0.01"
			id="deposit_amount"
			name="deposit_amount"
			value="<?php echo esc_attr( $deposit_amount ); ?>"
			class="widefat"
		/>
	</p>

	<p>
		<label for="balance_amount"><strong>Balance Amount</strong></label>
		<input
			type="number"
			step="0.01"
			id="balance_amount"
			name="balance_amount"
			value="<?php echo esc_attr( $balance_amount ); ?>"
			class="widefat"
		/>
	</p>

	<hr>

	<p>
		<label for="internal_notes"><strong>Internal Notes</strong></label>
		<textarea
			id="internal_notes"
			name="internal_notes"
			rows="5"
			class="widefat"
		><?php echo esc_textarea( $internal_notes ); ?></textarea>
	</p>

	<?php
}

/**
 * Project Details Metabox
 */
function qs_project_details_metabox( $post ) {

	wp_nonce_field(
		'qs_save_project_details',
		'qs_project_details_nonce'
	);

	$quote_number    = get_post_meta( $post->ID, '_quote_number', true );
	$project_name    = get_post_meta( $post->ID, '_project_name', true );
	$company_name    = get_post_meta( $post->ID, '_company_name', true );

	$customer_name   = get_post_meta( $post->ID, '_customer_name', true );
	$customer_email  = get_post_meta( $post->ID, '_customer_email', true );
	$customer_phone  = get_post_meta( $post->ID, '_customer_phone', true );

	$delivery_address = get_post_meta( $post->ID, '_delivery_address', true );
	$project_notes    = get_post_meta( $post->ID, '_project_notes', true );

	?>

	<table class="form-table">

		<tr>
			<th>
				<label>Quote Number</label>
			</th>
			<td>
				<input
					type="text"
					value="<?php echo esc_attr( $quote_number ); ?>"
					class="regular-text"
					readonly
				/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="project_name">Project Name</label>
			</th>
			<td>
				<input
					type="text"
					id="project_name"
					name="project_name"
					value="<?php echo esc_attr( $project_name ); ?>"
					class="regular-text"
				/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="company_name">Company</label>
			</th>
			<td>
				<input
					type="text"
					id="company_name"
					name="company_name"
					value="<?php echo esc_attr( $company_name ); ?>"
					class="regular-text"
				/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="customer_name">Customer Name</label>
			</th>
			<td>
				<input
					type="text"
					id="customer_name"
					name="customer_name"
					value="<?php echo esc_attr( $customer_name ); ?>"
					class="regular-text"
				/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="customer_email">Customer Email</label>
			</th>
			<td>
				<input
					type="email"
					id="customer_email"
					name="customer_email"
					value="<?php echo esc_attr( $customer_email ); ?>"
					class="regular-text"
				/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="customer_phone">Customer Phone</label>
			</th>
			<td>
				<input
					type="text"
					id="customer_phone"
					name="customer_phone"
					value="<?php echo esc_attr( $customer_phone ); ?>"
					class="regular-text"
				/>
			</td>
		</tr>

		<tr>
			<th>
				<label for="delivery_address">Delivery Address</label>
			</th>
			<td>
				<textarea
					id="delivery_address"
					name="delivery_address"
					rows="4"
					style="width:100%;"
				><?php echo esc_textarea( $delivery_address ); ?></textarea>
			</td>
		</tr>

		<tr>
			<th>
				<label for="project_notes">Project Notes</label>
			</th>
			<td>
				<textarea
					id="project_notes"
					name="project_notes"
					rows="5"
					style="width:100%;"
				><?php echo esc_textarea( $project_notes ); ?></textarea>
			</td>
		</tr>

	</table>

	<?php
}

 
/**
 * Save Project Details
 */
function qs_save_project_details( $post_id ) {

	if (
		! isset( $_POST['qs_project_details_nonce'] ) ||
		! wp_verify_nonce(
			$_POST['qs_project_details_nonce'],
			'qs_save_project_details'
		)
	) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( isset( $_POST['project_name'] ) ) {
		update_post_meta(
			$post_id,
			'_project_name',
			sanitize_text_field( $_POST['project_name'] )
		);
	}

	if ( isset( $_POST['company_name'] ) ) {
		update_post_meta(
			$post_id,
			'_company_name',
			sanitize_text_field( $_POST['company_name'] )
		);
	}

	if ( isset( $_POST['customer_name'] ) ) {
		update_post_meta(
			$post_id,
			'_customer_name',
			sanitize_text_field( $_POST['customer_name'] )
		);
	}

	if ( isset( $_POST['customer_email'] ) ) {
		update_post_meta(
			$post_id,
			'_customer_email',
			sanitize_email( $_POST['customer_email'] )
		);
	}

	if ( isset( $_POST['customer_phone'] ) ) {
		update_post_meta(
			$post_id,
			'_customer_phone',
			sanitize_text_field( $_POST['customer_phone'] )
		);
	}

	if ( isset( $_POST['delivery_address'] ) ) {
		update_post_meta(
			$post_id,
			'_delivery_address',
			sanitize_textarea_field( $_POST['delivery_address'] )
		);
	}

	if ( isset( $_POST['project_notes'] ) ) {
		update_post_meta(
			$post_id,
			'_project_notes',
			sanitize_textarea_field( $_POST['project_notes'] )
		);
	}

	
	/**
	* Cabinet Specifications
	*/
	if ( isset( $_POST['door_profile'] ) ) {
		update_post_meta(
			$post_id,
			'_door_profile',
			sanitize_text_field( $_POST['door_profile'] )
		);
	}

	if ( isset( $_POST['timber'] ) ) {
		update_post_meta(
			$post_id,
			'_timber',
			sanitize_text_field( $_POST['timber'] )
		);
	}

	if ( isset( $_POST['finish'] ) ) {
		update_post_meta(
			$post_id,
			'_finish',
			sanitize_text_field( $_POST['finish'] )
		);
	}

	if ( isset( $_POST['handle_profile'] ) ) {
		update_post_meta(
			$post_id,
			'_handle_profile',
			sanitize_text_field( $_POST['handle_profile'] )
		);
	}

	/**
	* Components
	  */
	if ( isset( $_POST['doors_drawers'] ) ) {
		update_post_meta(
		$post_id,
		'_doors_drawers',
		sanitize_textarea_field( $_POST['doors_drawers'] )
		);
	}

	if ( isset( $_POST['end_panels'] ) ) {
		update_post_meta(
		$post_id,
		'_end_panels',
		sanitize_textarea_field( $_POST['end_panels'] )
		);
	}

	if ( isset( $_POST['fillers'] ) ) {
		update_post_meta(
		$post_id,
		'_fillers',
		sanitize_textarea_field( $_POST['fillers'] )
		);
	}

	if ( isset( $_POST['kickboards'] ) ) {
		update_post_meta(
			$post_id,
			'_kickboards',
			sanitize_textarea_field( $_POST['kickboards'] )
		);
	}

	/**
	* Pricing & Workflow
	*/
	if ( isset( $_POST['pricing_type'] ) ) {
		update_post_meta(
			$post_id,
			'_pricing_type',
			sanitize_text_field( $_POST['pricing_type'] )
		);
	}

	if ( isset( $_POST['subtotal'] ) ) {
		update_post_meta(
			$post_id,
			'_subtotal',
			floatval( $_POST['subtotal'] )
		);
	}

	if ( isset( $_POST['discount'] ) ) {
		update_post_meta(
			$post_id,
			'_discount',
			floatval( $_POST['discount'] )
		);
	}

	if ( isset( $_POST['additional_charges'] ) ) {
		update_post_meta(
			$post_id,
			'_additional_charges',
			floatval( $_POST['additional_charges'] )
		);
	}

	if ( isset( $_POST['total'] ) ) {
		update_post_meta(
			$post_id,
			'_total',
			floatval( $_POST['total'] )
		);
	}

	if ( isset( $_POST['deposit_amount'] ) ) {
		update_post_meta(
			$post_id,
			'_deposit_amount',
			floatval( $_POST['deposit_amount'] )
		);
	}

	if ( isset( $_POST['balance_amount'] ) ) {
		update_post_meta(
			$post_id,
			'_balance_amount',
			floatval( $_POST['balance_amount'] )
		);
	}

	if ( isset( $_POST['internal_notes'] ) ) {
		update_post_meta(
			$post_id,
			'_internal_notes',
			sanitize_textarea_field( $_POST['internal_notes'] )
		);
	}
}

add_action(
	'save_post_quote',
	'qs_save_project_details'
);