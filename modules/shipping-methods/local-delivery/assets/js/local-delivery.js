+(function () {
    function enableCityInput(enable=false) {
        if(!enable) {
            jQuery('#avaita-billing-city').prop('disabled', true);
            jQuery('#avaita-billing-city').parents('.avaita-address-finder-wrapper').addClass('loading');
        } else {
            jQuery('#avaita-billing-city').prop('disabled', false);
            jQuery('#avaita-billing-city').parents('.avaita-address-finder-wrapper').removeClass('loading');
        
        }
    }

    function enableAreaInput(enable=false) {
        if(!enable) {
            jQuery('#avaita-billing-area').prop('disabled', true);
            jQuery('#avaita-billing-area').parents('.avaita-address-finder-wrapper').addClass('loading');
        } else {
            jQuery('#avaita-billing-area').prop('disabled', false);
            jQuery('#avaita-billing-area').parents('.avaita-address-finder-wrapper').removeClass('loading');
        }
    }

    function enableSubAreaInput(enable=false) {
        if(!enable) {
            jQuery('#avaita-billing-subarea').prop('disabled', true);
            jQuery('#avaita-billing-subarea').parents('.avaita-address-finder-wrapper').addClass('loading');
        } else {
            jQuery('#avaita-billing-subarea').prop('disabled', false);
            jQuery('#avaita-billing-subarea').parents('.avaita-address-finder-wrapper').removeClass('loading');
        }
    }

    function fetchAreaBySelectedCity($el = null) {
        city = $el ? $el.val() : jQuery('#avaita-billing-city').val();
        if (city) {
            enableCityInput(false);
            enableAreaInput(false);
            enableSubAreaInput(false);

            resetAreaInput();
            resetSubAreaInput();
            
            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/areas?city=' + city)
            .then((response) => response.json())
            .then((response) => {
                if (response && response.length > 0) {
                    const data = jQuery('#avaita-billing-area').data("first_option");
                    const options = response.map(function(data){
                        return new Option(data.area, encodeURIComponent(data.area), false, false);
                    });
                    jQuery('#billing_city').val(city);
                    jQuery('#avaita-billing-area').html([...[new Option(Object.values(data)[0], "", false, false)], ...options]); //.trigger('change');
                }
                enableCityInput(true);
                enableAreaInput(true);
                enableSubAreaInput(true);
            });
        }

        return city;
    }

    function fetchSubAreaBySelectedArea($el = null) {
        area = $el ? $el.val() : jQuery('#avaita-billing-area').val();
        if (area) {
            resetSubAreaInput();
            enableAreaInput(false);
            enableSubAreaInput(false);
            
            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/subareas?city=' + city + '&area=' + area)
            .then((response) => response.json())
            .then((response) => {
                if (response && response.length > 0) {
                    const data = jQuery('#avaita-billing-subarea').data("first_option");
                    const options = response.map(function(data){
                        return new Option(data.subarea, encodeURIComponent(data.subarea), false, false);
                    });
                    jQuery('#avaita-billing-area').val(area);
                    jQuery('#avaita-billing-subarea').html([...[new Option(Object.values(data)[0], "", false, false)], ...options]); //.trigger('change');
                }
                enableAreaInput(true);
                enableSubAreaInput(true);
            });
        }
        return area;
    }

    function resetAreaInput() {
        const data = jQuery('#avaita-billing-area').data("reset_option");
        const options = [new Option(Object.values(data)[0], "", false, false)];
        jQuery('#avaita-billing-area').html(options);
    }

    function resetSubAreaInput() {
        const data = jQuery('#avaita-billing-subarea').data("reset_option");
        const options = [new Option(Object.values(data)[0], "", false, false)];
        jQuery('#avaita-billing-subarea').html(options);
    }

    jQuery(document).ready(function () {
        jQuery('#avaita-billing-city_field').before('<h3>Delivery Details</h3>');
        city = jQuery('#avaita-billing-city').val();
        area = jQuery('#avaita-billing-area').val();
        subarea = jQuery('#avaita-billing-subarea').val();

        jQuery('.avaita-address-finder-wrapper').append('<div class="spinner"></div>');

        jQuery('#avaita-billing-city').select2();
        jQuery('#avaita-billing-area').select2();
        jQuery('#avaita-billing-subarea').select2();
       
        jQuery(document).on('change', '#avaita-billing-city', function(event) {
            city = fetchAreaBySelectedCity(jQuery(event.target));

            const cityText = jQuery(event.target).find('option:selected').text();
            
            jQuery('#shipping_city').val(cityText);
        });

        jQuery(document).on('change', '#avaita-billing-area', function(event) {
            area = fetchSubAreaBySelectedArea(jQuery(event.target));
        });

        jQuery(document).on('change', '#avaita-billing-subarea', function(event) {
            enableCityInput(false);
            enableAreaInput(false);
            enableSubAreaInput(false);
            
            const subareaValue = jQuery(event.target).val();
            const subarea = jQuery(event.target).find('option:selected').text();

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/shipping-info?city=' + city + '&area=' + area + '&subarea=' + subareaValue)
            .then((response) => response.json())
            .then((response) => {
                if (response) {
                    if (jQuery('#avaita-address-finder-line1').length === 0) {
                        jQuery(event.target).parents('.avaita-address-finder-wrapper').after(AVAITA_DELIVERY_VARS.billing_line1_html)
                    }

                    jQuery('#billing_address_1').val(subarea);
                    jQuery('#shipping_address_1').val(subarea);
                    
                    jQuery('body').trigger('wc_fragment_refresh');
                    jQuery('body').trigger('update_checkout');
                }
                enableCityInput(true);
                enableAreaInput(true);
                enableSubAreaInput(true);
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




