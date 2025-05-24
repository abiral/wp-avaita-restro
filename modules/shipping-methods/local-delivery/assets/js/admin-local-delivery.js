+(function ($) {
    var city = null;
    var shippingConditions = null;
    var lastAddedShippingItem = null;
    var orderSubTotal = 0;

    $(document).ready(function(){
        if (jQuery('.select2').length > 0) {
            jQuery('.select2').select2();
        }
        if (jQuery('.add-local-delivery-charge').length > 0) {
            jQuery('.add-local-delivery-charge').on('click', function() {
                save_line_items();
            });
        }


        jQuery(document).on('change', '#avaita_billing_city', function(event) {
            city = jQuery(event.target).val();
            jQuery('#avaita_billing_city').prop('disabled', true);
            jQuery('#avaita_billing_area').prop('disabled', true);

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/areas?city=' + city)
            .then((response) => response.json())
            .then((response) => {
                if (response && response.length > 0) {
                    const options = response.map(function(data){
                        return new Option(data.area, encodeURIComponent(data.area), false, false);
                    });
                    jQuery('#avaita_billing_area').html('<option value="">None</option>');
                    jQuery('#avaita_billing_area').append(options).trigger('change');
                }
                jQuery('#avaita_billing_city').prop('disabled', false);
                jQuery('#avaita_billing_area').prop('disabled', false);
            });

            // const cityText = jQuery(event.target).find('option:selected').text();
        });

        jQuery(document).on('change', '#avaita_billing_area', function(event) {
            const areaValue = jQuery(event.target).val();
            if (!areaValue) return;
            jQuery('#avaita_billing_city').prop('disabled', true);
            jQuery('#avaita_billing_area').prop('disabled', true);

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/area-details?city=' + city + '&area=' + areaValue)
            .then((response) => response.json())
            .then((response) => {
                if (response) {
                    if (!isFreeDeliveryEligible(response)) {
                        if (lastAddedShippingItem) {
                            $(`tr.shipping input[name="shipping_method_title[${lastAddedShippingItem}]"]`).val(response.delivery_label);
                            $(`tr.shipping input[name="shipping_cost[${lastAddedShippingItem}]"]`).val(response.delivery_price);
                            $(`tr.shipping .line_cost .view`).html(response.formatted_delivery_price);
                            $(`tr.shipping .name .view:first`).text(response.delivery_label);
                        } else {
                            shippingConditions = response;
                            add_shipping(save_line_items);
                        }
                    }

                    $('#avaita-delivery-calculator #delivery-distance .value').text(response.formatted_distance);
                    $('#avaita-delivery-calculator #delivery-price .value').html(response.formatted_delivery_price);
                    $('#avaita-delivery-calculator #delivery-free-threshold .value').html(response.formatted_minimum_free_delivery);
                }
               jQuery('#avaita_billing_city').prop('disabled', false);
                jQuery('#avaita_billing_area').prop('disabled', false);
            });
        });

        $(window).on('items_saved', function(){
            const totalEl = jQuery('.line_subtotal');
            if (!totalEl) {
                return null;
            }

            const orderTotal = parseFloat(totalEl.val());
            if (orderSubTotal == orderTotal) {
                return null;
            }

            orderSubTotal = orderTotal;

            if (isFreeDeliveryEligible(shippingConditions)) {
                alert('Free');
                // handleFreeShippingEvent();
            } else {
                alert('Paid');
                // handlePaidShippingEvent();
            }
        })
    });
    

    var wc_meta_boxes_order = {
        init_tiptip: function() {
			$( '#tiptip_holder' ).removeAttr( 'style' );
			$( '#tiptip_arrow' ).removeAttr( 'style' );
			$( '.tips' ).tipTip({
				'attribute': 'data-tip',
				'fadeIn': 50,
				'fadeOut': 50,
				'delay': 200,
				'keepAlive': true
			});
		},
    }

    var wc_meta_boxes_order_items = {
        block: function() {
			$( '#woocommerce-order-items' ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			$( '#woocommerce-order-items' ).unblock();
		},

		filter_data: function( handle, data ) {
			const filteredData = $( '#woocommerce-order-items' )
				.triggerHandler(
					`woocommerce_order_meta_box_${handle}_ajax_data`,
					[ data ]
				);

			if ( filteredData ) {
				return filteredData;
			}

			return data;
		},

		reloaded_items: function() {
			wc_meta_boxes_order.init_tiptip();
			wc_meta_boxes_order_items.stupidtable.init();
		},

        stupidtable: {
			init: function() {
				$( '.woocommerce_order_items' ).stupidtable();
				$( '.woocommerce_order_items' ).on( 'aftertablesort', this.add_arrows );
			},

			add_arrows: function( event, data ) {
				var th    = $( this ).find( 'th' );
				var arrow = data.direction === 'asc' ? '&uarr;' : '&darr;';
				var index = data.column;
				th.find( '.wc-arrow' ).remove();
				th.eq( index ).append( '<span class="wc-arrow">' + arrow + '</span>' );
			}
		}
    }

    function add_shipping (cb) {
        wc_meta_boxes_order_items.block();

        var data = {
            action   : 'ald_add_order_shipping',
            order_id : woocommerce_admin_meta_boxes.post_id,
            security : woocommerce_admin_meta_boxes.order_item_nonce,
            delivery_price: shippingConditions.delivery_price,
            area: shippingConditions.area,
            city: shippingConditions.city,
            minimum_free_delivery: shippingConditions.minimum_free_delivery,
            dataType : 'json'
        };

        data = wc_meta_boxes_order_items.filter_data( 'add_shipping', data );

        $.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
            if ( response.success ) {
                $( 'table.woocommerce_order_items tbody#order_shipping_line_items' ).append( response.data.html );
                window.wcTracks.recordEvent( 'order_edit_add_shipping', {
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    status: $( '#order_status' ).val()
                } );
                cb();
            } else {
                window.alert( response.data.error );
            }
            wc_meta_boxes_order.init_tiptip();
            wc_meta_boxes_order_items.unblock();
        });

        return false;
    };

    function save_line_items() {
        var data = {
            order_id: woocommerce_admin_meta_boxes.post_id,
            items:    $( 'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]' ).serialize(),
            action:   'woocommerce_save_order_items',
            security: woocommerce_admin_meta_boxes.order_item_nonce,
            delivery_price: shippingConditions.delivery_price,
            area: shippingConditions.area,
            city: shippingConditions.city,
            minimum_free_delivery: shippingConditions.minimum_free_delivery
        };

        data = wc_meta_boxes_order_items.filter_data( 'save_line_items', data );

        wc_meta_boxes_order_items.block();

        $.ajax({
            url:  woocommerce_admin_meta_boxes.ajax_url,
            data: data,
            type: 'POST',
            success: function( response ) {
                if ( response.success ) {
                    $( '#woocommerce-order-items' ).find( '.inside' ).empty();
                    $( '#woocommerce-order-items' ).find( '.inside' ).append( response.data.html );

                    // Update notes.
                    if ( response.data.notes_html ) {
                        $( 'ul.order_notes' ).empty();
                        $( 'ul.order_notes' ).append( $( response.data.notes_html ).find( 'li' ) );
                    }

                    wc_meta_boxes_order_items.reloaded_items();
                    wc_meta_boxes_order_items.unblock();

                    lastAddedShippingItem = jQuery('table.woocommerce_order_items tbody#order_shipping_line_items tr[data-order_item_id]').data('order_item_id')
                } else {
                    wc_meta_boxes_order_items.unblock();
                    window.alert( response.data.error );
                }
            },
            complete: function() {
                window.wcTracks.recordEvent( 'order_edit_save_line_items', {
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    status: $( '#order_status' ).val()
                } );
            }
        });

        $( this ).trigger( 'items_saved' );

        return false;
    };

    function delete_item (itemId) {
        wc_meta_boxes_order_items.block();

        var data = $.extend( {}, wc_meta_boxes_order_items.get_taxable_address(), {
            order_id      : woocommerce_admin_meta_boxes.post_id,
            order_item_ids: lastAddedShippingItem,
            action        : 'woocommerce_remove_order_item',
            security      : woocommerce_admin_meta_boxes.order_item_nonce
        } );

        // Check if items have changed, if so pass them through so we can save them before deleting.
        if ( 'true' === $( 'button.cancel-action' ).attr( 'data-reload' ) ) {
            data.items = $( 'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]' ).serialize();
        }

        data = wc_meta_boxes_order_items.filter_data( 'delete_item', data );

        $.ajax({
            url:     woocommerce_admin_meta_boxes.ajax_url,
            data:    data,
            type:    'POST',
            success: function( response ) {
                if ( response.success ) {
                    $( '#woocommerce-order-items' ).find( '.inside' ).empty();
                    $( '#woocommerce-order-items' ).find( '.inside' ).append( response.data.html );

                    // Update notes.
                    if ( response.data.notes_html ) {
                        $( 'ul.order_notes' ).empty();
                        $( 'ul.order_notes' ).append( $( response.data.notes_html ).find( 'li' ) );
                    }

                    wc_meta_boxes_order_items.reloaded_items();
                    wc_meta_boxes_order_items.unblock();
                } else {
                    window.alert( response.data.error );
                }
                wc_meta_boxes_order_items.unblock();
            },
            complete: function() {
                window.wcTracks.recordEvent( 'order_edit_remove_item', {
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    status: $( '#order_status' ).val()
                } );
            }
        });
        return false;
    };

    function isFreeDeliveryEligible(shippingDetails) {
        if (!shippingDetails) {
            console.log('Shipping Details', shippingDetails);
            return false;
        }

        const minimumFreeDelivery = parseFloat(shippingDetails.minimum_free_delivery);
        console.log('min delivery', minimumFreeDelivery);

        let total = 0;
        const totalEl = jQuery('.line_subtotal');
        if (totalEl.length > 0) {
            total = parseFloat(totalEl.val());
        }

        console.log('total', total);

        return total > minimumFreeDelivery ? true : false;
    }

    function handleFreeShippingEvent(){
        delete_item(lastAddedShippingItem);
        // $('#avaita-delivery-calculator #delivery-total .value').text(response);
    }

    function handlePaidShippingEvent(){
        add_shipping(save_line_items);
        // $('#avaita-delivery-calculator #delivery-total .value').text(response);
    }
})(jQuery);

// line_total