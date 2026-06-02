<?php

global $avaita_local_shipping_db;

class Avaita_Local_Shipping_Database
{
    private $table;

    function __construct()
    {
        global $wpdb;
        $this->table = "{$wpdb->prefix}avaita_delivery_addresses";
        add_action('avaita_plugin_activated', array($this, 'register_database'));
    }


    function register_database()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . $this->table . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            area varchar(255) NOT NULL,
            sub_area varchar(255) NOT NULL,
            street varchar(255) DEFAULT NULL,
            city varchar(255) NOT NULL,
            state varchar(3) DEFAULT 'LUM',
            distance float NOT NULL,
            minimum_order_threshold float NOT NULL,
            minimum_free_delivery float NOT NULL,
            delivery_price float NOT NULL,
            PRIMARY KEY (id),
            FULLTEXT(area, sub_area, street, city),
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            deleted_at timestamp NULL DEFAULT NULL
        ) " . $charset_collate . ";";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    function get_all_cities()
    {
        global $wpdb;
        $response = $wpdb->get_results('SELECT DISTINCT city FROM ' . $this->table);
        return $response;
    }

    function get_all_areas($city)
    {
        global $wpdb;
        
        $city = urldecode(urldecode($city));
        $city = trim($city);
        
        $query = $wpdb->prepare(
            'SELECT DISTINCT area FROM ' . $this->table . ' WHERE city = %s' . ' ORDER BY area ASC',
            $city
        );

        $response = $wpdb->get_results($query);
        
        return $response;
    }

    function get_all_sub_areas($city, $area)
    {
        global $wpdb;

        $city = urldecode(urldecode($city));
        $area = urldecode(urldecode($area));
        
        $city = trim($city);
        $area = trim($area);

        $query = $wpdb->prepare(
            "SELECT DISTINCT sub_area FROM {$this->table} WHERE city = %s AND area = %s AND deleted_at IS NULL ORDER BY sub_area ASC",
            $city,
            $area
        );

        $results = $wpdb->get_results($query);

        return [
            'city' => $city,
            'area' => $area,
            'data' => $results ? $results : []
        ];
    }

    function get_all_street($city, $area, $sub_area)
    {
        global $wpdb;

        $city = urldecode($city);
        $area = urldecode($area);
        $sub_area = urldecode($sub_area);
        $city = trim($city);
        $area = trim($area);
        $sub_area = trim($sub_area);

        $query = $wpdb->prepare(
            "SELECT DISTINCT street FROM {$this->table} WHERE city = %s AND area = %s AND sub_area = %s AND deleted_at IS NULL ORDER BY street ASC",
            $city,
            $area,
            $sub_area
        );

        $results = $wpdb->get_results($query);

        return [
            'debug_info' => [
                'query_executed' => $query,
                'city' => $city,
                'area' => $area,
                'sub_area' => $sub_area,
                'rows_found' => $results ? count($results) : 0
            ],
            'data' => $results ? $results : []
        ];
    }

    function get_shipping_charge_for_street($city, $area, $sub_area, $street)
    {
        global $wpdb;
        $city = urldecode($city);
        $area = urldecode($area);
        $sub_area = urldecode($sub_area);
        $street = urldecode($street);
        $city = trim($city);
        $area = trim($area);
        $sub_area = trim($sub_area);
        $street = trim($street);

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE city = %s AND area = %s AND sub_area = %s AND street = %s",
            $city, $area, $sub_area, $street
        );

        return $wpdb->get_row($query);
    }

    function get_delivery_data($args = []) {
        global $wpdb;

        $args = wp_parse_args($args, [
            'city' => '', 'area' => '', 'sub_area' => '', 'street' => '',
            'search' => '', 'orderby' => '', 'order' => 'desc', 'page' => 1,
        ]);

        $limit  = 20;
        $page   = max(1, (int) $args['page']);
        $offset = ($page - 1) * $limit;

        // --- Filters: exact match, fully prepared ---
        $where  = [];
        $params = [];
        foreach (['city', 'area', 'sub_area', 'street'] as $col) {
            if ($args[$col] !== '') {
                $where[]  = "$col = %s";
                $params[] = $args[$col];
            }
        }

        // --- Free-text search: partial (LIKE) match across the text columns ---
        if (trim((string) $args['search']) !== '') {
            $like = '%' . $wpdb->esc_like(trim($args['search'])) . '%';
            $where[] = '(area LIKE %s OR sub_area LIKE %s OR street LIKE %s OR city LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // --- Sort: whitelist column + direction (cannot be parameterized) ---
        $sortable = [
            'distance'        => 'distance',
            'order_threshold' => 'minimum_order_threshold',
            'free_threshold'  => 'minimum_free_delivery',
            'delivery_price'  => 'delivery_price',
        ];
        $orderby_col = $sortable[$args['orderby']] ?? 'id';
        $order_dir   = strtolower($args['order']) === 'asc' ? 'ASC' : 'DESC';
        $order_sql   = " ORDER BY {$orderby_col} {$order_dir}";

        $count_sql  = "SELECT COUNT(*) FROM {$this->table}{$where_sql}";
        $select_sql = "SELECT * FROM {$this->table}{$where_sql}{$order_sql} LIMIT %d, %d";

        $count = $params
            ? $wpdb->get_var($wpdb->prepare($count_sql, $params))
            : $wpdb->get_var($count_sql);

        $response = $wpdb->get_results(
            $wpdb->prepare($select_sql, array_merge($params, [$offset, $limit]))
        );

        return [
            'page'     => $page,
            'total'    => $count,
            'per_page' => $limit,
            'data'     => $response,
        ];
    }

    function get_all_delivery_data() {
        global $wpdb;

        $query = 'SELECT * FROM ' . $this->table . ' ORDER BY id DESC';
        return $wpdb->get_results($query);
    }

    function get_delivery_data_by_id($id) {
        global $wpdb;

        $query = $wpdb->prepare('SELECT * FROM ' . $this->table . ' WHERE id = %d', $id);
        return $wpdb->get_row($query);
    }

    function add_delivery_data($payload) {
        global $wpdb;

       return $wpdb->insert($this->table, array(
            'area' => sanitize_text_field($payload['area']),
            'sub_area' => sanitize_text_field($payload['sub_area']),
            'street' => isset($payload['street']) ? sanitize_text_field($payload['street']) : null,
            'city' => sanitize_text_field($payload['city']),
            'state' => sanitize_text_field($payload['state'] ?? 'LUM'),
            'distance' => floatval($payload['distance']),
            'minimum_order_threshold' => floatval($payload['minimum_order_threshold']),
            'minimum_free_delivery' => floatval($payload['minimum_free_delivery']),
            'delivery_price' => floatval($payload['delivery_price']),
        ));
    }

    function update_delivery_data($id, $payload) {
        global $wpdb;
        return $wpdb->update($this->table, array(
            'area' => sanitize_text_field($payload['area']),
            'sub_area' => sanitize_text_field($payload['sub_area']),
            'street' => isset($payload['street']) ? sanitize_text_field($payload['street']) : null,
            'city' => sanitize_text_field($payload['city']),
            'state' => sanitize_text_field($payload['state'] ?? 'LUM'),
            'distance' => floatval($payload['distance']),
            'minimum_order_threshold' => floatval($payload['minimum_order_threshold']),
            'minimum_free_delivery' => floatval($payload['minimum_free_delivery']),
            'delivery_price' => floatval($payload['delivery_price']),
        ), array('id' => $id));
    }

    function remove_delivery_data($id) {
        global $wpdb;
        return $wpdb->delete($this->table, array('id' => $id));
    }

    function find_by_location($area, $sub_area, $street) {
        global $wpdb;

        $area     = trim((string) $area);
        $sub_area = trim((string) $sub_area);
        $street   = trim((string) $street);

        if ($street === '') {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE area = %s AND sub_area = %s AND (street IS NULL OR street = '') AND deleted_at IS NULL LIMIT 1",
                $area, $sub_area
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE area = %s AND sub_area = %s AND street = %s AND deleted_at IS NULL LIMIT 1",
                $area, $sub_area, $street
            );
        }

        return $wpdb->get_row($query);
    }

    function bulk_upsert_delivery_data($rows) {
        global $wpdb;

        $result = array(
            'inserted' => 0,
            'updated'  => 0,
            'errors'   => array(),
        );

        if (empty($rows) || !is_array($rows)) {
            return $result;
        }

        $wpdb->query('START TRANSACTION');

        foreach ($rows as $index => $row) {
            $id = isset($row['id']) ? intval($row['id']) : 0;

            if ($id > 0 && $this->get_delivery_data_by_id($id)) {
                // id present and the record exists -> update that row.
                $ok = $this->update_delivery_data($id, $row);
                $action = 'updated';
            } else if ($id > 0) {
                // id present but not found -> insert as a new record (CSV id ignored).
                $ok = $this->add_delivery_data($row);
                $action = 'inserted';
            } else {
                // no id -> location upsert (match area+sub_area+street -> update, else insert).
                $existing = $this->find_by_location(
                    $row['area'] ?? '',
                    $row['sub_area'] ?? '',
                    $row['street'] ?? ''
                );
                if ($existing) {
                    $ok = $this->update_delivery_data($existing->id, $row);
                    $action = 'updated';
                } else {
                    $ok = $this->add_delivery_data($row);
                    $action = 'inserted';
                }
            }

            if ($ok === false) {
                $result['errors'][] = array(
                    'row'     => $index + 1,
                    'message' => $wpdb->last_error ?: ($action . ' failed'),
                );
                $wpdb->query('ROLLBACK');
                return $result;
            }
            $result[$action]++;
        }

        $wpdb->query('COMMIT');

        return $result;
    }
}

$avaita_local_shipping_db = new Avaita_Local_Shipping_Database();