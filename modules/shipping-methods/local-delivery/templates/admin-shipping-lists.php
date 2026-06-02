<?php
global $avaita_local_shipping_db;
$page_number  = isset($_GET['page_number']) && $_GET['page_number'] ? absint($_GET['page_number']) : 1;
$page         = isset($_GET['page']) && $_GET['page'] ? sanitize_text_field($_GET['page']) : 'ava-restro';
$query_args   = array();

// --- Filter (cascading) + sort params ---
$f_city     = isset($_GET['f_city'])     ? sanitize_text_field(wp_unslash($_GET['f_city']))     : '';
$f_area     = isset($_GET['f_area'])     ? sanitize_text_field(wp_unslash($_GET['f_area']))     : '';
$f_sub_area = isset($_GET['f_sub_area']) ? sanitize_text_field(wp_unslash($_GET['f_sub_area'])) : '';
$search     = isset($_GET['search_query']) ? sanitize_text_field(wp_unslash($_GET['search_query'])) : '';
$orderby    = isset($_GET['orderby'])    ? sanitize_key($_GET['orderby'])                       : '';
$order      = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'asc' : 'desc';

// Build cascading option lists from existing helpers, and validate the chain:
// drop a child filter (and its descendants) if its parent value isn't valid.
$cities    = $avaita_local_shipping_db->get_all_cities();
$areas     = $f_city ? $avaita_local_shipping_db->get_all_areas($f_city) : [];
if ($f_area && !in_array($f_area, wp_list_pluck($areas, 'area'), true)) { $f_area = $f_sub_area = ''; }
$sub_areas = ($f_city && $f_area) ? $avaita_local_shipping_db->get_all_sub_areas($f_city, $f_area)['data'] : [];
if ($f_sub_area && !in_array($f_sub_area, wp_list_pluck($sub_areas, 'sub_area'), true)) { $f_sub_area = ''; }

$has_filters = ($f_city || $f_area || $f_sub_area || $search !== '');

$delivery_data = $avaita_local_shipping_db->get_delivery_data([
    'city' => $f_city, 'area' => $f_area, 'sub_area' => $f_sub_area, 'search' => $search,
    'orderby' => $orderby, 'order' => $order, 'page' => $page_number,
]);

if ($page) {
    $query_args['page'] = $page;
}

foreach (['f_city' => $f_city, 'f_area' => $f_area, 'f_sub_area' => $f_sub_area, 'search_query' => $search] as $k => $v) {
    if ($v !== '') { $query_args[$k] = $v; }
}
if ($orderby) {
    $query_args['orderby'] = $orderby;
    $query_args['order']   = $order;
}

$query_args['page_number'] = 'pagePlaceholder';

$page_url = admin_url('admin.php') . '?' . http_build_query($query_args, '', '&');

$total_items = ($delivery_data && isset($delivery_data['total'])) ? (int) $delivery_data['total'] : 0;

$export_url = add_query_arg(
    '_wpnonce',
    wp_create_nonce('wp_rest'),
    get_rest_url() . AVAITA_API_NAMESPACE . '/admin/delivery-addresses/export'
);

// Build a sortable-column header link that toggles direction and resets to page 1,
// preserving the active filters carried in $query_args.
$sort_link = function ($key, $label) use ($query_args, $orderby, $order) {
    $is_active = ($orderby === $key);
    $next      = ($is_active && $order === 'asc') ? 'desc' : 'asc';
    $args      = array_merge($query_args, ['orderby' => $key, 'order' => $next, 'page_number' => 1]);
    $url       = admin_url('admin.php') . '?' . http_build_query($args, '', '&');
    $cls       = $is_active ? 'sorted ' . $order : 'sortable';
    // Active column shows the current direction; inactive (faded) hints the next direction.
    $dir       = $is_active ? $order : $next;
    $icon      = $dir === 'asc' ? 'dashicons-arrow-up' : 'dashicons-arrow-down';
    return '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '"><span>' . esc_html($label)
        . '</span><span class="sorting-indicator dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span></a>';
};

// Small info icon with a hover/focus tooltip, used to explain shortened column headers.
$info_tip = function ($text) {
    return '<span class="avaita-tip" tabindex="0" role="img" aria-label="' . esc_attr($text) . '" data-tip="' . esc_attr($text) . '">'
        . '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span></span>';
};
?>

