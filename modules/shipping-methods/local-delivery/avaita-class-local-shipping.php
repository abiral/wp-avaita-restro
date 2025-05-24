<?php
class Avaita_Local_Shipping_Method extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'avaita_local_shipping_method';
        $this->instance_id        = absint($instance_id);
        $this->method_title = esc_html__('Local Shipping', 'avaita');
        $this->method_description = esc_html__('Local WooCommerce Shipping', 'avaita');
        $this->init();
    }

    public function init()
    {
        $this->init_settings();
        $this->init_form_fields();
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'avaita'),
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable this shipping method', 'avaita'),
            ),
            'title' => array(
                'title'       => esc_html__('Method Title', 'avaita'),
                'type'        => 'text',
                'description' => esc_html__('Enter the method title', 'avaita'),
                'default'     => esc_html__('Local Delivery', 'avaita'),
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'avaita'),
                'type'        => 'textarea',
                'description' => esc_html__('Enter the Description', 'avaita'),
            ),
        );
        $this->form_fields = $form_fields;
    }

    public function calculate_shipping($package = array())
    {
        $city = WC()->session->get('avaita_billing_city');
        $area = WC()->session->get('avaita_billing_area');

        if ($city && $area) {
            $delivery_price = WC()->session->get('avaita_delivery_price');
            $address = WC()->session->get('avaita_formatted_address');

            $cart_total = WC()->cart->get_cart_contents_total();

            // $order_threshold = WC()->session->get('avaita_minimum_order_threshold');
            $free_threshold = WC()->session->get('avaita_minimum_free_deliery');

            $label = $this->settings['title'];

            if ($cart_total > $free_threshold) {
                $label = __('Free', 'avaita');
                $delivery_price = 0;
            }

            $this->add_rate(array(
                'id'     => $this->id,
                'label'  => $label,
                'cost'   => $delivery_price,
                'meta_data' => array('avaita_minimum_free_deliery' => $free_threshold)
            ));
        }
    }
}


// add_filter('woocommerce_billing_fields', 'vitamin_override_billing_fields', 10);
// function vitamin_override_billing_fields($fields)
// {
//     unset($fields['billing_state']);
//     $fields['billing_delivery_area'] = vitamin_get_delivery_area_field();
//     return $fields;
// }

// add_filter('woocommerce_checkout_fields', 'vitamin_override_checkout_fields');
// function vitamin_override_checkout_fields($fields)
// {
//     $fields['billing']['billing_delivery_area'] = vitamin_get_delivery_area_field();
//     return $fields;
// }


// function vitamin_get_delivery_area_field()
// {
//     return array(
//         'type' => 'select',
//         'default' => 'Sukhanagar',
//         'required' => true,
//         'label' => __('Delivery Area', 'vitamin'),
//         'options' => vitamin_get_delivery_areas(),
//     );
// }

