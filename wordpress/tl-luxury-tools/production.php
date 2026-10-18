<?php
/** T&L launch controls. All sales are closed until an administrator completes the launch review. */
defined('ABSPATH') || exit;

function tl_activate_store_tools() {
    // add_option deliberately leaves every existing store, product and setting intact.
    add_option('tl_sales_state', 'closed', '', false);
    add_option('tl_fulfilment', 'undecided', '', false);
}

function tl_is_nonproduction() { return wp_get_environment_type() !== 'production'; }

function tl_sales_are_open() {
    // A staging clone can contain the live database's open flag and payment settings.
    // Do not mutate that copied state merely by reading it; the environment closes sales.
    if (tl_is_demo() || tl_is_nonproduction() || get_option('tl_sales_state', 'closed') !== 'open') return false;
    $confirmed = get_option('tl_payment_launch_fingerprint', '');
    if (!$confirmed || !hash_equals($confirmed, tl_payment_config_fingerprint())) {
        tl_revoke_payment_acceptance('הגדרות הסליקה השתנו או טרם אושרו לפתיחה.');
        return false;
    }
    return true;
}

// No mail is dispatched by a local, development or staging copy, even after a live DB clone.
add_filter('pre_wp_mail', function($return) { return tl_is_nonproduction() ? true : $return; }, 98);

function tl_closed_message() { return 'הרכישה באתר תיפתח בקרוב.'; }

function tl_close_sales($reason = '') {
    update_option('tl_sales_state', 'closed', false);
    delete_option('tl_payment_launch_fingerprint');
    if ($reason !== '') update_option('tl_sales_closed_reason', sanitize_text_field($reason), false);
}

// Exact option names verified against the official PayPlus 8.3.1 release.
// These are configuration checks only. Credentials are never printed or sent to the browser.
function tl_payplus_settings_are_live($settings) {
    if (!is_array($settings) || ($settings['enabled'] ?? '') !== 'yes' || ($settings['api_test_mode'] ?? '') !== 'no') return false;
    foreach (['api_key','secret_key','payment_page_id'] as $key) if (!isset($settings[$key]) || !is_scalar($settings[$key]) || trim((string)$settings[$key]) === '') return false;
    return true;
}
function tl_payment_critical_fields($settings) {
    $fields = [];
    foreach (['enabled','api_test_mode','api_key','secret_key','payment_page_id','transaction_type','display_mode','fire_completed'] as $key) $fields[$key] = $settings[$key] ?? '';
    return $fields;
}
function tl_payment_config_fingerprint() {
    $settings = get_option('woocommerce_payplus-payment-gateway_settings', []);
    return hash_hmac('sha256', wp_json_encode(tl_payment_critical_fields(is_array($settings)?$settings:[])), wp_salt('auth'));
}
function tl_revoke_payment_acceptance($reason) {
    tl_close_sales($reason);
    $confirmations = get_option('tl_launch_confirmations', []);
    if (is_array($confirmations) && isset($confirmations['payments'])) { unset($confirmations['payments']); update_option('tl_launch_confirmations', $confirmations, false); }
}
add_action('updated_option', function($option, $old_value, $value) {
    if ($option !== 'woocommerce_payplus-payment-gateway_settings') return;
    $old = tl_payment_critical_fields(is_array($old_value)?$old_value:[]);
    $new = tl_payment_critical_fields(is_array($value)?$value:[]);
    if ($old !== $new) tl_revoke_payment_acceptance('הגדרות PayPlus השתנו. יש לבדוק ולאשר שוב את הסליקה לפני פתיחת הזמנות.');
}, 10, 3);
add_action('deleted_option', function($option) {
    if ($option === 'woocommerce_payplus-payment-gateway_settings') tl_revoke_payment_acceptance('הגדרות PayPlus הוסרו. קבלת ההזמנות נסגרה.');
});

