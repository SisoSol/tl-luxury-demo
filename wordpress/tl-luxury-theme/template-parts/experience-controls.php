<?php defined('ABSPATH') || exit; ?>
<section id="tl-cookie-banner" class="tl-cookie-banner" aria-label="העדפות פרטיות">
  <div><h2>הפרטיות שלך, הבחירה שלך.</h2><p>האתר משתמש באחסון חיוני להפעלת החנות ולשמירת העדפות. כלי מדידה ופרסום אינם פעילים כרגע; אם יחוברו, הם ייטענו רק לאחר הסכמה מתאימה.</p></div>
  <div class="tl-cookie-actions"><button type="button" data-tl-cookie-reject>רק מה שנחוץ</button><button type="button" data-tl-cookie-accept>אישור הכול</button><button type="button" data-tl-cookie-settings>בחירת העדפות</button></div>
</section>
<dialog id="tl-cookie-dialog" class="tl-preference-dialog" aria-labelledby="tl-cookie-heading">
 <button type="button" class="tl-dialog-close" data-tl-close aria-label="סגירת העדפות הפרטיות">סגירה ×</button>
 <h2 id="tl-cookie-heading" tabindex="-1">העדפות פרטיות</h2>
 <p>אפשר לשנות את הבחירה בכל עת דרך הקישור בתחתית האתר. שמירת ההעדפה אינה מפעילה כלי מדידה או פרסום שלא חוברו לחנות.</p>
 <form><label class="tl-preference-row"><span>אחסון חיוני<small>נחוץ להפעלת החנות, אבטחה ושמירת העדפות. נשאר פעיל.</small></span><input type="checkbox" checked disabled aria-label="אחסון חיוני — תמיד פעיל"></label>
 <label class="tl-preference-row"><span>מדידה וניתוח שימוש<small>כלים סטטיסטיים עתידיים ייטענו רק אם תאשרי.</small></span><input type="checkbox" name="tl-cookie-analytics"></label>
 <label class="tl-preference-row"><span>פרסום ושיווק<small>כלי פרסום עתידיים ייטענו רק אם תאשרי.</small></span><input type="checkbox" name="tl-cookie-marketing"></label>
 <div class="tl-preference-actions"><button type="submit">שמירת ההעדפות</button></div></form>
 <p id="tl-cookie-status" class="tl-cookie-status" role="status"></p>
 <p><a href="<?php echo esc_url(home_url('/privacy/')); ?>">למדיניות הפרטיות</a></p>
</dialog>
<dialog id="tl-a11y-dialog" class="tl-preference-dialog" aria-labelledby="tl-a11y-heading">
 <button type="button" class="tl-dialog-close" data-tl-close aria-label="סגירת התאמות הנגישות">סגירה ×</button>
 <h2 id="tl-a11y-heading" tabindex="-1">התאמות נגישות</h2><p>התאמות לתצוגה ולתנועה. האתר תומך גם בניווט מקלדת ובהעדפת הפחתת התנועה של המכשיר.</p>
 <?php foreach(['large'=>'הגדלת טקסט','contrast'=>'ניגודיות גבוהה','motion'=>'הפחתת תנועה','links'=>'הדגשת קישורים','focus'=>'הדגשת מיקוד מקלדת'] as $key=>$label): ?>
 <label class="tl-preference-row"><span><?php echo esc_html($label); ?></span><input type="checkbox" data-tl-pref="<?php echo esc_attr($key); ?>"></label>
 <?php endforeach; ?>
 <div class="tl-preference-actions"><button type="button" data-tl-reset-a11y>איפוס ההתאמות</button></div>
 <p><a href="<?php echo esc_url(home_url('/accessibility/')); ?>">להצהרת הנגישות</a></p>
</dialog>