// function vitamin_get_delivery_areas()
// {
//     return array(
//         'Adarshanagar' => __('Adarshanagar', 'vitamin'),
//         'Amar Path, Butwal' => __('Amar Path, Butwal', 'vitamin'),
//         'Bamghat' => __('Bamghat', 'vitamin'),
//         'Banbatika' => __('Banbatika', 'vitamin'),
//         'Banijya Campus' => __('Banijya Campus', 'vitamin'),
//         'Basecamp' => __('Basecamp', 'vitamin'),
//         'Basecamp Gumba' => __('Basecamp Gumba', 'vitamin'),
//         'Belbas' => __('Belbas', 'vitamin'),
//         'Bhaluhi' => __('Bhaluhi', 'vitamin'),
//         'Bhudki Chowk Area' => __('Bhudki Chowk Area', 'vitamin'),
//         'Buddanagar' => __('Buddanagar', 'vitamin'),
//         'Butwal Bhatbhateni side' => __('Butwal Bhatbhateni side', 'vitamin'),
//         'Chamcham Chowk' => __('Chamcham Chowk', 'vitamin'),
//         'Chandbari' => __('Chandbari', 'vitamin'),
//         'Deepnagar' => __('Deepnagar', 'vitamin'),
//         'Deepnagar Amda Hospital' => __('Deepnagar Amda Hospital', 'vitamin'),
//         'Deepnagar line' => __('Deepnagar line', 'vitamin'),
//         'Delta Supermarket Side' => __('Delta Supermarket Side', 'vitamin'),
//         'Devinagar Chaupari' => __('Devinagar Chaupari', 'vitamin'),
//         'Devsiddha Chowk' => __('Devsiddha Chowk', 'vitamin'),
//         'Dharm Path' => __('Dharm Path', 'vitamin'),
//         'Drivertole' => __('Drivertole', 'vitamin'),
//         'Farsatikar chowk' => __('Farsatikar chowk', 'vitamin'),
//         'Fulbari' => __('Fulbari', 'vitamin'),
//         'GBC Hospital Side' => __('GBC Hospital Side', 'vitamin'),
//         'Girichowk' => __('Girichowk', 'vitamin'),
//         'Golpark' => __('Golpark', 'vitamin'),
//         'Hatbajar line' => __('Hatbajar line', 'vitamin'),
//         'Hillpark area' => __('Hillpark area', 'vitamin'),
//         'Horizan chowk' => __('Horizan chowk', 'vitamin'),
//         'Itabhatti, Butwal' => __('Itabhatti, Butwal', 'vitamin'),
//         'Janakinagar' => __('Janakinagar', 'vitamin'),
//         'Jholunge Pul' => __('Jholunge Pul', 'vitamin'),
//         'Jitagadhi' => __('Jitagadhi', 'vitamin'),
//         'Jyotinagar' => __('Jyotinagar', 'vitamin'),
//         'Kalika Manav Gyaan' => __('Kalika Manav Gyaan', 'vitamin'),
//         'Kalika Path' => __('Kalika Path', 'vitamin'),
//         'Kalikachowk' => __('Kalikachowk', 'vitamin'),
//         'Kalikanagar' => __('Kalikanagar', 'vitamin'),
//         'Laxminagar' => __('Laxminagar', 'vitamin'),
//         'Laxminagar Gate' => __('Laxminagar Gate', 'vitamin'),
//         'Lumbini Provience Hospital' => __('Lumbini Provience Hospital', 'vitamin'),
//         'Magarghat' => __('Magarghat', 'vitamin'),
//         'Mainabagar' => __('Mainabagar', 'vitamin'),
//         'Maitripath, Butwal' => __('Maitripath, Butwal', 'vitamin'),
//         'Majhgau' => __('Majhgau', 'vitamin'),
//         'Mangalapur' => __('Mangalapur', 'vitamin'),
//         'Manigram' => __('Manigram', 'vitamin'),
//         'Manigram 4 number' => __('Manigram 4 number', 'vitamin'),
//         'Milanchowk' => __('Milanchowk', 'vitamin'),
//         'Motipur' => __('Motipur', 'vitamin'),
//         'Mutu hospital Area' => __('Mutu hospital Area', 'vitamin'),
//         'Naharpur' => __('Naharpur', 'vitamin'),
//         'Naya Basapark' => __('Naya Basapark', 'vitamin'),
//         'Nayagau' => __('Nayagau', 'vitamin'),
//         'Nayamil' => __('Nayamil', 'vitamin'),
//         'Opposite to Himalayan bank' => __('Opposite to Himalayan bank', 'vitamin'),
//         'Pashchimanchal Finance' => __('Pashchimanchal Finance', 'vitamin'),
//         'Purano Buspark' => __('Purano Buspark', 'vitamin'),
//         'Pushpalal Park Area' => __('Pushpalal Park Area', 'vitamin'),
//         'Rajamarga Chauraha' => __('Rajamarga Chauraha', 'vitamin'),
//         'Rangashala Marga' => __('Rangashala Marga', 'vitamin'),
//         'Semlaar' => __('Semlaar', 'vitamin'),
//         'Shankarnagar' => __('Shankarnagar', 'vitamin'),
//         'Shantichowk' => __('Shantichowk', 'vitamin'),
//         'Shrawan path' => __('Shrawan path', 'vitamin'),
//         'Shukrapath' => __('Shukrapath', 'vitamin'),
//         'Siddababa' => __('Siddababa', 'vitamin'),
//         'Stadium Area' => __('Stadium Area', 'vitamin'),
//         'Sukhanagar' => __('Sukhanagar', 'vitamin'),
//         'Talim kendra area' => __('Talim kendra area', 'vitamin'),
//         'Tamnagar' => __('Tamnagar', 'vitamin'),
//         'Tapaha line' => __('Tapaha line', 'vitamin'),
//         'Tinkune, Butwal' => __('Tinkune, Butwal', 'vitamin'),
//         'Traffic chowk' => __('Traffic chowk', 'vitamin'),
//         'Yogikuti' => __('Yogikuti', 'vitamin'),
//         'Others' => __('Others', 'vitamin'),
//     );
// }

