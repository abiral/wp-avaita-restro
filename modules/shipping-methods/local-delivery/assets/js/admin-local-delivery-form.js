+(function ($) {
    function getRandTime() {
        const randTime = Math.floor(Math.random() * 5) + 1;
        return randTime;
    }

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

    const avaita_ajax = {
        /* Mock AJAX Operation */
        add_location: function (params, cb) {
            const randTime = getRandTime();
            console.log(randTime);
            setTimeout(function () {
                if (randTime % 2 == 0) {
                    console.log('Location added successfully', params);
                    cb(true);
                } else {
                    console.error('Unable to add location', params);
                    cb(false);
                }
            }, randTime);
        },

        update_location: function (id, params, cb) {
            const randTime = getRandTime();
            setTimeout(function () {
                if (randTime % 2 == 0) {
                    console.log(`Location ${id} updated successfully`, params);
                    cb(true);
                } else {
                    console.error(`Unable to update location ${id}`, params);
                    cb(false);
                }
            }, randTime);
        },

        delete_location: function (id) {
            const randTime = getRandTime();
            setTimeout(function () {
                if (randTime % 2 == 0) {
                    console.log(`Location ${id} deleted successfully`);
                    cb(true);
                } else {
                    console.log(`Unable to delete location ${id}`);
                    cb(false);
                }
            }, randTime);
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
        /* TODO: Show loader, disable all the input buttons, and fields to prevent it from being clicked */
        /* TODO: Collect all the parameter, and save it to a variable */
        const payload = {};
        const id = parentEl.find('input[name=id]').val();

        const handleSubmission = function (response) {

        };

        if (id) {
            avaita_ajax.update_location(id, payload, handleSubmission);
        } else {
            avaita_ajax.add_location(payload, handleSubmission);
        }
    });

    $('.edit-location').on('click', function (event) {
        event.preventDefault();
        const parentEl = $(this).parents('tr');
        /* TODO: Get the values in row, and add it to the inputs in the form */
        /* Eg: $('tr.input-area').find('input[name=id].val(parentEl.data('location-id')); */
    });


    $('.delete-location').on('click', function (event) {
        event.preventDefault();
        const parentEl = $(this).parents('tr');
        /* TODO: Get the id from row and call delete_location from avaita_ajax*/
    });
}) (jQuery);