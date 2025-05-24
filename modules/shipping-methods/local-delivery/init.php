<?php
require __DIR__ . '/avaita-class-local-shipping-db.php';
require __DIR__ . '/order-address-fields.php';
require __DIR__ . '/api.php';
require __DIR__ . '/admin/init.php';

add_action('woocommerce_shipping_init', 'avaita_init_shipping_method');
function avaita_init_shipping_method()
{
    require_once __DIR__ . '/avaita-class-local-shipping.php';
}

add_filter('woocommerce_shipping_methods', 'avaita_register_shipping_method');
function avaita_register_shipping_method($methods)
{
    $methods[] = 'Avaita_Local_Shipping_Method';
    return $methods;
}

// add_action('admin_enqueue_scripts', 'avaita_local_shipping_admin_scripts');
function avaita_local_shipping_admin_scripts()
{
    if (!(is_admin() && isset($_GET['page']) && $_GET['page'] === 'ava-restro')) {
        return false;
    }

    if ($_GET['page'] === 'ava-restro' || $_GET['tab'] === 'local-shipping') {
        wp_enqueue_script('avaita-local-shipping-admin', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0.0', true);
        wp_localize_script('avaita-local-shipping-admin', 'avaitaLocalShipping', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('order-item')
        ));
    }
}


add_action('woocommerce_checkout_update_order_review', 'avaita_process_order_data_for_shipping');
function avaita_process_order_data_for_shipping()
{

    $city = WC()->session->get('avaita_billing_city');
    $area = WC()->session->get('avaita_billing_area');

    $packages = WC()->cart->get_shipping_packages();
    foreach ($packages as $package_key => $package) {
        WC()->session->set('shipping_for_package_' . $package_key, false);
    }
}

add_action('woocommerce_checkout_update_order_review', 'avaita_update_shipping_session');
function avaita_update_shipping_session($postdata)
{
    global $avaita_local_shipping_db;

    $form_data = null;
    $city = '';
    $area = '';
    parse_str($postdata, $form_data);

    if (!empty($form_data['avaita_billing_city'])) {
        $city = $form_data['avaita_billing_city'];
    }

    if (!empty($form_data['avaita_billing_area'])) {
        $area = $form_data['avaita_billing_area'];
    }

    if (!$area && $city) {
        return;
    }

    $area_charge = $avaita_local_shipping_db->get_shipping_charge_for_area($city, $area);

    if (!$area_charge) {
        return;
    }

    WC()->session->set('avaita_billing_city', $city);
    WC()->session->set('avaita_billing_area', $area);

    $delivery_price = $area_charge->delivery_price;
    $minimum_order_threshold = $area_charge->minimum_order_threshold;
    $minimum_free_deliery = $area_charge->minimum_free_delivery;
    $formatted_address = implode(', ', [$area_charge->area, $area_charge->city]);

    if ($delivery_price) {
        WC()->session->set('avaita_delivery_price', $delivery_price);
    }

    if ($minimum_order_threshold) {
        WC()->session->set('avaita_minimum_order_threshold', $minimum_order_threshold);
    }

    if ($minimum_free_deliery) {
        WC()->session->set('avaita_minimum_free_deliery', $minimum_free_deliery);
    }

    if ($formatted_address) {
        WC()->session->set('avaita_formatted_address', $formatted_address);
    }
}

