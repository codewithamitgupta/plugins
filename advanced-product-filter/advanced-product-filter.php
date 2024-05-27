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
                    'type' => 'text',
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


// Create shortcode for the filter interface
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
                <input type="text" name="color" placeholder="Color">
            </div>
            <div class="filter-group">
                <label for="filter-size">Size</label>
                <input type="text" name="size" placeholder="Size">
            </div>
            <div class="filter-group">
                <label for="filter-brand">Brand</label>
                <input type="text" name="brand" placeholder="Brand">
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
        </form>
        <div id="apf-results"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('apf_filter', 'apf_filter_shortcode');

// Handle AJAX filtering
function apf_filter_products() {
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
    );

    if (!empty($_POST['category'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field' => 'id',
            'terms' => intval($_POST['category']),
        );
    }

    if (!empty($_POST['min_price']) || !empty($_POST['max_price'])) {
        $args['meta_query'][] = array(
            'key' => '_price',
            'value' => array(floatval($_POST['min_price']), floatval($_POST['max_price'])),
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC',
        );
    }

    if (!empty($_POST['color'])) {
        $args['meta_query'][] = array(
            'key' => 'color',
            'value' => sanitize_text_field($_POST['color']),
            'compare' => 'LIKE',
        );
    }

    if (!empty($_POST['size'])) {
        $args['meta_query'][] = array(
            'key' => 'size',
            'value' => sanitize_text_field($_POST['size']),
            'compare' => 'LIKE',
        );
    }

    if (!empty($_POST['brand'])) {
        $args['meta_query'][] = array(
            'key' => 'brand',
            'value' => sanitize_text_field($_POST['brand']),
            'compare' => 'LIKE',
        );
    }

    if (!empty($_POST['availability']) && $_POST['availability'] != 'all') {
        $args['meta_query'][] = array(
            'key' => '_stock_status',
            'value' => $_POST['availability'] == 'in_stock' ? 'instock' : 'outofstock',
        );
    }

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

// Display the filter shortcode on the WooCommerce shop page
function apf_display_filter_on_shop_page() {
    if (is_shop()) {
        echo do_shortcode('[apf_filter]');
    }
}
add_action('woocommerce_before_shop_loop', 'apf_display_filter_on_shop_page');
