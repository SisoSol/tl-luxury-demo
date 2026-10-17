<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    add_theme_support('html5', ['search-form','gallery','caption','style','script']);
    register_nav_menus(['primary'=>'תפריט ראשי','footer'=>'תפריט תחתון']);
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('tl-luxury', get_stylesheet_uri(), [], '5.0.0');
    wp_enqueue_style('tl-luxury-rtl',get_template_directory_uri().'/rtl.css',['tl-luxury'],'5.0.0');
    wp_enqueue_script('tl-luxury', get_template_directory_uri().'/assets/store.js', [], '5.0.0', true);
});
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper',10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end',10);
remove_action('woocommerce_sidebar','woocommerce_get_sidebar',10);
add_action('woocommerce_before_main_content', function(){echo '<main id="tl-main" class="tl-main">';},10);
add_action('woocommerce_after_main_content', function(){echo '</main>';},10);
add_filter('loop_shop_columns', function(){return 4;});
add_filter('loop_shop_per_page', function(){return 20;});
function tl_text($key, $fallback) {
    $value=get_option('tl_'.$key, $fallback);
    if($key==='hero_title' && preg_match('/^יוקרה שנמצאת\s+בפרטים\.?$/u',$value))return "יהלום הוא לא\nהדרך היחידה ליוקרה.";
    return $value;
}
add_filter('the_content',function($content){return is_page('about')?str_replace('T&L — יוקרה שנמצאת בפרטים.','T&L — יהלום הוא לא הדרך היחידה ליוקרה.',$content):$content;});
function tl_image($key,$fallback){$id=absint(get_option('tl_'.$key,0));$url=$id?wp_get_attachment_image_url($id,'full'):false;return $url?:get_template_directory_uri().'/assets/'.$fallback;}
function tl_theme_sales_open(){return function_exists('tl_sales_are_open') && tl_sales_are_open();}
function tl_public_page_url($slug){$page=get_page_by_path($slug);return $page && $page->post_status==='publish'?get_permalink($page):false;}
add_action('woocommerce_single_product_summary',function(){
    echo '<div class="tl-product-service">';
    $sizes=tl_public_page_url('sizes');$delivery=tl_public_page_url('delivery');$contact=tl_public_page_url('contact');
    if($sizes)echo '<a href="'.esc_url($sizes).'">מדריך מידות</a>';
    if($delivery)echo '<a href="'.esc_url($delivery).'">משלוחים והחזרות</a>';
    if($contact)echo '<a href="'.esc_url($contact).'">שאלה על התכשיט?</a>';
    if(!tl_theme_sales_open())echo '<p>הרכישה באתר תיפתח בקרוב.</p>';
    echo '</div>';
},35);
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
