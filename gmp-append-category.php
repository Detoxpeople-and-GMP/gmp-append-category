<?php
/*
Plugin Name: GMP Append Category to Products
Description: Select a category and products to append the category to the selected products.
Version: 1.0.1
Author: ShalomT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('admin_menu', 'gmp_append_category_menu');

function gmp_append_category_menu()
{
    add_menu_page(
        'Append Category to Products',
        'Append Category',
        'manage_woocommerce',
        'gmp-append-category',
        'gmp_append_category_page',
        'dashicons-category',
        56
    );
}

function gmp_append_category_page()
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    if (isset($_POST['gmp_save_category'])) {
        gmp_handle_form_submission();
    }

    // Get all categories
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);

    ?>
    <div class="wrap">
        <h1>Append Category to Products</h1>
        <form method="post">
            <input type="hidden" name="gmp_save_category" value="1">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="gmp_categories">Select Categories</label></th>
                    <td>
                        <select name="gmp_categories[]" id="gmp_categories" multiple style="width: 100%;"></select>
                        <p class="description">Start typing to search for categories.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><label for="gmp_products">Search and Select Products</label></th>
                    <td>
                        <select name="gmp_products[]" id="gmp_products" multiple style="width: 100%;"></select>
                        <p class="description">Start typing to search for products.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save'); ?>
        </form>
    </div>
    <?php
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('#gmp_products').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term, // search term
                            action: 'gmp_search_products'
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 3,
                placeholder: 'Search for products',
                allowClear: true
            });

            $('#gmp_categories').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term, // search term
                            action: 'gmp_search_categories'
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 3,
                placeholder: 'Search for categories',
                allowClear: true
            });
        });
    </script>


    <?php
}

add_action('wp_ajax_gmp_search_products', 'gmp_search_products');

function gmp_search_products()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error();
        return;
    }

    // Get the search term
    $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    // Query products matching the search term
    $args = [
        'post_type' => 'product',
        'posts_per_page' => 10,
        's' => $search_term,
        'post_status' => 'publish',
    ];

    $query = new WP_Query($args);
    $results = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $results[] = [
                'id' => get_the_ID(),
                'text' => get_the_title(),
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json($results);
}

add_action('wp_ajax_gmp_search_categories', 'gmp_search_categories');

function gmp_search_categories()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error();
        return;
    }

    // Get the search term
    $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    // Query categories matching the search term
    $args = [
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'name__like' => $search_term,
        'number' => 10,
    ];

    $categories = get_terms($args);
    $results = [];

    if (!is_wp_error($categories)) {
        foreach ($categories as $category) {
            $results[] = [
                'id' => $category->term_id,
                'text' => $category->name,
            ];
        }
    }

    wp_send_json($results);
}


add_action('admin_enqueue_scripts', 'gmp_enqueue_scripts');

function gmp_enqueue_scripts($hook)
{
    if ($hook !== 'toplevel_page_gmp-append-category') {
        return;
    }

    wp_enqueue_script('select2', plugins_url('js/select2.min.js', __FILE__), array('jquery'), '4.0.13', true);
    wp_enqueue_style('select2', plugins_url('css/select2.min.css', __FILE__), array(), '4.0.13');
}


function gmp_handle_form_submission()
{
    if (!isset($_POST['gmp_categories']) || !isset($_POST['gmp_products'])) {
        return;
    }

    $category_ids = array_map('intval', $_POST['gmp_categories']);
    $product_ids = array_map('intval', $_POST['gmp_products']);

    foreach ($product_ids as $product_id) {
        foreach ($category_ids as $category_id) {
            wp_set_object_terms($product_id, $category_id, 'product_cat', true);
        }
    }

    add_option('gmp_success_message', 'Categories successfully appended to the selected products.');
}


function gmp_show_success_message()
{
    if ($message = get_option('gmp_success_message')) {
        echo '<div class="updated notice"><p>' . esc_html($message) . '</p></div>';
        delete_option('gmp_success_message');
    }
}
