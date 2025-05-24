<?php

add_action('rest_api_init', 'avaita_register_local_delivery_endpoints');
function avaita_register_local_delivery_endpoints()
{
    register_rest_route(AVAITA_API_NAMESPACE, '/delivery-addresses/areas', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'avaita_get_available_areas',
    ));

    register_rest_route(AVAITA_API_NAMESPACE, '/delivery-addresses/area-details', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'avaita_get_available_area_details',
    ));
}

function avaita_get_available_areas($request)
{
    global $avaita_local_shipping_db;

    $query_params = $request->get_query_params();
    $response = $avaita_local_shipping_db->get_all_areas($query_params['city']);

    if (count($response) == 0) {
        return new WP_REST_Response([], 404);
    }

    return new WP_REST_Response($response, 200);
}


function avaita_get_available_area_details($request)
{
    global $avaita_local_shipping_db;

    $query_params = $request->get_query_params();
    $response = $avaita_local_shipping_db->get_shipping_charge_for_area($query_params['city'], $query_params['area']);

    if (!$response) {
        return new WP_REST_Response(null, 404);
    }


    $response->formatted_delivery_price = wc_price($response->delivery_price);
    $response->formatted_minimum_free_delivery = wc_price($response->minimum_free_delivery);
    $response->formatted_distance = $response->distance ? $response->distance . ' ' . __('km', 'woocommerce') : $response->distance;
    $response->delivery_label = sprintf(__('Deliver to %s', 'woocommerce'), $response->area . ', ' . $response->city);


    return new WP_REST_Response($response, 200);
}