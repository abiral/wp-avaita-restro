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
            array($this, 'render_settings_page_content')
        );
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