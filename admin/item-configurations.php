<?php
/**
 * Modern wp-admin editor for per-item Quote System configurations.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The previous wp-admin table hid item configuration fields, so compatibility
 * hooks preserved them on save. This editor exposes the fields directly and
 * those preservers would prevent an administrator from intentionally changing
 * or clearing a value. */
if ( function_exists( 'qs_item_config_capture_admin_rows' ) ) {
	remove_action( 'save_post_quote', 'qs_item_config_capture_admin_rows', 1 );
	remove_action( 'save_post_quote', 'qs_item_config_restore_admin_rows', 30 );
}
if ( function_exists( 'qs_item_config_capture_admin_types' ) ) {
	remove_action( 'save_post_quote', 'qs_item_config_capture_admin_types', 2 );
	remove_action( 'save_post_quote', 'qs_item_config_restore_admin_types', 40 );
}

/** Return options for a Quote Product type as id => label pairs. */
function qs_admin_item_config_options( $type ) {
	$options = array();
	if ( function_exists( 'qs_item_config_product_options' ) ) {
		foreach ( qs_item_config_product_options( $type ) as $item ) {
			if ( isset( $item['id'], $item['label'] ) ) {
				$options[ (string) $item['id'] ] = (string) $item['label'];
			}
		}
	}
	return $options;
}

function qs_admin_item_select( $name, $value, $options, $placeholder, $classes = '' ) {
	?>
	<select name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $classes ); ?>">
		<option value=""><?php echo esc_html( $placeholder ); ?></option>
		<?php foreach ( $options as $option_value => $label ) : ?>
			<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

function qs_admin_item_input( $name, $value, $placeholder, $type = 'text', $classes = '', $attrs = '' ) {
	?>
	<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="<?php echo esc_attr( $classes ); ?>" <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php
}

function qs_admin_item_config_field_name( $component, $index, $field ) {
	return 'components[' . $component . '][' . absint( $index ) . '][' . $field . ']';
}

