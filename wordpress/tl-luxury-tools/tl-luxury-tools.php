<?php
/**
 * Plugin Name: T&L — ניהול החנות
 * Description: A focused Hebrew WooCommerce dashboard and no-code storefront content editor. Demo seeding only runs when TL_LUXURY_DEMO is explicitly true.
 * Version: 5.0.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
function tl_is_demo(){return defined('TL_LUXURY_DEMO') && TL_LUXURY_DEMO;}
function tl_brand_story(){ $story=json_decode(file_get_contents(__DIR__.'/brand-story.json'),true);return implode("\n\n",$story['paragraphs']??[]); }
require_once __DIR__.'/production.php';
register_activation_hook(__FILE__, 'tl_activate_store_tools');
add_action('before_woocommerce_init',function(){if(class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil'))\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',__FILE__,true);});
add_action('admin_enqueue_scripts',function($hook){if($hook==='toplevel_page_tl-luxury'&&current_user_can('manage_woocommerce')){wp_enqueue_media();wp_enqueue_style('tl-management',plugins_url('dashboard.css',__FILE__),[],'5.0.0');wp_enqueue_script('tl-media-editor',plugins_url('media-editor.js',__FILE__),['jquery','media-editor'],'5.0.0',true);}});
add_action('admin_menu',function(){add_menu_page('T&L — ניהול החנות','T&L — החנות','manage_woocommerce','tl-luxury','tl_management_screen','dashicons-store',3);});
add_action('admin_post_tl_save_content',function(){
    if(!current_user_can('manage_woocommerce')) wp_die('אין הרשאה לעריכת החנות.',403);
    check_admin_referer('tl_save_content');
    foreach(['hero_title'=>120,'hero_text'=>260,'announcement'=>110,'sets_title'=>100,'sets_text'=>240] as $key=>$limit){
        $value=isset($_POST[$key])?sanitize_textarea_field(wp_unslash($_POST[$key])):'';
        update_option('tl_'.$key,mb_substr($value,0,$limit));
    }
    update_option('tl_contact',preg_replace('/[^0-9]/','',substr(wp_unslash($_POST['contact']??''),0,30)));
    foreach(['hero_image','hero_mobile_image','sets_image','about_image'] as $key){$id=absint($_POST[$key]??0);if($id===0||wp_attachment_is_image($id))update_option('tl_'.$key,$id);}
    wp_safe_redirect(admin_url('admin.php?page=tl-luxury&saved=1'));exit;
});
function tl_management_screen(){
    if(!current_user_can('manage_woocommerce')) return;
    $user=wp_get_current_user();
    echo '<div class="wrap" dir="rtl"><style>.tl-image-field img[hidden]{display:none!important}.tl-dashboard{max-width:1180px;font-size:16px;font-family:Arial,sans-serif}.tl-dashboard h1{font-size:30px;line-height:1.4;margin-bottom:12px}.tl-dashboard h2{font-size:22px;margin:0 0 18px}.tl-dashboard .tl-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin:25px 0}.tl-dashboard .tl-action,.tl-panel{background:white;border:1px solid #dcdcde;padding:24px;text-decoration:none;color:#1d2327}.tl-action strong{display:block;font-size:22px;margin-bottom:8px}.tl-panel{margin:24px 0}.tl-panel label{display:block;font-weight:600;margin-top:20px}.tl-panel input,.tl-panel textarea{display:block;width:100%;max-width:620px;margin:8px 0;font-size:16px;padding:10px}.tl-panel textarea{min-height:90px}.tl-dashboard .button{font-size:16px;min-height:42px;line-height:40px;padding:0 22px}.tl-dashboard .button-primary{background:#191919;border-color:#191919}.tl-checklist li{margin-bottom:12px}.tl-admin-note{background:#f0f0f1;padding:14px 18px;border-right:3px solid #191919}.tl-users{display:flex;gap:30px;flex-wrap:wrap}.tl-user{padding:16px 22px;border:1px solid #dcdcde;min-width:220px}@media(max-width:900px){.tl-dashboard .tl-actions{grid-template-columns:1fr}}</style><div class="tl-dashboard">';
    echo '<h1>ניהול החנות של T&amp;L</h1><p>שלום, '.esc_html($user->display_name).'. מכאן אפשר לעדכן את החנות בלי לערוך קוד.</p>';
    if(tl_is_demo()) echo '<p class="tl-admin-note">WooCommerce אמיתי בסביבת הדגמה אישית בדפדפן. שני חשבונות ניהול נפרדים, אך הדמו אינו שרת משותף. אין סליקה, משלוח או שליחת דואר בפועל.</p>';
    if(isset($_GET['saved'])) echo '<div class="notice notice-success"><p>התוכן נשמר. השינוי כבר מופיע בחנות.</p></div>';
    $links=[['edit.php?post_type=product','מוצרים','תמונות, מחירים, מידות ומלאי'],['admin.php?page=wc-orders','הזמנות','מצב הזמנה, לקוחות והחזרים'],['edit.php?post_type=shop_coupon','קופונים','קוד הנחה, תוקף ותנאים'],['edit-tags.php?taxonomy=product_cat&post_type=product','קולקציות','טבעות, עגילים, שרשראות, צמידים וסטים'],['admin.php?page=wc-admin&path=%2Fanalytics%2Foverview','דוחות','מכירות ומוצרים — נתוני אמת בלבד'],['admin.php?page=wc-settings&tab=shipping','משלוחים','אזורים, שיטות ומחירים']];
    echo '<div class="tl-actions">';foreach($links as $item)echo '<a class="tl-action" href="'.esc_url(admin_url($item[0])).'"><strong>'.esc_html($item[1]).'</strong>'.esc_html($item[2]).'</a>';echo '</div>';
    tl_launch_dashboard();
    echo '<section class="tl-panel"><h2>עריכת עמוד הבית</h2><form action="'.esc_url(admin_url('admin-post.php')).'" method="post">';wp_nonce_field('tl_save_content');echo '<input type="hidden" name="action" value="tl_save_content">';
    foreach(['hero_title'=>['כותרת ראשית',"יהלום הוא לא\nהדרך היחידה ליוקרה."],'hero_text'=>['טקסט פתיחה',"נוצרו לרגעים המיוחדים,\nונועדו להאיר גם את היומיום."],'announcement'=>['הודעה בראש האתר','T&L · תכשיטי מואסנייט'],'sets_title'=>['כותרת אזור הסטים',"תכשיטים\nשמשתלבים."],'sets_text'=>['תיאור אזור הסטים',"שרשראות, צמידים ועגילים.\nיחד או בנפרד."]] as $key=>$field){echo '<label for="tl-'.$key.'">'.esc_html($field[0]).'</label><textarea id="tl-'.$key.'" name="'.$key.'">'.esc_textarea(get_option('tl_'.$key,$field[1])).'</textarea>';}
    echo '<h3>תמונות האתר</h3><p>בחירת תמונה פותחת את ספריית המדיה של WordPress. אפשר להעלות קובץ מהמחשב או לבחור תמונה קיימת.</p>';
    foreach(['hero_image'=>'תמונה ראשית','hero_mobile_image'=>'תמונה נפרדת לטלפון — לא חובה','sets_image'=>'תמונת הסטים','about_image'=>'תמונת עמוד המותג'] as $key=>$label){$id=absint(get_option('tl_'.$key,0));$url=$id?wp_get_attachment_image_url($id,'medium'):'';echo '<div class="tl-image-field" style="padding:18px 0;border-bottom:1px solid #ddd"><strong>'.esc_html($label).'</strong><input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($id).'"><img src="'.esc_url($url?:'').'" alt="תצוגה מקדימה" style="max-width:190px;max-height:140px;object-fit:contain;display:block;margin:12px 0" '.($url?'':'hidden').'><button type="button" class="button tl-select-image">בחירת תמונה</button> <button type="button" class="button tl-remove-image">חזרה לתמונה המקורית</button><p class="tl-image-feedback" role="status"></p></div>';}
    $about=get_page_by_path('about');if($about)echo '<p><a href="'.esc_url(get_edit_post_link($about->ID)).'">עריכת הטקסט בעמוד על T&L</a></p>';
    echo '<label for="tl-contact">מספר WhatsApp עסקי, כולל קידומת המדינה</label><input id="tl-contact" name="contact" inputmode="numeric" value="'.esc_attr(get_option('tl_contact','')).'"><p>למשל 9725… ללא סימן +. אם השדה ריק, לא מופיע קישור WhatsApp.</p><button class="button button-primary" type="submit">שמירת השינויים</button> <a class="button" href="'.esc_url(home_url('/')).'">לצפייה בחנות</a></form></section>';
    echo '<section class="tl-panel"><h2>מנהלי החנות, חשבונות נפרדים</h2><div class="tl-users">';$managers=get_users(['role'=>'shop_manager','number'=>10]);foreach($managers as $manager) echo '<div class="tl-user"><strong>'.esc_html($manager->display_name).'</strong><p>'.esc_html($manager->user_login).' · מנהל חנות</p></div>';if(!$managers)echo '<p>אחרי התקנת החנות יוצרים שני חשבונות אישיים בתפקיד מנהל חנות. התוסף אינו יוצר משתמשים או סיסמאות.</p>';echo '</div><p>כל מנהל מתחבר לחשבון שלו. שניהם עורכים את אותם מוצרים, הזמנות וקופונים בחנות שמותקנת על שרת. התקנת תוספים ושינויים בתשתית נשארים אצל בעל הרשאת מנהל מערכת.</p></section>';
    echo '<section class="tl-panel"><h2>שיווק החנות</h2><ul class="tl-checklist"><li><a href="'.esc_url(admin_url('edit.php?post_type=shop_coupon')).'">יצירת קופון</a> — אחוז או סכום קבוע, תאריך תפוגה והגבלת שימוש.</li><li><a href="https://woocommerce.com/products/google-listings-and-ads/" target="_blank" rel="noopener noreferrer">Google for WooCommerce</a> — קטלוג מוצרים ב־Google לאחר חיבור חשבון.</li><li><a href="https://woocommerce.com/products/facebook/" target="_blank" rel="noopener noreferrer">Meta for WooCommerce</a> — סנכרון קטלוג וקמפיינים לאחר חיבור חשבון.</li><li>כותרת ותיאור לכל מוצר, תמונה עם טקסט חלופי, כתובת קצרה וקולקציה מתאימה.</li><li>קישורי UTM לקמפיינים; GA4, דיוור ושחזור סל לאחר בחירת ספק וחיבור החשבונות.</li></ul><p>חיבור חשבונות פרסום, דיוור ומדידה נעשה לפי בחירת בעלי החנות. מגדירים הסכמות ובודקים אירועים לפני הפעלת מעקב; אין אישור חיבור אוטומטי בלוח הזה.</p></section>';
    echo '<section class="tl-panel"><h2>סליקה: PayPlus</h2><p>הכיוון שנבחר: התוסף הרשמי של PayPlus ל־WooCommerce, עם עמוד תשלום חיצוני. מפתחות API נשמרים בהגדרות התוסף בשרת בלבד. אין פרטי כרטיס באתר הזה.</p><a href="https://docs.payplus.co.il/reference/post_paymentpages-generatelink" target="_blank" rel="noopener noreferrer">תיעוד API רשמי</a>';
    if(current_user_can('manage_options'))echo ' · <a href="'.esc_url(admin_url('admin.php?page=wc-settings&tab=checkout')).'">הגדרות אמצעי תשלום</a>';
    echo '<p>נדרשים חשבון מסחרי, הצעת מחיר ובדיקות תשלום, הודעה חתומה והחזר לפני פתיחת החנות.</p></section></div></div>';
}
add_action('pre_get_posts',function($query){if(!is_admin() && $query->is_main_query() && isset($_GET['on_sale']) && $query->get('post_type')==='product'){$ids=wc_get_product_ids_on_sale();$query->set('post__in',$ids?:[0]);}});
// Browser-only Playground never sends real email. Production readiness is enforced in production.php.
add_filter('pre_wp_mail',function($return){return tl_is_demo()?true:$return;},99);
add_filter('woocommerce_enable_setup_wizard',function($enabled){return tl_is_demo()?false:$enabled;});
function tl_seed_demo(){
    if(!tl_is_demo() || !class_exists('WooCommerce')) return;
    if(get_option('tl_demo_seeded')==='2') return;
    WC_Install::create_pages();
    $names=['rings'=>'טבעות','earrings'=>'עגילים','necklaces'=>'שרשראות','bracelets'=>'צמידים','sets'=>'סטים'];$categories=[];
    foreach($names as $slug=>$name){$term=get_term_by('slug',$slug,'product_cat');if(!$term){$new=wp_insert_term($name,'product_cat',['slug'=>$slug]);if(is_wp_error($new))continue;$categories[$slug]=$new['term_id'];}else{$categories[$slug]=$term->term_id;}}
    $data=json_decode(file_get_contents(__DIR__.'/catalogue.json'),true);
    foreach($data as $index=>$item){
        if(wc_get_product_id_by_sku($item['id']))continue;
        $variable=count($item['sizes']??[])>1;
        $product=$variable?new WC_Product_Variable():new WC_Product_Simple();
        $product->set_name($item['name']);$product->set_slug($item['slug']);$product->set_sku($item['id']);$product->set_status('publish');$product->set_menu_order($index);
        $product->set_description(tl_clean_seed_copy($item['description']));
        $product->set_short_description('מואסנייט · '.($item['metal']==='gold'?'גוון זהב':'גוון כסף'));
        $product->set_category_ids([$categories[$item['category']]]);$product->set_stock_status('instock');
        $product->update_meta_data('_tl_demo_image',$item['img']);$product->update_meta_data('_tl_demo_image_source',$item['source']);
        $product->update_meta_data('_tl_demo_product',true);
        $product->update_meta_data('_tl_product_approved','no');
        $attribute=new WC_Product_Attribute();$attribute->set_name('גוון');$attribute->set_options([$item['metal']==='gold'?'זהב':'כסף']);$attribute->set_visible(true);$attribute->set_variation(false);$attributes=[$attribute];
        if($variable){$size=new WC_Product_Attribute();$size->set_name('מידה');$size->set_options($item['sizes']);$size->set_visible(true);$size->set_variation(true);$attributes[]=$size;}
        $product->set_attributes($attributes);
        if(!$variable){$product->set_regular_price((string)($item['compareAt']?:$item['price']));if($item['compareAt'])$product->set_sale_price((string)$item['price']);$product->set_manage_stock(true);$product->set_stock_quantity(12);}
        $id=$product->save();
        if($variable){foreach($item['sizes'] as $number){$variation=new WC_Product_Variation();$variation->set_parent_id($id);$variation->set_attributes([sanitize_title('מידה')=>(string)$number]);$variation->set_regular_price((string)($item['compareAt']?:$item['price']));if($item['compareAt'])$variation->set_sale_price((string)$item['price']);$variation->set_manage_stock(true);$variation->set_stock_quantity(5);$variation->set_status('publish');$variation->save();}WC_Product_Variable::sync($id);}
    }
    $pages=['home'=>['בית',''],'about'=>['על T&L',wpautop(tl_brand_story())],'sizes'=>['מדריך מידות','<p>לכל תכשיט מוצגות המידות הזמינות בעמוד המוצר. מדריך המדידה המלא יפורסם עם פתיחת הרכישה באתר.</p>'],'delivery'=>['משלוחים והחזרות','<p>הרכישה באתר תיפתח בקרוב. אזורי האספקה, העלויות, זמני המשלוח ותנאי ההחזרה יפורסמו לפני פתיחת ההזמנות.</p>'],'contact'=>['יצירת קשר','[tl_business_contact]'],'privacy'=>['פרטיות','<p>הרכישה באתר תיפתח בקרוב. מדיניות הפרטיות ופרטי הגורם האחראי למידע יפורסמו לפני פתיחת טפסי הלקוחות והרכישה.</p>'],'terms'=>['תקנון החנות','<p>תנאי הרכישה, פרטי העסק, האחריות והביטולים יפורסמו לפני פתיחת ההזמנות באתר.</p>'],'accessibility'=>['נגישות','<p>האתר כולל ניווט מקלדת, כיווניות מימין לשמאל, הגדלת טקסט והתאמה להעדפה להפחתת תנועה. הצהרת הנגישות ופרטי איש הקשר יפורסמו לאחר השלמת הבדיקה ולפני פתיחת ההזמנות.</p>']];
    foreach($pages as $slug=>$page){$existing=get_page_by_path($slug);$page_id=$existing?$existing->ID:wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$page[0],'post_content'=>$page[1]]);if(!$existing&&in_array($slug,['sizes','delivery','privacy','terms','accessibility'],true))update_post_meta($page_id,'_tl_placeholder_content_hash',hash('sha256',$page[1]));if($slug==='home'){update_option('show_on_front','page');update_option('page_on_front',$page_id);}}
    $coupon=new WC_Coupon();$coupon->set_code('TL10');$coupon->set_discount_type('percent');$coupon->set_amount('10');$coupon->set_individual_use(true);$coupon->set_usage_limit(100);$coupon->set_description('קופון להדגמת ניהול — אין חיוב בדמו');$coupon->save();
    update_option('woocommerce_currency','ILS');update_option('woocommerce_default_country','IL');update_option('woocommerce_enable_coupons','yes');update_option('woocommerce_calc_taxes','no');update_option('woocommerce_store_address','');update_option('woocommerce_onboarding_profile',['completed'=>true]);update_option('woocommerce_task_list_hidden','yes');update_option('woocommerce_allow_tracking','no');update_option('blog_public','0');update_option('tl_demo_seeded','2');
    flush_rewrite_rules();
}
