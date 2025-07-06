<?php

/**
 * Plugin Name: Avaita Restro
 * Description: A plugin that creates a site as a server to POS
 * Author: Avaita Softwares
 * Author URI: https://avaitasoftwares.biz
 * Version: 0.2
 * Plugin URI: https://avaitasoftwares.biz
 */

define('AVAITA_RESTRO_VERSION', '0.2');
define('AVAITA_RESTRO_DIR', __DIR__);
define('AVAITA_RESTRO_MODULES_DIR', AVAITA_RESTRO_DIR . '/modules');
define('AVAITA_RESTRO_URL', plugin_dir_url(__FILE__));
define('AVAITA_API_NAMESPACE', 'avaita');

require_once AVAITA_RESTRO_DIR . '/includes/class.ava-nepali-date.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/order-reminder/init.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/shipping-methods/init.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/tools/init.php';
require_once AVAITA_RESTRO_MODULES_DIR . '/admin/init.php';
require_once AVAITA_RESTRO_DIR . '/includes/hooks.php';
// require_once AVAITA_RESTRO_DIR . '/api/init.php';

register_activation_hook(__FILE__, function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'WooCommerce is required for Avaita Restro.' );
    }
    do_action('avaita_plugin_activated');
});