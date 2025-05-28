<?php
// /avaita/admin/delivery-address
class Avaita_Admin_Local_Shipping_API    {

    private $namespace = AVAITA_API_NAMESPACE;
    private $rest_base = 'admin/delivery-address';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            'methods' => 'POST',
            'callback' => array($this, 'add_delivery_address'),
            'permission_callback' => function () {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            'methods' => 'PATCH',
            'callback' => array($this, 'update_delivery_address'),
            'permission_callback' => function () {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'remove_delivery_address'),
            'permission_callback' => function () {
                return current_user_can('manage_woocommerce');
            }
        ));
    }

    public function add_delivery_address($request) {
        global $avaita_local_shipping_db, $wpdb;

        $params = $request->get_json_params();
        $results = $avaita_local_shipping_db->bulk_add_delivery_data($params);

        if ($results === false) {
            return new WP_Error('insert_failed', __('Failed to insert delivery addresses', 'avaita'), array('status' => 500));
        }

        return rest_ensure_response(array('message' => __('Addresses added successfully', 'avaita'), 'ids' => $results));
    }

    public function update_delivery_address($request) {
        global $avaita_local_shipping_db;

        $id = (int) $request['id'];
        $params = $request->get_json_params();
        $fields = array();

        $allowed_fields = ['area', 'street', 'city', 'state', 'distance', 'minimum_order_threshold', 'minimum_free_delivery', 'delivery_price'];

        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $fields[$field] = is_numeric($params[$field]) ? floatval($params[$field]) : sanitize_text_field($params[$field]);
            }
        }

        if (empty($fields)) {
            return new WP_Error('no_fields', __('No valid fields provided for update','avaita'), array('status' => 400));
        }

        $updated = $avaita_local_shipping_db->update_delivery_data($id, $fields);

        if ($updated === false) {
            return new WP_Error('update_failed', __('Failed to update delivery address', 'avaita'), array('status' => 500));
        }

        return rest_ensure_response(array('message' => __('Address updated successfully', 'avaita')));
    }

    public function remove_delivery_address($request) {
        global $avaita_local_shipping_db, $wpdb;

        $id = (int) $request['id'];

        $deleted = $avaita_local_shipping_db->remove_delivery_data($id);

        if ($deleted === false) {
            return new WP_Error('delete_failed', __('Failed to delete delivery address', 'avaita'), array('status' => 500));
        }

        return rest_ensure_response(array('message' => __('Address deleted successfully', 'avaita')));
    }
}

new Avaita_Admin_Local_Shipping_API ();