function qs_admin_item_config_row( $component, $index, $row, $option_sets ) {
	$row = is_array( $row ) ? $row : array();
	$get = static function ( $key, $default = '' ) use ( $row ) {
		return isset( $row[ $key ] ) ? $row[ $key ] : $default;
	};
	$name = static function ( $field ) use ( $component, $index ) {
		return qs_admin_item_config_field_name( $component, $index, $field );
	};
	?>
	<div class="qs-admin-config-row" data-qs-admin-row>
		<div class="qs-admin-config-row-head">
			<strong class="qs-admin-config-row-title">Item <?php echo esc_html( $index + 1 ); ?></strong>
			<button type="button" class="button-link-delete" data-qs-remove-admin-row>Remove</button>
		</div>

		<?php if ( 'doors_drawers' === $component ) : ?>
			<div class="qs-admin-config-grid qs-admin-config-grid-specs">
				<label><span>Type</span>
					<select name="<?php echo esc_attr( $name( 'type' ) ); ?>" data-qs-item-type>
						<?php foreach ( array( 'Door', 'Drawer', 'Drawer Bank', 'Profile End Panel' ) as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $get( 'type', 'Door' ), $type ); ?>><?php echo esc_html( $type ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><span>Profile</span><?php qs_admin_item_select( $name( 'door_profile' ), $get( 'door_profile' ), $option_sets['profiles'], 'Select profile', 'qs-admin-carry' ); ?></label>
				<label><span>Timber</span><?php qs_admin_item_select( $name( 'timber' ), $get( 'timber' ), $option_sets['timbers'], 'Select timber', 'qs-admin-carry qs-admin-timber' ); ?></label>
				<label><span>Handle Profile</span><?php qs_admin_item_select( $name( 'handle_profile' ), $get( 'handle_profile' ), $option_sets['handles'], 'Select handle', 'qs-admin-carry' ); ?></label>
				<label><span>Finish</span><?php qs_admin_item_select( $name( 'finish' ), $get( 'finish' ), $option_sets['finishes'], 'Select finish', 'qs-admin-carry qs-admin-finish' ); ?></label>
				<label class="qs-admin-paint-field"><span>Paint Colour</span><?php qs_admin_item_input( $name( 'paint_colour' ), $get( 'paint_colour' ), 'Paint colour', 'text', 'qs-admin-carry qs-admin-paint' ); ?></label>
			</div>

			<div class="qs-admin-config-grid qs-admin-dimensions">
				<label><span>Width (mm)</span><?php qs_admin_item_input( $name( 'width' ), $get( 'width' ), 'Width', 'number', '', 'min="1" step="1"' ); ?></label>
				<label class="qs-admin-standard-height"><span>Height (mm)</span><?php qs_admin_item_input( $name( 'height' ), $get( 'height' ), 'Height', 'number', '', 'min="1" step="1"' ); ?></label>
				<label><span>Quantity</span><?php qs_admin_item_input( $name( 'quantity' ), $get( 'quantity' ), 'Quantity', 'number', '', 'min="1" step="1"' ); ?></label>
				<label class="qs-admin-drawer-bank-only"><span>Drawer Count</span>
					<select name="<?php echo esc_attr( $name( 'drawer_count' ) ); ?>" data-qs-drawer-count>
						<?php foreach ( array( 2, 3, 4 ) as $count ) : ?><option value="<?php echo esc_attr( $count ); ?>" <?php selected( (int) $get( 'drawer_count', 3 ), $count ); ?>><?php echo esc_html( $count ); ?> Drawers</option><?php endforeach; ?>
					</select>
				</label>
				<label class="qs-admin-drawer-bank-only qs-drawer-height" data-drawer-counts="2 3 4"><span>Top Height</span><?php qs_admin_item_input( $name( 'top_height' ), $get( 'top_height' ), 'Top', 'number', '', 'min="1" step="1"' ); ?></label>
				<label class="qs-admin-drawer-bank-only qs-drawer-height" data-drawer-counts="4"><span>Top Middle Height</span><?php qs_admin_item_input( $name( 'top_middle_height' ), $get( 'top_middle_height' ), 'Top middle', 'number', '', 'min="1" step="1"' ); ?></label>
				<label class="qs-admin-drawer-bank-only qs-drawer-height" data-drawer-counts="3"><span>Middle Height</span><?php qs_admin_item_input( $name( 'middle_height' ), $get( 'middle_height' ), 'Middle', 'number', '', 'min="1" step="1"' ); ?></label>
				<label class="qs-admin-drawer-bank-only qs-drawer-height" data-drawer-counts="4"><span>Bottom Middle Height</span><?php qs_admin_item_input( $name( 'bottom_middle_height' ), $get( 'bottom_middle_height' ), 'Bottom middle', 'number', '', 'min="1" step="1"' ); ?></label>
				<label class="qs-admin-drawer-bank-only qs-drawer-height" data-drawer-counts="2 3 4"><span>Bottom Height</span><?php qs_admin_item_input( $name( 'bottom_height' ), $get( 'bottom_height' ), 'Bottom', 'number', '', 'min="1" step="1"' ); ?></label>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $name( 'edge_profile' ) ); ?>" value="<?php echo esc_attr( $get( 'edge_profile' ) ); ?>">
		<?php elseif ( in_array( $component, array( 'end_panels', 'fillers' ), true ) ) : ?>
			<div class="qs-admin-config-grid qs-admin-config-grid-specs">
				<label><span>Timber</span><?php qs_admin_item_select( $name( 'timber' ), $get( 'timber' ), $option_sets['timbers'], 'Select timber', 'qs-admin-carry qs-admin-timber' ); ?></label>
				<label><span>Finish</span><?php qs_admin_item_select( $name( 'finish' ), $get( 'finish' ), $option_sets['finishes'], 'Select finish', 'qs-admin-carry qs-admin-finish' ); ?></label>
				<label class="qs-admin-paint-field"><span>Paint Colour</span><?php qs_admin_item_input( $name( 'paint_colour' ), $get( 'paint_colour' ), 'Paint colour', 'text', 'qs-admin-carry qs-admin-paint' ); ?></label>
			</div>
			<div class="qs-admin-config-grid qs-admin-dimensions">
				<label><span>Width (mm)</span><?php qs_admin_item_input( $name( 'width' ), $get( 'width' ), 'Width', 'number', '', 'min="1" step="1"' ); ?></label>
				<label><span>Height (mm)</span><?php qs_admin_item_input( $name( 'height' ), $get( 'height' ), 'Height', 'number', '', 'min="1" step="1"' ); ?></label>
				<label><span>Quantity</span><?php qs_admin_item_input( $name( 'quantity' ), $get( 'quantity' ), 'Quantity', 'number', '', 'min="1" step="1"' ); ?></label>
				<label><span>Faces Seen</span><?php qs_admin_item_input( $name( 'faces_seen' ), $get( 'faces_seen' ), 'Faces seen' ); ?></label>
				<label><span>Edges Seen</span><?php qs_admin_item_input( $name( 'edges_seen' ), $get( 'edges_seen' ), 'Edges seen' ); ?></label>
			</div>
		<?php elseif ( 'kickboards' === $component ) : ?>
			<div class="qs-admin-config-grid qs-admin-config-grid-specs">
				<label><span>Kick Material</span><?php qs_admin_item_select( $name( 'material' ), $get( 'material' ), $option_sets['kickboards'], 'Select material', 'qs-admin-carry' ); ?></label>
				<label><span>Timber</span><?php qs_admin_item_select( $name( 'timber' ), $get( 'timber' ), $option_sets['timbers'], 'Select timber', 'qs-admin-carry qs-admin-timber' ); ?></label>
				<label><span>Finish</span><?php qs_admin_item_select( $name( 'finish' ), $get( 'finish' ), $option_sets['finishes'], 'Select finish', 'qs-admin-carry qs-admin-finish' ); ?></label>
				<label class="qs-admin-paint-field"><span>Paint Colour</span><?php qs_admin_item_input( $name( 'paint_colour' ), $get( 'paint_colour' ), 'Paint colour', 'text', 'qs-admin-carry qs-admin-paint' ); ?></label>
			</div>
			<div class="qs-admin-config-grid qs-admin-dimensions">
				<label><span>Height (mm)</span><?php qs_admin_item_input( $name( 'height' ), $get( 'height' ), 'Height', 'number', '', 'min="1" step="1"' ); ?></label>
				<label><span>Length (mm)</span><?php qs_admin_item_input( $name( 'length' ), $get( 'length' ), 'Length', 'number', '', 'min="1" step="1"' ); ?></label>
				<label><span>Quantity</span><?php qs_admin_item_input( $name( 'quantity' ), $get( 'quantity' ), 'Quantity', 'number', '', 'min="1" step="1"' ); ?></label>
			</div>
		<?php endif; ?>

		<label class="qs-admin-notes"><span>Notes</span><textarea name="<?php echo esc_attr( $name( 'notes' ) ); ?>" rows="2" placeholder="Notes for quote / job sheet / PDF"><?php echo esc_textarea( $get( 'notes' ) ); ?></textarea></label>
	</div>
	<?php
}