// The Store API can create a draft order on GET as well as process payment on POST.
// Block every checkout method/version before the route callback, not only its payment gateway.
function tl_guard_store_api($result, $server, $request) {
    if (tl_sales_are_open()) return $result;
    $route = rawurldecode($request->get_route());
    if (preg_match('#^/wc/store(?:/v[0-9]+)?/(?:checkout(?:/|$)|cart/update-customer(?:/|$))#i', $route)) {
        return new WP_Error('tl_store_not_open', tl_closed_message(), ['status' => 403]);
    }
    return $result;
}
add_filter('rest_pre_dispatch', 'tl_guard_store_api', 1, 3);
add_filter('wp_robots', function($robots) {
    if (!tl_sales_are_open()) { $robots['noindex'] = true; $robots['nofollow'] = true; unset($robots['index'], $robots['follow']); }
    return $robots;
});
foreach (['checkout','update_order_review'] as $tl_ajax_action) foreach (['wc_ajax_','wp_ajax_woocommerce_','wp_ajax_nopriv_woocommerce_'] as $tl_ajax_prefix) add_action($tl_ajax_prefix.$tl_ajax_action, function() {
    if (!tl_sales_are_open()) wp_send_json(['result'=>'failure','messages'=>'<div class="woocommerce-error">'.esc_html(tl_closed_message()).'</div>'], 403);
}, 0);
add_filter('woocommerce_shipping_show_shipping_calculator', function($show) { return tl_sales_are_open() && $show; });
add_action('wp_loaded', function() {
    if (!tl_sales_are_open() && isset($_POST['calc_shipping'])) { wp_safe_redirect(wc_get_cart_url()); exit; }
}, 9);
add_filter('woocommerce_available_payment_gateways', function($gateways) { return tl_sales_are_open() ? $gateways : []; }, 100);
add_action('woocommerce_checkout_process', function() {
    if (!tl_sales_are_open()) wc_add_notice(tl_closed_message(), 'error');
}, 1);
add_action('woocommerce_after_checkout_validation', function($data, $errors) {
    if (!tl_sales_are_open()) $errors->add('tl_store_not_open', tl_closed_message());
}, 1, 2);
add_action('woocommerce_before_pay_action', function() {
    if (!tl_sales_are_open()) throw new Exception(tl_closed_message());
}, 1);
add_filter('woocommerce_registration_errors', function($errors) {
    if (!tl_sales_are_open()) $errors->add('tl_store_not_open', tl_closed_message());
    return $errors;
}, 1);
add_filter('option_woocommerce_enable_myaccount_registration', function($enabled) { return tl_sales_are_open() ? $enabled : 'no'; });
add_filter('woocommerce_checkout_registration_enabled', function($enabled) { return tl_sales_are_open() && $enabled; });
add_action('template_redirect', function() {
    if (tl_sales_are_open() || !function_exists('is_checkout')) return;
    if (is_checkout() || (function_exists('is_account_page') && is_account_page() && !current_user_can('manage_woocommerce'))) {
        nocache_headers();
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
}, 0);
add_action('woocommerce_proceed_to_checkout', function() {
    if (!tl_sales_are_open()) echo '<p class="tl-prelaunch-message" role="status">'.esc_html(tl_closed_message()).'</p>';
}, 5);
add_action('wp', function() {
    if (!tl_sales_are_open()) remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
});
add_filter('render_block_woocommerce/proceed-to-checkout-block', function($html) {
    return tl_sales_are_open() ? $html : '<p class="tl-prelaunch-message" role="status">'.esc_html(tl_closed_message()).'</p>';
});

// Old seeded descriptions are never rewritten in the database. Only their two exact legacy
// presentation fragments are suppressed; owners' prose and custom product details are preserved.
function tl_clean_seed_copy($text) {
    return str_replace([
        '<p>פריט הדגמה. התמונה, המחיר והמאפיינים נועדו להמחשה ואינם מפרט מוצר למכירה.</p>',
        ' · פריט הדגמה',
    ], '', $text);
}
add_filter('woocommerce_product_get_description', 'tl_clean_seed_copy', 20);
add_filter('woocommerce_product_get_short_description', 'tl_clean_seed_copy', 20);

function tl_stock_is_configured($product) {
    // Variants may legitimately share one stock pool on their parent.
    $owner_id = $product->get_stock_managed_by_id();
    $owner = $owner_id === $product->get_id() ? $product : wc_get_product($owner_id);
    return $product->managing_stock() && $owner && $owner->get_stock_quantity() !== null && $owner->get_stock_quantity() >= 0 && !$product->backorders_allowed();
}
function tl_product_readiness($product) {
    $issues = [];
    if (!$product || !$product->exists()) return ['המוצר אינו קיים'];
    if ($product->get_meta('_tl_demo_product')) $issues[] = 'יש להחליף ולאשר את פריט הקטלוג הראשוני';
    if ($product->get_meta('_tl_product_approved') !== 'yes') $issues[] = 'נדרש אישור פרטי המוצר';
    if (!$product->get_name() || !$product->get_sku()) $issues[] = 'נדרשים שם ומק״ט';
    if (trim(wp_strip_all_tags($product->get_description())) === '') $issues[] = 'נדרש תיאור מוצר';
    if (!$product->get_image_id() || !wp_attachment_is_image($product->get_image_id())) $issues[] = 'נדרשת תמונת מוצר מספריית המדיה';
    if ($product->is_type('variable')) {
        $children = $product->get_children();
        if (!$children) $issues[] = 'נדרשים וריאנטים פעילים';
        $active = 0;
        foreach ($children as $child_id) {
            $child = wc_get_product($child_id);
            if (!$child || $child->get_status() !== 'publish') continue;
            $active++;
            if ($child->get_price() === '' || !is_numeric($child->get_price()) || (float)$child->get_price() <= 0) $issues[] = 'נדרש מחיר חיובי לכל מידה';
            if (!tl_stock_is_configured($child)) $issues[] = 'נדרש מלאי מוגדר למידות או מלאי משותף למוצר, ללא הזמנת יתר';
        }
        if (!$active) $issues[] = 'נדרשת לפחות מידה פעילה אחת';
    } elseif ($product->is_type('simple')) {
        if ($product->get_price() === '' || !is_numeric($product->get_price()) || (float)$product->get_price() <= 0) $issues[] = 'נדרש מחיר חיובי';
        if (!tl_stock_is_configured($product)) $issues[] = 'נדרש ניהול מלאי וכמות, ללא הזמנת יתר';
    } else {
        $issues[] = 'סוג מוצר זה מצריך בדיקת מתכנת לפני פתיחה';
    }
    return array_values(array_unique($issues));
}
function tl_product_is_approved_for_sale($product) {
    if (!$product || !$product->exists()) return false;
    $parent = $product->get_parent_id() ? wc_get_product($product->get_parent_id()) : $product;
    return $parent && !$parent->get_meta('_tl_demo_product') && !tl_product_readiness($parent);
}
// Approval is checked again at purchase time, not only on the day the store opens.
// Closed catalogue mode intentionally retains ordinary cart interaction for presentations.
foreach (['woocommerce_is_purchasable','woocommerce_variation_is_purchasable'] as $tl_purchasable_hook) add_filter($tl_purchasable_hook, function($purchasable, $product) {
    return !tl_sales_are_open() ? $purchasable : $purchasable && tl_product_is_approved_for_sale($product);
}, 100, 2);
function tl_unapproved_cart_products($cart = null) {
    if (!$cart && function_exists('WC')) $cart = WC()->cart;
    if (!$cart) return [];
    $names = [];
    foreach ($cart->get_cart() as $item) if (!empty($item['data']) && !tl_product_is_approved_for_sale($item['data'])) $names[] = $item['data']->get_name();
    return array_values(array_unique($names));
}
add_action('woocommerce_check_cart_items', function() {
    if (tl_sales_are_open() && tl_unapproved_cart_products()) wc_add_notice('אחד התכשיטים בסל אינו זמין כעת לרכישה. יש להסיר אותו כדי להמשיך.', 'error');
}, 1);
add_action('woocommerce_store_api_cart_errors', function($errors, $cart) {
    if (tl_sales_are_open() && tl_unapproved_cart_products($cart)) $errors->add('tl_product_not_approved', 'אחד התכשיטים בסל אינו זמין כעת לרכישה.');
}, 1, 2);
add_action('woocommerce_after_checkout_validation', function($data, $errors) {
    if (tl_sales_are_open() && tl_unapproved_cart_products()) $errors->add('tl_product_not_approved', 'אחד התכשיטים בסל אינו זמין כעת לרכישה.');
}, 2, 2);
add_action('woocommerce_product_options_general_product_data', function() {
    echo '<div class="options_group">';
    woocommerce_wp_checkbox([
        'id' => '_tl_product_approved',
        'label' => 'אישור מוצר לפרסום',
        'description' => 'בדקתי: שם, תמונות בבעלות החנות, הרכב המתכת והאבן, מידות, מחיר, מלאי ותנאי אחריות. זה אינו מוצר להמחשה.',
        'desc_tip' => false,
    ]);
    echo '</div>';
});
add_action('woocommerce_admin_process_product_object', function($product) {
    if (!current_user_can('edit_product', $product->get_id())) return;
    $product->update_meta_data('_tl_product_approved', isset($_POST['_tl_product_approved']) ? 'yes' : 'no');
    // Approval of a fully replaced seed item removes only its internal seed flag.
    // Remote seed-image metadata stays as history and is never used for live readiness.
    if (isset($_POST['_tl_product_approved']) && $product->get_image_id() && wp_attachment_is_image($product->get_image_id())) $product->delete_meta_data('_tl_demo_product');
});
// A newly added or incomplete item is blocked individually. Other approved products
// continue selling; routine no-code catalogue work never closes the whole store.

function tl_launch_confirmations() {
    return [
        'policies' => 'בעלי החנות אישרו תקנון, פרטיות, נגישות, משלוחים, החזרות ואחריות — עם פרטי העסק הנכונים.',
        'payments' => 'נבדקו תשלום מצליח, סירוב וביטול, הודעת שרת חוזרת, ניסיון כפול והחזר. מצב הסליקה והסכומים אושרו מול PayPlus.',
        'shipping' => 'נבדקו כתובות ושיטות האספקה, מחיר וסף משלוח חינם אם קיים, זמני אספקה ואיסוף עצמי אם הוצע.',
        'email' => 'התקבלו בפועל הודעות הזמנה אצל הלקוח ואצל החנות, והוגדרו כתובת שולח ו־SPF/DKIM לפי ספק הדואר.',
        'tax' => 'מחירים, מע״מ והפקת חשבוניות נבדקו עם גורם הנהלת החשבונות של העסק.',
        'security' => 'הוגדרו גיבוי יומי ונבדק שחזור; חשבונות נפרדים, סיסמאות חזקות, אימות דו־שלבי ועדכוני אבטחה.',
        'privacy' => 'נבדקו הרשאות גישה ושמירת מידע, טפסים והסכמות. כל כלי מדידה או דיוור מופעל רק אחרי חיבור והגדרה מתאימים.',
        'mobile' => 'נבדקו בטלפון ובמחשב: מידות, מלאי, קופון, סל, חשבון, דוא״ל, תשלום ונגישות.',
    ];
}

function tl_shipping_status() {
    $delivery = 0; $pickup = 0; $summary = [];
    if (!class_exists('WC_Shipping_Zones')) return ['delivery'=>0, 'pickup'=>0, 'summary'=>[]];
    foreach (WC_Shipping_Zones::get_zones() as $zone_data) {
        $zone = new WC_Shipping_Zone($zone_data['id']);
        $covers_israel = false;
        foreach ($zone->get_zone_locations() as $location) if ($location->type === 'country' && $location->code === 'IL') $covers_israel = true;
        foreach ($zone->get_shipping_methods(true) as $method) {
            $summary[] = $zone->get_zone_name().' — '.$method->get_title();
            if (!$covers_israel) continue;
            if ($method->id === 'local_pickup') $pickup++;
            if ($method->id === 'free_shipping') $delivery++;
            if ($method->id === 'flat_rate' && trim((string)$method->get_option('cost', '')) !== '') $delivery++;
            // Carrier plugins are verified through the operator test, not a made-up API flag.
            if (!in_array($method->id, ['flat_rate','free_shipping','local_pickup'], true)) $delivery++;
        }
    }
    return compact('delivery', 'pickup', 'summary');
}

function tl_ready_policy_pages() {
    $missing = [];
    foreach (['privacy'=>'פרטיות','terms'=>'תקנון','delivery'=>'משלוחים והחזרות','accessibility'=>'נגישות','sizes'=>'מדריך מידות'] as $slug=>$label) {
        $page = get_page_by_path($slug);
        $placeholder = $page ? get_post_meta($page->ID, '_tl_placeholder_content_hash', true) : '';
        if (!$page || $page->post_status !== 'publish' || mb_strlen(trim(wp_strip_all_tags($page->post_content))) < 80 || ($placeholder && hash_equals($placeholder, hash('sha256', $page->post_content)))) $missing[] = $label;
    }
    return $missing;
}

function tl_launch_readiness() {
    $checks = [];
    $checks['runtime'] = ['label'=>'אחסון החנות', 'ready'=>!tl_is_demo() && !tl_is_nonproduction(), 'detail'=>tl_is_demo() ? 'סביבת WordPress אישית בדפדפן; אינה שרת מכירה משותף.' : (tl_is_nonproduction() ? 'זו סביבת בדיקה ('.wp_get_environment_type().'). קבלת הזמנות ושליחת דואר חסומות, גם אם הועתקו נתוני חנות פתוחה.' : 'התוסף מותקן ב־WordPress במצב production. יש לאמת את תשתית האחסון בבדיקות ההשקה.'), 'url'=>admin_url('site-health.php')];
    $url = wp_parse_url(home_url('/'));
    $public_https = ($url['scheme'] ?? '') === 'https' && !preg_match('/(?:localhost|127\.0\.0\.1|\.local$|\.test$)/i', $url['host'] ?? '');
    $checks['domain'] = ['label'=>'דומיין ו־HTTPS', 'ready'=>$public_https, 'detail'=>$public_https ? 'כתובת האתר משתמשת ב־HTTPS. תעודה, הפניות וחידוש נבדקים מול האחסון.' : 'נדרש דומיין החנות עם HTTPS תקין.', 'url'=>admin_url('options-general.php')];
    $business_ready = true;
    foreach (['name','id','phone','email','address'] as $field) if (!trim((string)get_option('tl_business_'.$field, ''))) $business_ready = false;
    if (!is_email(get_option('tl_business_email', ''))) $business_ready = false;
    if (!tl_business_phone_is_valid(get_option('tl_business_phone', ''))) $business_ready = false;
    $checks['business'] = ['label'=>'פרטי העסק ושירות הלקוחות', 'ready'=>$business_ready, 'detail'=>'שם עסק, מספר עוסק/חברה, טלפון, דוא״ל וכתובת. הפרטים מוזנים למטה על ידי בעלי החנות.', 'url'=>'#tl-business'];
    $products = function_exists('wc_get_products') ? wc_get_products(['status'=>'publish','limit'=>-1,'return'=>'objects']) : [];
    $unready = [];
    foreach ($products as $product) {
        $issues = tl_product_readiness($product);
        if ($issues) $unready[] = $product->get_name().' — '.implode(', ', $issues);
    }
    $checks['products'] = ['label'=>'קטלוג אמיתי ומאושר', 'ready'=>count($products)>0 && !$unready, 'detail'=>count($products).' מוצרים מפורסמים; '.count($unready).' מצריכים השלמה. כל מוצר מחייב אישור, תמונת מדיה, מחיר ומלאי. מוצרים שלא מוכנים יש להעביר לטיוטה.', 'url'=>admin_url('edit.php?post_type=product'), 'issues'=>$unready];
    $missing = tl_ready_policy_pages();
    $checks['policies'] = ['label'=>'עמודי מידע ומדיניות', 'ready'=>!$missing, 'detail'=>$missing ? 'חסרים עמודים מאושרים ומלאים: '.implode(', ', $missing).'.' : 'העמודים מפורסמים ומכילים טקסט. נדרש גם אישור בעלי החנות למטה; בדיקה זו אינה סקירה משפטית.', 'url'=>admin_url('edit.php?post_type=page')];
    $shipping = tl_shipping_status(); $fulfilment = get_option('tl_fulfilment', 'undecided');
    $shipping_ready = ($fulfilment === 'delivery' && $shipping['delivery'] > 0) || ($fulfilment === 'pickup' && $shipping['pickup'] > 0) || ($fulfilment === 'both' && $shipping['pickup'] > 0 && $shipping['delivery'] > 0);
    $checks['shipping'] = ['label'=>'משלוח או איסוף עצמי', 'ready'=>$shipping_ready, 'detail'=>$shipping['summary'] ? implode(' · ', $shipping['summary']) : 'לא נמצאו שיטות אספקה פעילות. בוחרים את אופן האספקה ומגדירים אזור ישראל ושיטות ב־WooCommerce.', 'url'=>admin_url('admin.php?page=wc-settings&tab=shipping')];
    $payplus = false; $registered = false;
    $settings = get_option('woocommerce_payplus-payment-gateway_settings', []);
    if (function_exists('WC') && WC()->payment_gateways()) {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $registered = isset($gateways['payplus-payment-gateway']);
        $payplus = $registered && $gateways['payplus-payment-gateway']->enabled === 'yes' && tl_payplus_settings_are_live($settings);
    }
    $payment_detail = !$registered ? 'נדרשת התקנת התוסף הרשמי של PayPlus והגדרתו בשרת.' : (($settings['api_test_mode'] ?? '') !== 'no' ? 'PayPlus במצב בדיקה או טרם הוגדר במפורש מצב Production. מצב Sandbox אינו מאשר פתיחה למכירה.' : 'נדרשת שיטת PayPlus מופעלת ושלושת שדות החיבור של Production. לא מזינים מפתחות API בטופס הזה.');
    if ($payplus) $payment_detail = 'PayPlus מוגדר Production ושדות החיבור קיימים בשרת. זו בדיקת הגדרה בלבד, לא אישור חשבון או תשלום. נדרש גם אישור בדיקות תשלום והחזר.';
    $checks['payment'] = ['label'=>'אמצעי תשלום רשמי', 'ready'=>$payplus, 'detail'=>$payment_detail, 'url'=>admin_url('admin.php?page=wc-settings&tab=checkout')];
    $checks['store_settings'] = ['label'=>'הגדרות מסחר', 'ready'=>get_woocommerce_currency()==='ILS' && wc_get_base_location()['country']==='IL', 'detail'=>'החנות מוגדרת לשקלים ולבסיס בישראל. מע״מ, חשבוניות ותמחור משלוח דורשים החלטת בעלי העסק.', 'url'=>admin_url('admin.php?page=wc-settings')];
    $saved = get_option('tl_launch_confirmations', []);
    $missing_confirmations = array_diff(array_keys(tl_launch_confirmations()), array_keys(array_filter($saved)));
    $checks['acceptance'] = ['label'=>'בדיקות קבלה ואישור בעלי החנות', 'ready'=>!$missing_confirmations, 'detail'=>count($saved).' מתוך '.count(tl_launch_confirmations()).' אישורים ידניים. סימון מתעד בדיקה שבוצעה; הוא אינו מבצע את הבדיקה.', 'url'=>'#tl-acceptance'];
    return $checks;
}

function tl_can_launch($checks = null) {
    if ($checks === null) $checks = tl_launch_readiness();
    foreach ($checks as $check) if (!$check['ready']) return false;
    return true;
}

function tl_admin_action_guard($nonce, $administrator = false) {
    if (!current_user_can($administrator ? 'manage_options' : 'manage_woocommerce')) wp_die('אין הרשאה לביצוע הפעולה.', '', ['response'=>403]);
    check_admin_referer($nonce);
}
function tl_management_redirect($notice) {
    wp_safe_redirect(add_query_arg('tl_notice', sanitize_key($notice), admin_url('admin.php?page=tl-luxury')));
    exit;
}
function tl_can_change_sales_state($state) {
    if ($state === 'closed') return current_user_can('manage_woocommerce');
    if ($state === 'open') return current_user_can('manage_options');
    return false;
}

function tl_business_phone_is_valid($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    return (bool)preg_match('/^\+?[0-9\s().-]+$/', $phone) && strlen($digits) >= 8 && strlen($digits) <= 15;
}
function tl_normalize_business_data($input) {
    $data = [];
    foreach (['name'=>160,'id'=>40,'phone'=>40,'address'=>300,'hours'=>160] as $field=>$limit) $data[$field] = mb_substr(sanitize_text_field($input['business_'.$field] ?? ''), 0, $limit);
    $email = sanitize_email(is_scalar($input['business_email'] ?? '') ? (string)($input['business_email'] ?? '') : '');
    $data['email'] = is_email($email) ? $email : '';
    $mode = sanitize_key($input['fulfilment'] ?? 'undecided');
    $data['fulfilment'] = in_array($mode, ['delivery','pickup','both'], true) ? $mode : 'undecided';
    return $data;
}
function tl_save_business_data($input) {
    $data = tl_normalize_business_data($input);
    $critical_changes = [];
    foreach (['name','id','address'] as $field) if ((string)get_option('tl_business_'.$field, '') !== $data[$field]) $critical_changes[] = $field;
    if (get_option('tl_fulfilment', 'undecided') !== $data['fulfilment']) $critical_changes[] = 'fulfilment';
    $invalid = [];
    foreach (['name','id','address','email'] as $field) if ($data[$field] === '') $invalid[] = $field;
    if (!tl_business_phone_is_valid($data['phone'])) $invalid[] = 'phone';
    if ($data['fulfilment'] === 'undecided') $invalid[] = 'fulfilment';
    foreach ($data as $field=>$value) update_option($field === 'fulfilment' ? 'tl_fulfilment' : 'tl_business_'.$field, $value, false);
    $review_required = (bool)($critical_changes || $invalid);
    // Contact details and opening hours are routine work. A valid edit or no-op does not
    // close a running store. Identity, address, fulfilment or invalid required data do.
    if ($review_required) {
        tl_close_sales('זהות העסק, כתובת או אופן האספקה השתנו, או שחסר פרט חובה תקין. נדרש אישור פתיחה מחדש.');
        delete_option('tl_launch_confirmations');
    }
    return ['review_required'=>$review_required,'critical_changes'=>$critical_changes,'invalid_fields'=>$invalid];
}
add_action('admin_post_tl_save_business', function() {
    tl_admin_action_guard('tl_save_business');
    tl_save_business_data(wp_unslash($_POST));
    tl_management_redirect('business_saved');
});
add_action('admin_post_tl_save_acceptance', function() {
    tl_admin_action_guard('tl_save_acceptance', true);
    $checks = [];
    foreach (tl_launch_confirmations() as $key=>$label) if (isset($_POST['confirm'][$key])) $checks[$key] = ['user'=>get_current_user_id(), 'at'=>current_time('mysql', true)];
    update_option('tl_launch_confirmations', $checks, false);
    if (count($checks) !== count(tl_launch_confirmations())) tl_close_sales('אישור בדיקות ההשקה אינו מלא.');
    tl_management_redirect('acceptance_saved');
});
add_action('admin_post_tl_change_sales_state', function() {
    $state = is_string($_POST['sales_state'] ?? null) ? sanitize_key(wp_unslash($_POST['sales_state'])) : '';
    if (!tl_can_change_sales_state($state)) wp_die('אין הרשאה לביצוע הפעולה.', '', ['response'=>403]);
    check_admin_referer('tl_change_sales_state');
    if ($state === 'closed') {
        tl_close_sales('מנהל החנות סגר את קבלת ההזמנות.');
        tl_management_redirect('closed');
    }
    if ($state !== 'open' || !isset($_POST['understand_live_sales']) || !tl_can_launch()) {
        tl_close_sales('יש להשלים את רשימת ההכנות ואת האישור לפני פתיחת ההזמנות.');
        tl_management_redirect('not_ready');
    }
    update_option('tl_payment_launch_fingerprint', tl_payment_config_fingerprint(), false);
    update_option('tl_sales_state', 'open', false);
    update_option('tl_sales_opened_by', ['user'=>get_current_user_id(), 'at'=>current_time('mysql', true)], false);
    delete_option('tl_sales_closed_reason');
    tl_management_redirect('opened');
});

function tl_base_pages_plan() {
    return [
        'home'=>['בית','','publish'],
        'about'=>['על T&L',wpautop(tl_brand_story()),'publish'],
        'contact'=>['יצירת קשר','[tl_business_contact]','publish'],
        'sizes'=>['מדריך מידות','יש להוסיף מדריך מדידה ומידות המתאימים למוצרים בפועל.','draft'],
        'delivery'=>['משלוחים והחזרות','יש למלא אזורי אספקה, עלויות, זמנים, איסוף עצמי ותנאי החזרה מאושרים של העסק.','draft'],
        'privacy'=>['מדיניות פרטיות','יש להוסיף מדיניות פרטיות המתאימה למערכות ולמידע שהחנות מפעילה בפועל.','draft'],
        'terms'=>['תקנון החנות','יש להוסיף תקנון מאושר עם פרטי העסק, תנאי רכישה, אחריות, אספקה וביטולים.','draft'],
        'accessibility'=>['הצהרת נגישות','יש לפרסם הצהרה אחרי בדיקת נגישות, כולל פרטי איש קשר ותהליך פנייה.','draft'],
    ];
}
function tl_create_missing_store_pages() {
    $created = [];
    if (class_exists('WC_Install')) WC_Install::create_pages();
    foreach (tl_base_pages_plan() as $slug=>$data) {
        if (get_page_by_path($slug)) continue;
        $id = wp_insert_post(['post_type'=>'page','post_status'=>$data[2],'post_name'=>$slug,'post_title'=>$data[0],'post_content'=>$data[1]], true);
        if (is_wp_error($id)) continue;
        $created[] = $slug;
        if ($data[2] === 'draft') update_post_meta($id, '_tl_placeholder_content_hash', hash('sha256', $data[1]));
        if ($slug === 'home' && !get_option('page_on_front')) { update_option('show_on_front', 'page'); update_option('page_on_front', $id); }
        if ($slug === 'terms' && !get_option('woocommerce_terms_page_id')) update_option('woocommerce_terms_page_id', $id);
        if ($slug === 'privacy' && !get_option('wp_page_for_privacy_policy')) update_option('wp_page_for_privacy_policy', $id);
    }
    return $created;
}
add_action('admin_post_tl_create_base_pages', function() {
    tl_admin_action_guard('tl_create_base_pages', true);
    tl_create_missing_store_pages();
    tl_management_redirect('pages_created');
});

// Scaffold an empty Israel zone only after an explicit preview and confirmation.
// No method, carrier account, rate or free-shipping promise is silently installed.
function tl_create_israel_zone() {
    foreach (WC_Shipping_Zones::get_zones() as $zone_data) {
        $zone = new WC_Shipping_Zone($zone_data['id']);
        foreach ($zone->get_zone_locations() as $location) if ($location->type === 'country' && $location->code === 'IL') return ['created'=>false,'id'=>$zone->get_id()];
    }
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name('ישראל');
    $zone->add_location('IL', 'country');
    $id = $zone->save();
    return ['created'=>true,'id'=>$id];
}
add_action('admin_post_tl_create_shipping_zone', function() {
    tl_admin_action_guard('tl_create_shipping_zone', true);
    if (!isset($_POST['confirm_empty_zone'])) tl_management_redirect('zone_not_confirmed');
    tl_create_israel_zone();
    tl_close_sales('הגדרות האספקה מצריכות בדיקת קבלה.');
    tl_management_redirect('zone_created');
});

function tl_business_contact_html() {
    $out = '<div class="tl-business-contact">';
    $name = get_option('tl_business_name', '');
    if ($name) $out .= '<p><strong>'.esc_html($name).'</strong></p>';
    $phone = get_option('tl_business_phone', '');
    if ($phone) $out .= '<p><a dir="ltr" href="'.esc_url('tel:'.preg_replace('/[^0-9+]/','',$phone)).'">'.esc_html($phone).'</a></p>';
    $email = get_option('tl_business_email', '');
    if (is_email($email)) $out .= '<p><a href="'.esc_url('mailto:'.$email).'">'.esc_html($email).'</a></p>';
    foreach (['address','hours'] as $field) { $value = get_option('tl_business_'.$field, ''); if ($value) $out .= '<p>'.esc_html($value).'</p>'; }
    if (!$name && !$phone && !$email) $out .= '<p>פרטי שירות הלקוחות יפורסמו עם פתיחת החנות.</p>';
    return $out.'</div>';
}
add_shortcode('tl_business_contact', 'tl_business_contact_html');

function tl_launch_dashboard() {
    $checks = tl_launch_readiness(); $ready_count = count(array_filter($checks, function($check) { return $check['ready']; }));
    $notices = ['business_saved'=>'פרטי העסק נשמרו. מצב ההזמנות ומוכנות הפתיחה מוצגים למעלה.','acceptance_saved'=>'אישורי הבדיקות נשמרו.','closed'=>'קבלת ההזמנות נסגרה.','not_ready'=>'ההזמנות לא נפתחו. יש להשלים את רשימת ההכנות.','opened'=>'החנות פתוחה לקבלת הזמנות.','pages_created'=>'הוכנו רק העמודים שהיו חסרים. תוכן קיים נשמר. עמודי המדיניות החדשים הם טיוטות.','zone_created'=>'אזור ישראל מוכן. אזור קיים לא שונה; לא נקבעו שיטה או מחיר.','zone_not_confirmed'=>'יצירת האזור דורשת אישור מפורש.'];
    $notice = sanitize_key($_GET['tl_notice'] ?? '');
    if (isset($notices[$notice])) echo '<div class="notice notice-info"><p>'.esc_html($notices[$notice]).'</p></div>';
    echo '<section class="tl-panel tl-launch-panel"><h2>מוכנות לפתיחת החנות</h2><p class="tl-sales-status"><strong>'.(tl_sales_are_open() ? 'פתוחה להזמנות' : 'קטלוג פתוח · קבלת הזמנות סגורה').'</strong></p><p>האתר מאפשר להכיר את התכשיטים ולבנות סל. לפני הפתיחה לא מוצג טופס פרטי לקוח, לא נוצרת הזמנה ולא מתבצע תשלום דרך מסלולי הקופה.</p><p>'.esc_html($ready_count.' מתוך '.count($checks).' שלבי הכנה הושלמו.').'</p>';
    $reason = get_option('tl_sales_closed_reason', '');
    if (!tl_sales_are_open() && $reason) echo '<p class="tl-admin-note">'.esc_html($reason).'</p>';
    echo '<ol class="tl-readiness">';
    foreach ($checks as $key=>$check) {
        echo '<li data-check="'.esc_attr($key).'" data-ready="'.($check['ready']?'yes':'no').'"><strong>'.($check['ready']?'✓ הושלם — ':'○ להשלמה — ').esc_html($check['label']).'</strong><p>'.esc_html($check['detail']).' <a href="'.esc_url($check['url']).'">לעריכה</a></p>';
        if (!empty($check['issues'])) { echo '<details><summary>מוצרים שמצריכים השלמה</summary><ul>'; foreach (array_slice($check['issues'],0,30) as $issue) echo '<li>'.esc_html($issue).'</li>'; echo '</ul></details>'; }
        echo '</li>';
    }
    echo '</ol>';
    if (current_user_can('manage_options')) {
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post">';wp_nonce_field('tl_change_sales_state');
        echo '<input type="hidden" name="action" value="tl_change_sales_state"><label><input type="checkbox" name="understand_live_sales" value="1"> אני מאשר/ת שהנתונים נבדקו ופתיחת החנות תאפשר הזמנות ותשלומים אמיתיים.</label><p><button class="button button-primary" name="sales_state" value="open" '.(tl_can_launch($checks)?'':'disabled').'>פתיחת החנות להזמנות</button> <button class="button" name="sales_state" value="closed">סגירת קבלת הזמנות</button></p></form>';
    } else {
        if (tl_sales_are_open()) {
            echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post">';wp_nonce_field('tl_change_sales_state');
            echo '<input type="hidden" name="action" value="tl_change_sales_state"><p><button class="button" name="sales_state" value="closed">עצירת קבלת הזמנות</button></p><p>הקטלוג והסל נשארים זמינים. פתיחה מחדש מתבצעת בידי מנהל מערכת אחרי בדיקת המוכנות.</p></form>';
        }
        echo '<p class="tl-admin-note">מנהלי החנות מעדכנים מוצרים, תוכן ומשלוחים ויכולים לעצור קבלת הזמנות. רק מנהל המערכת פותח קבלת הזמנות אחרי השלמת הבדיקות.</p>';
    }
    echo '</section>';
    echo '<section class="tl-panel" id="tl-business"><h2>פרטי העסק ואספקה</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tl_save_business');
    echo '<input type="hidden" name="action" value="tl_save_business">';
    foreach (['name'=>'שם העסק הרשום','id'=>'מספר עוסק / חברה','phone'=>'טלפון לשירות לקוחות','email'=>'דוא״ל לשירות לקוחות','address'=>'כתובת העסק','hours'=>'שעות פעילות'] as $field=>$label) echo '<label for="tl-business-'.$field.'">'.esc_html($label).'</label><input id="tl-business-'.$field.'" name="business_'.$field.'" type="'.($field==='email'?'email':'text').'" value="'.esc_attr(get_option('tl_business_'.$field,'')).'">';
    echo '<label for="tl-fulfilment">איך הלקוחה מקבלת את התכשיט?</label><select id="tl-fulfilment" name="fulfilment">';
    foreach (['undecided'=>'טרם הוחלט','delivery'=>'משלוח','pickup'=>'איסוף עצמי','both'=>'משלוח ואיסוף עצמי'] as $value=>$label) echo '<option value="'.esc_attr($value).'" '.selected(get_option('tl_fulfilment','undecided'),$value,false).'>'.esc_html($label).'</option>';
    echo '</select><p>הבחירה אינה יוצרת שירות או קובעת מחיר. את האזורים, העלות, הזמינות וסף המשלוח החינם אם הוחלט עליו מגדירים ב־WooCommerce. לאיסוף עצמי מוסיפים שיטת ״איסוף עצמי״ באזור ישראל; זו שיטת האיסוף הנתמכת ברשימת ההכנות, ולא מסך נקודות האיסוף הנפרד של Blocks.</p><button class="button button-primary">שמירת פרטי העסק</button></form><p><a class="button" href="'.esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')).'">הגדרת משלוחים ואיסוף עצמי</a></p>';
    if (current_user_can('manage_options')) {
        if (isset($_GET['shipping-preview'])) {
            echo '<div class="tl-admin-note"><h3>תצוגה מקדימה — אזור אספקה ישראל</h3><p>הפעולה תיצור אזור בשם ישראל רק אם עדיין לא קיים אזור ישראל. היא לא תשנה אזור קיים, לא תוסיף שיטת משלוח ולא תקבע עלות.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tl_create_shipping_zone');echo '<input type="hidden" name="action" value="tl_create_shipping_zone"><label><input type="checkbox" name="confirm_empty_zone" value="1" required> אני מאשר/ת יצירת אזור ריק בלבד.</label><button class="button">יצירת האזור</button></form></div>';
        } else echo '<a href="'.esc_url(add_query_arg(['page'=>'tl-luxury','shipping-preview'=>'1'],admin_url('admin.php'))).'">תצוגה מקדימה להכנת אזור ישראל — ללא שינוי הגדרות</a>';
    }
    echo '</section>';
    if (current_user_can('manage_options')) {
        echo '<section class="tl-panel"><h2>הכנת עמודים ללא דריסת תוכן</h2><p>הפעולה יוצרת רק עמודים חסרים. עמודי תקנון, פרטיות, משלוחים, נגישות ומידות נוצרים כטיוטות להשלמת בעלי החנות. היא אינה מוסיפה מוצרים, משתמשים או מחירים.</p><form action="'.esc_url(admin_url('admin-post.php')).'" method="post">';wp_nonce_field('tl_create_base_pages');echo '<input type="hidden" name="action" value="tl_create_base_pages"><button class="button">הכנת עמודי החנות החסרים</button></form></section>';
        $saved = get_option('tl_launch_confirmations', []);
        echo '<section class="tl-panel" id="tl-acceptance"><h2>בדיקות קבלה לפני פתיחה</h2><p>את הבדיקות מבצעים באתר הבדיקה של האחסון, עם מצב הבדיקה הרשמי של PayPlus. התיבות מתעדות בדיקות שבוצעו בפועל; הן אינן מפעילות סליקה, משלוח או דואר.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tl_save_acceptance');echo '<input type="hidden" name="action" value="tl_save_acceptance">';
        foreach (tl_launch_confirmations() as $key=>$label) echo '<label class="tl-confirmation"><input type="checkbox" name="confirm['.esc_attr($key).']" value="1" '.checked(isset($saved[$key]),true,false).'> '.esc_html($label).'</label>';
        echo '<button class="button button-primary">שמירת אישורי הבדיקות</button></form></section>';
    }
}
