<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Registers the cascading delivery-address selector into the WooCommerce block
 * checkout. The script renders the selector (reusing the existing REST cascade
 * endpoints) and pushes the chosen location to the Store API so the existing
 * shipping method can price it. See init.php for the server-side callbacks.
 */
class Avaita_Local_Delivery_Blocks_Integration implements IntegrationInterface
{
    const HANDLE = 'avaita-local-delivery-checkout-block';

    public function get_name()
    {
        return 'avaita-local-delivery';
    }

    public function initialize()
    {
        wp_register_script(
            self::HANDLE,
            AVAITA_RESTRO_URL . '/modules/shipping-methods/local-delivery/assets/js/checkout-block.js',
            array('wc-blocks-checkout', 'wp-element', 'wp-plugins', 'wp-i18n', 'wp-html-entities'),
            AVAITA_RESTRO_VER,
            true
        );

        global $avaita_local_shipping_db;
        $cities = $avaita_local_shipping_db ? $avaita_local_shipping_db->get_all_cities() : array();

        $selection = array('city' => '', 'area' => '', 'sub_area' => '', 'street' => '');
        if (WC()->session) {
            $selection = array(
                'city'     => (string) WC()->session->get('avaita_billing_city'),
                'area'     => (string) WC()->session->get('avaita_billing_area'),
                'sub_area' => (string) WC()->session->get('avaita_billing_sub_area'),
                'street'   => (string) WC()->session->get('avaita_billing_street'),
            );
        }

        wp_localize_script(self::HANDLE, 'AVAITA_DELIVERY_BLOCK', array(
            'apiHost'   => get_rest_url() . AVAITA_API_NAMESPACE,
            'nonce'     => wp_create_nonce('wp_rest'),
            'cities'    => array_values(wp_list_pluck($cities, 'city')),
            'selection' => $selection,
            'labels'    => array(
                'title'    => __('Delivery location', 'avaita-restro'),
                'city'     => __('Select Your City', 'avaita-restro'),
                'area'     => __('Select Your Area', 'avaita-restro'),
                'sub_area' => __('Select Your Sub Area', 'avaita-restro'),
                'street'   => __('Select Your Street', 'avaita-restro'),
            ),
        ));
    }

    public function get_script_handles()
    {
        return array(self::HANDLE);
    }

    public function get_editor_script_handles()
    {
        return array(self::HANDLE);
    }

    public function get_script_data()
    {
        return array();
    }
}