function qs_admin_item_config_components_metabox( $post ) {
	$option_sets = array(
		'profiles'   => qs_admin_item_config_options( 'door-profile' ),
		'timbers'    => qs_admin_item_config_options( 'timber' ),
		'handles'    => qs_admin_item_config_options( 'accessory' ),
		'finishes'   => qs_admin_item_config_options( 'finish' ),
		'kickboards' => qs_admin_item_config_options( 'kickboard' ),
	);
	$groups = array(
		'doors_drawers' => 'Doors & Drawers',
		'end_panels'    => 'End Panels',
		'fillers'       => 'Fillers',
		'kickboards'    => 'Kickboards',
	);
	?>
	<p class="description">Each item now owns its own Profile / Timber / Handle / Finish configuration. Changes here use the same saved data and pricing calculation as the frontend Quote Builder.</p>
	<div class="qs-admin-config-summary" data-qs-admin-summary></div>
	<?php
	$legacy = array(
		'door_profile'   => get_post_meta( $post->ID, '_door_profile', true ),
		'timber'         => get_post_meta( $post->ID, '_timber', true ),
		'handle_profile' => get_post_meta( $post->ID, '_handle_profile', true ),
		'finish'         => get_post_meta( $post->ID, '_finish', true ),
		'paint_colour'   => get_post_meta( $post->ID, '_paint_colour', true ),
	);
	foreach ( $groups as $component => $title ) :
		$rows = qs_component_rows( $post->ID, $component );
		if ( ! $rows ) {
			$rows = array( array() );
		}
		foreach ( $rows as &$row ) {
			$fields = 'doors_drawers' === $component
				? array( 'door_profile', 'timber', 'handle_profile', 'finish', 'paint_colour' )
				: array( 'timber', 'finish', 'paint_colour' );
			foreach ( $fields as $field ) {
				if ( ( ! isset( $row[ $field ] ) || '' === (string) $row[ $field ] ) && ! empty( $legacy[ $field ] ) ) {
					$row[ $field ] = $legacy[ $field ];
				}
			}
		}
		unset( $row );
		?>
		<section class="qs-admin-config-section" data-qs-admin-component="<?php echo esc_attr( $component ); ?>">
			<div class="qs-admin-config-section-head"><h3><?php echo esc_html( $title ); ?></h3><button type="button" class="button" data-qs-add-admin-row>Add Item</button></div>
			<div class="qs-admin-config-rows">
				<?php foreach ( $rows as $index => $row ) : qs_admin_item_config_row( $component, $index, $row, $option_sets ); endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
	<?php
}

