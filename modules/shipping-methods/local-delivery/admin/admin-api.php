<?php
// /avaita/admin/delivery-address
class Avaita_Admin_Local_Shipping_API    {

    private $namespace = AVAITA_API_NAMESPACE;
    private $rest_base = 'admin/delivery-address';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // GET all delivery addresses
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            'methods' => 'GET',
            'callback' => array($this, 'get_delivery_addresses'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // GET single delivery address
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_delivery_address'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            'methods' => 'POST',
            'callback' => array($this, 'add_delivery_address'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            'methods' => 'PATCH',
            'callback' => array($this, 'update_delivery_address'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'remove_delivery_address'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // CSV export — streams a CSV file of all delivery addresses
        register_rest_route($this->namespace, '/admin/delivery-addresses/export', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_delivery_addresses'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Bulk import — accepts an array of pre-computed rows and upserts them
        register_rest_route($this->namespace, '/admin/delivery-addresses/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_delivery_addresses'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
    }

    public function check_admin_permissions($request) {
        // Check if user has the required capability
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return false;
        }

        // For cookie-based requests (admin area), rely on existing WordPress authentication
        if ( is_user_logged_in() && is_admin() ) {
            return true;
        }

        // For REST requests with nonce, validate the nonce
        $nonce = null;
        if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = $_REQUEST['_wpnonce'];
        } elseif ( $request->get_header( 'X-WP-Nonce' ) ) {
            $nonce = $request->get_header( 'X-WP-Nonce' );
        }

        if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }

        return false;
    }

    public function get_delivery_addresses($request) {
        global $avaita_local_shipping_db;

        try {
            $addresses = $avaita_local_shipping_db->get_all_delivery_data();
            
            if ($addresses === false) {
                return new WP_Error('fetch_failed', __('Failed to fetch delivery addresses', 'avaita'), array('status' => 500));
            }

            return rest_ensure_response($addresses);
        } catch (Exception $e) {
            return new WP_Error('fetch_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function get_delivery_address($request) {
        global $avaita_local_shipping_db;

        $id = (int) $request['id'];

        if (!$id) {
            return new WP_Error('invalid_id', __('Invalid address ID', 'avaita'), array('status' => 400));
        }

        try {
            $address = $avaita_local_shipping_db->get_delivery_data_by_id($id);
            
            if (!$address) {
                return new WP_Error('not_found', __('Delivery address not found', 'avaita'), array('status' => 404));
            }

            return rest_ensure_response($address);
        } catch (Exception $e) {
            return new WP_Error('fetch_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function add_delivery_address($request) {
        global $avaita_local_shipping_db, $wpdb;

        $params = $request->get_json_params();
        $result = $avaita_local_shipping_db->add_delivery_data($params);

        if ($result === false) {
            return new WP_Error('insert_failed', __('Failed to insert delivery address', 'avaita'), array('status' => 500));
        }

        return rest_ensure_response(array('message' => __('Address added successfully', 'avaita'), 'id' => $wpdb->insert_id));
    }

    public function update_delivery_address($request) {
        global $avaita_local_shipping_db;

        $id = (int) $request['id'];
        $params = $request->get_json_params();
        $fields = array();

        $allowed_fields = ['area', 'sub_area', 'street', 'city', 'state', 'distance', 'minimum_order_threshold', 'minimum_free_delivery', 'delivery_price'];

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

    public function export_delivery_addresses($request) {
        global $avaita_local_shipping_db;

        $rows = $avaita_local_shipping_db->get_all_delivery_data();
        $filename = 'delivery-areas-' . date('Y-m-d') . '.csv';

        // Discard anything WordPress/REST may have buffered so the CSV is the only body.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');

        // UTF-8 BOM so Excel opens it with the correct encoding.
        fwrite($out, "\xEF\xBB\xBF");

        $columns = array(
            'id', 'area', 'sub_area', 'street', 'city', 'state',
            'distance', 'minimum_order_threshold', 'minimum_free_delivery', 'delivery_price',
        );

        fputcsv($out, $columns);

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $line = array();
                foreach ($columns as $col) {
                    $line[] = isset($row->$col) ? $row->$col : '';
                }
                fputcsv($out, $line);
            }
        }

        fclose($out);
        exit;
    }

    public function import_delivery_addresses($request) {
        global $avaita_local_shipping_db;

        $params = $request->get_json_params();
        $rows   = isset($params['rows']) && is_array($params['rows']) ? $params['rows'] : array();

        if (empty($rows)) {
            return new WP_Error('no_rows', __('No rows provided for import', 'avaita'), array('status' => 400));
        }

        $required_text    = array('area', 'sub_area', 'city');
        $required_numeric = array('distance', 'minimum_order_threshold', 'minimum_free_delivery', 'delivery_price');

        $validated = array();
        $errors    = array();
        $skipped   = 0;

        foreach ($rows as $index => $row) {
            $row_num = $index + 1;

            if (!is_array($row)) {
                $errors[] = array('row' => $row_num, 'message' => 'Row is not an object');
                $skipped++;
                continue;
            }

            $row_error = null;

            foreach ($required_text as $field) {
                $val = isset($row[$field]) ? trim((string) $row[$field]) : '';
                if ($val === '') {
                    $row_error = sprintf('Missing required field: %s', $field);
                    break;
                }
            }

            if ($row_error === null) {
                foreach ($required_numeric as $field) {
                    if (!isset($row[$field]) || !is_numeric($row[$field]) || floatval($row[$field]) < 0) {
                        $row_error = sprintf('Invalid numeric value for: %s', $field);
                        break;
                    }
                }
            }

            if ($row_error) {
                $errors[] = array('row' => $row_num, 'message' => $row_error);
                $skipped++;
                continue;
            }

            $validated[] = array(
                'id'                      => isset($row['id']) ? intval($row['id']) : 0,
                'area'                    => trim((string) $row['area']),
                'sub_area'                => trim((string) $row['sub_area']),
                'street'                  => isset($row['street']) ? trim((string) $row['street']) : '',
                'city'                    => trim((string) $row['city']),
                'state'                   => !empty($row['state']) ? trim((string) $row['state']) : 'LUM',
                'distance'                => floatval($row['distance']),
                'minimum_order_threshold' => floatval($row['minimum_order_threshold']),
                'minimum_free_delivery'   => floatval($row['minimum_free_delivery']),
                'delivery_price'          => floatval($row['delivery_price']),
            );
        }

        $upsert = $avaita_local_shipping_db->bulk_upsert_delivery_data($validated);

        if (!empty($upsert['errors'])) {
            $errors = array_merge($errors, $upsert['errors']);
        }

        return rest_ensure_response(array(
            'message'  => __('Import completed', 'avaita'),
            'inserted' => $upsert['inserted'],
            'updated'  => $upsert['updated'],
            'skipped'  => $skipped,
            'errors'   => $errors,
        ));
    }
}

new Avaita_Admin_Local_Shipping_API ();

// Add a debug function to help test the API
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    add_action( 'wp_loaded', function() {
        if ( isset( $_GET['avaita_debug_nonce'] ) && current_user_can( 'manage_options' ) ) {
            $nonce = wp_create_nonce( 'wp_rest' );
            $api_url = get_rest_url() . AVAITA_API_NAMESPACE . '/admin/delivery-address/1';
            die( "Debug Info:<br>REST Nonce: {$nonce}<br>Test URL: <a href='{$api_url}?_wpnonce={$nonce}' target='_blank'>{$api_url}?_wpnonce={$nonce}</a><br>Or use: AVAITA_DELIVERY_VARS.nonce = '{$nonce}'" );
        }
    });
}