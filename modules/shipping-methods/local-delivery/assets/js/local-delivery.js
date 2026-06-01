+(function () {
    function blockAddressFields(){
        jQuery('#avaita-billing-city').prop('disabled', true);
        jQuery('#avaita-billing-area').prop('disabled', true);
        jQuery('#avaita-billing-sub-area').prop('disabled', true);
        jQuery('#avaita-billing-street').prop('disabled', true);
        jQuery('#avaita-billing-city').parents('.avaita-address-finder-wrapper').addClass('loading');
        jQuery('#avaita-billing-area').parents('.avaita-address-finder-wrapper').addClass('loading');
        jQuery('#avaita-billing-sub-area').parents('.avaita-address-finder-wrapper').addClass('loading');
        jQuery('#avaita-billing-street').parents('.avaita-address-finder-wrapper').addClass('loading');
    }

    function unblockAddressFields(){
        jQuery('#avaita-billing-city').prop('disabled', false);
        jQuery('#avaita-billing-area').prop('disabled', false);
        jQuery('#avaita-billing-sub-area').prop('disabled', false);
        jQuery('#avaita-billing-street').prop('disabled', false);
        jQuery('#avaita-billing-street').parents('.avaita-address-finder-wrapper').removeClass('loading');
        jQuery('#avaita-billing-sub-area').parents('.avaita-address-finder-wrapper').removeClass('loading');
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
                    return new Option(data.area, encodeURIComponent(data.area), false, data.area === AVAITA_DELIVERY_VARS.checkout.values.area);
                });
                jQuery('#billing_city').val(city);
                jQuery('#avaita-billing-area').html(options).trigger('change');
            }
            unblockAddressFields();
        });
        return city;
    }

    function fetchSubAreaBySelectedArea($el = null) {
        blockAddressFields();
        area = $el ? $el.val() : jQuery('#avaita-billing-area').val();
        city = jQuery('#avaita-billing-city').val();

        if (!area) {
            unblockAddressFields();
            return;
        }


        fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/sub-areas?city=' + city + '&area=' + area)
            .then((response) => response.json())
            .then((response) => {
                if (response && response && response.length > 0) {
                    console.log('response', response);
                    const options = response.map(function (data) {
                        console.log('data', data);
                        return new Option(data.sub_area, encodeURIComponent(data.sub_area), false, data.sub_area === AVAITA_DELIVERY_VARS.checkout.values.sub_area);
                    });

                    jQuery('#avaita-billing-sub-area').html('<option value="">'+AVAITA_DELIVERY_VARS.checkout.placeholders.sub_area+'</option>');
                    jQuery('#avaita-billing-sub-area').append(options).trigger('change');
                }
                unblockAddressFields();
            })
            .catch(err => {
                console.error("Fetch error:", err);
                unblockAddressFields();
            });
        return area;
    }


    // function fetchSubAreaBySelectedArea($el = null) {
    //     blockAddressFields();
    //     area = $el ? $el.val() : jQuery('#avaita-billing-area').val();
    //     city = jQuery('#avaita-billing-city').val();

    //     if (!area) {
    //         unblockAddressFields();
    //         return;
    //     }

    //     fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/sub-areas?city=' + city + '&area=' + area)
    //         .then((response) => response.json())
    //         .then((response) => {
    //             if (response && response.length > 0) {
    //                 const options = response.map(function (data) {
    //                     return new Option(data.sub_area, encodeURIComponent(data.sub_area), false, false);
    //                 });

    //                 jQuery('#avaita-billing-sub-area').html('<option value="">Select the sub area</option>');
    //                 jQuery('#avaita-billing-sub-area').append(options).trigger('change');
    //             } else {
    //                 jQuery('#avaita-billing-sub-area').html('<option value="">No sub-areas found</option>').trigger('change');
    //             }
    //             unblockAddressFields();
    //         })
    //         .catch(err => {
    //             console.error("Fetch error:", err);
    //             unblockAddressFields();
    //         });
    //     return area;
    // }

    function fetchStreetBySelectedArea($el = null) {
        blockAddressFields();
        area = jQuery('#avaita-billing-area').val();
        subArea = $el ? $el.val() : jQuery('#avaita-billing-sub-area').val();
        city = jQuery('#avaita-billing-city').val();

        if (!subArea) {
            unblockAddressFields();
            return;
        }

        fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/street?city=' + city + '&area=' + area + '&sub_area=' + subArea)
            .then((response) => response.json())
            .then((response) => {
                if (response && response.length > 0) {
                    const options = response.map(function (data) {
                        return new Option(data.street, encodeURIComponent(data.street), false, data.street === AVAITA_DELIVERY_VARS.checkout.values.street);
                    });

                    jQuery('#avaita-billing-street').html('<option value="">'+AVAITA_DELIVERY_VARS.checkout.placeholders.street+'</option>');
                    jQuery('#avaita-billing-street').append(options).trigger('change').trigger('change');
                }
                unblockAddressFields();
            })
            .catch(err => {
                console.error("Fetch error:", err);
                unblockAddressFields();
            });
        return area;
    }

    function maybeTriggerChange(city, area, subArea, street) {
        if(street) {
            jQuery('#avaita-billing-street').trigger('change');
        } else if(subArea) {
            jQuery('#avaita-billing-sub-area').trigger('change');
        } else if(area) {
            jQuery('#avaita-billing-area').trigger('change');
        } else if(city) {
            jQuery('#avaita-billing-city').trigger('change');
        }
    }


    jQuery(document).ready(function () {
        jQuery('#avaita-billing-city_field').before('<h3>Delivery Details</h3>');
        city = jQuery('#avaita-billing-city').val();
        area = jQuery('#avaita-billing-area').val();
        subArea = jQuery('#avaita-billing-sub-area').val();
        street = jQuery('#avaita-billing-street').val();

        maybeTriggerChange(city, area, subArea, street);

        jQuery('.avaita-address-finder-wrapper').append('<div class="spinner"></div>');

        jQuery('#avaita-billing-city').select2();
        jQuery('#avaita-billing-area').select2();
        jQuery('#avaita-billing-sub-area').select2();
        jQuery('#avaita-billing-street').select2();



        jQuery(document).on('change', '#avaita-billing-city', function (event) {
            city = fetchAreaBySelectedCity(jQuery(event.target));
            const cityText = jQuery(event.target).find('option:selected').text();
            jQuery('#shipping_city').val(cityText);
        });

        jQuery(document).on('change', '#avaita-billing-area', function (event) {
            const $el = jQuery(event.target);
            area = $el.val();
            fetchSubAreaBySelectedArea(jQuery(event.target));
            const areaText = jQuery(event.target).find('option:selected').text();
            jQuery('#billing_address_1').val(areaText);
            jQuery('#shipping_address_1').val(areaText);
        });

        jQuery(document).on('change', '#avaita-billing-sub-area', function (event) {
            const subAreaValue = jQuery(event.target).val();
            const subAreaText = jQuery(event.target).find('option:selected').text();

            if (!subAreaValue) return;

            jQuery('#billing_address_1').val(area + ', ' + subAreaText);
            jQuery('#shipping_address_1').val(area + ', ' + subAreaText);

            fetchStreetBySelectedArea(jQuery(event.target));

        });

        jQuery(document).on('change', '#avaita-billing-street', function (event) {
            const streetValue = jQuery(event.target).val();
            if (!streetValue) return;

            blockAddressFields();
            const cityVal = jQuery('#avaita-billing-city').val();
            const areaVal = jQuery('#avaita-billing-area').val();
            const subAreaVal = jQuery('#avaita-billing-sub-area').val();

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/area-details?city=' + cityVal + '&area=' + areaVal + '&sub_area=' + subAreaVal + '&street=' + streetValue)
                .then((response) => response.json())
                .then((response) => {
                    if (response) {
                        const streetText = jQuery(event.target).find('option:selected').text();
                        jQuery('#billing_street').val(streetText);
                        jQuery('#shipping_street').val(streetText);

                        jQuery('body').trigger('update_checkout');
                    }
                    unblockAddressFields();
                })
                .catch(() => unblockAddressFields());
        });

        jQuery(document).on('input', '#avaita-address-finder-line1', function (event) {
            jQuery('#billing_street').val(jQuery(event.target).val());
            jQuery('#shipping_street').val(jQuery(event.target).val());
        });

        if (city && area && subArea && street) {
            jQuery('#avaita-billing-sub-area').trigger('change');
        } else if (city && area) {
            jQuery('#avaita-billing-area').trigger('change');
        } else if (city) {
            jQuery('#avaita-billing-city').trigger('change');
        }
    });
})();