function qs_admin_item_config_summary_metabox( $post ) {
	$groups = array(
		'doors_drawers' => 'Doors & Drawers',
		'end_panels'    => 'End Panels',
		'fillers'       => 'Fillers',
		'kickboards'    => 'Kickboards',
	);
	$has_rows = false;
	foreach ( $groups as $component => $title ) {
		$rows = qs_component_rows( $post->ID, $component );
		if ( ! $rows ) {
			continue;
		}
		$has_rows = true;
		?>
		<div class="qs-admin-side-summary-group">
			<strong><?php echo esc_html( $title ); ?></strong>
			<?php foreach ( $rows as $row ) :
				$qty = isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
				if ( ! $qty ) { continue; }
				$parts = array();
				if ( 'doors_drawers' === $component ) {
					$parts[] = isset( $row['type'] ) ? $row['type'] : 'Door';
					if ( ! empty( $row['door_profile'] ) ) { $parts[] = qs_quote_product_label( $row['door_profile'] ); }
					if ( ! empty( $row['handle_profile'] ) ) { $parts[] = qs_quote_product_label( $row['handle_profile'] ); }
				}
				if ( ! empty( $row['timber'] ) ) { $parts[] = qs_quote_product_label( $row['timber'] ); }
				if ( ! empty( $row['finish'] ) ) { $parts[] = qs_quote_product_label( $row['finish'] ); }
				?>
				<p><span><?php echo esc_html( implode( ' · ', array_filter( $parts ) ) ); ?></span><b>× <?php echo esc_html( $qty ); ?></b></p>
			<?php endforeach; ?>
		</div>
		<?php
	}
	if ( ! $has_rows ) {
		echo '<p>No configured items yet.</p>';
	}
}

