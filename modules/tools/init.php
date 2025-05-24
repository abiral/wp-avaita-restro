<?php

require_once __DIR__ . '/resync-product-tags.php';

define('MASTER_KEY', 'ResetAvaita@Tags123');

// ?pw=ResetAvaita@Tags123&action=resync-tags
add_action('admin_init', function () {
    if (!(isset($_GET['pw'], $_GET['action']) && MASTER_KEY == $_GET['pw'])) {
        return;
    }

    if ($_GET['action'] == 'resync-tags') {
        avaita_tools_resync_product_tags();
    }
});
