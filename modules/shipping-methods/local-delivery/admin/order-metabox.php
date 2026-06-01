<?php 

add_action( 'add_meta_boxes', 'avaita_local_delivery_add_order_meta_box' );

function avaita_local_delivery_add_order_meta_box() {
    // Register on both the legacy post screen ('shop_order') and the HPOS
    // orders screen ('woocommerce_page_wc-orders') so the box shows regardless
    // of which order-storage mode WooCommerce is running.
    $screens = array( 'shop_order' );
    if ( function_exists( 'wc_get_page_screen_id' ) ) {
        $screens[] = wc_get_page_screen_id( 'shop-order' );
    }
    $screens = array_values( array_unique( array_filter( $screens ) ) );

    add_meta_box(
        'custom_order_meta_box',  // ID
        __( 'Delivery Option', 'avaita-restro' ), // Title
        'avaita_local_delivery_fee_callback', // Callback
        $screens, // Screen(s) — legacy + HPOS
        'side', // Context
        'high' // Priority
    );
}

function avaita_local_delivery_fee_callback( $post_or_order ) {
    echo '<style>
        #avaita-delivery-calculator { background: #f8f5f5; padding: 1px 10px; }
        #avaita-delivery-calculator ul li .label { font-weight: bold; }
        #avaita-delivery-calculator .button { margin-top: 5px; }
        #avaita-extra-fee-section { background: #f9f9f9; padding: 10px; border-radius: 4px; }
        #avaita-extra-fee-section h4 { margin-top: 0; margin-bottom: 10px; color: #333; }
        #avaita-extra-fee-section .extra-fee-buttons { margin-top: 10px; }
        #avaita-extra-fee-section input[type="text"], 
        #avaita-extra-fee-section input[type="number"] { 
            max-width: 100%; 
        }
        .avaita-extra-fee-notice {
            margin-left: 0;
            margin-right: 0;
        }
        .notice.avaita-extra-fee-notice p {
            margin: 0.5em 0;
        }
        /* Stack the action buttons full-width so they read cleanly in the narrow metabox */
        #avaita-delivery-calculator .delivery-actions,
        #avaita-extra-fee-section .extra-fee-buttons {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 10px;
        }
        #avaita-delivery-calculator .delivery-actions .button,
        #avaita-extra-fee-section .extra-fee-buttons .button {
            display: block;
            width: 100%;
            margin: 0;
            text-align: center;
        }
    </style>';
    $avaita_billing_city     = '';
    $avaita_billing_area     = '';
    $avaita_billing_sub_area = '';
    $avaita_billing_street   = '';

    // On HPOS the callback receives a WC_Order; on the legacy post editor it
    // receives a WP_Post. Normalize to a WC_Order.
    $order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;

    global $avaita_local_shipping_db;
    $cities_response = $avaita_local_shipping_db->get_all_cities();
    $cities = array(
        '' => __('Select Your City', 'avaita-restro'),
    );

    foreach ($cities_response as $row) {
        $cities[urlencode($row->city)] = $row->city;
    }

    if ( $order instanceof WC_Order ) {
        $user_id = $order->get_user_id();

        if ($user_id) {
            $avaita_billing_city     = get_user_meta($user_id, 'billing_city', true );
            $avaita_billing_area     = get_user_meta($user_id, 'billing_area', true );
            $avaita_billing_sub_area = get_user_meta($user_id, 'billing_sub_area', true );
            $avaita_billing_street   = get_user_meta($user_id, 'billing_street', true );
        }

        // Fall back to order meta (works for both HPOS and legacy via the order object).
        if (! $avaita_billing_city)     { $avaita_billing_city     = $order->get_meta( 'billing_city' ); }
        if (! $avaita_billing_area)     { $avaita_billing_area     = $order->get_meta( 'billing_area' ); }
        if (! $avaita_billing_sub_area) { $avaita_billing_sub_area = $order->get_meta( 'billing_sub_area' ); }
        if (! $avaita_billing_street)   { $avaita_billing_street   = $order->get_meta( 'billing_street' ); }
    }

    ?>
    <p>
        <label for="custom_field"><?php _e( 'City', 'woocommerce' ); ?>*</label>
        <select 
            id="avaita_billing_city" 
            name="avaita_billing_city" 
            class="widefat select2"
        >
            <?php foreach($cities as $key => $value): ?>
                <option value="<?php echo $key; ?>" <?php selected( $avaita_billing_city, $key ); ?>><?php echo $value; ?></option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="custom_field"><?php _e( 'Area', 'woocommerce' ); ?>*</label>
        <select 
            id="avaita_billing_area" 
            name="avaita_billing_area" 
            class="widefat select2" 
            data-current="<?php echo $avaita_billing_area; ?>"
            data-selectLabel="<?php _e('Select Area', 'avaita'); ?>"
        >
            <option><?php _e('Select city first','woocommerce'); ?></option>
        </select>
    </p>

    <p>
        <label for="custom_field"><?php _e( 'Sub Area', 'woocommerce' ); ?>*</label>
        <select 
            id="avaita_billing_sub_area" 
            name="avaita_billing_sub_area" 
            class="widefat select2" 
            data-current="<?php echo $avaita_billing_sub_area; ?>"
            data-selectLabel="<?php _e('Select Sub Area', 'avaita'); ?>"
        >
            <option><?php _e('Select area first','woocommerce'); ?></option>
        </select>
    </p>

    <p>
        <label for="custom_field"><?php _e( 'Street', 'woocommerce' ); ?>*</label>
        <select 
            id="avaita_billing_street" 
            name="avaita_billing_street" 
            class="widefat select2" 
            data-current="<?php echo $avaita_billing_street; ?>"
            data-selectLabel="<?php _e('Select Street', 'avaita'); ?>"
        >
            <option><?php _e('Select sub area first','woocommerce'); ?></option>
        </select>
    </p>

    <div id="avaita-delivery-calculator"> 
        <h4><?php _e('Delivery Cost Details', 'woocommerce'); ?></h4>
        <ul>
            <li id="delivery-distance"><span class="label"><?php _e('Distance', 'woocommerce'); ?></span>: <span class="value"></span></li>
            <li id="delivery-price"><span class="label"><?php _e('Distance Charge', 'woocommerce'); ?></span>: <span class="value"></span></li>
            <li id="delivery-free-threshold"><span class="label"><?php _e('Free Delivery on', 'woocommerce'); ?></span>: <span class="value"></span></li>
           <!--  <li id="delivery-total"><span class="label"><?php _e('Delivery', 'woocommerce'); ?></span>: <span class="value"></span></li> -->
        </ul>
        <div class="delivery-actions">
            <button type="button" class="button button-primary calculate-add-delivery" title="<?php _e('Add Delivery Charge', 'woocommerce'); ?>">
                <?php _e('Add Charge', 'woocommerce'); ?>
            </button>
            <button type="button" class="button button-secondary remove-delivery" title="<?php _e('Remove Delivery Charge', 'woocommerce'); ?>">
                <?php _e('Remove Charge', 'woocommerce'); ?>
            </button>
        </div>
    </div>

    <div id="avaita-extra-fee-section" style="margin-top: 15px;">
        <h4><?php _e('Extra Fees', 'woocommerce'); ?></h4>
        <div id="extra-fee-inputs" style="margin-bottom: 10px;">
            <p>
                <label for="extra_fee_name"><?php _e('Fee Name', 'woocommerce'); ?></label>
                <input type="text" id="extra_fee_name" name="extra_fee_name" class="widefat" placeholder="<?php _e('e.g. Service Charge, Handling Fee', 'woocommerce'); ?>" />
            </p>
            <p>
                <label for="extra_fee_amount"><?php _e('Amount', 'woocommerce'); ?></label>
                <input type="number" id="extra_fee_amount" name="extra_fee_amount" class="widefat" step="0.01" min="0" placeholder="0.00" />
            </p>
            <div class="extra-fee-buttons">
                <button type="button" class="button button-primary add-extra-fee" title="<?php _e('Add Extra Fee', 'woocommerce'); ?>">
                    <?php _e('Add Extra Fee', 'woocommerce'); ?>
                </button>
                <button type="button" class="button button-secondary remove-all-extra-fees" title="<?php _e('Remove All Extra Fees', 'woocommerce'); ?>">
                    <?php _e('Remove All Fees', 'woocommerce'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php
}