/** Replace the legacy Components table with the per-item configuration editor. */
function qs_admin_item_config_replace_metaboxes( $post_type, $post = null ) {
	if ( 'quote' !== $post_type ) {
		return;
	}

	remove_meta_box( 'qs_components', 'quote', 'normal' );
	add_meta_box( 'qs_components', 'Components & Item Configurations', 'qs_admin_item_config_components_metabox', 'quote', 'normal', 'default' );

	if ( $post instanceof WP_Post && function_exists( 'qs_item_config_has_row_configuration' ) && qs_item_config_has_row_configuration( $post->ID ) ) {
		remove_meta_box( 'qs_cabinet_specifications', 'quote', 'normal' );
	}

	add_meta_box( 'qs_configuration_summary', 'Configuration Summary', 'qs_admin_item_config_summary_metabox', 'quote', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'qs_admin_item_config_replace_metaboxes', 40, 2 );

function qs_admin_item_config_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'quote' !== $screen->post_type ) {
		return;
	}

	$css = <<<'CSS'
#qs_components .inside{padding:0 14px 14px}.qs-admin-config-section{margin:18px 0 24px;padding:0 0 22px;border-bottom:1px solid #dcdcde}.qs-admin-config-section:last-child{border-bottom:0}.qs-admin-config-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}.qs-admin-config-section-head h3{margin:0;font-size:16px}.qs-admin-config-row{margin:0 0 12px;padding:14px;border:1px solid #c3c4c7;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)}.qs-admin-config-row.is-empty{opacity:.88}.qs-admin-config-row-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}.qs-admin-config-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px 12px;margin-bottom:10px}.qs-admin-config-grid-specs{grid-template-columns:repeat(3,minmax(0,1fr));padding-bottom:10px;border-bottom:1px solid #f0f0f1}.qs-admin-config-grid label,.qs-admin-notes{display:block}.qs-admin-config-grid label>span,.qs-admin-notes>span{display:block;margin:0 0 4px;font-size:12px;font-weight:600;color:#50575e}.qs-admin-config-grid select,.qs-admin-config-grid input,.qs-admin-notes textarea{width:100%;max-width:none}.qs-admin-notes textarea{min-height:56px}.qs-admin-drawer-bank-only[hidden],.qs-admin-standard-height[hidden],.qs-admin-paint-field[hidden]{display:none!important}.qs-admin-config-summary{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 18px}.qs-admin-config-summary span{padding:5px 9px;border:1px solid #40586b;background:transparent;color:#40586b;font-size:12px}.qs-admin-side-summary-group{padding:0 0 10px;margin:0 0 10px;border-bottom:1px solid #dcdcde}.qs-admin-side-summary-group:last-child{border-bottom:0}.qs-admin-side-summary-group p{display:flex;justify-content:space-between;gap:8px;margin:7px 0;font-size:12px}.qs-admin-side-summary-group p span{min-width:0}.qs-admin-side-summary-group p b{white-space:nowrap}@media(max-width:1100px){.qs-admin-config-grid,.qs-admin-config-grid-specs{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:782px){.qs-admin-config-grid,.qs-admin-config-grid-specs{grid-template-columns:1fr}}
CSS;
	wp_register_style( 'qs-admin-item-configurations', false, array(), QS_VERSION );
	wp_enqueue_style( 'qs-admin-item-configurations' );
	wp_add_inline_style( 'qs-admin-item-configurations', $css );

	$js = <<<'JS'
