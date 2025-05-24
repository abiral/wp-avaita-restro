<?php

require_once __DIR__ . '/class.ava_customers.php';
require_once __DIR__ . '/class.ava_orders.php';
require_once __DIR__ . '/class.ava_products_cats.php';
require_once __DIR__ . '/class.ava_products.php';

$customers_api = Ava_Customers::get_instance();
$orders_api = Ava_Orders::get_instance();
$products_cats_api = Ava_Products_Cats::get_instance();
$products_api  = Ava_Products::get_instance();

add_action( 'rest_api_init', array( $customers_api, 'register_endpoints'));
add_action( 'rest_api_init', array( $orders_api, 'register_endpoints'));
add_action( 'rest_api_init', array( $products_cats_api, 'register_endpoints'));
add_action( 'rest_api_init', array( $products_api, 'register_endpoints'));
