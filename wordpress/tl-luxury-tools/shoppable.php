<?php
/** Native, owner-editable video hero and product-linked editorial imagery. */
defined('ABSPATH') || exit;

function tl_shoppable_defaults(){
    $file=__DIR__.'/shoppable-content.json';$data=is_readable($file)?json_decode(file_get_contents($file),true):[];
    return is_array($data)?$data:[];
}
function tl_shoppable_settings(){
    $saved=get_option('tl_shoppable',[]);
    return is_array($saved)?$saved:[];
}
function tl_shoppable_text($value,$limit=200){return mb_substr(sanitize_textarea_field(is_scalar($value)?(string)$value:''),0,$limit);}
function tl_shoppable_attachment($value,$video=false){
    if(!is_scalar($value)||!preg_match('/^\d+$/',(string)$value))return false;
    $id=absint($value);if(!$id)return 0;$post=get_post($id);
    if(!$post||$post->post_type!=='attachment'||$post->post_status==='trash')return false;
    return ($video?in_array(get_post_mime_type($id),['video/mp4','video/webm'],true):wp_attachment_is_image($id))?$id:false;
}
function tl_shoppable_product($value,$sku=false){
    if(!function_exists('wc_get_product'))return false;
    $id=$sku?wc_get_product_id_by_sku((string)$value):absint($value);$product=$id?wc_get_product($id):false;
    if($product&&$product->get_parent_id())$product=wc_get_product($product->get_parent_id());
    return $product&&$product->get_status()==='publish'&&$product->is_visible()?$product:false;
}
function tl_shoppable_validate($input){
    if(!is_array($input))return new WP_Error('invalid','נתוני הקמפיין אינם תקינים.');
    $out=['hero_video'=>0,'hero_poster'=>0,'look_image'=>0,'collection'=>'all','hotspots'=>[]];
    foreach(['hero_video'=>true,'hero_poster'=>false,'look_image'=>false] as $field=>$video){$value=tl_shoppable_attachment($input[$field]??0,$video);if($value===false)return new WP_Error('media','יש לבחור תמונה תקינה או סרטון MP4 / WebM מספריית המדיה.');$out[$field]=$value;}
    $collection=$input['collection']??'all';if(!is_scalar($collection))return new WP_Error('collection','יש לבחור קולקציה תקינה.');
    if($collection!=='all'){$term=get_term(absint($collection),'product_cat');if(!$term||is_wp_error($term))return new WP_Error('collection','הקולקציה שנבחרה אינה קיימת.');$out['collection']=(string)$term->term_id;}
    $out['ctaLabel']=tl_shoppable_text($input['ctaLabel']??'גלי את הקולקציה',60);
    $out['lookTitle']=tl_shoppable_text($input['lookTitle']??'כל הלוק, בפרטים.',100);
    $out['lookText']=tl_shoppable_text($input['lookText']??'לחצי על נקודה כדי לגלות את התכשיט.',200);
    $rows=is_array($input['hotspots']??null)?array_slice($input['hotspots'],0,3):[];
    foreach($rows as $row){if(!is_array($row))return new WP_Error('point','נתוני הנקודה אינם תקינים.');$raw_id=$row['product']??0;if($raw_id==='')continue;if(!is_scalar($raw_id)||!preg_match('/^\d+$/',(string)$raw_id))return new WP_Error('product','יש לבחור מוצר תקין מהחיפוש.');$id=absint($raw_id);if(!$id)continue;$product=tl_shoppable_product($id);if(!$product)return new WP_Error('product','יש לבחור מוצר ראשי מפורסם וגלוי בחנות.');
        foreach(['x','y'] as $axis){$raw=$row[$axis]??null;if(!is_scalar($raw)||!is_numeric($raw)||!is_finite((float)$raw)||(float)$raw<0||(float)$raw>100)return new WP_Error('position','מיקום הנקודה צריך להיות מספר בין 0 ל־100.');}
        $out['hotspots'][]=['product'=>$product->get_id(),'x'=>round((float)$row['x'],2),'y'=>round((float)$row['y'],2)];
    }
    return $out;
}
add_action('admin_post_tl_save_shoppable',function(){
    if(!current_user_can('manage_woocommerce'))wp_die('אין הרשאה לעריכת החנות.','',['response'=>403]);
    check_admin_referer('tl_save_shoppable');$settings=tl_shoppable_validate(wp_unslash($_POST));
    if(is_wp_error($settings))wp_die(esc_html($settings->get_error_message()),'בדיקת הקמפיין',['response'=>400,'back_link'=>true]);
    update_option('tl_shoppable',$settings,false);wp_safe_redirect(admin_url('admin.php?page=tl-luxury&saved=1#tl-shoppable'));exit;
});
add_action('admin_enqueue_scripts',function($hook){
    if($hook!=='toplevel_page_tl-luxury'||!current_user_can('manage_woocommerce'))return;
    wp_enqueue_media();wp_enqueue_script('wc-enhanced-select');
    wp_enqueue_style('woocommerce_admin_styles');
    wp_enqueue_script('tl-shoppable-admin',plugins_url('shoppable-admin.js',__FILE__),['jquery','media-editor','wc-enhanced-select'],'6.4.0',true);
},30);
add_action('wp_enqueue_scripts',function(){
    if(get_template()!=='tl-luxury-theme')return;
    wp_enqueue_style('tl-shoppable',get_template_directory_uri().'/assets/shoppable.css',['tl-experience'],'6.4.0');
    wp_enqueue_script('tl-shoppable',get_template_directory_uri().'/assets/shoppable.js',[],'6.4.0',true);
},30);
function tl_shoppable_media_field($field,$label,$id,$video=false){
    echo '<div class="tl-shoppable-media" data-kind="'.($video?'video':'image').'" data-field="'.esc_attr($field).'"><strong>'.esc_html($label).'</strong><input type="hidden" name="'.esc_attr($field).'" value="'.absint($id).'"><p class="tl-shoppable-filename">'.esc_html($id?get_the_title($id):'קובץ הקמפיין המקורי').'</p><button type="button" class="button tl-shoppable-select">בחירה מספריית המדיה</button> <button type="button" class="button tl-shoppable-reset">חזרה למקור</button><p role="status" class="tl-shoppable-feedback"></p></div>';
}
function tl_shoppable_admin_screen(){
    if(!current_user_can('manage_woocommerce'))return;$settings=tl_shoppable_settings();$defaults=tl_shoppable_defaults();$look=$defaults['look']??[];$hero=$defaults['hero']??[];
    echo '<section class="tl-panel" id="tl-shoppable"><h2>סרטון הפתיחה ותכשיטים בתוך התמונה</h2><p>כאן מחליפים את סרטון הפתיחה ואת תמונת הלוק, ובוחרים אילו תכשיטים ייפתחו בלחיצה על הנקודות. השינויים נשמרים באותה חנות.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tl_save_shoppable');echo '<input type="hidden" name="action" value="tl_save_shoppable">';
    tl_shoppable_media_field('hero_video','סרטון הפתיחה',absint($settings['hero_video']??0),true);tl_shoppable_media_field('hero_poster','תמונת פתיחה לפני הפעלת הסרטון',absint($settings['hero_poster']??0));
    echo '<p>כותרת הפתיחה והטקסט שלה נערכים באזור ״תוכן ותמונות האתר״ שמעל. הסרטון מוצג ללא קול וכולל כפתור עצירה. בהעדפת הפחתת תנועה או חיסכון בנתונים הוא ממתין להפעלה ידנית.</p><label for="tl-hero-cta">טקסט כפתור הפתיחה</label><input id="tl-hero-cta" name="ctaLabel" maxlength="60" value="'.esc_attr($settings['ctaLabel']??($hero['ctaLabel']??'גלי את הקולקציה')).'"><label for="tl-hero-collection">לאיזו קולקציה הכפתור מוביל?</label><select name="collection" id="tl-hero-collection"><option value="all">כל התכשיטים</option>';
    $terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);if(!is_wp_error($terms))foreach($terms as $term)echo '<option value="'.absint($term->term_id).'" '.selected((string)($settings['collection']??'all'),(string)$term->term_id,false).'>'.esc_html($term->name).'</option>';echo '</select>';
    tl_shoppable_media_field('look_image','תמונה עם נקודות לבחירת תכשיטים',absint($settings['look_image']??0));
    foreach(['lookTitle'=>['כותרת הלוק',$look['title']??'כל הלוק, בפרטים.'],'lookText'=>['הסבר קצר',$look['text']??'לחצי על נקודה כדי לגלות את התכשיט.']] as $name=>$data)echo '<label for="tl-'.$name.'">'.esc_html($data[0]).'</label><input id="tl-'.$name.'" name="'.$name.'" maxlength="200" value="'.esc_attr($settings[$name]??$data[1]).'">';
    $image=absint($settings['look_image']??0);$preview=$image?wp_get_attachment_image_url($image,'large'):get_template_directory_uri().'/assets/'.basename($look['image']??'look-v10.webp');
    echo '<p>בוחרים מוצר דרך החיפוש, מסמנים איזו נקודה עורכים ואז לוחצים על המקום הרצוי בתמונה. X נמדד משמאל לימין ו־Y מלמעלה למטה, באחוזים. אפשר גם להזין מספרים. אפשר להשאיר מוצר ריק כדי להסיר נקודה.</p><div class="tl-look-coordinate-preview" style="position:relative;max-width:700px;margin:20px 0;cursor:crosshair"><img src="'.esc_url($preview).'" alt="תצוגה לבחירת מיקום הנקודות" style="display:block;width:100%;height:auto"></div>';
    $points=$settings['hotspots']??($look['hotspots']??[]);
    for($i=0;$i<3;$i++){$point=$points[$i]??[];$product=isset($point['product'])?tl_shoppable_product($point['product']):tl_shoppable_product($point['productId']??'',true);$x=$point['x']??50;$y=$point['y']??50;
        echo '<fieldset class="tl-hotspot-editor" data-index="'.$i.'" style="border:1px solid #ccc;padding:16px;margin:16px 0"><legend>נקודה '.($i+1).'</legend><label style="display:flex;gap:10px"><input type="radio" name="active_point" value="'.$i.'" '.checked($i,0,false).' style="width:auto">עריכת המיקום של נקודה זו</label><label for="tl-hotspot-product-'.$i.'">בחירת מוצר מהחנות</label><select id="tl-hotspot-product-'.$i.'" class="wc-product-search" name="hotspots['.$i.'][product]" style="width:100%" data-placeholder="הקלידו שם תכשיט או מק״ט" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">';if($product)echo '<option value="'.$product->get_id().'" selected>'.esc_html($product->get_formatted_name()).'</option>';echo '</select><label for="tl-hotspot-x-'.$i.'">X — מרחק משמאל (%)</label><input id="tl-hotspot-x-'.$i.'" type="number" min="0" max="100" step="0.01" name="hotspots['.$i.'][x]" value="'.esc_attr($x).'"><label for="tl-hotspot-y-'.$i.'">Y — מרחק מלמעלה (%)</label><input id="tl-hotspot-y-'.$i.'" type="number" min="0" max="100" step="0.01" name="hotspots['.$i.'][y]" value="'.esc_attr($y).'"></fieldset>';
    }
    echo '<p class="tl-shoppable-coordinate-status" role="status"></p><button class="button button-primary" type="submit">שמירת סרטון הפתיחה והנקודות</button></form></section>';
}
function tl_shoppable_hero(){
    $defaults=tl_shoppable_defaults();$hero=$defaults['hero']??[];$saved=tl_shoppable_settings();$assets=get_template_directory_uri().'/assets/';$video=absint($saved['hero_video']??0);$poster=absint($saved['hero_poster']??0);$poster_url=$poster?wp_get_attachment_image_url($poster,'full'):false;$collection=$saved['collection']??($hero['collection']??'all');$href=function_exists('wc_get_page_permalink')?wc_get_page_permalink('shop'):home_url('/');
    if($collection!=='all'){$term=is_numeric($collection)?get_term(absint($collection),'product_cat'):get_term_by('slug',$collection,'product_cat');if($term&&!is_wp_error($term)){$link=get_term_link($term);if(!is_wp_error($link))$href=$link;}}
    $portrait=($hero['layout']??'')==='portrait';if($video){$meta=wp_get_attachment_metadata($video);if(is_array($meta)&&!empty($meta['width'])&&!empty($meta['height']))$portrait=$meta['height']>$meta['width'];}
    $opening=$poster_url?:$assets.basename($hero['poster']??'hero-v11-poster.webp');
    echo '<section class="tl-video-hero'.($portrait?' tl-video-hero-portrait':'').'" aria-labelledby="tl-video-hero-title"><img class="tl-video-hero-backdrop" src="'.esc_url($opening).'" alt="" aria-hidden="true" width="1080" height="1920"><img class="tl-video-hero-still" src="'.esc_url($opening).'" alt="" aria-hidden="true" width="1080" height="1920"><video class="tl-video-hero-media" muted playsinline loop disablepictureinpicture preload="none" poster="'.esc_url($poster_url?:$assets.basename($hero['poster']??'hero-v10-poster.webp')).'"'.(!$poster&&!$video&&!empty($hero['mobilePoster'])?' data-mobile-poster="'.esc_url($assets.basename($hero['mobilePoster'])).'"':'').' aria-hidden="true" tabindex="-1">';
    if($video)echo '<source src="'.esc_url(wp_get_attachment_url($video)).'" type="'.esc_attr(get_post_mime_type($video)).'">';else{if(!empty($hero['mobileMp4']))echo '<source media="(max-width:767px)" src="'.esc_url($assets.basename($hero['mobileMp4'])).'" type="video/mp4">';foreach(['webm'=>'video/webm','mp4'=>'video/mp4'] as $key=>$mime)echo '<source src="'.esc_url($assets.basename($hero[$key]??'hero-v10.'.$key)).'" type="'.$mime.'">';}
    echo '</video><div class="tl-video-hero-shade" aria-hidden="true"></div><div class="tl-video-hero-content"><span>תכשיטי מואסנייט · T&amp;L</span><h1 id="tl-video-hero-title">'.nl2br(esc_html(tl_text('hero_title',$hero['title']??"יהלום הוא לא\nהדרך היחידה ליוקרה."))).'</h1><p>'.nl2br(esc_html(tl_text('hero_text',$hero['text']??'נוצרו לרגעים המיוחדים, ונועדו להאיר גם את היומיום.'))).'</p><a class="tl-button" href="'.esc_url($href).'">'.esc_html(($saved['ctaLabel']??'')?:($hero['ctaLabel']??'גלי את הקולקציה')).' <span aria-hidden="true">←</span></a></div><button type="button" class="tl-video-hero-toggle tl-video-hero-motion-control" aria-label="שליטה בתנועת הרקע" aria-pressed="false">שליטה בתנועת הרקע</button><p class="tl-video-hero-status screen-reader-text" role="status"></p></section>';
}
function tl_shoppable_look(){
    $defaults=tl_shoppable_defaults();$look=$defaults['look']??[];$saved=tl_shoppable_settings();$points=$saved['hotspots']??($look['hotspots']??[]);$resolved=[];
    foreach($points as $index=>$point){$product=isset($point['product'])?tl_shoppable_product($point['product']):tl_shoppable_product($point['productId']??'',true);if(!$product)continue;$x=$point['x']??null;$y=$point['y']??null;if(!is_numeric($x)||!is_numeric($y)||$x<0||$x>100||$y<0||$y>100)continue;$resolved[]=['product'=>$product,'x'=>$x,'y'=>$y,'index'=>$index];}
    if(!$resolved)return;
    $image=absint($saved['look_image']??0);$url=$image?wp_get_attachment_image_url($image,'full'):false;$url=$url?:get_template_directory_uri().'/assets/'.basename($look['image']??'look-v10.webp');
    echo '<section class="tl-section tl-shoppable-look" aria-labelledby="tl-look-heading"><div class="tl-section-heading"><h2 id="tl-look-heading">'.esc_html(($saved['lookTitle']??'')?:($look['title']??'כל הלוק, בפרטים.')).'</h2><p>'.esc_html(($saved['lookText']??'')?:($look['text']??'לחצי על נקודה כדי לגלות את התכשיט.')).'</p></div><div class="tl-look-image"><img src="'.esc_url($url).'" alt="'.esc_attr($look['alt']??'תצלום לוק עם תכשיטים לבחירה'). '" loading="lazy" width="1536" height="1024">';
    foreach($resolved as $point){$product=$point['product'];$id='tl-hotspot-'.$point['index'];echo '<button type="button" class="tl-product-hotspot" style="left:'.esc_attr($point['x']).'%;top:'.esc_attr($point['y']).'%" aria-haspopup="dialog" aria-controls="'.$id.'" aria-label="לגלות את '.esc_attr($product->get_name()).'"><span aria-hidden="true">+</span></button>';}
    echo '</div><div class="tl-look-product-links" aria-label="התכשיטים שבתמונה">';foreach($resolved as $point)echo '<a href="'.esc_url($point['product']->get_permalink()).'">'.esc_html($point['product']->get_name()).' ←</a>';echo '</div>';
    foreach($resolved as $point){$product=$point['product'];$id='tl-hotspot-'.$point['index'];echo '<dialog class="tl-product-drawer" id="'.$id.'" aria-labelledby="'.$id.'-title"><button class="tl-product-drawer-close" type="button" aria-label="סגירת פרטי התכשיט">סגירה ×</button><div class="tl-product-drawer-image">'.$product->get_image('woocommerce_single').'</div><div class="tl-product-drawer-copy"><span>התכשיט שבתמונה</span><h3 id="'.$id.'-title" tabindex="-1">'.esc_html($product->get_name()).'</h3><div class="tl-product-drawer-price">'.wp_kses_post($product->get_price_html()).'</div><a class="tl-button" href="'.esc_url($product->get_permalink()).'">לפרטים ובחירת מידה <span aria-hidden="true">←</span></a></div></dialog>';}
    echo '</section>';
}
