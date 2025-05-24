<?php
/**
 * Shows a shipping line
 *
 * @package WooCommerce\Admin
 *
 * @var object $item The item being displayed
 * @var int $item_id The id of the item being displayed
 *
 * @package WooCommerce\Admin\Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<tr id="avaita-local-shipping-item" data-type="avaita-local-shipping-item" class="avaita-local-shipping-item shipping <?php echo ( ! empty( $class ) ) ? esc_attr( $class ) : ''; ?>" data-order_item_id="<?php echo esc_attr( $item_id ); ?>">
	<td class="thumb"><div></div></td>

	<td class="name">
		<div class="view">
			<?php echo esc_html( $item->get_name() ? $item->get_name() : __( 'Shipping', 'woocommerce' ) ); ?>
		</div>
		<div class="edit" style="display: none;">
			<input type="hidden" name="shipping_method_id[]" value="<?php echo esc_attr( $item_id ); ?>" />
			<input type="text" class="shipping_method_name" placeholder="<?php esc_attr_e( 'Shipping name', 'woocommerce' ); ?>" name="shipping_method_title[<?php echo esc_attr( $item_id ); ?>]" value="<?php echo esc_attr( $item->get_name() ); ?>" />
		</div>
	</td>

	<?php do_action( 'woocommerce_admin_order_item_values', null, $item, absint( $item_id ) ); ?>

	<td class="item_cost" width="1%">&nbsp;</td>
	<td class="quantity" width="1%">&nbsp;</td>

	<td class="line_cost" width="1%">
		<div class="view">
			<?php
			echo wp_kses_post( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) );
			?>
            <input type="hidden" name="shipping_cost[<?php echo esc_attr( $item_id ); ?>]" placeholder="<?php echo esc_attr( wc_format_localized_price( 0 ) ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $item->get_total() ) ); ?>" class="line_total wc_input_price" />
		</div>
	</td>
	<td class="wc-order-edit-line-item">
		<?php if ( $order->is_editable() ) : ?>
			<div class="wc-order-edit-line-item-actions">
				<a class="delete-order-item tips" href="#" data-tip="<?php esc_attr_e( 'Delete shipping', 'woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Delete shipping', 'woocommerce' ); ?>"></a>
			</div>
		<?php endif; ?>
	</td>
</tr>
