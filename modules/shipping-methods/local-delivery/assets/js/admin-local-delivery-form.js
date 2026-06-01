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
        } else if (distance >= 20) {
            delivery_charge = distance * 20;
            minimum_price_for_delivery = distance * 250;
            minimum_order_acceptance = minimum_price_for_delivery / 2;
            street_extra_price = delivery_charge * 0.2;
        } else {
            console.log('Invalid distance for delivery');
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

    // Expose the single source of truth for tier logic so other admin
    // scripts (e.g. CSV import) can reuse it without duplication.
    window.AvaitaDelivery = window.AvaitaDelivery || {};
    window.AvaitaDelivery.calculateDeliveryCharge = calculateDeliveryCharge;

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



    function setFormMode(mode) {
        const $shell = $('.avaita-form-shell');
        if ($shell.length === 0) return;
        const $title = $shell.find('.avaita-card-title');
        const $tag   = $shell.find('.avaita-card-mode-tag');
        const $save  = $shell.find('button.save-data');
        const $icon  = $save.find('.dashicons');
        const $label = $save.find('.save-data-label');
        const $cancel = $shell.find('.cancel-edit');

        const $hint = $shell.find('.avaita-actions-hint-text');

        if (mode === 'edit') {
            $title.text($shell.data('label-update'));
            $tag.text($shell.data('mode-tag-edit')).addClass('is-edit');
            $label.text($save.data('label-update'));
            $icon.removeClass('dashicons-plus-alt2').addClass('dashicons-update');
            $cancel.show();
            $hint.text('Editing an existing area. Cancel to discard.');
        } else {
            $title.text($shell.data('label-add'));
            $tag.text($shell.data('mode-tag-add')).removeClass('is-edit');
            $label.text($save.data('label-add'));
            $icon.removeClass('dashicons-update').addClass('dashicons-plus-alt2');
            $cancel.hide();
            $hint.text('Fill in the form, then save.');
        }
    }

    function clearForm() {
        const $shell = $('.avaita-form-shell');
        if ($shell.length === 0) return;
        $shell.find('input[name="id"]').val('');
        $shell.find('input[name="area"]').val('');
        $shell.find('input[name="sub_area"]').val('');
        $shell.find('input[name="street"]').val('');
        $shell.find('input[name="city"]').val('');
        $shell.find('input[name="distance"]').val('');
        $shell.find('input[name="minimum_order_threshold"]').val('');
        $shell.find('input[name="minimum_free_delivery"]').val('');
        $shell.find('input[name="delivery_price"]').val('');
        $shell.find('button.save-data').prop('disabled', true);
        setFormMode('add');
    }

    $(document).ready(function () {
        setFormMode('add');

        $('.avaita-form-shell input').on("input", function () {
            $(this).parents('tr').find('button.save-data').prop('disabled', false);
        });

        $(document).on('click', '.reset-form, .cancel-edit', function (event) {
            event.preventDefault();
            clearForm();
        });

        $('input[name="distance"]').on("input", function () {
            // $(this).parents('tr').find('button.save-data').prop('disabled', false);
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
            sub_area: parentEl.find('input[name="sub_area"]').val(),
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

    $(document).on('click', '.edit-location', function (event) {
        event.preventDefault();
        const parentEl = $(this).parents('tr');
        const $shell = $('.avaita-form-shell');
        $shell.find('input[name="id"]').val(parentEl.data('location-id'));
        $shell.find('input[name="area"]').val(parentEl.find('td.area').data('val'));
        $shell.find('input[name="sub_area"]').val(parentEl.find('td.sub-area').data('val'));
        $shell.find('input[name="street"]').val(parentEl.find('td.street').data('val'));
        $shell.find('input[name="city"]').val(parentEl.find('td.city').data('val'));
        $shell.find('input[name="state"]').val(parentEl.find('td.state').data('val'));
        $shell.find('input[name="distance"]').val(parentEl.find('td.distance').data('val'));
        $shell.find('input[name="minimum_order_threshold"]').val(parentEl.find('td.min-threshold').data('val'));
        $shell.find('input[name="minimum_free_delivery"]').val(parentEl.find('td.min-delivery').data('val'));
        $shell.find('input[name="delivery_price"]').val(parentEl.find('td.delivery-price').data('val'));

        setFormMode('edit');
        $shell.find('button.save-data').prop('disabled', false);

        // Scroll the form into view so the user knows it's now in edit mode.
        $('html, body').animate({ scrollTop: $shell.offset().top - 60 }, 200);
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

    /* ---------- Cascading filter dropdowns (Delivery Areas list) ----------
       Repopulates child <select> options as a parent changes, reusing the
       same REST endpoints as the checkout cascade. Applying the filter is a
       normal GET submit (the "Filter" button) — the data is queried server-side. */
    (function () {
        const $city = $('#avaita-filter-city');
        if (!$city.length || typeof AVAITA_DELIVERY_VARS === 'undefined') {
            return;
        }

        const apiHost  = AVAITA_DELIVERY_VARS.api_host;
        const $area    = $('#avaita-filter-area');
        const $subArea = $('#avaita-filter-sub-area');

        // Keep only the placeholder option, clear selection, disable.
        function resetSelect($sel) {
            $sel.find('option:gt(0)').remove();
            $sel.val('').prop('disabled', true);
        }

        function fillSelect($sel, items, key) {
            resetSelect($sel);
            if (items && items.length) {
                items.forEach(function (item) {
                    // Raw text value so the GET submit round-trips through $_GET.
                    $sel.append(new Option(item[key], item[key]));
                });
                $sel.prop('disabled', false);
            }
        }

        function fetchOptions(path, $target, key) {
            fetch(apiHost + path)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    fillSelect($target, Array.isArray(data) ? data : [], key);
                })
                .catch(function () { resetSelect($target); });
        }

        $city.on('change', function () {
            const city = $(this).val();
            resetSelect($area);
            resetSelect($subArea);
            if (!city) { return; }
            fetchOptions('/delivery-addresses/areas?city=' + encodeURIComponent(city), $area, 'area');
        });

        $area.on('change', function () {
            const city = $city.val();
            const area = $(this).val();
            resetSelect($subArea);
            if (!area) { return; }
            fetchOptions('/delivery-addresses/sub-areas?city=' + encodeURIComponent(city) + '&area=' + encodeURIComponent(area), $subArea, 'sub_area');
        });
    })();
})(jQuery);