(function(){
function nameParts(name){var m=name.match(/^components\[([^\]]+)\]\[(\d+)\]\[([^\]]+)\]$/);return m?{component:m[1],index:Number(m[2]),field:m[3]}:null;}
function reindex(section){section.querySelectorAll('[data-qs-admin-row]').forEach(function(row,index){var title=row.querySelector('.qs-admin-config-row-title');if(title)title.textContent='Item '+(index+1);row.querySelectorAll('[name]').forEach(function(input){var p=nameParts(input.name);if(p)input.name='components['+p.component+']['+index+']['+p.field+']';});});}
function timberIsPainted(row){var select=row.querySelector('.qs-admin-timber');if(!select)return false;var text=select.selectedOptions&&select.selectedOptions[0]?select.selectedOptions[0].textContent:'';return /paint/i.test(text);}
function syncPaint(row){var painted=timberIsPainted(row),finish=row.querySelector('.qs-admin-finish'),paint=row.querySelector('.qs-admin-paint'),wrap=row.querySelector('.qs-admin-paint-field');if(wrap)wrap.hidden=!painted;if(finish){finish.disabled=painted;if(painted)finish.value='';}if(paint){paint.disabled=!painted;if(!painted)paint.value='';}}
function syncDrawer(row){var type=row.querySelector('[data-qs-item-type]'),bank=type&&type.value==='Drawer Bank',height=row.querySelector('.qs-admin-standard-height');if(height)height.hidden=bank;row.querySelectorAll('.qs-admin-drawer-bank-only').forEach(function(el){el.hidden=!bank;});if(!bank)return;var count=String((row.querySelector('[data-qs-drawer-count]')||{}).value||'3');row.querySelectorAll('.qs-drawer-height').forEach(function(label){label.hidden=!label.dataset.drawerCounts.split(' ').includes(count);if(label.hidden){var input=label.querySelector('input');if(input)input.value='';}});}
function syncRow(row){syncPaint(row);syncDrawer(row);}
function summary(){var host=document.querySelector('[data-qs-admin-summary]');if(!host)return;var labels={doors_drawers:'Doors & Drawers',end_panels:'End Panels',fillers:'Fillers',kickboards:'Kickboards'};host.innerHTML='';document.querySelectorAll('[data-qs-admin-component]').forEach(function(section){var component=section.dataset.qsAdminComponent,total=0;section.querySelectorAll('[data-qs-admin-row]').forEach(function(row){var q=row.querySelector('[name$="[quantity]"]');total+=Number(q&&q.value||0);});var badge=document.createElement('span');badge.textContent=(labels[component]||component)+': '+total;host.appendChild(badge);});}
function cleanNewRow(row){row.querySelectorAll('input,textarea').forEach(function(input){if(input.classList.contains('qs-admin-carry'))return;input.value='';});row.querySelectorAll('select').forEach(function(select){if(select.classList.contains('qs-admin-carry'))return;if(select.hasAttribute('data-qs-item-type'))select.value='Door';else if(select.hasAttribute('data-qs-drawer-count'))select.value='3';});row.querySelectorAll('.qs-admin-paint').forEach(function(input){if(!timberIsPainted(row))input.value='';});}
document.querySelectorAll('[data-qs-admin-component]').forEach(function(section){section.querySelectorAll('[data-qs-admin-row]').forEach(syncRow);section.addEventListener('change',function(e){var row=e.target.closest('[data-qs-admin-row]');if(row)syncRow(row);summary();});section.addEventListener('input',summary);section.addEventListener('click',function(e){var add=e.target.closest('[data-qs-add-admin-row]');if(add){var rows=section.querySelector('.qs-admin-config-rows'),source=rows.querySelector('[data-qs-admin-row]:last-child');if(!source)return;var clone=source.cloneNode(true);cleanNewRow(clone);rows.appendChild(clone);reindex(section);syncRow(clone);clone.scrollIntoView({behavior:'smooth',block:'center'});summary();return;}var remove=e.target.closest('[data-qs-remove-admin-row]');if(remove){var row=remove.closest('[data-qs-admin-row]'),all=section.querySelectorAll('[data-qs-admin-row]');if(all.length>1)row.remove();else cleanNewRow(row);reindex(section);summary();}});});
summary();
}());
JS;
	wp_enqueue_script( 'jquery' );
	wp_add_inline_script( 'jquery-core', $js, 'after' );
}
add_action( 'admin_enqueue_scripts', 'qs_admin_item_config_assets' );
