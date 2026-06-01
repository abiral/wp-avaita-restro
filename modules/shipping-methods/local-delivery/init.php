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
    $form_data = null;
    parse_str($postdata, $form_data);

    $city     = !empty($form_data['avaita_billing_city'])     ? $form_data['avaita_billing_city']     : '';
    $area     = !empty($form_data['avaita_billing_area'])     ? $form_data['avaita_billing_area']     : '';
    $sub_area = !empty($form_data['avaita_billing_sub_area']) ? $form_data['avaita_billing_sub_area'] : '';
    $street   = !empty($form_data['avaita_billing_street'])   ? $form_data['avaita_billing_street']   : '';

    avaita_set_delivery_selection($city, $area, $sub_area, $street);
}

/**
 * Shared: look up the delivery charge for a selected location and store it in
 * the WC session (selected location, delivery price, thresholds, formatted
 * address and small-order fee). Used by BOTH the classic checkout hook above
 * and the block-checkout Store API update callback below, so the existing
 * calculate_shipping() works unchanged regardless of checkout type.
 *
 * @return bool True when a matching location was found and applied.
 */
function avaita_set_delivery_selection($city, $area, $sub_area, $street)
{
    global $avaita_local_shipping_db;

    $city     = trim((string) $city);
    $area     = trim((string) $area);
    $sub_area = trim((string) $sub_area);
    $street   = trim((string) $street);

    if (!$area && !$city && !$sub_area && !$street) {
        return false;
    }

    $street_charge = $avaita_local_shipping_db->get_shipping_charge_for_street($city, $area, $sub_area, $street);

    if (!$street_charge) {
        return false;
    }

    WC()->session->set('avaita_billing_city', $city);
    WC()->session->set('avaita_billing_area', $area);
    WC()->session->set('avaita_billing_sub_area', $sub_area);
    WC()->session->set('avaita_billing_street', $street);

    $minimum_order_threshold = $street_charge->minimum_order_threshold;
    $formatted_address = implode(', ', [$street_charge->street, $street_charge->sub_area, $street_charge->area, $street_charge->city]);

    WC()->session->set('avaita_delivery_price', $street_charge->delivery_price);
    WC()->session->set('avaita_minimum_order_threshold', $minimum_order_threshold);
    WC()->session->set('avaita_minimum_free_deliery', $street_charge->minimum_free_delivery);
    WC()->session->set('avaita_formatted_address', $formatted_address);

    $cart_total = WC()->cart->get_cart_contents_total();
    $small_order_fee = 0;

    if ($minimum_order_threshold && $cart_total < $minimum_order_threshold) {
        $min_order_diff_percent = (($minimum_order_threshold - $cart_total)/$minimum_order_threshold) * 100;
        $small_order_fee = ($min_order_diff_percent/2) * 1.5;
    }

    WC()->session->set('avaita_small_order_fee', $small_order_fee);

    return true;
}

/**
 * ---------------------------------------------------------------------------
 * Block (Store API) checkout support
 * ---------------------------------------------------------------------------
 * The classic hooks above never fire on the block checkout. These three pieces
 * make the cascading delivery-address selector + dynamic price work there too:
 *  1. Register the JS integration (renders the selector in the checkout block).
 *  2. A Store API update callback that receives the selection from the block
 *     (via extensionCartUpdate) and feeds it through the shared setter.
 *  3. Persist the chosen address to the order on block checkout.
 */
add_action('woocommerce_blocks_checkout_block_registration', 'avaita_register_checkout_block_integration');
function avaita_register_checkout_block_integration($integration_registry)
{
    require_once __DIR__ . '/blocks/class-local-delivery-blocks-integration.php';
    $integration_registry->register(new Avaita_Local_Delivery_Blocks_Integration());
}

