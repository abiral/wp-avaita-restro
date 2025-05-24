<?php

class Ava_Customers {
    public function register_endpoints() {
        register_rest_route( 'ava-restro', '/customers', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'get'),
        ));
    }

    public function get($request) {
        $customers = get_users(array(
            'role' => 'customer'
        ));

        if ($customers) {
            return array_map(function($c) {
                $first_name = get_user_meta($c->ID, 'first_name', true);
                $last_name = get_user_meta($c->ID, 'last_name', true);
                $phone = get_user_meta($c->ID, 'billing_phone', true);
                $delivery_area = get_user_meta($c->ID, 'delivery_area', true);
                
                return array (
                    "ID" => $c->ID,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "phone" => $phone,
                    "delivery_area" => $delivery_area,
                    "user_login" => $c->user_login,
                    "user_nicename" => $c->user_nicename,
                    "user_email" => $c->user_email,
                    "user_registered" => $c->user_registered,
                    "display_name" => $c->display_name,
                );
            }, $customers);
        }

        return new WP_REST_Response( array( 'message' => __('No Customers found', 'ava'), 'data' => array()), 404 );
    }
    
    public static function get_instance() {
        return new Ava_Customers();
    }
}