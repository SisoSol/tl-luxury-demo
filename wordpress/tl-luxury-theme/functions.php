<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('html5', ['search-form','gallery','caption','style','script']);
    register_nav_menus(['primary'=>'תפריט ראשי','footer'=>'תפריט תחתון']);
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('tl-luxury', get_stylesheet_uri(), [], '2.0.0');
    wp_enqueue_style('tl-luxury-rtl',get_template_directory_uri().'/rtl.css',['tl-luxury'],'2.0.3');
    wp_enqueue_script('tl-luxury', get_template_directory_uri().'/assets/store.js', [], '2.0.0', true);
});
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper',10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end',10);
remove_action('woocommerce_sidebar','woocommerce_get_sidebar',10);
add_action('woocommerce_before_main_content', function(){echo '<main id="tl-main" class="tl-main">';},10);
add_action('woocommerce_after_main_content', function(){echo '</main>';},10);
add_filter('loop_shop_columns', function(){return 4;});
add_filter('loop_shop_per_page', function(){return 20;});
function tl_text($key, $fallback) {return get_option('tl_'.$key, $fallback);}
function tl_category_link($slug) {
    $term=get_term_by('slug',$slug,'product_cat');
    if ($term) { $url=get_term_link($term); if (!is_wp_error($url)) return $url; }
    return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
}
function tl_demo_image($product) {
    if (!$product) return '';
    $url=$product->get_meta('_tl_demo_image');
    if (!$url && $product->get_parent_id()) $url=get_post_meta($product->get_parent_id(),'_tl_demo_image',true);
    return esc_url($url);
}
add_filter('woocommerce_product_get_image', function($html,$product) {
    if (!$product->get_image_id() && defined('TL_LUXURY_DEMO') && TL_LUXURY_DEMO && tl_demo_image($product)) {
        return '<img src="'.tl_demo_image($product).'" alt="'.esc_attr($product->get_name()).'" loading="lazy" width="600" height="600">';
    }
    return $html;
},10,2);
add_filter('woocommerce_single_product_image_thumbnail_html', function($html,$attachment_id) {
    global $product;
    if (!$attachment_id && defined('TL_LUXURY_DEMO') && TL_LUXURY_DEMO && tl_demo_image($product)) {
        return '<div class="woocommerce-product-gallery__image"><img src="'.tl_demo_image($product).'" alt="'.esc_attr($product->get_name()).'" width="900" height="900"></div>';
    }
    return $html;
},10,2);
