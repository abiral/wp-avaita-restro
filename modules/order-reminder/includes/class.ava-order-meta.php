<?php

class Ava_Restro_Order_Reminder_Order_Meta
{
    private $nepali_date;

    function __construct()
    {
        $this->nepali_date = new Ava_Restro_Nepali_Date();

        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_meta_to_order_edit_page'), 10, 1);
        add_action('save_post_shop_order', array($this, 'save_meta_value'), 10, 2);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_meta_to_order_list_table'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_meta_content_to_order_list_table'), 20, 2);
        add_action('admin_head', array($this, 'add_admin_css'));
    }

    function add_meta_to_order_edit_page($order)
    {
        $value = get_post_meta($order->get_id(), 'ava_has_order_reminder', true);
        $checked = $value ? 'checked' : '';
        echo '  <div style="clear: both;padding: 15px 0;display: flex;align-items: center;">
            <label for="ava_has_order_reminder" style="margin-right:5px">' . __('Has Order Reminder', 'avaita') . '</label>
            <input type="checkbox" name="ava_has_order_reminder" id="ava_has_order_reminder" value="1" ' . $checked . ' />
        </div>';
    }

    function save_meta_value($order_id, $post)
    {
        if (isset($_POST['ava_has_order_reminder'])) {
            $value = sanitize_text_field($_POST['ava_has_order_reminder']);
            update_post_meta($order_id, 'ava_has_order_reminder', $value);
            $order = wc_get_order($order_id);
            $order_date = $order->order_date;
            $next_date = $this->nepali_date->get_next_nep_year_by_ad(strtotime($order_date));
            update_post_meta($order_id, 'ava_has_order_reminder', true);
            update_post_meta($order_id, 'ava_order_reminder_date', $next_date);
        } else {
            update_post_meta($order_id, 'ava_has_order_reminder', false);
        }
    }





    function add_meta_to_order_list_table($columns)
    {
        $columns['ava_has_order_reminder'] = __('Has Order Reminder', 'avaita');
        return $columns;
    }

    function add_meta_content_to_order_list_table($column, $post_id)
    {
        if ($column == 'order_number') {
            $has_reminder = get_post_meta($post_id, 'ava_has_order_reminder', true);
            echo $has_reminder ? '<span class="dashicons dashicons-bell has-reminder" title="' . __('This order has reminder', 'avaita') . '"></span>' : '';
        }
    }

    function add_admin_css()
    {
        global $pagenow, $typenow;

        if ($pagenow == 'edit.php' && $typenow == 'shop_order') {
            echo '<style>
                .has-reminder { cursor: pointer; line-height: inherit; margin-left: 3px; font-size: 15px;}
            </style>';
        }

        if ($pagenow == 'post.php' && $typenow == 'shop_order') {
        }
    }
}
