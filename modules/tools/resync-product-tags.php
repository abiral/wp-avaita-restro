<?php

function avaita_tools_resync_product_tags()
{
    $terms = get_terms(array(
        'taxonomy'      => 'product_tag',
        'orderby'       => 'name',
        'order'         => 'ASC',
        'hide_empty'    => false,
        'search'        => '#',
        'number'        => 100,
    ));
    if (! empty($terms) && ! is_wp_error($terms)) {
        foreach ($terms as $term) {
            wp_update_term($term->term_id, 'product_tag', array('name' => $term->slug));
        }
    }

    die(count($terms) . ' tags synced');
}