// function vitamin_get_delivery_charge($key)
// {
//     $array = array(
//         'adarshanagar' => 0,
//         'amar_path_butwal' => 0,
//         'bamghat' => 50,
//         'banbatika' => 75,
//         'banijya_campus' => 0,
//         'basecamp' => 0,
//         'basecamp_gumba' => 0,
//         'belbas' => 50,
//         'bhaluhi' => 100,
//         'bhudki_chowk_area' => 0,
//         'buddanagar' => 50,
//         'butwal_bhatbhateni_side' => 0,
//         'chamcham_chowk' => 0,
//         'chandbari' => 0,
//         'deepnagar' => 0,
//         'deepnaga_amd_hospital' => 0,
//         'deepnagar_line' => 0,
//         'delta_supermarket_side' => 0,
//         'devinagar_chaupari' => 0,
//         'devsiddh_chowk' => 50,
//         'dharm_path' => 0,
//         'drivertole' => 100,
//         'farsatikar_chowk' => 200,
//         'fulbari' => 75,
//         'gbc_hospital_side' => 0,
//         'girichowk' => 0,
//         'golpark' => 50,
//         'hatbajar_line' => 40,
//         'hillpark_area' => 0,
//         'horizan_chowk' => 0,
//         'itabhatti_butwal' => 0,
//         'janakinagar' => 50,
//         'jholunge_pul' => 75,
//         'jitagadhi' => 50,
//         'jyotinagar' => 75,
//         'kalika_manav_gyaan' => 0,
//         'kalika_path' => 0,
//         'kalikachowk' => 0,
//         'kalikanagar' => 0,
//         'laxminagar' => 50,
//         'laxminagar_gate' => 50,
//         'lumbini_provience_hospital' => 0,
//         'magarghat' => 75,
//         'mainabagar' => 50,
//         'maitripath_butwal' => 0,
//         'majhgau' => 50,
//         'mangalapur' => 120,
//         'manigram' => 100,
//         'manigram_4_number' => 100,
//         'milanchowk' => 0,
//         'motipur' => 100,
//         'mutu_hospita_area' => 0,
//         'naharpur' => 75,
//         'naya_basapark' => 0,
//         'nayagau' => 75,
//         'nayamil' => 75,
//         'opposite_to_himalayan_bank' => 0,
//         'pashchimanchal_finance' => 0,
//         'purano_buspark' => 50,
//         'pushpalal_park area' => 0,
//         'rajamarga_chauraha' => 0,
//         'rangashala_marga' => 50,
//         'semlaar' => 100,
//         'shankarnagar' => 50,
//         'shantichowk' => 50,
//         'shrawan_path' => 0,
//         'shukrapath' => 0,
//         'siddababa' => 75,
//         'stadium_area' => 50,
//         'sukhanagar' => 0,
//         'talim_kendra_area' => 0,
//         'tamnagar' => 100,
//         'tapaha_line' => 50,
//         'tinkune_butwal' => 0,
//         'traffic_chowk' => 0,
//         'yogikuti' => 50,
//     );

//     if (isset($array[$key])) {
//         return $array[$key];
//     }

//     return 200;
// }

