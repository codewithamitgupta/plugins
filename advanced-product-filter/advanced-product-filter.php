<?php
/**
 * Plugin Name: Advanced Product Filter
 * Description: A plugin to add advanced filtering options for products using ACF.
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include the plugin.php file to use deactivate_plugins function
if (!function_exists('deactivate_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'apf_woocommerce_notice');
    deactivate_plugins(plugin_basename(__FILE__));
    return;
}

// Check if ACF is active
if (!in_array('advanced-custom-fields/acf.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'apf_acf_notice');
    deactivate_plugins(plugin_basename(__FILE__));
    return;
}

// Admin notice for WooCommerce
function apf_woocommerce_notice() {
    $install_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=woocommerce'), 'install-plugin_woocommerce');
    echo '<div class="error"><p><strong>Advanced Product Filter</strong> requires <strong>WooCommerce</strong> to be installed and activated. <a href="' . $install_url . '">Install WooCommerce</a>. The plugin has been deactivated.</p></div>';
}

// Admin notice for ACF
function apf_acf_notice() {
    $install_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=advanced-custom-fields'), 'install-plugin_advanced-custom-fields');
    echo '<div class="error"><p><strong>Advanced Product Filter</strong> requires <strong>Advanced Custom Fields</strong> to be installed and activated. <a href="' . $install_url . '">Install Advanced Custom Fields</a>. The plugin has been deactivated.</p></div>';
}

// Enqueue necessary scripts and styles
function apf_enqueue_scripts() {
    wp_enqueue_style('apf-styles', plugin_dir_url(__FILE__) . 'css/styles.css');
    wp_enqueue_script('apf-scripts', plugin_dir_url(__FILE__) . 'js/scripts.js', array('jquery'), '1.0.0', true);

    wp_localize_script('apf-scripts', 'apf_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'apf_enqueue_scripts');

// Register custom fields using ACF
function apf_register_acf_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_brand',
            'title' => 'Product Brand',
            'fields' => array(
                array(
                    'key' => 'field_brand',
                    'label' => 'Brand',
                    'name' => 'brand',
                    'type' => 'select',
                    'choices' => array(
                        'brand1' => 'Brand 1',
                        'brand2' => 'Brand 2',
                        'brand3' => 'Brand 3',
                    ),
                    'allow_null' => 0,
                    'multiple' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'product',
                    ),
                ),
            ),
        ));
    }
}
add_action('acf/init', 'apf_register_acf_fields');

function apf_filter_shortcode() {
    ob_start();
    ?>
    <div id="apf-filter">
        <form id="apf-filter-form">
            <div class="filter-group">
                <label for="filter-category">Category</label>
                <?php wp_dropdown_categories(array('taxonomy' => 'product_cat', 'name' => 'category', 'hide_empty' => true, 'show_option_all' => 'All Categories')); ?>
            </div>
            <div class="filter-group">
                <label for="filter-price">Price Range</label>
                <input type="number" name="min_price" placeholder="Min Price">
                <input type="number" name="max_price" placeholder="Max Price">
            </div>
            <div class="filter-group">
                <label for="filter-color">Color</label>
                <?php apf_dropdown_product_attribute('pa_color'); ?>
            </div>
            <div class="filter-group">
                <label for="filter-size">Size</label>
                <?php apf_dropdown_product_attribute('pa_size'); ?>
            </div>
            <div class="filter-group">
                <label for="filter-brand">Brand</label>
                <?php apf_dropdown_brand_attribute(); ?>
            </div>
            <div class="filter-group">
                <label for="filter-availability">Availability</label>
                <select name="availability">
                    <option value="all">All</option>
                    <option value="in_stock">In Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
            </div>
            <button type="submit">Filter</button>
            <button type="button" id="reset-filters">Reset</button>
        </form>
        <div id="apf-results"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('apf_filter', 'apf_filter_shortcode');


// Display the filter shortcode on the front page
function apf_display_filter_on_front_page() {
    if (is_front_page()) {
        echo do_shortcode('[apf_filter]');
    }
}
add_action('wp_head', 'apf_display_filter_on_front_page');

// Dropdown for product attribute
function apf_dropdown_product_attribute($taxonomy) {
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
    ));
    
    if (!empty($terms) && !is_wp_error($terms)) {
        echo '<select name="' . esc_attr($taxonomy) . '">';
        echo '<option value="">Select ' . ucfirst(str_replace('pa_', '', $taxonomy)) . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
        }
        echo '</select>';
    }
}

// Dropdown for brand attribute
function apf_dropdown_brand_attribute() {
    $brands = get_terms(array(
        'taxonomy' => 'brand',
        'hide_empty' => true,
    ));

    if (!empty($brands) && !is_wp_error($brands)) {
        echo '<select name="brand">';
        echo '<option value="">Select Brand</option>';
        foreach ($brands as $brand) {
            echo '<option value="' . esc_attr($brand->slug) . '">' . esc_html($brand->name) . '</option>';
        }
        echo '</select>';
    }
}

// Modify WooCommerce product query to filter products
function apf_modify_shop_query($query) {
    if (!is_admin() && $query->is_main_query() && is_shop()) {
        $meta_query = array('relation' => 'AND');
        $tax_query = array('relation' => 'AND');

        if (isset($_GET['category']) && !empty($_GET['category'])) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'id',
                'terms'    => intval($_GET['category']),
            );
        }

        if ((isset($_GET['min_price']) && !empty($_GET['min_price'])) || (isset($_GET['max_price']) && !empty($_GET['max_price']))) {
            if (!empty($_GET['min_price'])) {
                $meta_query[] = array(
                    'key'     => '_price',
                    'value'   => floatval($_GET['min_price']),
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                );
            }

            if (!empty($_GET['max_price'])) {
                $meta_query[] = array(
                    'key'     => '_price',
                    'value'   => floatval($_GET['max_price']),
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                );
            }

            $query->set('meta_query', $meta_query);
        }

        if (isset($_GET['pa_color']) && !empty($_GET['pa_color'])) {
            $tax_query[] = array(
                'taxonomy' => 'pa_color',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_GET['pa_color']),
            );
        }

        if (isset($_GET['pa_size']) && !empty($_GET['pa_size'])) {
            $tax_query[] = array(
                'taxonomy' => 'pa_size',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_GET['pa_size']),
            );
        }

        if (isset($_GET['brand']) && !empty($_GET['brand'])) {
            $meta_query[] = array(
                'key'     => 'brand',
                'value'   => sanitize_text_field($_GET['brand']),
                'compare' => 'LIKE',
            );
        }

        if (isset($_GET['availability']) && $_GET['availability'] != 'all') {
            $meta_query[] = array(
                'key'     => '_stock_status',
                'value'   => $_GET['availability'] == 'in_stock' ? 'instock' : 'outofstock',
            );
        }

        $query->set('meta_query', $meta_query);
        $query->set('tax_query', $tax_query);
    }
}
add_action('pre_get_posts', 'apf_modify_shop_query');

// AJAX handler for filtering products
function apf_filter_products() {
    $meta_query = array('relation' => 'AND');
    $tax_query = array('relation' => 'AND');

    if (!empty($_POST['category'])) {
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'id',
            'terms'    => intval($_POST['category']),
        );
    }

    if (!empty($_POST['min_price']) || !empty($_POST['max_price'])) {
        $meta_query[] = array(
            'key'     => '_price',
            'value'   => array(floatval($_POST['min_price']), floatval($_POST['max_price'])),
            'compare' => 'BETWEEN',
            'type'    => 'NUMERIC',
        );
    }

    if (!empty($_POST['pa_color'])) {
        $tax_query[] = array(
            'taxonomy' => 'pa_color',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_POST['pa_color']),
        );
    }

    if (!empty($_POST['pa_size'])) {
        $tax_query[] = array(
            'taxonomy' => 'pa_size',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_POST['pa_size']),
        );
    }

    if (!empty($_POST['brand'])) {
        $meta_query[] = array(
            'key'     => 'brand',
            'value'   => sanitize_text_field($_POST['brand']),
            'compare' => 'LIKE',
        );
    }

    if (!empty($_POST['availability']) && $_POST['availability'] != 'all') {
        $meta_query[] = array(
            'key'     => '_stock_status',
            'value'   => $_POST['availability'] == 'in_stock' ? 'instock' : 'outofstock',
        );
    }

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'meta_query'     => $meta_query,
        'tax_query'      => $tax_query,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }
    } else {
        echo 'No products found';
    }

    wp_die();
}
add_action('wp_ajax_apf_filter', 'apf_filter_products');
add_action('wp_ajax_nopriv_apf_filter', 'apf_filter_products');

add_action('woocommerce_before_shop_loop', 'apf_display_filter_on_shop_page', 20);

function apf_display_filter_on_shop_page() {
    echo do_shortcode('[apf_filter]');
}