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

    register_rest_route(AVAITA_API_NAMESPACE, '/delivery-addresses/sub-areas', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'avaita_get_available_sub_areas',
    ));

    register_rest_route(AVAITA_API_NAMESPACE, '/delivery-addresses/street', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'avaita_get_available_street',
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

function avaita_get_available_sub_areas($request)
{
    global $avaita_local_shipping_db;

    $query_params = $request->get_query_params();
    $response = $avaita_local_shipping_db->get_all_sub_areas($query_params['city'], $query_params['area']);

    if (!isset($response['data']) || count($response['data']) == 0) {
        return new WP_REST_Response([], 404);
    }

    return new WP_REST_Response($response['data'], 200);
}

function avaita_get_available_street($request)
{
    global $avaita_local_shipping_db;
    $params = $request->get_query_params();
    $response = $avaita_local_shipping_db->get_all_street($params['city'], $params['area'], $params['sub_area']);

    if (empty($response['data'])) {
        return new WP_REST_Response([], 404);
    }
    
    return new WP_REST_Response($response['data'], 200);
}

function avaita_get_available_area_details($request)
{
    global $avaita_local_shipping_db;

    $query_params = $request->get_query_params();
    $response = $avaita_local_shipping_db->get_shipping_charge_for_street(
        $query_params['city'], 
        urldecode($query_params['area']), 
        urldecode($query_params['sub_area']), 
        urldecode($query_params['street'])
    );

    if (!$response) {
        return new WP_REST_Response(null, 404);
    }

    $response->formatted_delivery_price = wc_price($response->delivery_price);
    $response->formatted_minimum_free_delivery = wc_price($response->minimum_free_delivery);
    $response->formatted_distance = $response->distance ? $response->distance . ' ' . __('km', 'woocommerce') : $response->distance;
    // $response->delivery_label = sprintf(
    //     __('Delivering to %s', 'woocommerce'), 
    //     $response->street . ', ' . $response->sub_area . ', ' . $response->area . ', ' . $response->city
    // );
    $response->delivery_label = '';

    return new WP_REST_Response($response, 200);
}