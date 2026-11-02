<?php
/** Optional storefront content. No tracking scripts or external services are installed here. */
defined('ABSPATH') || exit;

function tl_experience_defaults(){
    $path=__DIR__.'/experience-content.json';
    $data=is_readable($path)?json_decode(file_get_contents($path),true):[];
    return is_array($data)?$data:[];
}
function tl_experience_settings(){
    $defaults=tl_experience_defaults();
    $saved=get_option('tl_experience',[]);
    if(!is_array($saved))$saved=[];
    $settings=array_merge(['reviewMode'=>'hidden','sampleReviews'=>array_column($defaults['reviews']??[],'text'),'realReviews'=>[],'realConfirmed'=>false,'films'=>[]],$saved);
    if(!in_array($settings['reviewMode'],['hidden','real'],true))$settings['reviewMode']='hidden';
    return $settings;
}
function tl_experience_text($value,$limit=600){return mb_substr(sanitize_textarea_field(is_scalar($value)?(string)$value:''),0,$limit);}
function tl_experience_attachment($id,$kind){
    $id=absint($id);if(!$id)return 0;
    $post=get_post($id);
    if(!$post||$post->post_type!=='attachment'||$post->post_status==='trash')return false;
    $mime=get_post_mime_type($id);
    if($kind==='poster')return wp_attachment_is_image($id)?$id:false;
    return in_array($mime,['video/mp4','video/webm'],true)?$id:false;
}
function tl_experience_validate($input){
    if(!is_array($input))return new WP_Error('invalid','הנתונים שנשלחו אינם תקינים.');
    $mode=$input['review_mode']??'hidden';
    if(!in_array($mode,['hidden','real'],true))return new WP_Error('mode','יש לבחור מצב תצוגה תקין.');
    $previous=tl_experience_settings();
    $out=['reviewMode'=>$mode,'sampleReviews'=>is_array($previous['sampleReviews'])?$previous['sampleReviews']:[],'realReviews'=>[],'realConfirmed'=>false,'films'=>[]];
    $values=is_array($input['real_review']??null)?array_slice($input['real_review'],0,3):[];
    foreach($values as $value){$text=tl_experience_text($value);if($text!=='')$out['realReviews'][]=$text;}
    if($mode==='real'){
        if(($input['confirm_real']??'')!=='1'||!$out['realReviews'])return new WP_Error('attestation','כדי לפרסם המלצות אמיתיות יש להזין טקסט שהתקבל מלקוחה ולאשר רשות לפרסמו ללא שם.');
        $out['realConfirmed']=true;
    }
    foreach(['gold','silver'] as $slug){
        $film=is_array($input['film'][$slug]??null)?$input['film'][$slug]:[];
        $video=tl_experience_attachment($film['video']??0,'video');$poster=tl_experience_attachment($film['poster']??0,'poster');
        if($video===false||$poster===false)return new WP_Error('media','יש לבחור סרטון MP4 או WebM ותמונת כרזה תקינה מספריית המדיה.');
        $out['films'][$slug]=['video'=>$video,'poster'=>$poster,'title'=>tl_experience_text($film['title']??'',100),'description'=>tl_experience_text($film['description']??'',300)];
    }
    return $out;
}
add_action('admin_post_tl_save_experience',function(){
    if(!current_user_can('manage_woocommerce'))wp_die('אין הרשאה לעריכת החנות.','', ['response'=>403]);
    check_admin_referer('tl_save_experience');
    $settings=tl_experience_validate(wp_unslash($_POST));
    if(is_wp_error($settings))wp_die(esc_html($settings->get_error_message()),'בדיקת התוכן',['response'=>400,'back_link'=>true]);
    update_option('tl_experience',$settings,false);
    wp_safe_redirect(admin_url('admin.php?page=tl-luxury&saved=1#tl-experience'));exit;
});
function tl_experience_admin_screen(){
    if(!current_user_can('manage_woocommerce'))return;
    $settings=tl_experience_settings();$defaults=tl_experience_defaults();
    echo '<section class="tl-panel" id="tl-experience"><h2>המלצות וסרטוני הקמפיין</h2><p>כל התוכן נשמר באותה חנות, דרך לוח הניהול הזה. אין צורך לערוך קוד.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('tl_save_experience');echo '<input type="hidden" name="action" value="tl_save_experience"><label for="tl-review-mode">תצוגת המלצות</label><select name="review_mode" id="tl-review-mode">';
    foreach(['hidden'=>'הסתרת אזור ההמלצות','real'=>'המלצות אמיתיות, ללא שמות'] as $value=>$label)echo '<option value="'.esc_attr($value).'" '.selected($settings['reviewMode'],$value,false).'>'.esc_html($label).'</option>';
    echo '</select><p>אזור ההמלצות מוסתר עד שמזינים המלצות שהתקבלו מלקוחות ומאשרים רשות לפרסמן. אין באתר דירוג כוכבים או סימון רכישה מאומתת.</p><h3>המלצות שהתקבלו מלקוחות</h3><p>מזינים רק את הטקסט שהלקוחה אישרה. השם שלה אינו מוצג באתר.</p>';
    for($i=0;$i<3;$i++)echo '<label for="tl-real-'.$i.'">המלצה אמיתית '.($i+1).'</label><textarea maxlength="600" id="tl-real-'.$i.'" name="real_review[]">'.esc_textarea($settings['realReviews'][$i]??'').'</textarea>';
    echo '<label style="display:flex;gap:10px;align-items:start"><input style="width:auto;margin-top:5px" type="checkbox" name="confirm_real" value="1">אני מאשר/ת שההמלצות האמיתיות התקבלו מלקוחות, ושיש לעסק רשות לפרסמן ללא שמות. נדרש אישור בכל שמירה במצב אמיתי.</label><h3>סרטוני קמפיין</h3><p>אפשר לבחור סרטון MP4 או WebM וכרזה מתוך ספריית המדיה. ברירת המחדל היא סרטון אווירה המבוסס על תמונת AI, ולא צילום של מוצר למכירה. אין הפעלה אוטומטית.</p>';
    foreach($defaults['films']??[] as $film){$slug=$film['id'];$saved=$settings['films'][$slug]??[];
        echo '<fieldset style="padding:20px 0;border:0;border-top:1px solid #ddd"><legend>'.esc_html($film['title']).'</legend><label for="tl-film-title-'.$slug.'">כותרת הסרטון</label><input id="tl-film-title-'.$slug.'" name="film['.$slug.'][title]" maxlength="100" value="'.esc_attr($saved['title']??$film['title']).'"><label for="tl-film-description-'.$slug.'">תיאור חזותי קצר</label><textarea id="tl-film-description-'.$slug.'" name="film['.$slug.'][description]" maxlength="300">'.esc_textarea($saved['description']??$film['description']).'</textarea>';
        foreach(['video'=>'סרטון','poster'=>'תמונת כרזה'] as $kind=>$label){$id=absint($saved[$kind]??0);$attachment=$id?get_the_title($id):'';
            echo '<div class="tl-film-media" data-kind="'.esc_attr($kind).'"><strong>'.esc_html($label).'</strong><input type="hidden" name="film['.$slug.']['.$kind.']" value="'.$id.'"><p class="tl-film-filename">'.esc_html($attachment?:'קובץ הקמפיין המקורי').'</p><button type="button" class="button tl-select-film-media">בחירה מספריית המדיה</button> <button type="button" class="button tl-remove-film-media">חזרה למקור</button><p class="tl-film-feedback" role="status"></p></div>';
        }echo '</fieldset>';
    }
    echo '<button type="submit" class="button button-primary">שמירת המלצות וסרטונים</button></form></section>';
}
function tl_experience_sections(){
    $defaults=tl_experience_defaults();$settings=tl_experience_settings();$assets=get_template_directory_uri().'/assets/';
    echo '<section class="tl-section tl-campaign-films" aria-labelledby="tl-films-heading"><div class="tl-section-heading"><h2 id="tl-films-heading">באור אחר.</h2></div><p class="tl-experience-note">סרטוני הקמפיין המקוריים מבוססים על תמונות שנוצרו ב־AI. התכשיטים בהם להמחשה, ולא תמונות מוצר מחייבות.</p><div class="tl-film-grid">';
    foreach($defaults['films']??[] as $film){$slug=$film['id'];$saved=$settings['films'][$slug]??[];$id=absint($saved['video']??0);$video=$id?wp_get_attachment_url($id):false;$poster=absint($saved['poster']??0);$poster=$poster?wp_get_attachment_image_url($poster,'large'):false;$title=($saved['title']??'')?:$film['title'];$description=($saved['description']??'')?:$film['description'];
        echo '<figure class="tl-film"><video controls playsinline muted preload="none" poster="'.esc_url($poster?:$assets.$film['poster']).'" aria-label="'.esc_attr($title).'" aria-describedby="tl-film-desc-'.esc_attr($slug).'">';
        if($video)echo '<source src="'.esc_url($video).'" type="'.esc_attr(get_post_mime_type($id)).'">';else{echo '<source src="'.esc_url($assets.$film['webm']).'" type="video/webm"><source src="'.esc_url($assets.$film['mp4']).'" type="video/mp4">';}
        echo 'הדפדפן אינו תומך בווידאו.</video><figcaption><h3>'.esc_html($title).'</h3><p id="tl-film-desc-'.esc_attr($slug).'">'.esc_html($description).'</p><p class="tl-film-error" role="status" hidden>הסרטון לא נטען. אפשר לנסות שוב בעוד רגע.</p></figcaption></figure>';
    }echo '</div></section>';
    if($settings['reviewMode']!=='real'||$settings['realConfirmed']!==true)return;
    $stored=is_array($settings['realReviews'])?$settings['realReviews']:[];
    $reviews=array_values(array_filter($stored,static function($text){return is_string($text)&&trim($text)!=='';}));
    if(!$reviews)return;
    echo '<section class="tl-section tl-customer-notes" aria-labelledby="tl-reviews-heading"><div class="tl-section-heading"><h2 id="tl-reviews-heading">לקוחות מספרות.</h2></div><div class="tl-review-grid">';
    foreach($reviews as $text)echo '<figure><blockquote><p>״'.esc_html($text).'״</p></blockquote><figcaption>המלצה ללא שם</figcaption></figure>';echo '</div></section>';
}