add_action( 'woocommerce_process_shop_order_meta', 'avaita_local_delivery_save_order_meta' );
function avaita_local_delivery_save_order_meta( $order_id ) {
    if ( ! isset( $_POST['avaita_billing_city'] ) ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $city     = sanitize_text_field( urldecode( wp_unslash( $_POST['avaita_billing_city'] ) ) );
    $area     = isset( $_POST['avaita_billing_area'] )     ? sanitize_text_field( urldecode( wp_unslash( $_POST['avaita_billing_area'] ) ) )     : '';
    $sub_area = isset( $_POST['avaita_billing_sub_area'] ) ? sanitize_text_field( urldecode( wp_unslash( $_POST['avaita_billing_sub_area'] ) ) ) : '';
    $street   = isset( $_POST['avaita_billing_street'] )   ? sanitize_text_field( urldecode( wp_unslash( $_POST['avaita_billing_street'] ) ) )   : '';

    // Use the order object so this works for both HPOS and the legacy post store.
    $order->update_meta_data( 'billing_city', $city );
    $order->update_meta_data( 'billing_address_1', $area );
    $order->update_meta_data( 'billing_area', $area );
    $order->update_meta_data( 'billing_address_2', $sub_area );
    $order->update_meta_data( 'billing_sub_area', $sub_area );
    $order->update_meta_data( 'billing_street', $street );
    $order->save();

    $user_id = $order->get_user_id();
    if ( ! $user_id && isset( $_POST['customer_user'] ) ) {
        $user_id = absint( $_POST['customer_user'] );
    }
    if ( $user_id ) {
        update_user_meta( $user_id, 'billing_city', $city );
        update_user_meta( $user_id, 'billing_area', $area );
        update_user_meta( $user_id, 'billing_sub_area', $sub_area );
        update_user_meta( $user_id, 'billing_street', $street );
    }
}

// Add AJAX handler for adding extra fees
add_action( 'wp_ajax_avaita_add_extra_fee', 'avaita_add_extra_fee_handler' );
function avaita_add_extra_fee_handler() {
    // Check nonce
    if ( ! wp_verify_nonce( $_POST['security'], 'order-item' ) ) {
        wp_die( 'Action failed. Invalid nonce.' );
    }

    // Check permissions
    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.' );
    }

    $order_id = absint( $_POST['order_id'] );
    $fee_name = sanitize_text_field( $_POST['fee_name'] );
    $fee_amount = floatval( $_POST['fee_amount'] );

    if ( ! $order_id || ! $fee_name || $fee_amount < 0 ) {
        wp_send_json_error( array( 'error' => __( 'Invalid fee data provided.', 'woocommerce' ) ) );
    }

    try {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            throw new Exception( __( 'Invalid order.', 'woocommerce' ) );
        }

        // Add extra fee item
        $item_id = wc_add_order_item( $order_id, array(
            'order_item_name' => $fee_name,
            'order_item_type' => 'fee'
        ) );

        if ( ! $item_id ) {
            throw new Exception( __( 'Unable to add fee.', 'woocommerce' ) );
        }

        // Add meta data for the fee
        wc_add_order_item_meta( $item_id, '_fee_amount', $fee_amount );
        wc_add_order_item_meta( $item_id, '_line_total', $fee_amount );
        wc_add_order_item_meta( $item_id, '_line_tax', 0 );

        // Generate the HTML for the new fee item
        ob_start();
        $order_id = $order->get_id(); // Make variables available for template
        include( __DIR__ . '/order-items-fee.php' ); // We'll create this template
        $html = ob_get_clean();

        if ( ! $html ) {
            // Fallback HTML generation
            $html = '<tr class="fee" data-order_item_id="' . $item_id . '">';
            $html .= '<td class="thumb"><div></div></td>';
            $html .= '<td class="name">';
            $html .= '<div class="view">' . esc_html( $fee_name ) . '</div>';
            $html .= '<div class="edit" style="display:none;">';
            $html .= '<input type="text" name="order_item_name[' . $item_id . ']" value="' . esc_attr( $fee_name ) . '" />';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '<td class="item_cost" width="1%" style="text-align:right;">';
            $html .= '<div class="view">' . wc_price( $fee_amount ) . '</div>';
            $html .= '<div class="edit" style="display:none;">';
            $html .= '<input type="text" name="line_total[' . $item_id . ']" value="' . esc_attr( $fee_amount ) . '" class="line_total" />';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '<td class="line_cost" width="1%" style="text-align:right;">';
            $html .= '<div class="view">' . wc_price( $fee_amount ) . '</div>';
            $html .= '</td>';
            $html .= '<td class="wc-order-edit-line-item" width="1%">';
            $html .= '<div class="wc-order-edit-line-item-actions">';
            $html .= '<a class="edit-order-item tips" href="#" data-tip="' . __( 'Edit item', 'woocommerce' ) . '"></a>';
            $html .= '<a class="delete-order-item tips" href="#" data-tip="' . __( 'Delete item', 'woocommerce' ) . '"></a>';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        wp_send_json_success( array( 'html' => $html, 'item_id' => $item_id ) );

    } catch ( Exception $e ) {
        wp_send_json_error( array( 'error' => $e->getMessage() ) );
    }
}

// Add AJAX handler for removing extra fees
add_action( 'wp_ajax_avaita_remove_extra_fees', 'avaita_remove_extra_fees_handler' );
function avaita_remove_extra_fees_handler() {
    // Check nonce
    if ( ! wp_verify_nonce( $_POST['security'], 'order-item' ) ) {
        wp_die( 'Action failed. Invalid nonce.' );
    }

    // Check permissions
    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.' );
    }

    $order_id = absint( $_POST['order_id'] );
    $fee_item_ids = array_map( 'absint', explode( ',', $_POST['fee_item_ids'] ) );

    if ( ! $order_id || empty( $fee_item_ids ) ) {
        wp_send_json_error( array( 'error' => __( 'Invalid data provided.', 'woocommerce' ) ) );
    }

    try {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            throw new Exception( __( 'Invalid order.', 'woocommerce' ) );
        }

        // Remove fee items
        foreach ( $fee_item_ids as $item_id ) {
            wc_delete_order_item( $item_id );
        }

        wp_send_json_success( array( 'message' => __( 'Extra fees removed successfully.', 'woocommerce' ) ) );

    } catch ( Exception $e ) {
        wp_send_json_error( array( 'error' => $e->getMessage() ) );
    }
}