<?php
/**
 * Order Fee Item HTML Template
 * Used for AJAX response when adding extra fees
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$order = wc_get_order($order_id);
$item = $order->get_item($item_id);

if (!$item) {
    return;
}

$item_name = $item->get_name();
$item_total = $item->get_total();
?>

<tr class="fee" data-order_item_id="<?php echo esc_attr($item_id); ?>">
    <td class="thumb">
        <div></div>
    </td>
    <td class="name">
        <div class="view">
            <?php echo esc_html($item_name); ?>
        </div>
        <div class="edit" style="display:none;">
            <input type="text" name="order_item_name[<?php echo $item_id; ?>]" placeholder="<?php esc_attr_e('Fee name', 'woocommerce'); ?>" value="<?php echo esc_attr($item_name); ?>" />
        </div>
    </td>
    <td class="item_cost" width="1%">
        <div class="view">
            <?php echo wc_price($item_total); ?>
        </div>
        <div class="edit" style="display:none;">
            <input type="text" name="line_total[<?php echo $item_id; ?>]" placeholder="0.00" value="<?php echo esc_attr($item_total); ?>" class="line_total wc_input_price" />
        </div>
    </td>
    <td class="line_cost" width="1%">
        <div class="view">
            <?php echo wc_price($item_total); ?>
        </div>
    </td>
    <td class="wc-order-edit-line-item" width="1%">
        <div class="wc-order-edit-line-item-actions">
            <a class="edit-order-item tips" href="#" data-tip="<?php esc_attr_e('Edit item', 'woocommerce'); ?>"></a>
            <a class="delete-order-item tips" href="#" data-tip="<?php esc_attr_e('Delete item', 'woocommerce'); ?>"></a>
        </div>
    </td>
</tr>