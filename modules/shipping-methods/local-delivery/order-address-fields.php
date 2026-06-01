<?php
add_action('wp_enqueue_scripts', 'avaita_load_checkout_scripts');
function avaita_load_checkout_scripts()
{
    wp_enqueue_script('avaita-local-delivery-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', null);
    wp_enqueue_style('avaita-local-delivery-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
    wp_enqueue_script('avaita-local-delivery', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/js/local-delivery.js', array('avaita-local-delivery-select2'), AVAITA_RESTRO_VER, true);
    wp_enqueue_style('avaita-local-delivery', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/css/local-delivery.css', array('avaita-local-delivery-select2'), AVAITA_RESTRO_VER);

    $user = wp_get_current_user();
    $user_id = $user ? $user->ID : 0;

    // $billing_address_2 = $user_id ? get_user_meta($user_id, 'billing_address_2', true) : '';


    // ob_start();
    // woocommerce_form_field('avaita-address-finder-line1', array(
    //     'label' => __('Enter your Street (पथ)', 'avaita-restro'),
    //     'type' => 'text',
    //     'required' => true,
    //     'default' => $billing_address_2,
    //     'input_class' => array('avaita-address-finder-line1', 'avaita-address-finder'),
    // ));

    // $billing_address1_html = ob_get_clean();
     
    $area = WC()->session->get('avaita_billing_area');
    $sub_area = WC()->session->get('avaita_billing_sub_area');
    $street = WC()->session->get('avaita_billing_street');

    wp_localize_script('avaita-local-delivery', 'AVAITA_DELIVERY_VARS', array(
        'api_host' => get_rest_url() . AVAITA_API_NAMESPACE,
        'nonce' => wp_create_nonce( 'wp_rest' ),
        // 'billing_line1_html' => esc_html($billing_address1_html),
        'checkout' => array(
            'placeholders' => array(
                'sub_area' => __('Select Your Sub Area', 'avaita-restro'),
                'street' => __('Select Your Street', 'avaita-restro'),
            ),
            'values' => array(
                'area' => $area ? urldecode($area) : '',
                'sub_area' => $sub_area ? urldecode($sub_area) : '',
                'street' => $street ? urldecode($street) : '',
            ),
        ),
    ));
}


add_action('admin_enqueue_scripts', 'avaita_load_admin_order_scripts');
function avaita_load_admin_order_scripts()
{
    wp_enqueue_script('avaita-local-delivery-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', null);
    wp_enqueue_style('avaita-local-delivery-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
    wp_enqueue_script('avaita-local-delivery-orders', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/js/admin-local-delivery.js', array('jquery', 'wc-admin-order-meta-boxes'), null, true);   

    $rest_nonce = wp_create_nonce( 'wp_rest' );
    $api_host   = get_rest_url() . AVAITA_API_NAMESPACE;

    $localized_vars = array(
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'api_host'   => $api_host,
        'nonce'      => $rest_nonce,
        'user_id'    => get_current_user_id(),
        'import_url' => $api_host . '/admin/delivery-addresses/import',
        'export_url' => add_query_arg( '_wpnonce', $rest_nonce, $api_host . '/admin/delivery-addresses/export' ),
    );

    wp_localize_script('avaita-local-delivery-orders', 'AVAITA_DELIVERY_VARS', $localized_vars);

    $ava_current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if (in_array($ava_current_page, array('ava-restro', 'ava-restro-delivery-areas'), true)) {
        wp_enqueue_script('avaita-local-delivery-form', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/js/admin-local-delivery-form.js', array('jquery'), null, true );
        wp_localize_script('avaita-local-delivery-form', 'AVAITA_DELIVERY_VARS', $localized_vars);

        wp_enqueue_script('avaita-local-delivery-import', AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/js/admin-local-delivery-import.js', array('jquery', 'avaita-local-delivery-form'), null, true );
        wp_localize_script('avaita-local-delivery-import', 'AVAITA_DELIVERY_VARS', $localized_vars);
    }
}

add_filter('woocommerce_billing_fields', 'avaita_add_billing_fields', 10);
function avaita_add_billing_fields($fields)
{
    global $avaita_local_shipping_db;
    $cities_response = $avaita_local_shipping_db->get_all_cities();
    $cities = array(
        '' => __('Select Your City', 'avaita-restro'),
    );

    foreach ($cities_response as $row) {
        $cities[urlencode($row->city)] = $row->city;
    }

    $billing_city = '';
    $billing_area = '';
    $billing_sub_area = '';
    $billing_street = '';
    $shipping_city = '';
    $shipping_area = '';
    $shipping_sub_area = '';
    $shipping_street = '';

    $user_id = null;
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $user_id = $user->ID;

        $billing_city = get_user_meta($user_id, 'billing_city', true);
        $billing_area = get_user_meta($user_id, 'billing_area', true);
        $billing_sub_area = get_user_meta($user_id, 'billing_sub_area', true);
        $billing_street = get_user_meta($user_id, 'billing_street', true);
        $shipping_city = get_user_meta($user_id, 'shipping_city', true);
        $shipping_area = get_user_meta($user_id, 'shipping_address_1', true);
        $shipping_sub_area = get_user_meta($user_id, 'shipping_address_2', true);
        $shipping_street = get_user_meta($user_id, 'shipping_address_3', true);

        if (!$shipping_city) {
            $shipping_city = $billing_city;
        }

        if (!$shipping_area) {
            $shipping_area = $billing_area;
        }

        if (!$shipping_sub_area) {
            $shipping_sub_area = $billing_sub_area;
        }

        if (!$shipping_street) {
            $shipping_street = $billing_street;
        }
    }

    if (!$billing_city) {
        $billing_city = WC()->session->get('avaita_billing_city');
    }


    if (!$billing_area) {
        $billing_area = WC()->session->get('avaita_billing_area');
    }

    if (!$billing_sub_area) {
        $billing_sub_area = WC()->session->get('avaita_billing_sub_area');
    }

    if (!$billing_street) {
        $billing_street = WC()->session->get('avaita_billing_street');
    }

    $areas = array(
        '' => __('Select the city first', 'avaita-restro')
    );

    if ($billing_city) {
        global $avaita_local_shipping_db;
        $response = $avaita_local_shipping_db->get_all_areas($billing_city);
        if ($response) {
            $areas = array(
                '' => __('Select the area', 'avaita-restro')
            );

            foreach ($response as $row) {
                $areas[urlencode($row->area)] = $row->area;
            }
        }
    }
    
    $sub_areas = array(
        '' =>__('Select the area first', 'avaita-restro')
    );
    
    if ($billing_city && $billing_area) {
        global $avaita_local_shipping_db;
    
        $response = $avaita_local_shipping_db->get_all_sub_areas(
            urldecode($billing_city),
            urldecode($billing_area)
        );
    
        if ($response && isset($response['data']) && count($response['data']) > 0) {
            $sub_areas = array(
                '' => __('Select the street', 'avaita-restro')
            );
    
            foreach ($response['data'] as $row) {
                $sub_areas[urlencode($row->sub_area)] = $row->sub_area;
            }
        }
    }
    
    $street = array(
        '' => __('Select the area first', 'avaita-restro')
    );
    
    if ($billing_city && $billing_area && $billing_sub_area) {
        global $avaita_local_shipping_db;
    
        $response = $avaita_local_shipping_db->get_all_street(
            urldecode($billing_city),
            urldecode($billing_area),
            urldecode($billing_sub_area)
        );
    
        if ($response && isset($response['data']) && count($response['data']) > 0) {
            $street = array(
                '' => __('Select the sub area', 'avaita-restro')
            );
    
            foreach ($response['data'] as $row) {
                $street[urlencode($row->street)] = $row->street;
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
            'label' => __('Select Your City', 'avaita-restro'),
            'class' => 'avaita-address-finder-wrapper',
        ),
        'avaita_billing_area' => array(
            'type' => 'select',
            'id' => 'avaita-billing-area',
            'options' => $areas,
            'class' => 'avaita-address-finder-wrapper',
            'required' => true,
            'label' => __('Select Your Area', 'avaita-restro'),
        ),
        'avaita_billing_sub_area' => array(
            'type'=> 'select',
            'id' => 'avaita-billing-sub-area',
            'options' => $sub_areas,
            'class' => 'avaita-address-finder-wrapper',
            'required' => true,
            'label' => __('Select Your Sub Area', 'avaita-restro'),
        ),
        'avaita_billing_street' => array(
            'type'=> 'select',
            'id' => 'avaita-billing-street',
            'options' => $street,
            'class' => 'avaita-address-finder-wrapper',
            'required' => true,
            'label' => __('Select Your Street', 'avaita-restro'),
        ),
        // 'avaita_shipping_city' => array(
        //     'type' => 'select',
        //     'id' => 'avaita-shipping-city',
        //     'default' => $shipping_city,
        //     'options' => $cities,
        //     'required' => true,
        //     'label' => __('Select Your City', 'avaita-restro'),
        //     'class' => 'avaita-address-finder-wrapper',
        // ),
        // 'avaita_shipping_area' => array(
        //     'type' => 'select',
        //     'id' => 'avaita-shipping-area',
        //     'options' => $areas,
        //     'class' => 'avaita-address-finder-wrapper',
        //     'required' => true,
        //     'label' => __('Select Your Area', 'avaita-restro'),
        // ),
    );

    if (count($new_fields['avaita_billing_area']['options']) <= 1) {
        $new_fields['avaita_billing_area']['custom_attributes'] = array('disabled' => 'disabled');
    }

    if (count($new_fields['avaita_billing_sub_area']['options']) <= 1) {
        $new_fields['avaita_billing_sub_area']['custom_attributes'] = array('disabled' => 'disabled');
    }

    if (count($new_fields['avaita_billing_street']['options']) <= 1) {
        $new_fields['avaita_billing_street']['custom_attributes'] = array('disabled' => 'disabled');
    }

    $fields['billing_city']['type'] = 'hidden';
    $fields['billing_city']['default'] = urldecode($billing_city);

    $fields['billing_address_1']['type'] = 'hidden';
    $fields['billing_address_1']['default'] = urldecode($billing_area);
    $new_fields['avaita_billing_area']['default'] = $billing_area;

    // $fields['billing_sub_area']['type'] = 'hidden';
    // $fields['billing_sub_area']['default'] = urldecode($billing_sub_area);
    $new_fields['avaita_billing_sub_area']['default'] = $billing_sub_area;

    // $fields['billing_street']['type'] = 'hidden';
    // $fields['billing_street']['default'] = urldecode($billing_street);
    $new_fields['avaita_billing_street']['default'] = $billing_street;

    unset($fields['billing_first_name']);
    unset($fields['billing_last_name']);
    unset($fields['billing_birthday']);
    unset($fields['billing_state']);
    unset($fields['billing_phone']);
    unset($fields['billing_email']);

    return array_merge($new_fields, $fields);
}


add_action( 'woocommerce_checkout_update_order_meta', 'avaita_save_checkout_field' );
function avaita_save_checkout_field( $order_id ) {
    update_post_meta( $order_id, 'billing_city', urldecode($_POST['avaita_billing_city']));
    update_post_meta( $order_id, 'billing_address_1', urldecode($_POST['avaita_billing_area']));
    update_post_meta( $order_id, 'billing_area', urldecode($_POST['avaita_billing_area']));
    update_post_meta( $order_id, 'billing_address_2', urldecode($_POST['avaita_billing_sub_area']));
    update_post_meta( $order_id, 'billing_sub_area', urldecode($_POST['avaita_billing_sub_area']));
    update_post_meta( $order_id, 'billing_street', urldecode($_POST['avaita_billing_street']));

    $order = wc_get_order( $order_id );
    $user_id = $order->get_user_id();

    if ($user_id) {
        update_user_meta($user_id, 'billing_city', urldecode( $_POST['avaita_billing_city'] ) );
        update_user_meta($user_id, 'billing_area', urldecode( $_POST['avaita_billing_area'] ) );
        update_user_meta($user_id, 'billing_sub_area', urldecode( $_POST['avaita_billing_sub_area'] ) );
        update_user_meta($user_id, 'billing_street', urldecode( $_POST['avaita_billing_street'] ) );
    }
}