add_action('woocommerce_add_to_cart', 'ald_set_default_shipping_class', 20, 6);
function ald_set_default_shipping_class($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
{
    // Define the default shipping class slug
    $default_shipping_class_slug = 'avaita_local_shipping_method';

    // Get the product object
    $product = wc_get_product($product_id);

    // Check if the product has a shipping class
    $current_shipping_class_id = $product->get_shipping_class_id();

    // If the product does not have a shipping class, set the default one
    if (empty($current_shipping_class_id)) {
        // Get the shipping class ID by slug
        $default_shipping_class_term = get_term_by('slug', $default_shipping_class_slug, 'product_shipping_class');

        if ($default_shipping_class_term && !is_wp_error($default_shipping_class_term)) {
            // Set the shipping class for the product
            wp_set_object_terms($product_id, (int)$default_shipping_class_term->term_id, 'product_shipping_class', false);
        }
    }
}

add_action('woocommerce_before_cart', 'ald_minimum_order_amount');
add_action('woocommerce_checkout_update_order_review', 'ald_minimum_order_amount', 10);
function ald_minimum_order_amount()
{
    if (!(is_cart() || is_checkout())) {
        return;
    }

    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    $chosen_shipping_method = isset($chosen_shipping_methods[0]) ? $chosen_shipping_methods[0] : '';

    if ($chosen_shipping_method != 'avaita_local_shipping_method') {
        return;
    }

    $cart_total = WC()->cart->subtotal;

    $order_threshold = WC()->session->get('avaita_minimum_order_threshold');
    $free_threshold = WC()->session->get('avaita_minimum_free_deliery');

    if ($order_threshold && $cart_total < $order_threshold) {
        wc_add_notice(
            sprintf(
                __('Your current order total is %s — you must have an order with a minimum of %s to place your order', 'avaita'),
                wc_price($cart_total),
                wc_price($order_threshold)
            ),
            'error'
        );
    }

    if ($cart_total < $free_threshold) {
        wc_add_notice(
            sprintf(
                __('Spend %s more to get %sFREE delivery%s', 'avaita'),
                wc_price($free_threshold - $cart_total),
                '<strong>',
                '</strong>'
            ),
            'offer'
        );
    }
}


add_filter('woocommerce_notice_types', 'ald_add_notice_type', 9, 1);
function ald_add_notice_type($notice_types)
{
    $notice_types[] = 'offer';
    return $notice_types;
}


add_filter('woocommerce_locate_template', 'ald_register_woo_template', 1, 3);
function ald_register_woo_template($template, $template_name, $template_path)
{
    global $woocommerce;
    $_template = $template;
    if (! $template_path)
        $template_path = $woocommerce->template_url;

    $plugin_path  = untrailingslashit(plugin_dir_path(__FILE__))  . '/templates/woocommerce/';

    $template = locate_template(
        array(
            $template_path . $template_name,
            $template_name
        )
    );

    if (! $template && file_exists($plugin_path . $template_name))
        $template = $plugin_path . $template_name;

    if (! $template)
        $template = $_template;

    return $template;
}


// add_action('woocommerce_order_item_add_action_buttons', 'ald_add_order_item_button', 10, 1);
// function ald_add_order_item_button($order)
// {
//     echo '<button type="button" class="button add-local-delivery-charge">' . __('Add Local Delivery Charge', '') . '</button>';
// }

add_action( 'wp_ajax_ald_add_order_shipping', 'ald_add_order_shipping' );
function ald_add_order_shipping() {
    check_ajax_referer( 'order-item', 'security' );

    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_die( -1 );
    }

    $response = array();

    try {
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            throw new Exception( __( 'Invalid order', 'woocommerce' ) );
        }

        $order_taxes      = $order->get_taxes();
        $shipping_methods = WC()->shipping() ? WC()->shipping()->load_shipping_methods() : array();

        // Add new shipping.
        $item = new WC_Order_Item_Shipping();
        $area_name = $_POST['area'] . ', ' . $_POST['city'];
        $item->set_name( sprintf(__("Deliver to %s", "woocommerce"), $area_name));
        $item->set_total($_POST['delivery_price']);
        $item->set_order_id( $order_id );
        $item_id = $item->save();

        ob_start();
        include __DIR__ . '/templates/admin-order-shipping-html.php';
        $response['html'] = ob_get_clean();
    } catch ( Exception $e ) {
        wp_send_json_error( array( 'error' => $e->getMessage() ) );
    }

    // wp_send_json_success must be outside the try block not to break phpunit tests.
    wp_send_json_success( $response );
}