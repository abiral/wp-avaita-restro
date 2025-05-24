<?php
/**
 * Plugin Name: Avaita Restro - Order Reminder
 * Description: A plugin to create an order reminder to the customer
 * Author: Abiral Neupane
 * Author URI: https://abrlnp.me
 * Version: 1.0
 * Plugin URI: https://abrlnp.me
 */

if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

require_once __DIR__ . '/includes/class.ava-order-meta.php';
require_once __DIR__ . '/includes/class.ava-product-meta.php';
require_once __DIR__ . '/includes/class.ava-reminder.php';

$nepali_date = new Ava_Restro_Nepali_Date();

new Ava_Restro_Order_Reminder_Order_Meta();
new Ava_Restro_Order_Reminder_Product_Meta();
new Ava_Restro_Order_Reminder($nepali_date);