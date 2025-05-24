<?php

class Ava_Restro_Order_Reminder {
    private $nepali_date;

    function __construct($date){
        $this->nepali_date = $date;
        add_action( 'wp_insert_post', array($this, 'handle_order_create'), 10, 3 );
        add_action('init', array($this, 'schedule_daily_event'));
        add_action('daily_reminder_event', array($this, 'send_order_reminder'));
    }
    
    function schedule_daily_event() {
        if (!wp_next_scheduled('daily_reminder_event')) {
            wp_schedule_event(time(), 'daily', 'daily_reminder_event');
        }
    }


    function send_order_reminder() {
        $this->remind_order();
    }

    function handle_order_create( $post_id, $post, $update ) {

        if ( ! $post_id || get_post_type( $post_id ) != 'shop_order' || $update == 1 ) {
            return;
        }

        $this->set_order_reminder($post_id);    
    
    }

    private function set_order_reminder($order_id) {
        $order = wc_get_order( $order_id );
        if ( !$order || !$order->has_status( 'processing' ) ){
            return;
        }

        $order_date = $order->order_date;
        $next_date = $this->nepali_date->get_next_nep_year_by_ad(strtotime($order_date));
        
        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = $item->get_product_id();
            $remindable = get_post_meta( $product_id, 'mark_product_as_remindable', true );
            if ($remindable) {
                update_post_meta( $order_id, 'ava_has_order_reminder', true);
                update_post_meta( $order_id, 'ava_order_reminder_date', $next_date);
                break;
            }
        }
    }

    function remind_order() {
        global $wpdb;
        $today = date('Y-m-d');
        
        $sql = 'SELECT pm1.`post_id` AS `order_id`, pm1.`meta_value` AS `reminder_date` FROM ' . $wpdb->postmeta . ' pm1 INNER JOIN ' . $wpdb->postmeta . ' pm2 ON pm1.post_id = pm2.post_id WHERE pm1.`meta_key` = "ava_order_reminder_date" AND pm1.`meta_value` = "'.$today.'" AND pm2.`meta_key` = "ava_has_order_reminder" AND pm2.`meta_value` = 1;';
        $remindable_orders = $wpdb->get_results($sql, ARRAY_A);
        if ($remindable_orders) {
            foreach($remindable_orders as $order_data) {
                $order = wc_get_order( $order_data['order_id'] );
                $user_id = $order->get_customer_id();
                /* 
                    $first_name = $order->get_billing_first_name();
                    $last_name  = $order->get_billing_last_name();
                    $full_name = trim(implode(' ', array($first_name, $last_name)));

                    if(!$full_name) {
                        $full_name = 'Customer';
                    }

                    $message = 'Namaste '.$full_name.' ! Wow, it\'s been a year since your purchase. Thank you for your support! We truly value your business. If you ever need anything, we\'re here for you. Drop by or give us a call. Looking forward to serving you again soon!';
                */
                $message = 'नमस्ते ! आज हजुरको कोहि मित्र या आफन्त को उत्सवमय दिन भयेको हुदा यो अहम घडिमा गुणस्तरिय (केक/ गिफ्ट / नास्ता) डेलिभरि या स्र्प्राइज सुबिधा लिनको लागि तू सम्पर्क गर्नुहोस :  VitaminCakes : 071537313';

                do_action ('sparrow_send_user_message', $user_id, $message);
                update_post_meta( $order_data['order_id'], 'ava_has_order_reminder', 0);
            }
        }
    }
}