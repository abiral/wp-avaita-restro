<?php

/**
 * Staging HTTP Basic Auth compatibility.
 *
 * When the site sits behind server-level HTTP Basic Auth (a staging gate), the
 * browser sends those Basic Auth credentials on every same-origin request,
 * including REST calls. WordPress's Application Passwords authenticator then
 * tries to log that Basic Auth username in as an application password; when it
 * isn't a real WP user it returns an `invalid_username` error that blocks the
 * ENTIRE REST API for unauthenticated visitors — breaking the guest-checkout
 * address lookups (e.g. /wp-json/avaita/delivery-addresses/areas).
 *
 * For unauthenticated requests to THIS plugin's public REST namespace, when the
 * Basic Auth user is not a WordPress user, strip the Basic Auth vars so the
 * Application Passwords authenticator skips them. Runs at priority 5, before
 * core's wp_authenticate_application_password (priority 20). Genuine logged-in
 * users (cookie) and real application-password requests are left untouched.
 */
add_filter('determine_current_user', 'avaita_ignore_staging_basic_auth_for_public_api', 5);
function avaita_ignore_staging_basic_auth_for_public_api($user)
{
    if (!empty($user)) {
        return $user; // Already authenticated (e.g. logged-in admin via cookie).
    }

    if (empty($_SERVER['PHP_AUTH_USER']) || !defined('AVAITA_API_NAMESPACE')) {
        return $user;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    if (strpos($request_uri, '/' . AVAITA_API_NAMESPACE . '/') === false) {
        return $user; // Not a request to our REST namespace.
    }

    // Only neutralize when the Basic Auth username is NOT a real WP user, so
    // genuine application-password requests keep working.
    if (!get_user_by('login', $_SERVER['PHP_AUTH_USER'])) {
        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    }

    return $user;
}

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