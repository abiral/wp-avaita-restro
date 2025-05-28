<style>
    .table>thead {
        vertical-align: bottom;
    }

    tbody, td, tfoot, th, thead, tr {
        border-color: inherit;
        border-style: solid;
        border-width: 0;
        text-align: center;
    }

    .table {
        width: 100%;
        margin-bottom: 1rem;
        vertical-align: top;
        border-color: #b2b2b2;
        caption-side: bottom;
        border-collapse: collapse;
    }

    .table>:not(caption)>*>* {
        padding: .5rem .5rem;
        color: #000;
        background-color: transparent;
        border-bottom-width: 1px;
    }

    .table th {
        font-weight: bold;
    }

    .pagination ul {
        display: flex;
        width: 500px;
        margin: 0 auto;
        justify-content: center;
    }

    .pagination ul li {
        margin-left: 20px;
        text-align: center;
        height: 20px;
        width: 20px;
    }

    .pagination ul li.active {
        background: #6d695f;
        color: #fff;
    }
</style>

<?php
global $avaita_local_shipping_db;
$page_number = isset($_GET['page_number']) && $_GET['page_number'] ? $_GET['page_number']: 1;
$page = isset($_GET['page']) && $_GET['page'] ? $_GET['page']: 'ava-restro';
$search_query = isset($_GET['search_query']) ? $_GET['search_query'] : '' ;
$query_args = array();

$delivery_data = $avaita_local_shipping_db->get_delivery_data($search_query, $page_number);

if ($page) {
    $query_args['page'] = $page;
}

if ($search_query) {
    $query_args['search_query'] = $search_query;
}

$query_args['page_number'] = 'pagePlaceholder';

$page_url = admin_url() . '?' . http_build_query($query_args, '', '&');

$js_messages = array(
    'saved_refresh_to_see' => __('{{number}} data saved. Refresh to view saved data'),
);

?>

<div>
    <div id="messageData" data-messages="<?php echo esc_html(json_encode($js_messages)); ?>"></div>
    <div id="avaita-admin-messages" style="margin-top: 20px"></div>
    <?php add_thickbox(); ?>
    <div id="avaita-loader" class="avaita-loader" style="display:none;">
        <div class="avaita-loader">
            <span id="avaita-loader-inner"></span>
        </div>
    </div>
    <div class="delivery-areas-list">
        <div class="avaita-header">
            <div>
                <div>
                    <h2 class="d-inline"><?php _e('Delivery Areas', 'avaita-restro'); ?></h2>
                    <div class="syncing-block d-inline">
                        <div class="sync-item syncing-notice d-none"><?php _e('Syncing', 'avaita'); ?></div>
                        <div class="sync-item syncing-synced d-inline"><span class="sync-number">0</span> <?php _e('records synced', 'avaita'); ?></div>
                        <div class="sync-item syncing-queue d-inline"><span class="sync-number">0</span> <?php _e('in queue', 'avaita'); ?></div>
                    </div>
                </div>
                <p><?php _e('Manage delivery areas and their respective shipping charges.', 'avaita-restro'); ?></p>
            </div>
            <div>
                <form class="avaita-search-form">
                    <?php foreach($query_args as $key => $value): ?>
                        <?php if ($key == 'search_query') continue; ?>
                        <?php if ($key == 'page_number') $value = $page_number; ?>
                        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>" />
                    <?php endforeach; ?>
                    <input type="text" name="search_query" value="<?php echo $search_query; ?>" />
                    <button class="btn-search" type="submit">Search</button>
                </form>
            </div>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th><?php _e('Area', 'avaita-restro'); ?></th>
                    <th><?php _e('Street', 'avaita-restro'); ?></th>
                    <th><?php _e('City', 'avaita-restro'); ?></th>
                    <th><?php _e('state', 'avaita-restro'); ?></th>
                    <th><?php _e('Distance', 'avaita-restro'); ?></th>
                    <th><?php _e('Order Threshold', 'avaita-restro'); ?></th>
                    <th><?php _e('Free Threshold', 'avaita-restro'); ?></th>
                    <th><?php _e('Delivery Price', 'avaita-restro'); ?></th>
                    <td>&nbsp;</td>
                </tr>
                <tr class="input-area">
                    <td><input class="form-input" type="text" name="area" /></td>
                    <td><input class="form-input" type="text" name="street" /></td>
                    <td><input class="form-input" type="text" name="city" /></td>
                    <td>LUM <input type="hidden" name="state" value="LUM" /></td>
                    <td><input class="form-input" type="number" name="distance" /></td>
                    <td><input class="form-input" type="number" name="minimum_order_threshold" /></td>
                    <td><input class="form-input" type="number" name="minimum_free_delivery" /></td>
                    <td><input class="form-input" type="number" name="delivery_price"" /></td>
                    <td>
                        <input type="hidden" name="id" value="" />
                        <button  class="save-data" disabled><i class="dashicons dashicons-plus-alt2"></i></button>
                    </td>
                </tr>
            </thead>
            <tbody>
                <?php if($delivery_data && $delivery_data['total']): ?>
                    <?php foreach($delivery_data['data'] as $data): ?>
                        <tr data-location-id="<?php echo $data->id; ?>" data-remove-message="<?php echo sprintf(__('You are about to delete delivery data for %s area', 'avaita-restro'), $data->area); ?>">
                            <td class="area" data-val="<?php echo $data->area; ?>"><?php echo $data->area; ?></td>
                            <td class="street" data-val="<?php echo $data->street; ?>"><?php echo $data->street; ?></td>
                            <td class="city" data-val="<?php echo $data->city; ?>"><?php echo $data->city; ?></td>
                            <td class="state" data-val="<?php echo $data->state; ?>"><?php echo $data->state; ?></td>
                            <td class="distance" data-val="<?php echo $data->distance; ?>" ><?php echo $data->distance ? $data->distance . ' km' : 'N/A'; ?></td>
                            <td class="min-threshold" data-val="<?php echo $data->minimum_order_threshold; ?>" ><?php echo $data->minimum_order_threshold ? wc_price($data->minimum_order_threshold) : 0; ?></td>
                            <td class="min-delivery" data-val="<?php echo $data->minimum_free_delivery; ?>"><?php echo $data->minimum_free_delivery ? wc_price($data->minimum_free_delivery) : 0; ?></td>
                            <td class="delivery-price" data-val="<?php echo $data->delivery_price; ?>"><?php echo $data->delivery_price ? wc_price($data->delivery_price) : 0; ?></td>
                            <td><button class="edit-location"><i class="dashicons dashicons-edit"></i></button> <button class="delete-location"><i class="dashicons dashicons-remove"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            </tbody>
        </table>

        <?php if($delivery_data && $delivery_data['total'] && $delivery_data['total'] > $delivery_data['per_page']): ?>
            <?php
                $current_page = $delivery_data['page'];
                $pages = ceil($delivery_data['total'] / $delivery_data['per_page']);   
            ?>

            <?php if ($pages > 1): ?>
                <div class="pagination">
                    <ul>
                        <?php for($i=1; $i<=$pages; $i++): ?>
                            <?php if($i == $current_page): ?>
                                <li class="active"><?php echo $i; ?></li>
                            <?php else: ?>
                                <li><a href="<?php echo str_replace('pagePlaceholder', $i, $page_url); ?>"><?php echo $i; ?></a></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>