add_action('woocommerce_blocks_loaded', 'avaita_register_blocks_store_api');
function avaita_register_blocks_store_api()
{
    if (function_exists('woocommerce_store_api_register_update_callback')) {
        woocommerce_store_api_register_update_callback([
            'namespace' => 'avaita-local-delivery',
            'callback'  => 'avaita_blocks_apply_delivery_selection',
        ]);
    }
}

/**
 * Store API update callback. Receives { city, area, sub_area, street } sent by
 * the checkout block's extensionCartUpdate(), applies it via the shared setter,
 * and forces WooCommerce to recompute shipping so the new rate is returned.
 */
function avaita_blocks_apply_delivery_selection($data)
{
    $city     = isset($data['city'])     ? sanitize_text_field(wp_unslash($data['city']))     : '';
    $area     = isset($data['area'])     ? sanitize_text_field(wp_unslash($data['area']))     : '';
    $sub_area = isset($data['sub_area']) ? sanitize_text_field(wp_unslash($data['sub_area'])) : '';
    $street   = isset($data['street'])   ? sanitize_text_field(wp_unslash($data['street']))   : '';

    avaita_set_delivery_selection($city, $area, $sub_area, $street);

    if (WC()->cart) {
        // Invalidate cached package rates so the new selection is reflected.
        $packages = WC()->cart->get_shipping_packages();
        foreach ($packages as $package_key => $package) {
            WC()->session->set('shipping_for_package_' . $package_key, false);
        }
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
}

/**
 * Persist the selected delivery address onto the order created via the block
 * (Store API) checkout — mirrors the classic woocommerce_checkout_update_order_meta path.
 */
add_action('woocommerce_store_api_checkout_update_order_from_request', 'avaita_blocks_save_order_address', 10, 2);
function avaita_blocks_save_order_address($order, $request)
{
    $city     = WC()->session->get('avaita_billing_city');
    $area     = WC()->session->get('avaita_billing_area');
    $sub_area = WC()->session->get('avaita_billing_sub_area');
    $street   = WC()->session->get('avaita_billing_street');

    if (!$city && !$area && !$sub_area && !$street) {
        return;
    }

    $order->update_meta_data('billing_city', $city);
    $order->update_meta_data('billing_area', $area);
    $order->update_meta_data('billing_sub_area', $sub_area);
    $order->update_meta_data('billing_street', $street);

    $formatted_address = WC()->session->get('avaita_formatted_address');
    if ($formatted_address) {
        $order->update_meta_data('avaita_formatted_address', $formatted_address);
    }
}

add_action('woocommerce_add_to_cart', 'ald_set_default_shipping_class', 20, 6);
function ald_set_default_shipping_class($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
{
    $default_shipping_class_slug = 'avaita_local_shipping_method';

    $product = wc_get_product($product_id);

    $current_shipping_class_id = $product->get_shipping_class_id();

    if (empty($current_shipping_class_id)) {    
        $default_shipping_class_term = get_term_by('slug', $default_shipping_class_slug, 'product_shipping_class');

    if ($default_shipping_class_term && !is_wp_error($default_shipping_class_term)) {
            wp_set_object_terms($product_id, (int)$default_shipping_class_term->term_id, 'product_shipping_class', false);
        }
    }
}

add_action('woocommerce_before_cart', 'avaita_restro_minimum_order_amount');
add_action('woocommerce_checkout_update_order_review', 'avaita_restro_minimum_order_amount', 10);
function avaita_restro_minimum_order_amount()
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
    $small_order_fee = WC()->session->get('avaita_small_order_fee');

    if($small_order_fee && $small_order_fee > 0) {
        wc_add_notice(
            sprintf(
                __('You can avoid Small order fee of %s by increasing your order total to %s', 'avaita-restro'),
                wc_price($small_order_fee),
                wc_price($order_threshold)
            ),
            'error'
        );
    }

    // if ($order_threshold && $cart_total < $order_threshold) {
    //     wc_add_notice(
    //         sprintf(
    //             __('Your current order total is %s — you must have an order with a minimum of %s to place your order', 'avaita-restro'),
    //             wc_price($cart_total),
    //             wc_price($order_threshold)
    //         ),
    //         'error'
    //     );
    // }

    if ($cart_total < $free_threshold) {
        wc_add_notice(
            sprintf(
                __('Spend %s more to get %sFREE delivery%s', 'avaita-restro'),
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

add_action( 'wp_ajax_avaita_add_order_shipping', 'avaita_add_order_shipping' );
function avaita_add_order_shipping() {
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
        $area_name = $_POST['street'] . ', '. $_POST['subArea'] . ', '. $_POST['area'] . ', ' . $_POST['city'];

        $base_price     = isset($_POST['delivery_price']) ? floatval($_POST['delivery_price']) : 0;
        $free_threshold = isset($_POST['minimum_free_delivery']) ? floatval($_POST['minimum_free_delivery']) : 0;

        // Apply the free-delivery rule up front against the current items subtotal.
        $items_total = 0;
        foreach ($order->get_items('line_item') as $line_item) {
            $items_total += (float) $line_item->get_total();
        }
        $cost = ($free_threshold && $items_total >= $free_threshold) ? 0 : $base_price;

        $item->set_name( sprintf(__("Deliver to %s", "woocommerce"), $area_name));
        $item->set_total($cost);
        // Hidden meta (underscore-prefixed) so the recalculate hook can re-apply
        // the free-delivery rule when the order total later changes.
        $item->add_meta_data('_avaita_base_delivery_price', $base_price, true);
        $item->add_meta_data('_avaita_free_threshold', $free_threshold, true);
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

/**
 * Re-apply the free-delivery rule to the admin-added delivery shipping line
 * whenever an order's totals are recalculated (e.g. the "Recalculate" button or
 * saving order items). If the products subtotal reaches the free threshold the
 * delivery line is set to 0; otherwise it is restored to its base price.
 *
 * Only acts on shipping lines that carry the _avaita_free_threshold meta, so it
 * never touches rates produced by the frontend shipping method.
 */
add_action('woocommerce_order_after_calculate_totals', 'avaita_enforce_free_delivery_on_recalc', 20, 2);
function avaita_enforce_free_delivery_on_recalc($and_taxes, $order)
{
    static $running = false;

    if ($running || ! $order instanceof WC_Order) {
        return;
    }

    $shipping_items = $order->get_items('shipping');
    if (empty($shipping_items)) {
        return;
    }

    $items_total = 0;
    foreach ($order->get_items('line_item') as $line_item) {
        $items_total += (float) $line_item->get_total();
    }

    $changed = false;
    foreach ($shipping_items as $item) {
        $threshold = $item->get_meta('_avaita_free_threshold');
        if ($threshold === '' || $threshold === null) {
            continue; // Not an Avaita delivery line.
        }

        $base_price = (float) $item->get_meta('_avaita_base_delivery_price');
        $new_cost   = ((float) $threshold > 0 && $items_total >= (float) $threshold) ? 0.0 : $base_price;

        if ((float) $item->get_total() !== $new_cost) {
            $item->set_total($new_cost);
            $item->set_taxes(array());
            $item->save();
            $changed = true;
        }
    }

    if ($changed) {
        $running = true;
        $order->calculate_totals(false);
        $running = false;
    }
}

add_action('woocommerce_cart_totals_after_shipping', 'show_delivery_address_in_cart', 1);
function show_delivery_address_in_cart() {
    $delivery_address = urldecode(WC()->session->get('avaita_formatted_address'));
    if ($delivery_address) {
        $output  = '<tr>';
        $output .= '<th>' . esc_html__('Delivery Address', 'avaita-restro') . '</th>';
        $output .= '<td>' . esc_html($delivery_address) . '</td>';

        $output .= '</tr>';
        echo $output;
    }
}