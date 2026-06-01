<?php

class Ava_Restro_Admin {
    private $base_url;

    private $page_param;

    private $available_tabs;

    private $active_tab;

    function __construct() {
        $this->base_url = admin_url('admin.php');
        $this->page_param = array('page' => 'ava-restro');
        $tabs = apply_filters('avaita_admin_available_tabs', array());

        if (count($tabs) > 0) {
            foreach ($tabs as $tab) {
                $this->available_tabs[$tab['id']] = array(
                    'title' => $tab['title'],
                    'url' => $this->get_tab_url($tab['id']),
                );
            }
        }
        // array(
        //     'general' => array(
        //         'title' => __('General', 'ava-restro'),
        //         'url' => $this->get_tab_url('general'),
        //     ),
        //     'order_reminder' => array(
        //         'title' => __('Order Reminder', 'ava-restro'),
        //         'url' => $this->get_tab_url('order_reminder'),
        //     ),
        //     'payment_gateway' => array(
        //         'title' => __('Payment Gateway', 'ava-restro'),
        //         'url' => $this->get_tab_url('payment_gateway'),
        //     ),
        // );

        $default_tab = array_keys($this->available_tabs)[0];
        $tab = isset($_GET['tab']) && $_GET['tab'] ? $_GET['tab'] : $default_tab;
        $this->active_tab = in_array($tab, array_keys($this->available_tabs)) ? $tab : $default_tab;

        add_action('admin_menu', array($this, 'register_settings_page'));
        // add_action('admin_init', array($this, 'initialize_settings_page'));
    }

    function register_settings_page() {
        add_menu_page(
            __('Avaita Restro', 'avaita-restro'),
            __('Avaita Restro', 'avaita-restro'),
            'manage_options',
            $this->page_param['page'],
            array($this, 'render_settings_page_content'),
            $this->get_menu_icon()
        );

        // Surface the delivery areas screen as an explicit submenu. A DISTINCT
        // slug is used (not the parent slug) so WordPress keeps both the auto
        // parent link and this item — a lone submenu sharing the parent slug is
        // not surfaced (see add_submenu_page(): the auto parent link is only
        // added when $menu_slug !== $parent_slug). The asset enqueue in
        // order-address-fields.php matches this slug too.
        add_submenu_page(
            $this->page_param['page'],
            __('Delivery Areas', 'avaita-restro'),
            __('Delivery Areas', 'avaita-restro'),
            'manage_options',
            'ava-restro-delivery-areas',
            array($this, 'render_settings_page_content')
        );

        // Drop the auto-generated first submenu item that duplicates the parent
        // ("Avaita Restro"), leaving just "Delivery Areas". The top-level link
        // then falls through to the remaining submenu page.
        remove_submenu_page($this->page_param['page'], $this->page_param['page']);
    }

    /**
     * Custom menu icon. Returns the bundled restro.svg as a base64 data URI so
     * it renders at the standard 20px menu-icon size (like a dashicon); falls
     * back to a built-in dashicon if the file is missing.
     */
    private function get_menu_icon() {
        $icon_path = __DIR__ . '/../../assets/icons/restro.svg';
        if (is_readable($icon_path)) {
            $svg = file_get_contents($icon_path);
            if ($svg !== false) {
                return 'data:image/svg+xml;base64,' . base64_encode($svg);
            }
        }
        return 'dashicons-store';
    }

    // function initialize_settings_page() {
    //     $title = $this->available_tabs[$this->active_tab]['title']; 
    //     add_settings_section( 'ava_restro_'.$this->active_tab, $title, null, $this->page_param['page'] );
    //     add_settings_field('ava_restro_' .$this->active_tab, null, array($this, 'ava_restro_settings_callback'), $this->page_param['page'], 'ava_restro_' . $this->active_tab);
    //     register_setting($this->page_param['page'], 'ava_restro_'. $this->active_tab);
    // }

    function render_settings_page_content() {
        $tabs = $this->available_tabs;
        $active_tab = $this->active_tab;
        require_once __DIR__ . '/templates/admin-page.php';
    }

    // function ava_restro_settings_callback() {
    //     $input = get_option('ava_restro_' . $this->active_tab, '');
    //     require_once __DIR__ . '/templates/' . str_replace('_', '-', $this->active_tab) . '-settings.php';
    // }

    private function get_tab_url($tab) {
        $this->page_param['tab'] = $tab;
        return $this->base_url . '?' . build_query($this->page_param);
    }
}

new Ava_Restro_Admin();