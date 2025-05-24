<?php

add_action('woocommerce_new_order', 'avaita_create_customer_account_if_not_exists', 10, 1);
function avaita_create_customer_account_if_not_exists($order_id)
{
    $order = wc_get_order($order_id);
    $current_user = wp_get_current_user();

    if (in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles)) {
        $billing_email = $order->get_billing_email();

        if (!email_exists($billing_email)) {
            $password = wp_generate_password();
            $user_id = wc_create_new_customer($billing_email, $billing_email, $password);
        } else {
            $user = get_user_by('email', $billing_email);
            if (! empty($user)) {
                $user_id = $user->ID;
            }
        }

        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'billing_first_name', $order->get_billing_first_name());
            update_user_meta($user_id, 'billing_last_name', $order->get_billing_last_name());
            update_user_meta($user_id, 'billing_company', $order->get_billing_company());
            update_user_meta($user_id, 'billing_address_1', $order->get_billing_address_1());
            update_user_meta($user_id, 'billing_address_2', $order->get_billing_address_2());
            update_user_meta($user_id, 'billing_city', $order->get_billing_city());
            update_user_meta($user_id, 'billing_postcode', $order->get_billing_postcode());
            update_user_meta($user_id, 'billing_country', $order->get_billing_country());
            update_user_meta($user_id, 'billing_state', $order->get_billing_state());
            update_user_meta($user_id, 'billing_phone', $order->get_billing_phone());
            update_user_meta($user_id, 'billing_email', $billing_email);

            update_user_meta($user_id, 'shipping_first_name', $order->get_shipping_first_name());
            update_user_meta($user_id, 'shipping_last_name', $order->get_shipping_last_name());
            update_user_meta($user_id, 'shipping_company', $order->get_shipping_company());
            update_user_meta($user_id, 'shipping_address_1', $order->get_shipping_address_1());
            update_user_meta($user_id, 'shipping_address_2', $order->get_shipping_address_2());
            update_user_meta($user_id, 'shipping_city', $order->get_shipping_city());
            update_user_meta($user_id, 'shipping_postcode', $order->get_shipping_postcode());
            update_user_meta($user_id, 'shipping_country', $order->get_shipping_country());
            update_user_meta($user_id, 'shipping_state', $order->get_shipping_state());

            update_user_meta($user_id, 'delivery_area', $order->get_meta('_billing_billing_delivery_area'));
            update_user_meta($user_id, 'billing_birthday', $order->get_meta('_billing_billing_birthday'));

            $order->set_customer_id($user_id);
            $order->save();

            wp_new_user_notification($user_id, null, 'user');
        }
    }
}
