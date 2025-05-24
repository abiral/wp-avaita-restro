+(function ($) {
    function calculateDeliveryCharge(distance) {
        let delivery_charge = 0;
        let street_extra_price = 0;
        let minimum_price_for_delivery = 0;
        let minimum_order_acceptance = 0;
        let total_delivery_charge = 0;

        if (distance > 0 && distance < 0.2) {
            delivery_charge = 0;
            street_extra_price = 0;
            minimum_price_for_delivery = 75;
            minimum_order_acceptance = 75;
        } else if (distance >= 0.2 && distance < 0.4) {
            delivery_charge = 60;
            street_extra_price = 0;
            minimum_price_for_delivery = 150;
            minimum_order_acceptance = 75;
        } else if (distance >= 0.4 && distance < 0.8) {
            delivery_charge = 60;
            street_extra_price = 0;
            minimum_price_for_delivery = 200;
            minimum_order_acceptance = 100;
        } else if (distance >= 0.8 && distance < 1) {
            delivery_charge = 60;
            street_extra_price = 0;
            minimum_price_for_delivery = 240;
            minimum_order_acceptance = 150;
        } else if (distance >= 1 && distance < 5) {
            delivery_charge = 60;
            minimum_price_for_delivery = distance * 250;
            minimum_order_acceptance = minimum_price_for_delivery / 1.5;

            if (distance > 0 && distance < 3) {
                street_extra_price = 0;
            } else {
                street_extra_price = delivery_charge * 0.2;
            }
        } else if (distance >= 5 && distance < 7) {
            delivery_charge = distance * 12;
            minimum_price_for_delivery = distance * 250;
            minimum_order_acceptance = minimum_price_for_delivery / 1.75;
            street_extra_price = delivery_charge * 0.2;
        } else if (distance >= 7 && distance < 10) {
            delivery_charge = distance * 18.75;
            minimum_price_for_delivery = distance * 250;
            minimum_order_acceptance = minimum_price_for_delivery / 2;
            street_extra_price = delivery_charge * 0.2;
        } else if (distance >= 10 && distance < 20) {
            delivery_charge = distance * 18.75;
            minimum_price_for_delivery = distance * 250;
            minimum_order_acceptance = minimum_price_for_delivery / 2;
            street_extra_price = delivery_charge * 0.2;
        } else if (distance >= 20 && distance < 30) {
            delivery_charge = distance * 20;
            minimum_price_for_delivery = distance * 250;
            minimum_order_acceptance = minimum_price_for_delivery / 2;
            street_extra_price = delivery_charge * 0.2;
        } else {
            console.log('Distance is too high for delivery');
            return null;
        }

        total_delivery_charge = delivery_charge + street_extra_price;

        return {
            delivery_charge: delivery_charge.toFixed(2),
            street_extra_price: street_extra_price.toFixed(2),
            minimum_price_for_delivery: minimum_price_for_delivery.toFixed(2),
            minimum_order_acceptance: minimum_order_acceptance.toFixed(2),
            total_delivery_charge: total_delivery_charge.toFixed(2),
        };
    }

    function blockUI() {
        tb_show('', '#TB_inline?height=240&amp;width=405&amp;inlineId=avaita-loader&amp;modal=true', null);
    }

    function unBlockUI() {
        tb_remove();
    }

    function showAdminNotice(type, message) {
        const html = `
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
            </div>
        `;
        $('#avaita-admin-messages').html(html);
    }


    const notice = {
        showSuccess: function (message) {
            unBlockUI();
            showAdminNotice('success', message);
        },

        showError: function (xhr) {
            unBlockUI();
            const errorMsg = xhr.responseJSON?.message || 'Something went wrong.';
            showAdminNotice('error', errorMsg);
        },

        hide: function (cb = null) {
            setTimeout(() => {
                $('#avaita-admin-messages .notice').fadeOut();
                if (cb) cb();
            }, 2000);
        }
    }

    const avaitaAjax = {
        addLocation: function (params, cb) {
            blockUI();
            $.ajax({
                url: AVAITA_DELIVERY_VARS.api_host + '/admin/delivery-address',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(params),
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', AVAITA_DELIVERY_VARS.nonce);
                },
                success: function (response) {
                    notice.showSuccess(response.message);
                    cb(response);
                },
                error: notice.showError,
            });
        },

        updateLocation: function (id, params, cb) {
            blockUI();
            $.ajax({
                url: AVAITA_DELIVERY_VARS.api_host + '/admin/delivery-address/' + id,
                method: 'PATCH',
                contentType: 'application/json',
                data: JSON.stringify(params),
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', AVAITA_DELIVERY_VARS.nonce);
                },
                success: function (response) {
                    notice.showSuccess(response.message);
                    cb(response);
                },
                error: notice.showError,
            });
        },

        deleteLocation: function (id, cb) {
            blockUI();
            $.ajax({
                url: AVAITA_DELIVERY_VARS.api_host + '/admin/delivery-address/' + id,
                method: 'DELETE',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', AVAITA_DELIVERY_VARS.nonce);
                },
                success: function (response) {
                    notice.showSuccess(response.message);
                    cb(response);
                },
                error: notice.showError,
            });
        }
    };



    $(document).ready(function () {
        $('input[name="distance"]').on("input", function () {
            $(this).parents('tr').find('button.save-data').prop('disabled', false);
            const distance = parseFloat($(this).val());

            /* Clear all data, if the distance is set as null */
            if (isNaN(distance) || distance === "") {
                $('input[name="minimum_order_threshold"]').val("");
                $('input[name="minimum_free_delivery"]').val("");
                $('input[name="delivery_price"]').val("");
            } else {
                const deliveryValues = calculateDeliveryCharge(distance);
                if (!deliveryValues) {
                    /* We don't have delivery value as it is either invalid or too big. In that case we don't allow user to add it */
                    $(this).parents('tr').find('button.save-data').prop('disabled', true);
                    return;
                }
                $('input[name="minimum_order_threshold"]').val(
                    deliveryValues.minimum_order_acceptance
                );
                $('input[name="minimum_free_delivery"]').val(
                    deliveryValues.minimum_price_for_delivery
                );

                $('input[name="delivery_price"]').val(
                    deliveryValues.total_delivery_charge
                );
            }
        });
    });

    $('.save-data').on('click', function (event) {
        event.preventDefault();
        const parentEl = $(this).parents('tr');
        blockUI();
        const id = parentEl.find('input[name="id"]').val();
        const payload = {
            id: id,
            area: parentEl.find('input[name="area"]').val(),
            street: parentEl.find('input[name="street"]').val(),
            city: parentEl.find('input[name="city"]').val(),
            state: parentEl.find('input[name="state"]').val(),
            distance: parentEl.find('input[name="distance"]').val(),
            minimum_order_threshold: parentEl.find('input[name="minimum_order_threshold"]').val(),
            minimum_free_delivery: parentEl.find('input[name="minimum_free_delivery"]').val(),
            delivery_price: parentEl.find('input[name="delivery_price"]').val()
        };

        const handleSubmission = function (response) {
            unBlockUI();
            setTimeout(function () {
                location.reload();
            }, 2000);
        };

        if (id) {
            avaitaAjax.updateLocation(id, payload, handleSubmission);
        } else {
            avaitaAjax.addLocation(payload, handleSubmission);
        }
    });

    $('.edit-location').on('click', function (event) {
        event.preventDefault();
        const parentEl = $(this).parents('tr');
        $('input[name="id"]').val(parentEl.data('location-id'));
        $('input[name="area"]').val(parentEl.find('td.area').data('val'));
        $('input[name="street"]').val(parentEl.find('td.street').data('val'));
        $('input[name="city"]').val(parentEl.find('td.city').data('val'));
        $('input[name="state"]').val(parentEl.find('td.state').data('val'));
        $('input[name="distance"]').val(parentEl.find('td.distance').data('val'));
        $('input[name="minimum_order_threshold"]').val(parentEl.find('td.min-threshold').data('val'));
        $('input[name="minimum_free_delivery"]').val(parentEl.find('td.min-delivery').data('val'));
        $('input[name="delivery_price"]').val(parentEl.find('td.delivery-price').data('val'));
    });


    $('.delete-location').on('click', function (event) {
        event.preventDefault();
        const parentEl = $(this).parents('tr');
        if (!confirm(parentEl.data('remove-message'))) {
            return;
        }

        const id = parentEl.data('location-id');
        avaitaAjax.deleteLocation(id, function () {
            unBlockUI();
            setTimeout(function () {
                location.reload();
            }, 2000);
        });
    });
})(jQuery);