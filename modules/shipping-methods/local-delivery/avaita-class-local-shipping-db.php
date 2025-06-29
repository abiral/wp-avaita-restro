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
            subarea varchar(255) NOT NULL, 
            street varchar(255) DEFAULT NULL,
            city varchar(255) NOT NULL,
            state varchar(3) DEFAULT 'LUM',
            distance float NOT NULL,
            minimum_order_threshold float NOT NULL,
            minimum_free_delivery float NOT NULL,
            delivery_price float NOT NULL,
            PRIMARY KEY (id),
            FULLTEXT(area, subarea, street, city) 
        ) " . $charset_collate . ";";


        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        
    }

    function get_all_cities()
    {
        global $wpdb;
        $response = $wpdb->get_results('SELECT city FROM ' . $this->table);
        return $response;
    }

    function get_all_areas($city)
    {
        global $wpdb;
        $city = urldecode($city);

        $response = $wpdb->get_results('SELECT area FROM ' . $this->table . ' WHERE city = "' . $city . '"');
        return $response;
    }

    function get_shipping_charge_for_area($city, $area)
    {
        global $wpdb;
        $city = urldecode($city);
        $area = urldecode($area);

        $response = $wpdb->get_row('SELECT * FROM ' . $this->table . ' WHERE city = "' . $city . '" AND area = "' . $area . '"');
        
        return $response ? $response : null;
    }

    function get_delivery_data($search='', $page=1) {
        global $wpdb;

        $select_attr = ['*'];
        $where_query = '';
        $order = '';
        $limit = 20;
        $offset = ($page - 1) * $limit;

        if ($search) {
            $search = preg_replace('/\b(\w+)/', '+$1', $search);
            $select_attr[] = 'MATCH(area, street, city) AGAINST("'.$search.'" IN BOOLEAN MODE) AS relevance';
            $order = 'relevance DESC';
            $where_query = 'MATCH(area, street, city) AGAINST("'.$search.'" IN BOOLEAN MODE)';
        }

        $query = 'SELECT '. implode(',' , $select_attr) . ' FROM ' .  $this->table;
        $count_query = 'SELECT COUNT(*) FROM ' .  $this->table;
        if ($where_query) {
            $query .=  ' WHERE ' . $where_query;
            $count_query .=  ' WHERE ' . $where_query;
        }

        if ($order) {
            $query .= ' ORDER BY ' . $order;
        }

        $query .= ' LIMIT ' . $offset . ', ' . $limit;

        $count = $wpdb->get_var($count_query);

        $response = $wpdb->get_results($query);

        return array (
            'page' => $page,
            'total' => $count,
            'per_page' => $limit,
            'data' => $response,
        );
    }

    function add_delivery_data($payload) {
        global $wpdb;

       return $wpdb->insert($this->table, array(
            'city' => sanitize_text_field($payload['city']),
            'area' => sanitize_text_field($payload['area']),
            'subarea' => sanitize_text_field($payload['subarea']), 
            'street' => isset($payload['street']) ? sanitize_text_field($payload['street']) : null,
            'state' => sanitize_text_field($payload['state'] ?? 'LUM'),
            'distance' => floatval($payload['distance']),
            'minimum_order_threshold' => floatval($payload['minimum_order_threshold']),
            'minimum_free_delivery' => floatval($payload['minimum_free_delivery']),
            'delivery_price' => floatval($payload['delivery_price']),
        ));
    }

    function bulk_add_delivery_data($payloads) {
        global $wpdb;
        $results = [];

        foreach ($payloads as $payload) {
            $result = $wpdb->insert($this->table, array(
                'city' => sanitize_text_field($payload['city']),
                'area' => sanitize_text_field($payload['area']),
                'subarea' => sanitize_text_field($payload['subarea']),
                'street' => isset($payload['street']) ? sanitize_text_field($payload['street']) : null,
                'state' => sanitize_text_field($payload['state'] ?? 'LUM'),
                'distance' => floatval($payload['distance']),
                'minimum_order_threshold' => floatval($payload['minimum_order_threshold']),
                'minimum_free_delivery' => floatval($payload['minimum_free_delivery']),
                'delivery_price' => floatval($payload['delivery_price']),
            ));
            
            $results[] = $wpdb->insert_id;
        }

        return $results;
    }

    function update_delivery_data($id, $payload) {
        global $wpdb;
        return $wpdb->update($this->table, $payload, array('id' => $id));
    }

    function remove_delivery_data($id) {
        global $wpdb;
        return $wpdb->delete($this->table, array('id' => $id));
    }
}

$avaita_local_shipping_db = new Avaita_Local_Shipping_Database();