<?php
add_action('wp_enqueue_scripts', 'avaita_load_checkout_scripts');
function avaita_load_checkout_scripts()
{
    wp_enqueue_script('avaita-local-delivery-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', null);
    wp_enqueue_style('avaita-local-delivery-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
    wp_enqueue_script('avaita-local-delivery', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/js/local-delivery.js', array('avaita-local-delivery-select2'), null, true);
    wp_enqueue_style('avaita-local-delivery', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/css/local-delivery.css', array('avaita-local-delivery-select2'));

    $user = wp_get_current_user();
    $user_id = $user ? $user->ID : 0;

    $billing_address_2 = $user_id ? get_user_meta($user_id, 'billing_address_2', true) : '';


    ob_start();
    woocommerce_form_field('avaita-address-finder-line1', array(
        'label' => __('Enter your Street (पथ)', 'avaita'),
        'type' => 'text',
        'required' => true,
        'default' => $billing_address_2,
        'input_class' => array('avaita-address-finder-line1', 'avaita-address-finder'),
    ));

    $billing_address1_html = ob_get_clean();

    wp_localize_script('avaita-local-delivery', 'AVAITA_DELIVERY_VARS', array(
        'api_host' => get_rest_url() . AVAITA_API_NAMESPACE,
        'billing_line1_html' => esc_html($billing_address1_html),
    ));
}


add_action('admin_enqueue_scripts', 'avaita_load_admin_order_scripts');
function avaita_load_admin_order_scripts()
{
    wp_enqueue_script('avaita-local-delivery-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', null);
    wp_enqueue_style('avaita-local-delivery-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
    wp_enqueue_script('avaita-local-delivery-orders', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/js/admin-local-delivery.js', array('jquery', 'wc-admin-order-meta-boxes'), null, true);   
    
    $localized_vars = array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'api_host' => get_rest_url() . AVAITA_API_NAMESPACE,
        'nonce' => wp_create_nonce('wp_rest'),
    );

    wp_localize_script('avaita-local-delivery-orders', 'AVAITA_DELIVERY_VARS', $localized_vars);

    if ($_GET['page'] && $_GET['page'] == 'ava-restro') {
        wp_enqueue_script('avaita-local-delivery-form', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/js/admin-local-delivery-form.js', array('jquery'), null, true );
        wp_localize_script('avaita-local-delivery-form', 'AVAITA_DELIVERY_VARS', $localized_vars);
    }
}

add_filter('woocommerce_billing_fields', 'avaita_add_billing_fields', 10);
function avaita_add_billing_fields($fields)
{
    global $avaita_local_shipping_db;
    $cities_response = $avaita_local_shipping_db->get_all_cities();
    $cities = array(
        '' => __('Select Your City', 'avaita'),
    );

    foreach ($cities_response as $row) {
        $cities[urlencode($row->city)] = $row->city;
    }

    $billing_city = '';
    $billing_area = '';
    $shipping_city = '';
    $shipping_area = '';

    $user_id = null;
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $user_id = $user->ID;

        $billing_city = get_user_meta($user_id, 'billing_city', true);
        $billing_area = get_user_meta($user_id, 'billing_address_1', true);
        $shipping_city = get_user_meta($user_id, 'shipping_city', true);
        $shipping_area = get_user_meta($user_id, 'shipping_address_1', true);

        if (!$shipping_city) {
            $shipping_city = $billing_city;
        }

        if (!$shipping_area) {
            $shipping_area = $billing_area;
        }
    }

    if (!$billing_city) {
        $billing_city = WC()->session->get('avaita_billing_city');
    }


    if ($billing_area) {
        $billing_area = WC()->session->get('avaita_billing_area');
    }

    $areas = array(
        '' => 'Select the city first'
    );

    if ($billing_city) {
        global $avaita_local_shipping_db;
        $response = $avaita_local_shipping_db->get_all_areas($billing_city);
        if ($response) {
            $areas = array(
                '' => 'Select the area'
            );

            foreach ($response as $row) {
                $areas[urlencode($row->area)] = $row->area;
            }
        }
    }

    $new_fields = array(
        'billing_first_name' => $fields['billing_first_name'],
        'billing_last_name' => $fields['billing_last_name'],
        'billing_birthday' => $fields['billing_birthday'],
        'billing_phone' => $fields['billing_phone'],
        'billing_email' => $fields['billing_email'],
        'avaita_billing_city' => array(
            'type' => 'select',
            'id' => 'avaita-billing-city',
            'default' => urlencode($billing_city),
            'options' => $cities,
            'required' => true,
            'label' => __('Select Your City', 'avaita'),
            'class' => 'avaita-address-finder-wrapper',
        ),
        'avaita_billing_area' => array(
            'type' => 'select',
            'id' => 'avaita-billing-area',
            'options' => $areas,
            'class' => 'avaita-address-finder-wrapper',
            'required' => true,
            'label' => __('Select Your Area', 'avaita'),
        ),
        // 'avaita_shipping_city' => array(
        //     'type' => 'select',
        //     'id' => 'avaita-shipping-city',
        //     'default' => $shipping_city,
        //     'options' => $cities,
        //     'required' => true,
        //     'label' => __('Select Your City', 'avaita'),
        //     'class' => 'avaita-address-finder-wrapper',
        // ),
        // 'avaita_shipping_area' => array(
        //     'type' => 'select',
        //     'id' => 'avaita-shipping-area',
        //     'options' => $areas,
        //     'class' => 'avaita-address-finder-wrapper',
        //     'required' => true,
        //     'label' => __('Select Your Area', 'avaita'),
        // ),
    );

    if (count($new_fields['avaita_billing_area']['options']) <= 1) {
        $new_fields['avaita_billing_area']['custom_attributes'] = array('disabled' => 'disabled');
    }

    $fields['billing_city']['type'] = 'hidden';
    $fields['billing_city']['default'] = urldecode($billing_city);

    $fields['billing_address_1']['type'] = 'hidden';
    $fields['billing_address_1']['default'] = urldecode($billing_area);
    $new_fields['avaita_billing_area']['default'] = $billing_area;

    $fields['billing_address_2']['type'] = 'hidden';

    unset($fields['billing_first_name']);
    unset($fields['billing_last_name']);
    unset($fields['billing_birthday']);
    unset($fields['billing_state']);
    unset($fields['billing_phone']);
    unset($fields['billing_email']);

    return array_merge($new_fields, $fields);
}