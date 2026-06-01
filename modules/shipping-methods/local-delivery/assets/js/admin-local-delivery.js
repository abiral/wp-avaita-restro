+(function ($) {
    var city = null;
    var area = null;
    var subArea = null;
    var shippingConditions = null;
    var lastAddedShippingItem = null;
    var allShippingItems = [];

    function blockInputsAndButtons() {
        jQuery('#avaita_billing_city').prop('disabled', true);
        jQuery('#avaita_billing_area').prop('disabled', true);
        jQuery('#avaita_billing_sub_area').prop('disabled', true);
        jQuery('#avaita_billing_street').prop('disabled', true);
        jQuery('#avaita-delivery-calculator button').prop('disabled', true);
    }

    function unblockInputsAndButtons() {
        jQuery('#avaita_billing_city').prop('disabled', false);
        jQuery('#avaita_billing_area').prop('disabled', false);
        jQuery('#avaita_billing_sub_area').prop('disabled', false);
        jQuery('#avaita_billing_street').prop('disabled', false);
        jQuery('#avaita-delivery-calculator button').prop('disabled', false);
    }

    $(document).ready(function(){
        if (jQuery('.select2').length > 0) {
            jQuery('.select2').select2();
        }
        if (jQuery('.add-local-delivery-charge').length > 0) {
            jQuery('.add-local-delivery-charge').on('click', function() {
                save_line_items();
            });
        }

        // Initialize shipping items tracking
        updateShippingItemsTracking();

        // Delivery cost calculation and management
        // Calculate and Add Delivery Cost button handler
        jQuery(document).on('click', '.calculate-add-delivery', function(e) {
            e.preventDefault();
            if (!shippingConditions) {
                alert('Please select complete delivery address first.');
                return;
            }
            
            if (isFreeDeliveryEligible(shippingConditions)) {
                if (confirm('Free delivery is eligible for this order. Remove existing shipping charges?')) {
                    deleteAllShippingItems();
                }
                return;
            }
            
            // Check if shipping already exists and update it, or add new
            var existingShippingItems = getAllShippingItems();
            if (existingShippingItems.length > 0) {
                updateExistingShipping();
            } else {
                add_shipping(save_line_items);
            }
        });

        // Remove Delivery button handler
        jQuery(document).on('click', '.remove-delivery', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to remove delivery charges?')) {
                deleteAllShippingItems();
            }
        });

        // Extra fee functionality
        // Add extra fee button handler
        jQuery(document).on('click', '.add-extra-fee', function(e) {
            e.preventDefault();
            var feeName = jQuery('#extra_fee_name').val().trim();
            var feeAmount = parseFloat(jQuery('#extra_fee_amount').val());

            if (!feeName) {
                alert('Please enter a fee name.');
                jQuery('#extra_fee_name').focus();
                return;
            }

            if (isNaN(feeAmount) || feeAmount <= 0) {
                alert('Please enter a valid amount greater than 0.');
                jQuery('#extra_fee_amount').focus();
                return;
            }

            addExtraFee(feeName, feeAmount);
        });

        // Remove all extra fees button handler
        jQuery(document).on('click', '.remove-all-extra-fees', function(e) {
            e.preventDefault();
            var feeItems = getAllFeeItems();
            if (feeItems.length === 0) {
                alert('No extra fees found to remove.');
                return;
            }

            if (confirm('Are you sure you want to remove all extra fees? This action cannot be undone.')) {
                removeAllExtraFees(feeItems);
            }
        });

        // Clear extra fee inputs after Enter key
        jQuery('#extra_fee_name, #extra_fee_amount').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                jQuery('.add-extra-fee').click();
            }
        });

        setTimeout(function() {
            if (jQuery('#avaita_billing_city').val()) {
                jQuery('#avaita_billing_city').trigger('change');
            }
        }, 1000);
        

        jQuery(document).on('change', '#avaita_billing_city', function(event) {
            city = jQuery(event.target).val();
            blockInputsAndButtons();

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/areas?city=' + city)
            .then((response) => response.json())
            .then((response) => {
                if (response && response.length > 0) {
                    const selectLabel = jQuery('#avaita_billing_area').data('selectlabel') || 'Select Area';
                    const selectedVal = jQuery('#avaita_billing_area').data('current'); 

                    const options = response.map(function(data){
                        return new Option(data.area, encodeURIComponent(data.area), false, selectedVal === data.area);
                    });
                    jQuery('#avaita_billing_area').html('<option value="">' + selectLabel + '</option>');
                    jQuery('#avaita_billing_area').append(options);
                }

                if (jQuery('#avaita_billing_area').val()) {
                    jQuery('#avaita_billing_area').trigger('change');
                }
            }).finally(() => {
                unblockInputsAndButtons();
            });
        });

        jQuery(document).on('change', '#avaita_billing_area', function(event) {
            area = jQuery(event.target).val();
            if (!area) return;
            blockInputsAndButtons();

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/sub-areas?city=' + city + '&area=' + area)
            .then((response) => response.json())
            .then((response) => {
                if (response && response.length > 0) {
                    const selectLabel = jQuery('#avaita_billing_sub_area').data('selectlabel') || 'Select Sub Area';
                    const selectedVal = jQuery('#avaita_billing_sub_area').data('current'); 
                    
                    const options = response.map(function(data){
                        return new Option(data.sub_area, encodeURIComponent(data.sub_area), false, selectedVal === data.sub_area);
                    });
                    jQuery('#avaita_billing_sub_area').html('<option value="">' + selectLabel + '</option>');
                    jQuery('#avaita_billing_sub_area').append(options);
                }

                if (jQuery('#avaita_billing_sub_area').val()) {
                    jQuery('#avaita_billing_sub_area').trigger('change');
                }
            }).finally(() => {
                unblockInputsAndButtons();
            });
        });

        jQuery(document).on('change', '#avaita_billing_sub_area', function(event) {
            subArea = jQuery(event.target).val();
            if (!subArea) return;
            
            blockInputsAndButtons();

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/street?city=' + city + '&area=' + area + '&sub_area=' + subArea)
            .then((response) => response.json())
            .then((response) => {
                if (response && response.length > 0) {
                    const selectLabel = jQuery('#avaita_billing_street').data('selectlabel') || 'Select Street';
                    const selectedVal = jQuery('#avaita_billing_street').data('current'); 

                    const options = response.map(function(data){
                        return new Option(data.street, encodeURIComponent(data.street), false, selectedVal === data.street);
                    });
                    jQuery('#avaita_billing_street').html('<option value="">' + selectLabel + '</option>');
                    jQuery('#avaita_billing_street').append(options);
                }

                if (jQuery('#avaita_billing_street').val()) {
                    jQuery('#avaita_billing_street').trigger('change');
                }
            }).finally(() => {
                unblockInputsAndButtons();
            });
        });


        jQuery(document).on('change', '#avaita_billing_street', function(event) {
            const street = jQuery(event.target).val();
            if (!street) return;
            
            blockInputsAndButtons();
            jQuery('#avaita_billing_street').prop('disabled', true);

            fetch(AVAITA_DELIVERY_VARS.api_host + '/delivery-addresses/area-details?city=' + city + '&area=' + area + '&sub_area=' + subArea + '&street=' + street)
            .then((response) => response.json())
            .then((response) => {
                if (response) {
                    shippingConditions = response;
                    
                    // Update delivery calculator display
                    $('#avaita-delivery-calculator #delivery-distance .value').text(response.formatted_distance);
                    $('#avaita-delivery-calculator #delivery-price .value').html(response.formatted_delivery_price);
                    $('#avaita-delivery-calculator #delivery-free-threshold .value').html(response.formatted_minimum_free_delivery);
                    
                    // Update existing shipping items with new delivery costs
                    var existingShippingItems = getAllShippingItems();
                    if (existingShippingItems.length > 0 && !isFreeDeliveryEligible(response)) {
                        updateExistingShipping();
                    }
                }
            }).finally(() => {
                unblockInputsAndButtons();
            });
        });

        $(window).on('items_saved', function(){
            // Update shipping items tracking after items are saved
            updateShippingItemsTracking();
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
            action   : 'avaita_add_order_shipping',
            order_id : woocommerce_admin_meta_boxes.post_id,
            security : woocommerce_admin_meta_boxes.order_item_nonce,
            delivery_price: shippingConditions.delivery_price,
            street: shippingConditions.street,
            subArea: shippingConditions.sub_area,
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
                updateShippingItemsTracking(); // Update tracking after adding
                if (cb) cb();
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

                    // Update shipping items tracking
                    updateShippingItemsTracking();
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
            return false;
        }

        const minimumFreeDelivery = parseFloat(shippingDetails.minimum_free_delivery);

        let total = 0;
        const totalEl = jQuery('.line_subtotal');
        if (totalEl.length > 0) {
            total = parseFloat(totalEl.val());
        }

        return total > minimumFreeDelivery ? true : false;
    }

    // Helper function to update existing shipping items with new delivery costs
    function updateExistingShipping() {
        if (!shippingConditions) return;
        
        var existingShippingItems = getAllShippingItems();
        if (existingShippingItems.length === 0) return;
        
        // Update the first shipping item (assuming there's typically only one delivery charge)
        var firstShippingItemId = existingShippingItems[0];
        
        // Update the shipping method title and cost in the DOM
        var $shippingRow = jQuery(`tr.shipping[data-order_item_id="${firstShippingItemId}"]`);
        if ($shippingRow.length > 0) {
            // Update title/name
            $shippingRow.find(`input[name="shipping_method_title[${firstShippingItemId}]"]`).val(shippingConditions.delivery_label);
            $shippingRow.find('.name .view').first().text(shippingConditions.delivery_label);
            
            // Update cost
            $shippingRow.find(`input[name="shipping_cost[${firstShippingItemId}]"]`).val(shippingConditions.delivery_price);
            $shippingRow.find('.line_cost .view').html(shippingConditions.formatted_delivery_price);
            
            // Update any line total displays
            $shippingRow.find('.item_cost .view').html(shippingConditions.formatted_delivery_price);
            
            // Save the changes
            save_line_items();
        }
    }

    // Helper function to get all current shipping item IDs
    function getAllShippingItems() {
        var shippingItems = [];
        jQuery('table.woocommerce_order_items tbody#order_shipping_line_items tr[data-order_item_id]').each(function() {
            var itemId = jQuery(this).data('order_item_id');
            if (itemId) {
                shippingItems.push(itemId);
            }
        });
        return shippingItems;
    }

    // Helper function to update shipping items tracking
    function updateShippingItemsTracking() {
        allShippingItems = getAllShippingItems();
        lastAddedShippingItem = allShippingItems.length > 0 ? allShippingItems[allShippingItems.length - 1] : null;
    }

    // Helper function to delete all existing shipping items
    function deleteAllShippingItems(callback) {
        var shippingItems = getAllShippingItems();
        
        if (shippingItems.length === 0) {
            if (callback) callback();
            return;
        }

        wc_meta_boxes_order_items.block();

        var data = {
            order_id: woocommerce_admin_meta_boxes.post_id,
            order_item_ids: shippingItems.join(','),
            action: 'woocommerce_remove_order_item',
            security: woocommerce_admin_meta_boxes.order_item_nonce,
            items: $( 'table.woocommerce_order_items :input[name], .wc-order-totals-items :input[name]' ).serialize()
        };

        data = wc_meta_boxes_order_items.filter_data( 'delete_item', data );

        $.ajax({
            url: woocommerce_admin_meta_boxes.ajax_url,
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
                    updateShippingItemsTracking();

                    if (callback) callback();
                } else {
                    window.alert( response.data.error );
                    wc_meta_boxes_order_items.unblock();
                }
            },
            error: function() {
                wc_meta_boxes_order_items.unblock();
                if (callback) callback();
            },
            complete: function() {
                window.wcTracks.recordEvent( 'order_edit_remove_item', {
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    status: $( '#order_status' ).val()
                } );
            }
        });
    }

    // Extra Fee Management Functions
    function addExtraFee(feeName, feeAmount) {
        wc_meta_boxes_order_items.block();

        var data = {
            action: 'avaita_add_extra_fee',
            order_id: woocommerce_admin_meta_boxes.post_id,
            security: woocommerce_admin_meta_boxes.order_item_nonce,
            fee_name: feeName,
            fee_amount: feeAmount,
            dataType: 'json'
        };

        jQuery.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
            if ( response.success ) {
                // Add the fee item HTML to the order items table
                jQuery( 'table.woocommerce_order_items tbody#order_fee_line_items' ).append( response.data.html );
                
                // Clear the input fields
                jQuery('#extra_fee_name').val('');
                jQuery('#extra_fee_amount').val('');
                
                // Show success message
                showNotice('Extra fee "' + feeName + '" added successfully.', 'success');
                
                // Trigger save to update totals
                save_line_items();
                
                window.wcTracks.recordEvent( 'order_edit_add_fee', {
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    status: jQuery( '#order_status' ).val()
                } );
            } else {
                window.alert( 'Error: ' + (response.data.error || 'Unable to add extra fee.') );
            }
            wc_meta_boxes_order.init_tiptip();
            wc_meta_boxes_order_items.unblock();
        }).fail(function() {
            window.alert( 'Error: Failed to add extra fee. Please try again.' );
            wc_meta_boxes_order_items.unblock();
        });
    }

    function removeAllExtraFees(feeItemIds) {
        if (!feeItemIds || feeItemIds.length === 0) return;

        wc_meta_boxes_order_items.block();

        var data = {
            action: 'avaita_remove_extra_fees',
            order_id: woocommerce_admin_meta_boxes.post_id,
            security: woocommerce_admin_meta_boxes.order_item_nonce,
            fee_item_ids: feeItemIds.join(','),
            dataType: 'json'
        };

        jQuery.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
            if ( response.success ) {
                // Remove fee items from DOM
                feeItemIds.forEach(function(itemId) {
                    jQuery('tr.fee[data-order_item_id="' + itemId + '"]').remove();
                });
                
                showNotice('All extra fees removed successfully.', 'success');
                
                // Trigger save to update totals
                save_line_items();
                
                window.wcTracks.recordEvent( 'order_edit_remove_fee', {
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    status: jQuery( '#order_status' ).val()
                } );
            } else {
                window.alert( 'Error: ' + (response.data.error || 'Unable to remove extra fees.') );
            }
            wc_meta_boxes_order_items.unblock();
        }).fail(function() {
            window.alert( 'Error: Failed to remove extra fees. Please try again.' );
            wc_meta_boxes_order_items.unblock();
        });
    }

    function getAllFeeItems() {
        var feeItems = [];
        jQuery('table.woocommerce_order_items tbody#order_fee_line_items tr.fee[data-order_item_id]').each(function() {
            var itemId = jQuery(this).data('order_item_id');
            if (itemId) {
                feeItems.push(itemId);
            }
        });
        return feeItems;
    }

    function showNotice(message, type) {
        type = type || 'success';
        var noticeClass = 'notice notice-' + type + ' is-dismissible';
        var notice = '<div class="' + noticeClass + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
        
        // Remove any existing notices
        jQuery('.notice.avaita-extra-fee-notice').remove();
        
        // Add new notice
        var $notice = jQuery(notice).addClass('avaita-extra-fee-notice');
        jQuery('#wpbody-content .wrap h1').after($notice);
        
        // Auto dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 3000);
        
        // Handle dismiss button
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        });
    }
})(jQuery);

// line_total