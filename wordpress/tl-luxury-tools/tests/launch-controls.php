<?php
/** Run only through runPHP in an isolated WordPress Playground or staging acceptance installation. */
defined('ABSPATH') || exit;
if (!defined('TL_LUXURY_AUTOMATED_TESTS') || TL_LUXURY_AUTOMATED_TESTS !== true || (!tl_is_demo() && wp_get_environment_type() !== 'staging')) throw new RuntimeException('Isolated automated-test environment required.');
$tl_test_results = [];
$tl_test_assert = function($condition, $label) use (&$tl_test_results) {
    if (!$condition) throw new RuntimeException('T&L LAUNCH TEST FAILED: '.$label);
    $tl_test_results[] = $label;
};
$tl_test_options = ['tl_sales_state','tl_sales_closed_reason','tl_fulfilment','tl_hero_title','tl_payment_launch_fingerprint','tl_launch_confirmations'];
$tl_test_original = [];
foreach ($tl_test_options as $key) $tl_test_original[$key] = get_option($key, null);
$tl_test_products = []; $tl_test_attachments = [];
$tl_test_user = get_current_user_id();
try {
    tl_close_sales();
    $tl_test_assert(!tl_sales_are_open(), 'sales default closed');
    $before_products = count(wc_get_products(['limit'=>-1,'return'=>'ids']));
    $before_users = count(get_users(['fields'=>'ID']));
    update_option('tl_hero_title', 'OWNER CONTENT MUST SURVIVE');
    tl_activate_store_tools();
    $tl_test_assert(get_option('tl_hero_title') === 'OWNER CONTENT MUST SURVIVE', 'activation preserves owner content');
    $tl_test_assert($before_products === count(wc_get_products(['limit'=>-1,'return'=>'ids'])) && $before_users === count(get_users(['fields'=>'ID'])), 'activation seeds neither products nor users');
    $tl_test_assert(!tl_can_launch(), 'incomplete setup cannot launch');
    $config_fixture=['enabled'=>'yes','api_test_mode'=>'yes','api_key'=>'TEST-FIXTURE','secret_key'=>'TEST-FIXTURE','payment_page_id'=>'TEST-FIXTURE'];
    $tl_test_assert(!tl_payplus_settings_are_live($config_fixture),'Sandbox configuration cannot satisfy launch check');
    $config_fixture['api_test_mode']='no';$config_fixture['secret_key']='';
    $tl_test_assert(!tl_payplus_settings_are_live($config_fixture),'missing live credentials cannot satisfy launch check');
    $config_fixture['secret_key']='TEST-FIXTURE';
    $tl_test_assert(tl_payplus_settings_are_live($config_fixture),'pure configuration validator checks exact official fields, not merchant connectivity');
    $before_orders = count(wc_get_orders(['limit'=>-1,'return'=>'ids','status'=>array_merge(array_keys(wc_get_order_statuses()),['wc-checkout-draft'])]));
    foreach ([
        ['GET','/wc/store/v1/checkout'], ['POST','/wc/store/v1/checkout'], ['PUT','/wc/store/v1/checkout'],
        ['POST','/wc/store/v1/checkout/99'], ['POST','/wc/store/v2/checkout'],
        ['POST','/wc/store/v1/cart/update-customer'],
    ] as $fixture) {
        $request = new WP_REST_Request($fixture[0], $fixture[1]);
        $request->set_body_params(['billing_address'=>['email'=>'not-stored@example.invalid']]);
        $response = rest_do_request($request);
        $tl_test_assert($response->get_status() === 403 && $response->get_data()['code'] === 'tl_store_not_open', 'REST blocked '.$fixture[0].' '.$fixture[1]);
    }
    $after_orders = count(wc_get_orders(['limit'=>-1,'return'=>'ids','status'=>array_merge(array_keys(wc_get_order_statuses()),['wc-checkout-draft'])]));
    $tl_test_assert($before_orders === $after_orders, 'blocked requests create no orders or checkout drafts');
    $tl_test_assert(tl_guard_store_api(null, null, new WP_REST_Request('GET','/wc/store/v1/cart')) === null, 'catalogue cart route stays available');
    $errors = new WP_Error();
    do_action('woocommerce_after_checkout_validation', [], $errors);
    $tl_test_assert(in_array('tl_store_not_open',$errors->get_error_codes(),true), 'classic checkout validation blocked');
    $tl_test_assert(apply_filters('woocommerce_available_payment_gateways',['test'=>(object)['id'=>'test']]) === [], 'no payment gateway exposed while closed');
    $errors = apply_filters('woocommerce_registration_errors',new WP_Error());
    $tl_test_assert(in_array('tl_store_not_open',$errors->get_error_codes(),true), 'customer registration blocked while closed');
    $robots = apply_filters('wp_robots',[]);
    $tl_test_assert(!empty($robots['noindex']) && !empty($robots['nofollow']), 'closed storefront noindex');
    $tl_test_assert(strpos(apply_filters('render_block_woocommerce/proceed-to-checkout-block','<a>checkout</a>'),'הרכישה באתר תיפתח בקרוב.') !== false, 'block cart uses truthful prelaunch message');
    $tl_test_assert(has_action('wc_ajax_checkout') !== false && has_action('wc_ajax_update_order_review') !== false, 'classic AJAX checkout hooks registered');
    $tl_test_assert(tl_clean_seed_copy('OWNER COPY · פריט הדגמה') === 'OWNER COPY', 'legacy seed suffix removed without changing owner copy');
    $product = new WC_Product_Simple();
    $product->set_name('Acceptance fixture');$product->set_status('publish');$product->set_sku('TL-TEST-'.wp_generate_password(8,false));
    $product->set_description('A real fixture description for launch-control validation.');
    $product->set_regular_price('100');$product->set_price('100');$product->set_manage_stock(true);$product->set_stock_quantity(3);$product->set_backorders('no');
    $product_id=$product->save();$tl_test_products[]=$product_id;
    $tl_test_assert(!tl_product_is_approved_for_sale($product),'unapproved product fails readiness');
    $file=get_template_directory().'/assets/campaign-still-life.webp';
    if (!is_file($file)) throw new RuntimeException('Install the packaged theme before running the test.');
    require_once ABSPATH.'wp-admin/includes/image.php';
    $upload=wp_upload_bits('tl-acceptance-photo.webp',null,file_get_contents($file));
    if ($upload['error']) throw new RuntimeException($upload['error']);
    $attachment=wp_insert_attachment(['post_title'=>'Acceptance fixture photo','post_mime_type'=>'image/webp','post_status'=>'inherit'],$upload['file']);
    $tl_test_attachments[]=$attachment;
    wp_update_attachment_metadata($attachment,wp_generate_attachment_metadata($attachment,$upload['file']));
    $product->set_image_id($attachment);$product->update_meta_data('_tl_product_approved','yes');$product->save();
    $tl_test_assert(!tl_product_readiness($product) && tl_product_is_approved_for_sale($product),'approved native image price and stock pass readiness');
    $variable=new WC_Product_Variable();$variable->set_name('Shared stock fixture');$variable->set_status('publish');$variable->set_sku('TL-VAR-'.wp_generate_password(8,false));$variable->set_description('Variable fixture with one inventory pool, not multiplied per size.');$variable->set_image_id($attachment);$variable->set_manage_stock(true);$variable->set_stock_quantity(3);$variable->set_backorders('no');$variable->update_meta_data('_tl_product_approved','yes');
    $attribute=new WC_Product_Attribute();$attribute->set_name('size');$attribute->set_options(['6','7']);$attribute->set_visible(true);$attribute->set_variation(true);$variable->set_attributes([$attribute]);
    $variable_id=$variable->save();$tl_test_products[]=$variable_id;
    foreach (['6','7'] as $size) {$child=new WC_Product_Variation();$child->set_parent_id($variable_id);$child->set_status('publish');$child->set_attributes(['size'=>$size]);$child->set_regular_price('100');$child->set_price('100');$child->set_manage_stock(false);$child_id=$child->save();$tl_test_products[]=$child_id;}
    WC_Product_Variable::sync($variable_id);wc_delete_product_transients($variable_id);$variable=wc_get_product($variable_id);
    $tl_test_assert(!tl_product_readiness($variable),'variable parent-managed shared stock passes readiness');
    foreach ($variable->get_children() as $child_id) {$child=wc_get_product($child_id);$tl_test_assert($child->get_stock_managed_by_id()===$variable_id && tl_stock_is_configured($child),'variation uses parent inventory pool');}
    $tl_test_assert($variable->get_stock_quantity()===3,'two sizes do not multiply shared stock');
    $product->update_meta_data('_tl_demo_product',true);$product->save();
    $tl_test_assert(!tl_product_is_approved_for_sale($product),'seed flag cannot become purchasable by approval alone');
    $product->delete_meta_data('_tl_demo_product');$product->update_meta_data('_tl_product_approved','no');$product->save();
    update_option('tl_payment_launch_fingerprint',tl_payment_config_fingerprint());update_option('tl_sales_state','open');
    if (tl_is_demo()) {
        $tl_test_assert(!tl_sales_are_open(),'explicit Playground stays closed even with open option');
    } else {
        $tl_test_assert(tl_sales_are_open(),'isolated staging can exercise live-state guards');
        $tl_test_assert(!$product->is_purchasable(),'unapproved product blocked even after global open');
        $cart=new class($product) {private $product;function __construct($p){$this->product=$p;}function get_cart(){return [['data'=>$this->product]];}};
        $cart_errors=new WP_Error();do_action('woocommerce_store_api_cart_errors',$cart_errors,$cart);
        $tl_test_assert(in_array('tl_product_not_approved',$cart_errors->get_error_codes(),true),'Store API validates product approval at checkout');
        $product->update_meta_data('_tl_product_approved','yes');$product->save();update_option('tl_payment_launch_fingerprint',tl_payment_config_fingerprint());update_option('tl_sales_state','open');
        $tl_test_assert($product->is_purchasable(),'approved ready product can be purchased in isolated live-state test');
        $product->set_image_id(0);$product->save();
        $tl_test_assert(tl_sales_are_open() && !$product->is_purchasable(),'incomplete item blocked while other approved products keep selling');
    }
    wp_set_current_user(0);
    $tl_test_assert(!current_user_can('manage_options'),'visitor has no launch permission');
    $tl_test_assert(count(tl_launch_confirmations())===8,'eight explicit operator acceptance checks');
    echo 'T&L LAUNCH CONTROLS: '.count($tl_test_results).' assertions; PASS'."\n";
    echo wp_json_encode(['status'=>'PASS','checks'=>count($tl_test_results),'runtime'=>'WordPress + WooCommerce / PHP '.PHP_VERSION,'environment'=>tl_is_demo()?'explicit browser Playground':'isolated staging','details'=>$tl_test_results],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
} finally {
    wp_set_current_user($tl_test_user);
    foreach ($tl_test_products as $id) {$p=wc_get_product($id);if($p)$p->delete(true);}
    foreach ($tl_test_attachments as $id) wp_delete_attachment($id,true);
    foreach ($tl_test_original as $key=>$value) {if($value===null)delete_option($key);else update_option($key,$value);}
}
