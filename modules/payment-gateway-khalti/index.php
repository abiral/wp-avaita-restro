<?php

defined('ABSPATH') || exit;

// Define WC_KHALTI_PLUGIN_FILE.
if (!defined('WC_KHALTI_PLUGIN_FILE')) {
    define('WC_KHALTI_PLUGIN_FILE', __FILE__);
}

// Include the main WooCommerce Khalti class.
if (!class_exists('WooCommerce_Khalti')) {
    include_once dirname(__FILE__) . '/includes/class-woocommerce-khalti.php';
    include_once dirname(__FILE__) . '/includes/class-woocommerce-khalti-data.php';
}

// Initialize the plugin.
add_action('plugins_loaded', array('WooCommerce_Khalti', 'get_instance'));
add_action('plugins_loaded', array('WooCommerce_Khalti_Data', 'get_instance'));
