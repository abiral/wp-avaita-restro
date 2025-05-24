<?php

class Ava_Restro_Order_Reminder_Product_Meta
{
    function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_input_meta'));
        add_action('save_post_product', array($this, 'save_meta_value'), 10, 2);
    }

    function add_input_meta()
    {
        woocommerce_wp_checkbox(array(
            'id'      => 'mark_product_as_remindable',
            'label'   => __('Remindable', 'avaita'),
            'description' => __('Checking this will make sure customer get reminder notification', 'avaita'),
        ));
    }

    function save_meta_value($product_id, $post)
    {
        if (isset($_POST['mark_product_as_remindable'])) {
            $value = sanitize_text_field($_POST['mark_product_as_remindable']);
            update_post_meta($product_id, 'mark_product_as_remindable', $value);
        }
    }
}