// function vitamin_get_delivery_min_order($key)
// {
//     $array = array(
//         'adarshanagar' => 300,
//         'amar_path_butwal' => 300,
//         'bamghat' => 400,
//         'banbatika' => 500,
//         'banijya_campus' => 300,
//         'basecamp' => 300,
//         'basecamp_gumba' => 300,
//         'belbas' => 500,
//         'bhaluhi' => 700,
//         'bhudki_chowk_area' => 300,
//         'buddanagar' => 500,
//         'butwal_bhatbhateni_side' => 300,
//         'chamcham_chowk' => 300,
//         'chandbari' => 300,
//         'deepnagar' => 300,
//         'deepnaga_amd_hospital' => 300,
//         'deepnagar_line' => 300,
//         'delta_supermarket_side' => 300,
//         'devinagar_chaupari' => 300,
//         'devsiddh_chowk' => 500,
//         'dharm_path' => 300,
//         'drivertole' => 700,
//         'farsatikar_chowk' => 1200,
//         'fulbari' => 500,
//         'gbc_hospital_side' => 300,
//         'girichowk' => 300,
//         'golpark' => 400,
//         'hatbajar_line' => 350,
//         'hillpark_area' => 300,
//         'horizan_chowk' => 300,
//         'itabhatti_butwal' => 300,
//         'janakinagar' => 400,
//         'jholunge_pul' => 500,
//         'jitagadhi' => 400,
//         'jyotinagar' => 500,
//         'kalika_manav_gyaan' => 300,
//         'kalika_path' => 300,
//         'kalikachowk' => 300,
//         'kalikanagar' => 300,
//         'laxminagar' => 500,
//         'laxminagar_gate' => 500,
//         'lumbini_provience_hospital' => 300,
//         'magarghat' => 500,
//         'mainabagar' => 400,
//         'maitripath_butwal' => 300,
//         'majhgau' => 400,
//         'mangalapur' => 800,
//         'manigram' => 700,
//         'manigram_4_number' => 700,
//         'milanchowk' => 300,
//         'motipur' => 700,
//         'mutu_hospita_area' => 300,
//         'naharpur' => 500,
//         'naya_basapark' => 300,
//         'nayagau' => 500,
//         'nayamil' => 500,
//         'opposite_to_himalayan_bank' => 300,
//         'pashchimanchal_finance' => 300,
//         'purano_buspark' => 400,
//         'pushpalal_park area' => 300,
//         'rajamarga_chauraha' => 300,
//         'rangashala_marga' => 400,
//         'semlaar' => 700,
//         'shankarnagar' => 400,
//         'shantichowk' => 400,
//         'shrawan_path' => 300,
//         'shukrapath' => 300,
//         'siddababa' => 500,
//         'stadium_area' => 400,
//         'sukhanagar' => 300,
//         'talim_kendra_area' => 300,
//         'tamnagar' => 700,
//         'tapaha_line' => 400,
//         'tinkune_butwal' => 300,
//         'traffic_chowk' => 300,
//         'yogikuti' => 400,
//     );

//     if (isset($array[$key])) {
//         return $array[$key];
//     }

//     return 1200;
// }

// function vitamin_get_area_key($area)
// {
//     return str_replace(' ', '_', preg_replace("/(?![.=$'€%-])\p{P}/u", "", strtolower($area)));
// }



// add_action('woocommerce_cart_calculate_fees', 'vitamin_calculate_cart_fees');
// function vitamin_calculate_cart_fees()
// {
//     if (is_admin() && !defined('DOING_AJAX')) {
//         return;
//     }

//     if (!isset($_POST['post_data'])) {
//         return;
//     }

//     parse_str($_POST['post_data'], $formdata);

//     $delivery_area =  $formdata['billing_delivery_area'];
//     if (!$delivery_area) {
//         return '';
//     }

//     $service_area = vitamin_get_area_key($delivery_area);

//     $delivery_charge = vitamin_get_delivery_charge($service_area);
//     $min_order = vitamin_get_delivery_min_order($service_area);

//     $cart_total = WC()->cart->get_cart_contents_total();

//     if ($cart_total < $min_order) {

//         if ($delivery_charge == 0) {
//             $delivery_charge = __('Free', 'vitamin');
//         }

//         WC()->cart->add_fee(__('Delivery Charge', 'vitamin'), $delivery_charge);
//     }
// }



// add_filter('woocommerce_admin_billing_fields', 'vitamin_add_admin_billing_fields');
// function vitamin_add_admin_billing_fields($fields)
// {
//     $fields['billing_delivery_area'] = array(
//         'type' => 'select',
//         'default' => 'Sukhanagar',
//         'required' => true,
//         'label' => __('Delivery Area', 'vitamin'),
//         'options' => vitamin_get_delivery_areas(),
//         'show'  => true,
//     );

//     return $fields;
// }