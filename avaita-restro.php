<?php

/**
 * Plugin Name: Avaita Restro
 * Description: A plugin that creates a site as a server to POS
 * Author: Avaita Softwares
 * Author URI: https://avaitasoftwares.biz
 * Version: 1.2
 * Plugin URI: https://avaitasoftwares.biz
 */

define('AVAITA_RESTRO_DIR', __DIR__);
define('AVAITA_RESTRO_MODULES_DIR', AVAITA_RESTRO_DIR . '/modules');
define('AVAITA_RESTRO_URL', plugin_dir_url(__FILE__));
define('AVAITA_API_NAMESPACE', 'avaita');
define('AVAITA_RESTRO_VER', '1.1');

require_once AVAITA_RESTRO_DIR . '/includes/class.ava-nepali-date.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/order-reminder/init.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/shipping-methods/init.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/tools/init.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/admin/init.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/api/init.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/api/endpoints/init.php';
require_once AVAITA_RESTRO_DIR . '/includes/hooks.php';

register_activation_hook(__FILE__, function () {
    do_action('avaita_plugin_activated');
});

add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        exit(0);
    }
});