<style>
    /* ---------- Theme tokens ---------- */
    .avaita-delivery-wrap {
        --ava-primary: #6366f1;
        --ava-primary-dark: #4f46e5;
        --ava-primary-soft: #eef2ff;
        --ava-primary-border: #c7d2fe;
        --ava-accent: #f59e0b;
        --ava-accent-soft: #fef3c7;
        --ava-danger: #dc2626;
        --ava-danger-soft: #fee2e2;
        --ava-success: #10b981;
        --ava-text: #1f2937;
        --ava-text-muted: #6b7280;
        --ava-line: #e5e7eb;
        --ava-card-bg: #ffffff;
        --ava-page-bg: #f9fafb;
        margin-top: 12px;
    }

    /* ---------- Page header ---------- */
    .avaita-delivery-wrap .avaita-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin: 0 0 20px;
        padding: 18px 22px;
        background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
        border: 1px solid var(--ava-primary-border);
        border-radius: 8px;
    }
    .avaita-delivery-wrap .avaita-header h2 {
        margin: 0 0 4px;
        font-size: 18px;
        font-weight: 700;
        line-height: 1.2;
        color: var(--ava-text);
    }
    .avaita-delivery-wrap .avaita-header p {
        margin: 0;
        color: var(--ava-text-muted);
        font-size: 13px;
    }
    .avaita-delivery-wrap .avaita-header-actions {
        display: flex;
        align-items: center;
        gap: 14px;
        justify-content: flex-end;
    }
    .avaita-delivery-wrap .avaita-bulk-actions {
        display: inline-flex;
        gap: 6px;
        align-items: center;
        padding-right: 14px;
        border-right: 1px solid var(--ava-primary-border);
    }
    .avaita-delivery-wrap .avaita-bulk-actions .button {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        line-height: 1;
    }
    .avaita-delivery-wrap .avaita-bulk-actions .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        line-height: 1;
    }
    .avaita-delivery-wrap .avaita-filter-form {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
    }
    .avaita-delivery-wrap .avaita-filter-search {
        min-width: 200px;
        border-color: var(--ava-primary-border);
    }
    .avaita-delivery-wrap .avaita-filter-search:focus {
        border-color: var(--ava-primary);
        box-shadow: 0 0 0 1px var(--ava-primary);
    }
    .avaita-delivery-wrap .avaita-filter-select {
        min-width: 150px;
        max-width: 200px;
        border-color: var(--ava-primary-border);
    }
    .avaita-delivery-wrap .avaita-filter-select:focus {
        border-color: var(--ava-primary);
        box-shadow: 0 0 0 1px var(--ava-primary);
    }
    .avaita-delivery-wrap .avaita-filter-select:disabled {
        background: var(--ava-page-bg);
        color: var(--ava-text-muted);
        cursor: not-allowed;
    }
    .avaita-delivery-wrap .avaita-filter-clear {
        display: inline-flex;
        align-items: center;
        height: 30px;            /* match WP .button height */
        padding: 0 12px;
        border: 1px solid var(--ava-line);
        border-radius: 4px;
        background: #fff;
        color: var(--ava-text-muted);
        text-decoration: none;
        line-height: 1;
    }
    .avaita-delivery-wrap .avaita-filter-clear:hover,
    .avaita-delivery-wrap .avaita-filter-clear:focus {
        border-color: var(--ava-primary);
        background: var(--ava-primary-soft);
        color: var(--ava-primary-dark);
        box-shadow: none;
    }
    /* Sortable column headers */
    .avaita-delivery-table thead .avaita-sortable a {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: inherit;
        text-decoration: none;
        vertical-align: middle;
    }
    .avaita-delivery-table thead .avaita-sortable a:hover { color: var(--ava-primary-dark); }
    .avaita-delivery-table thead .avaita-sortable .sorting-indicator {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        width: 14px;
        height: 14px;
        line-height: 1;
    }
    .avaita-delivery-table thead .avaita-sortable a.sorted .sorting-indicator { color: var(--ava-primary); }
    .avaita-delivery-table thead .avaita-sortable a.sortable .sorting-indicator {
        color: var(--ava-text-muted);
        opacity: .4;
    }
    .avaita-delivery-table thead .avaita-sortable a.sortable:hover .sorting-indicator { opacity: 1; }

    /* Info icon + tooltip on shortened column headers */
    .avaita-delivery-table thead .avaita-tip {
        position: relative;
        display: inline-flex;
        align-items: center;
        margin-left: 4px;
        color: var(--ava-text-muted);
        cursor: help;
        vertical-align: middle;
    }
    .avaita-delivery-table thead .avaita-tip:hover,
    .avaita-delivery-table thead .avaita-tip:focus { color: var(--ava-primary); outline: none; }
    .avaita-delivery-table thead .avaita-tip .dashicons {
        font-size: 15px; width: 15px; height: 15px; line-height: 1;
    }
    .avaita-delivery-table thead .avaita-tip::after {
        content: attr(data-tip);
        position: absolute;
        top: calc(100% + 9px);
        right: 0;
        z-index: 30;
        width: max-content;
        max-width: 220px;
        padding: 7px 10px;
        background: #1f2937;
        color: #fff;
        font-size: 11px;
        font-weight: 500;
        line-height: 1.45;
        letter-spacing: normal;
        text-transform: none;
        text-align: left;
        white-space: normal;
        border-radius: 6px;
        box-shadow: 0 4px 14px rgba(17,24,39,.22);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-3px);
        transition: opacity .12s ease, transform .12s ease;
        pointer-events: none;
    }
    .avaita-delivery-table thead .avaita-tip::before {
        content: "";
        position: absolute;
        top: calc(100% + 3px);
        right: 4px;
        border: 6px solid transparent;
        border-bottom-color: #1f2937;
        z-index: 31;
        opacity: 0;
        visibility: hidden;
        transition: opacity .12s ease;
        pointer-events: none;
    }
    .avaita-delivery-table thead .avaita-tip:hover::after,
    .avaita-delivery-table thead .avaita-tip:focus::after {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .avaita-delivery-table thead .avaita-tip:hover::before,
    .avaita-delivery-table thead .avaita-tip:focus::before {
        opacity: 1;
        visibility: visible;
    }
    @media (max-width: 1100px) {
        .avaita-delivery-wrap .avaita-header {
            flex-direction: column;
            align-items: stretch;
        }
        .avaita-delivery-wrap .avaita-header-actions {
            flex-wrap: wrap;
            justify-content: flex-start;
        }
        .avaita-delivery-wrap .avaita-bulk-actions {
            padding-right: 0;
            border-right: 0;
        }
    }

    /* ---------- Card ---------- */
    .avaita-delivery-card {
        background: var(--ava-card-bg);
        border: 1px solid var(--ava-line);
        box-shadow: 0 1px 3px rgba(17,24,39,.06), 0 1px 2px rgba(17,24,39,.04);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .avaita-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 0;
        padding: 14px 22px;
        background: linear-gradient(90deg, var(--ava-primary) 0%, #8b5cf6 100%);
        color: #fff;
    }
    .avaita-card-title {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: #fff;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .avaita-card-title::before {
        content: "";
        display: inline-block;
        width: 8px; height: 8px;
        border-radius: 50%;
        background: rgba(255,255,255,.85);
    }
    .avaita-card-mode-tag {
        display: inline-flex;
        align-items: center;
        padding: 3px 12px;
        background: rgba(255,255,255,.22);
        color: #fff;
        border: 1px solid rgba(255,255,255,.4);
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .avaita-card-mode-tag.is-edit {
        background: var(--ava-accent-soft);
        color: #92400e;
        border-color: var(--ava-accent);
    }
    .avaita-card-body { padding: 18px 22px 4px; }

    /* ---------- Sections ---------- */
    .avaita-section { margin-bottom: 18px; }
    .avaita-section-title {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 10px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--ava-primary-dark);
    }
    .avaita-section-title .dashicons {
        font-size: 14px; width: 14px; height: 14px;
        color: var(--ava-primary);
    }
    .avaita-section-help {
        margin: -4px 0 10px;
        font-size: 12px;
        color: var(--ava-text-muted);
    }

    /* Reset table chrome so tr.input-area can host the card layout */
    .avaita-form-shell,
    .avaita-form-shell > tbody,
    .avaita-form-shell > tbody > tr,
    .avaita-form-shell > tbody > tr > td {
        display: block;
        width: auto;
        border: 0;
        padding: 0;
        margin: 0;
        background: transparent;
    }

    /* ---------- Form grid ---------- */
    .avaita-form-grid {
        display: grid;
        gap: 12px 16px;
    }
    .avaita-form-grid.cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .avaita-form-grid.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .avaita-form-grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .avaita-form-grid .avaita-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
    .avaita-form-grid label {
        font-size: 11px;
        font-weight: 700;
        color: var(--ava-text-muted);
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .avaita-form-grid .avaita-field-hint {
        font-size: 11px;
        color: var(--ava-text-muted);
        line-height: 1.35;
    }
    .avaita-form-grid .avaita-field-hint--inline {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .avaita-form-grid .avaita-field-hint--inline .dashicons {
        font-size: 14px; width: 14px; height: 14px;
        color: var(--ava-primary);
    }
    .avaita-form-grid input[type="text"],
    .avaita-form-grid input[type="number"] {
        width: 100%;
        max-width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 5px;
        padding: 6px 10px;
        font-size: 13px;
        transition: border-color .12s, box-shadow .12s;
    }
    .avaita-form-grid input[type="text"]:focus,
    .avaita-form-grid input[type="number"]:focus {
        border-color: var(--ava-primary);
        box-shadow: 0 0 0 3px rgba(99,102,241,.15);
        outline: 0;
    }
    .avaita-form-grid .avaita-static-field {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 0 12px;
        background: var(--ava-primary-soft);
        border: 1px solid var(--ava-primary-border);
        color: var(--ava-primary-dark);
        border-radius: 5px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
    }

    /* Pricing row: flat 4-column grid; Distance is marked as the driver
       via a colored left border + label color. Auto-calculated fields
       carry a small "AUTO" badge so the semantics remain obvious. */
    .avaita-pricing-row .avaita-field {
        position: relative;
        padding: 8px 10px 8px 12px;
        border-radius: 6px;
    }
    .avaita-pricing-row .avaita-field--driver {
        background: var(--ava-primary-soft);
        border: 1px solid var(--ava-primary-border);
    }
    .avaita-pricing-row .avaita-field--driver label { color: var(--ava-primary-dark); }
    .avaita-pricing-row .avaita-field--driver input[type="number"] {
        font-weight: 600;
        border-color: var(--ava-primary-border);
    }
    .avaita-pricing-row .avaita-field--derived {
        background: #fafbff;
        border: 1px solid var(--ava-line);
    }
    .avaita-auto-badge {
        position: absolute;
        top: 6px;
        right: 8px;
        display: inline-block;
        padding: 1px 6px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--ava-text-muted);
        background: #fff;
        border: 1px solid var(--ava-line);
        border-radius: 4px;
        line-height: 1.4;
    }

    /* ---------- Form actions ---------- */
    .avaita-form-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        margin: 14px -22px 0;
        padding: 10px 22px;
        background: #f9fafb;
        border-top: 1px solid var(--ava-line);
    }
    .avaita-form-actions .avaita-actions-hint {
        flex: 1;
        font-size: 12px;
        color: var(--ava-text-muted);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .avaita-form-actions .avaita-actions-hint .dashicons {
        font-size: 14px; width: 14px; height: 14px;
        color: var(--ava-text-muted);
    }
    .avaita-form-actions .button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        line-height: 1;
    }
    .avaita-form-actions .button .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        line-height: 1;
        margin: 0;
    }
    .avaita-form-actions .button-primary {
        background: var(--ava-primary);
        border-color: var(--ava-primary-dark);
        color: #fff;
        text-shadow: none;
        box-shadow: 0 1px 2px rgba(79,70,229,.25);
    }
    .avaita-form-actions .button-primary:hover,
    .avaita-form-actions .button-primary:focus {
        background: var(--ava-primary-dark);
        border-color: var(--ava-primary-dark);
        color: #fff;
    }
    .avaita-form-actions .button-primary:disabled,
    .avaita-form-actions .button-primary[disabled] {
        background: #e5e7eb !important;
        border-color: #d1d5db !important;
        color: #9ca3af !important;
        box-shadow: none !important;
        cursor: not-allowed;
    }
    .avaita-form-actions .button-primary:disabled .dashicons,
    .avaita-form-actions .button-primary[disabled] .dashicons {
        color: #9ca3af !important;
    }

    /* ---------- Table ---------- */
    .avaita-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 8px;
    }
    .avaita-delivery-table {
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid var(--ava-line);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(17,24,39,.04);
        width: 100%;
        min-width: 640px;
    }
    .avaita-delivery-table thead th {
        background: #f9fafb !important;
        color: var(--ava-text-muted);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        text-align: left;
        padding: 9px 10px;
        border-bottom: 1px solid var(--ava-line);
    }
    .avaita-delivery-table tbody td {
        vertical-align: middle;
        text-align: left;
        padding: 8px 10px;
        font-size: 13px;
        border-bottom: 1px solid var(--ava-line);
        line-height: 1.4;
        white-space: nowrap;
    }
    /* Text columns wrap and share the remaining width, so nothing is truncated
       and the table can shrink to fit without horizontal scrolling. */
    .avaita-delivery-table tbody td.area,
    .avaita-delivery-table tbody td.sub-area,
    .avaita-delivery-table tbody td.street,
    .avaita-delivery-table tbody td.city {
        white-space: normal;
        word-break: break-word;
    }
    .avaita-delivery-table td.area,
    .avaita-delivery-table td.sub-area,
    .avaita-delivery-table td.street { width: 18%; }
    .avaita-delivery-table td.city { width: 9%; }
    .avaita-delivery-table tbody tr:last-child td { border-bottom: 0; }
    .avaita-delivery-table tbody tr:nth-child(even) td { background: #fafbff; }
    .avaita-delivery-table tbody tr:hover td { background: #f5f3ff; }

    .avaita-delivery-table .col-num { text-align: right; white-space: nowrap; width: 104px; }
    .avaita-delivery-table thead .col-num { text-align: right; }
    .avaita-delivery-table .col-actions { text-align: right; width: 80px; white-space: nowrap; }
    .avaita-delivery-table .col-state { width: 70px; }
    .avaita-delivery-table .col-distance { width: 76px; }

    .avaita-delivery-table .row-actions-inline button.button-link {
        text-decoration: none;
        padding: 4px 6px;
        border-radius: 4px;
        transition: background .12s;
    }
    .avaita-delivery-table .row-actions-inline button.button-link:hover {
        background: var(--ava-primary-soft);
    }
    .avaita-delivery-table .row-actions-inline .edit-location { color: var(--ava-primary); }
    .avaita-delivery-table .row-actions-inline .edit-location:hover { color: var(--ava-primary-dark); }
    .avaita-delivery-table .row-actions-inline .delete-location { color: var(--ava-danger); }
    .avaita-delivery-table .row-actions-inline .delete-location:hover {
        color: var(--ava-danger);
        background: var(--ava-danger-soft);
    }
    .avaita-delivery-table .row-actions-inline .dashicons {
        width: 18px; height: 18px; font-size: 18px;
    }

    .avaita-delivery-table .muted { color: #9ca3af; }

    .avaita-delivery-table .state-badge {
        display: inline-block;
        padding: 3px 10px;
        background: var(--ava-primary-soft);
        color: var(--ava-primary-dark);
        border: 1px solid var(--ava-primary-border);
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
    }

    .avaita-empty {
        padding: 40px 16px;
        text-align: center;
        color: var(--ava-text-muted);
        background: #fff !important;
    }

    /* ---------- Pagination ---------- */
    .avaita-tablenav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 4px 4px;
    }
    .avaita-tablenav .displaying-num {
        color: var(--ava-text-muted);
        font-size: 13px;
    }
    .avaita-pagination-links { display: inline-flex; gap: 4px; align-items: center; }
    .avaita-pagination-links a,
    .avaita-pagination-links span.current {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 10px;
        border: 1px solid var(--ava-line);
        border-radius: 6px;
        background: #fff;
        color: var(--ava-primary-dark);
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
    }
    .avaita-pagination-links a:hover {
        background: var(--ava-primary-soft);
        border-color: var(--ava-primary-border);
    }
    .avaita-pagination-links span.current {
        background: var(--ava-primary);
        color: #fff;
        border-color: var(--ava-primary-dark);
    }
    .avaita-pagination-links .nav .dashicons {
        font-size: 16px; width: 16px; height: 16px; line-height: 1;
    }
    .avaita-pagination-links .nav.is-disabled {
        color: #d1d5db;
        background: #f9fafb;
        cursor: not-allowed;
    }
    .avaita-pagination-links .ellipsis {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 32px;
        color: var(--ava-text-muted);
        font-weight: 700;
        letter-spacing: .1em;
        user-select: none;
    }

    @media (max-width: 1100px) {
        .avaita-form-grid.cols-5 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .avaita-form-grid.cols-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 782px) {
        .avaita-form-grid.cols-5,
        .avaita-form-grid.cols-4,
        .avaita-form-grid.cols-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .avaita-delivery-wrap .avaita-header {
            flex-direction: column;
            align-items: stretch;
            padding: 14px 16px;
        }
        .avaita-card-header { padding: 12px 16px; }
        .avaita-card-body { padding: 14px 16px 4px; }
        .avaita-form-actions {
            margin: 14px -16px 0;
            padding: 10px 16px;
            flex-wrap: wrap;
        }
        .avaita-form-actions .avaita-actions-hint { display: none; }
        .avaita-form-actions .button { flex: 1; justify-content: center; }
    }
    @media (max-width: 600px) {
        .avaita-form-grid.cols-5,
        .avaita-form-grid.cols-4,
        .avaita-form-grid.cols-3 { grid-template-columns: 1fr; }
        .avaita-tablenav {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .avaita-tablenav .displaying-num {
            text-align: center;
            white-space: nowrap;
        }
        .avaita-pagination-links {
            justify-content: center;
            flex-wrap: wrap;
        }
        .avaita-pricing-row .avaita-field { padding: 10px 12px; }
        .avaita-auto-badge {
            position: static;
            display: inline-block;
            margin-bottom: 2px;
        }
        .avaita-delivery-wrap .avaita-bulk-actions {
            padding-right: 0;
            border-right: 0;
            width: 100%;
        }
        .avaita-delivery-wrap .avaita-bulk-actions .button { flex: 1; justify-content: center; }
        .avaita-delivery-wrap .avaita-filter-form { width: 100%; }
        .avaita-delivery-wrap .avaita-filter-search { min-width: 0; flex: 1 1 100%; }
        .avaita-delivery-wrap .avaita-filter-select {
            min-width: 0;
            max-width: none;
            flex: 1 1 45%;
        }
    }
</style>

<div class="avaita-delivery-wrap">
    <div id="avaita-admin-messages" style="margin-top: 12px"></div>
    <?php add_thickbox(); ?>
    <div id="avaita-loader" class="avaita-loader" style="display:none;">
        <div class="avaita-loader">
            <span id="avaita-loader-inner"></span>
        </div>
    </div>

    <div class="delivery-areas-list">
        <div class="avaita-header">
            <div>
                <h2><?php _e('Delivery Areas', 'avaita-restro'); ?></h2>
                <p><?php _e('Manage delivery areas and their respective shipping charges.', 'avaita-restro'); ?></p>
            </div>
            <div class="avaita-header-actions">
                <div class="avaita-bulk-actions">
                    <a class="button avaita-export-link" href="<?php echo esc_url($export_url); ?>" target="_blank" rel="noopener">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export CSV', 'avaita-restro'); ?>
                    </a>
                    <button type="button" class="button avaita-import-trigger">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('Import CSV', 'avaita-restro'); ?>
                    </button>
                    <input type="file" accept=".csv,text/csv" class="avaita-import-file" hidden />
                </div>
                <form class="avaita-filter-form" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr($page); ?>" />
                    <?php if ($orderby): ?>
                        <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>" />
                        <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>" />
                    <?php endif; ?>
                    <input type="search" name="search_query" class="avaita-filter-search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search area, street, city…', 'avaita-restro'); ?>" />
                    <select name="f_city" id="avaita-filter-city" class="avaita-filter-select">
                        <option value=""><?php esc_html_e('All Cities', 'avaita-restro'); ?></option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?php echo esc_attr($c->city); ?>" <?php selected($f_city, $c->city); ?>><?php echo esc_html($c->city); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="f_area" id="avaita-filter-area" class="avaita-filter-select" <?php disabled(!$f_city); ?>>
                        <option value=""><?php esc_html_e('All Areas', 'avaita-restro'); ?></option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?php echo esc_attr($a->area); ?>" <?php selected($f_area, $a->area); ?>><?php echo esc_html($a->area); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="f_sub_area" id="avaita-filter-sub-area" class="avaita-filter-select" <?php disabled(!$f_area); ?>>
                        <option value=""><?php esc_html_e('All Sub Areas', 'avaita-restro'); ?></option>
                        <?php foreach ($sub_areas as $s): ?>
                            <option value="<?php echo esc_attr($s->sub_area); ?>" <?php selected($f_sub_area, $s->sub_area); ?>><?php echo esc_html($s->sub_area); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Filter', 'avaita-restro'); ?></button>
                    <?php if ($has_filters): ?>
                        <a class="button avaita-filter-clear" href="<?php echo esc_url(admin_url('admin.php?page=' . $page)); ?>"><?php esc_html_e('Clear', 'avaita-restro'); ?></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php /*
            The JS (admin-local-delivery-form.js) finds the save button by walking up from
            each input to the nearest <tr>, so we keep the <tr class="input-area"> wrapper.
            We render it inside a bare table whose chrome is reset to block layout via
            the .avaita-form-shell class below, so it visually behaves like a card.
        */ ?>
        <table class="avaita-form-shell avaita-delivery-card"
               data-label-add="<?php esc_attr_e('Add Delivery Area', 'avaita-restro'); ?>"
               data-label-update="<?php esc_attr_e('Update Delivery Area', 'avaita-restro'); ?>"
               data-mode-tag-add="<?php esc_attr_e('New', 'avaita-restro'); ?>"
               data-mode-tag-edit="<?php esc_attr_e('Editing', 'avaita-restro'); ?>">
            <tbody>
                <tr class="input-area">
                    <td>
                        <div class="avaita-card-header">
                            <h3 class="avaita-card-title"><?php _e('Add Delivery Area', 'avaita-restro'); ?></h3>
                            <span class="avaita-card-mode-tag"><?php _e('New', 'avaita-restro'); ?></span>
                        </div>

                        <div class="avaita-card-body">
                            <div class="avaita-section">
                                <div class="avaita-section-title">
                                    <span class="dashicons dashicons-location"></span>
                                    <?php _e('Location', 'avaita-restro'); ?>
                                </div>
                                <div class="avaita-form-grid cols-5">
                                    <div class="avaita-field">
                                        <label for="avaita-fld-area"><?php _e('Area', 'avaita-restro'); ?></label>
                                        <input id="avaita-fld-area" type="text" name="area" placeholder="<?php esc_attr_e('e.g. Finance Chowk', 'avaita-restro'); ?>" />
                                    </div>
                                    <div class="avaita-field">
                                        <label for="avaita-fld-sub-area"><?php _e('Sub Area', 'avaita-restro'); ?></label>
                                        <input id="avaita-fld-sub-area" type="text" name="sub_area" />
                                    </div>
                                    <div class="avaita-field">
                                        <label for="avaita-fld-street"><?php _e('Street', 'avaita-restro'); ?></label>
                                        <input id="avaita-fld-street" type="text" name="street" />
                                    </div>
                                    <div class="avaita-field">
                                        <label for="avaita-fld-city"><?php _e('City', 'avaita-restro'); ?></label>
                                        <input id="avaita-fld-city" type="text" name="city" />
                                    </div>
                                    <div class="avaita-field">
                                        <label><?php _e('State', 'avaita-restro'); ?></label>
                                        <span class="avaita-static-field">LUM</span>
                                        <input type="hidden" name="state" value="LUM" />
                                    </div>
                                </div>
                            </div>

                            <div class="avaita-section">
                                <div class="avaita-section-title">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    <?php _e('Pricing', 'avaita-restro'); ?>
                                </div>
                                <p class="avaita-section-help"><?php _e('Enter the distance — Order Threshold, Free Threshold and Delivery Price are auto-calculated from it. You can still fine-tune them after.', 'avaita-restro'); ?></p>

                                <div class="avaita-form-grid cols-4 avaita-pricing-row">
                                    <div class="avaita-field avaita-field--driver">
                                        <label for="avaita-fld-distance"><?php _e('Distance (km)', 'avaita-restro'); ?></label>
                                        <input id="avaita-fld-distance" type="number" step="0.1" min="0" name="distance" placeholder="0.0" />
                                        <span class="avaita-field-hint"><?php _e('You enter this.', 'avaita-restro'); ?></span>
                                    </div>
                                    <div class="avaita-field avaita-field--derived">
                                        <span class="avaita-auto-badge"><?php _e('Auto', 'avaita-restro'); ?></span>
                                        <label for="avaita-fld-min-order"><?php _e('Order Threshold', 'avaita-restro'); ?></label>
                                        <input id="avaita-fld-min-order" type="number" step="0.01" min="0" name="minimum_order_threshold" />
                                        <span class="avaita-field-hint"><?php _e('Min order accepted.', 'avaita-restro'); ?></span>
                                    </div>
                                    <div class="avaita-field avaita-field--derived">
                                        <span class="avaita-auto-badge"><?php _e('Auto', 'avaita-restro'); ?></span>
                                        <label for="avaita-fld-free-thresh"><?php _e('Free Threshold', 'avaita-restro'); ?></label>
                                        <input id="avaita-fld-free-thresh" type="number" step="0.01" min="0" name="minimum_free_delivery" />
                                        <span class="avaita-field-hint"><?php _e('Free above this total.', 'avaita-restro'); ?></span>
                                    </div>
                                    <div class="avaita-field avaita-field--derived">
                                        <span class="avaita-auto-badge"><?php _e('Auto', 'avaita-restro'); ?></span>
                                        <label for="avaita-fld-price"><?php _e('Delivery Price', 'avaita-restro'); ?></label>
                                        <input id="avaita-fld-price" type="number" step="0.01" min="0" name="delivery_price" />
                                        <span class="avaita-field-hint"><?php _e('Charged below free threshold.', 'avaita-restro'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="avaita-form-actions">
                                <input type="hidden" name="id" value="" />
                                <span class="avaita-actions-hint">
                                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                                    <span class="avaita-actions-hint-text"><?php _e('Fill in the form, then save.', 'avaita-restro'); ?></span>
                                </span>
                                <button type="button" class="button cancel-edit" style="display:none;">
                                    <span class="dashicons dashicons-no-alt"></span>
                                    <?php _e('Cancel', 'avaita-restro'); ?>
                                </button>
                                <button type="button" class="button reset-form">
                                    <span class="dashicons dashicons-image-rotate"></span>
                                    <?php _e('Clear', 'avaita-restro'); ?>
                                </button>
                                <button class="button button-primary save-data"
                                        data-label-add="<?php esc_attr_e('Add Delivery Area', 'avaita-restro'); ?>"
                                        data-label-update="<?php esc_attr_e('Update Delivery Area', 'avaita-restro'); ?>"
                                        disabled>
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    <span class="save-data-label"><?php _e('Add Delivery Area', 'avaita-restro'); ?></span>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="avaita-table-scroll">
        <table class="wp-list-table widefat striped avaita-delivery-table">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Area', 'avaita-restro'); ?></th>
                    <th scope="col"><?php _e('Sub Area', 'avaita-restro'); ?></th>
                    <th scope="col"><?php _e('Street', 'avaita-restro'); ?></th>
                    <th scope="col"><?php _e('City', 'avaita-restro'); ?></th>
                    <th scope="col" class="col-state"><?php _e('State', 'avaita-restro'); ?></th>
                    <th scope="col" class="col-num col-distance avaita-sortable"><?php echo $sort_link('distance', __('Distance', 'avaita-restro')); ?></th>
                    <th scope="col" class="col-num avaita-sortable"><?php echo $sort_link('order_threshold', __('Minimum', 'avaita-restro')) . $info_tip(__('User has to cross the minimum order threshold to make an order', 'avaita-restro')); ?></th>
                    <th scope="col" class="col-num avaita-sortable"><?php echo $sort_link('free_threshold', __('Free', 'avaita-restro')) . $info_tip(__('User will get a free delivery if the specified threshold is crossed', 'avaita-restro')); ?></th>
                    <th scope="col" class="col-num avaita-sortable"><?php echo $sort_link('delivery_price', __('Delivery', 'avaita-restro')) . $info_tip(__('The delivery price will be applied for specified location', 'avaita-restro')); ?></th>
                    <th scope="col" class="col-actions"><?php _e('Actions', 'avaita-restro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($delivery_data && $delivery_data['total']): ?>
                    <?php foreach ($delivery_data['data'] as $data): ?>
                        <tr data-location-id="<?php echo esc_attr($data->id); ?>" data-remove-message="<?php echo esc_attr(sprintf(__('You are about to delete delivery data for %s area', 'avaita-restro'), $data->area)); ?>">
                            <td class="area" data-val="<?php echo esc_attr($data->area); ?>"><strong><?php echo esc_html($data->area); ?></strong></td>
                            <td class="sub-area" data-val="<?php echo esc_attr($data->sub_area); ?>"><?php echo $data->sub_area ? esc_html($data->sub_area) : '<span class="muted">—</span>'; ?></td>
                            <td class="street" data-val="<?php echo esc_attr($data->street); ?>"><?php echo $data->street ? esc_html($data->street) : '<span class="muted">—</span>'; ?></td>
                            <td class="city" data-val="<?php echo esc_attr($data->city); ?>"><?php echo esc_html($data->city); ?></td>
                            <td class="state col-state" data-val="<?php echo esc_attr($data->state); ?>"><span class="state-badge"><?php echo esc_html($data->state); ?></span></td>
                            <td class="distance col-num" data-val="<?php echo esc_attr($data->distance); ?>"><?php echo $data->distance ? esc_html($data->distance) . ' km' : '<span class="muted">N/A</span>'; ?></td>
                            <td class="min-threshold col-num" data-val="<?php echo esc_attr($data->minimum_order_threshold); ?>"><?php echo $data->minimum_order_threshold ? wc_price($data->minimum_order_threshold) : '<span class="muted">—</span>'; ?></td>
                            <td class="min-delivery col-num" data-val="<?php echo esc_attr($data->minimum_free_delivery); ?>"><?php echo $data->minimum_free_delivery ? wc_price($data->minimum_free_delivery) : '<span class="muted">—</span>'; ?></td>
                            <td class="delivery-price col-num" data-val="<?php echo esc_attr($data->delivery_price); ?>"><?php echo $data->delivery_price ? wc_price($data->delivery_price) : '<span class="muted">—</span>'; ?></td>
                            <td class="col-actions row-actions-inline">
                                <button class="button-link edit-location" title="<?php esc_attr_e('Edit', 'avaita-restro'); ?>" aria-label="<?php esc_attr_e('Edit', 'avaita-restro'); ?>"><span class="dashicons dashicons-edit"></span></button>
                                <button class="button-link is-destructive delete-location" title="<?php esc_attr_e('Delete', 'avaita-restro'); ?>" aria-label="<?php esc_attr_e('Delete', 'avaita-restro'); ?>"><span class="dashicons dashicons-trash"></span></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="avaita-empty">
                            <?php if ($has_filters): ?>
                                <?php _e('No delivery areas match the selected filters.', 'avaita-restro'); ?>
                            <?php else: ?>
                                <?php _e('No delivery areas yet. Use the form above to add your first one.', 'avaita-restro'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($delivery_data && $delivery_data['total']): ?>
            <?php
                $current_page = (int) $delivery_data['page'];
                $per_page     = (int) $delivery_data['per_page'];
                $pages        = (int) ceil($delivery_data['total'] / max(1, $per_page));
                $range_start  = (($current_page - 1) * $per_page) + 1;
                $range_end    = min($current_page * $per_page, $total_items);
            ?>
            <div class="avaita-tablenav">
                <span class="displaying-num">
                    <?php
                        printf(
                            esc_html(_n('%1$s–%2$s of %3$s item', '%1$s–%2$s of %3$s items', $total_items, 'avaita-restro')),
                            number_format_i18n($range_start),
                            number_format_i18n($range_end),
                            number_format_i18n($total_items)
                        );
                    ?>
                </span>

                <?php if ($pages > 1): ?>
                    <?php
                        $mid_size = 1;
                        $end_size = 1;

                        $pages_to_show = array();
                        for ($i = 1; $i <= min($end_size, $pages); $i++) {
                            $pages_to_show[$i] = true;
                        }
                        for ($i = max(1, $current_page - $mid_size); $i <= min($pages, $current_page + $mid_size); $i++) {
                            $pages_to_show[$i] = true;
                        }
                        for ($i = max(1, $pages - $end_size + 1); $i <= $pages; $i++) {
                            $pages_to_show[$i] = true;
                        }
                        ksort($pages_to_show);
                        $pages_to_show = array_keys($pages_to_show);

                        $page_link = function ($p) use ($page_url) {
                            return esc_url(str_replace('pagePlaceholder', $p, $page_url));
                        };
                    ?>
                    <span class="avaita-pagination-links" role="navigation" aria-label="<?php esc_attr_e('Pagination', 'avaita-restro'); ?>">
                        <?php if ($current_page > 1): ?>
                            <a class="nav" href="<?php echo $page_link($current_page - 1); ?>" aria-label="<?php esc_attr_e('Previous page', 'avaita-restro'); ?>">
                                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                            </a>
                        <?php else: ?>
                            <span class="nav is-disabled" aria-hidden="true">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                            </span>
                        <?php endif; ?>

                        <?php $prev_i = 0; ?>
                        <?php foreach ($pages_to_show as $i): ?>
                            <?php if ($prev_i && $i - $prev_i > 1): ?>
                                <span class="ellipsis" aria-hidden="true">&hellip;</span>
                            <?php endif; ?>
                            <?php if ($i === $current_page): ?>
                                <span class="current" aria-current="page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $page_link($i); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                            <?php $prev_i = $i; ?>
                        <?php endforeach; ?>

                        <?php if ($current_page < $pages): ?>
                            <a class="nav" href="<?php echo $page_link($current_page + 1); ?>" aria-label="<?php esc_attr_e('Next page', 'avaita-restro'); ?>">
                                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                            </a>
                        <?php else: ?>
                            <span class="nav is-disabled" aria-hidden="true">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
