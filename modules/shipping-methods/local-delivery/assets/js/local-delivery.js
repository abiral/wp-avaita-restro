+(function () {
    function blockAddressFields(){
        jQuery('#avaita-billing-city').prop('disabled', true);
        jQuery('#avaita-billing-area').prop('disabled', true);
        jQuery('#avaita-billing-city').parents('.avaita-address-finder-wrapper').addClass('loading');
        jQuery('#avaita-billing-area').parents('.avaita-address-finder-wrapper').addClass('loading');
    }

    function unblockAddressFields(){
        jQuery('#avaita-billing-city').prop('disabled', false);
        jQuery('#avaita-billing-area').prop('disabled', false);
        jQuery('#avaita-billing-area').parents('.avaita-address-finder-wrapper').removeClass('loading');
        jQuery('#avaita-billing-city').parents('.avaita-address-finder-wrapper').removeClass('loading');
    }

    function fetchAreaBySelectedCity($el = null) {
        blockAddressFields() 
        city = $el ? $el.val() : jQuery('#avaita-billing-city').val();
        if (!city) {
            unblockAddressFields();
            return;
        }
        fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/areas?city=' + city)
        .then((response) => response.json())
        .then((response) => {
            if (response && response.length > 0) {
                const options = response.map(function(data){
                    return new Option(data.area, encodeURIComponent(data.area), false, false);
                });
                jQuery('#billing_city').val(city);
                jQuery('#avaita-billing-area').html(options).trigger('change');
            }
            unblockAddressFields();
        });
        return city;
    }

    jQuery(document).ready(function () {
        jQuery('#avaita-billing-city_field').before('<h3>Delivery Details</h3>');
        city = jQuery('#avaita-billing-city').val();
        area = jQuery('#avaita-billing-area').val();

        jQuery('.avaita-address-finder-wrapper').append('<div class="spinner"></div>');

        jQuery('#avaita-billing-city').select2();
        jQuery('#avaita-billing-area').select2();
       
        jQuery(document).on('change', '#avaita-billing-city', function(event) {
            city = fetchAreaBySelectedCity(jQuery(event.target));

            const cityText = jQuery(event.target).find('option:selected').text();
            
            jQuery('#shipping_city').val(cityText);
        });

        jQuery(document).on('change', '#avaita-billing-area', function(event) {
            blockAddressFields();
            const areaValue = jQuery(event.target).val();
            const area = jQuery(event.target).find('option:selected').text();

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/area-details?city=' + city + '&area=' + areaValue)
            .then((response) => response.json())
            .then((response) => {
                if (response) {
                    if (jQuery('#avaita-address-finder-line1').length === 0) {
                        jQuery(event.target).parents('.avaita-address-finder-wrapper').after(AVAITA_DELIVERY_VARS.billing_line1_html)
                    }

                    jQuery('#billing_address_1').val(area);
                    jQuery('#shipping_address_1').val(area);
                    
                    jQuery('body').trigger('wc_fragment_refresh');
                    jQuery('body').trigger('update_checkout');
                    unblockAddressFields();
                }
                unblockAddressFields();
            });
        });


        jQuery(document).on('input', '#avaita-address-finder-line1', function(event) {
            jQuery('#billing_address_2').val(jQuery(event.target).val());
            jQuery('#shipping_address_2').val(jQuery(event.target).val());
        });

        if (city && area) {
            jQuery('#avaita-billing-area').trigger('change');
        } else if (city) {
            jQuery('#avaita-billing-city').trigger('change');
        }
    });
})();




