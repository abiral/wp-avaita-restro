<?php 

add_action( 'add_meta_boxes', 'avaita_local_delivery_add_order_meta_box' );

function avaita_local_delivery_add_order_meta_box() {
    add_meta_box(
        'custom_order_meta_box',  // ID
        __( 'Delivery Option', 'woocommerce' ), // Title
        'avaita_local_delivery_fee_callback', // Callback
        'shop_order', // Screen
        'side', // Context
        'high' // Priority
    );
}

function avaita_local_delivery_fee_callback( $post ) {
    echo '<style>
        #avaita-delivery-calculator ul li .label { font-weight: bold; }
    </style>';
    $avaita_billing_city = '';
    $avaita_billing_area = '';
    $order = wc_get_order( $post->ID );

    global $avaita_local_shipping_db;
    $cities_response = $avaita_local_shipping_db->get_all_cities();
    $cities = array(
        '' => __('Select Your City', 'avaita'),
    );

    foreach ($cities_response as $row) {
        $cities[urlencode($row->city)] = $row->city;
    }

    if ( $order ) {
        $customer_id = $order->get_user_id();
        $avaita_billing_city = get_user_meta( $customer_id, 'billing_city', true );
        $avaita_billing_area = get_user_meta( $customer_id, 'billing_area', true );
    }

    ?>
<p>
    <label for="custom_field"><?php _e( 'City', 'woocommerce' ); ?>*</label>
    <select id="avaita_billing_city" name="avaita_billing_city" class="widefat select2">
        <?php foreach($cities as $key => $value): ?>
        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
        <?php endforeach; ?>
    </select>
</p>

<p>
    <label for="custom_field"><?php _e( 'Area', 'woocommerce' ); ?>*</label>
    <select id="avaita_billing_area" name="avaita_billing_area" class="widefat select2">
        <option><?php _e('Select city first','woocommerce'); ?></option>
    </select>
</p>

<div id="avaita-delivery-calculator">
    <h4><?php _e('Delivery Cost Details', 'woocommerce'); ?></h4>
    <ul>
        <li id="delivery-distance"><span class="label"><?php _e('Distance', 'woocommerce'); ?></span>: <span
                class="value"></span></li>
        <li id="delivery-price"><span class="label"><?php _e('Distance Charge', 'woocommerce'); ?></span>: <span
                class="value"></span></li>
        <li id="delivery-free-threshold"><span class="label"><?php _e('Free Delivery on', 'woocommerce'); ?></span>:
            <span class="value"></span></li>
        <!--  <li id="delivery-total"><span class="label"><?php _e('Delivery', 'woocommerce'); ?></span>: <span class="value"></span></li> -->
    </ul>
</div>
<?php
}