<?php
require __DIR__  . '/order-metabox.php';
add_filter('avaita_admin_available_tabs', 'avaita_add_shipping_settings_tab', 10, 1);
function avaita_add_shipping_settings_tab($tabs) {
    $tabs[] = array(
        'title' => __('Local Shipping', 'avaita-restro'),
        'id' => 'local-shipping',
    );

    return $tabs;
}

add_action('avaita_admin_page', 'avaita_render_shipping_admin_page', 10, 1);
function avaita_render_shipping_admin_page($tab) {
    if ($tab != 'local-shipping') {
        return;
    }

    require_once __DIR__ . '/../templates/admin-shipping-lists.php'; 
}