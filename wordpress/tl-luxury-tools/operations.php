<?php
/** Read-only operations shortcuts and notification configuration, not delivery proof. */
defined('ABSPATH') || exit;

function tl_operations_order_url() {
    $hpos = class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    return admin_url($hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order');
}

function tl_operations_email_state() {
    $state = ['available'=>false,'enabled'=>false,'recipients'=>[],'invalid_recipients'=>false,'configured'=>false,'delivery_verified'=>false,
        'mail_blocked'=>(function_exists('tl_is_demo') && tl_is_demo()) || (function_exists('tl_is_nonproduction') && tl_is_nonproduction())];
    if (!function_exists('WC') || !WC()) return $state;
    $emails = WC()->mailer()->get_emails();
    $email = $emails['WC_Email_New_Order'] ?? null;
    if (!$email || !is_callable([$email,'is_enabled']) || !is_callable([$email,'get_recipient'])) return $state;
    $state['available'] = true;
    $state['enabled'] = (bool) $email->is_enabled();
    $recipients = array_values(array_filter(array_map('trim', explode(',', (string) $email->get_recipient())), 'strlen'));
    foreach ($recipients as $recipient) {
        if (is_email($recipient)) $state['recipients'][] = $recipient;
        else $state['invalid_recipients'] = true;
    }
    $state['recipients'] = array_values(array_unique($state['recipients']));
    $state['configured'] = $state['enabled'] && !empty($state['recipients']) && !$state['invalid_recipients'];
    return $state;
}

function tl_operations_admin_screen() {
    if (!current_user_can('manage_woocommerce')) return;
    $email = tl_operations_email_state();
    $new_order_settings = admin_url('admin.php?page=wc-settings&tab=email&section=wc_email_new_order');
    $version = defined('WC_VERSION') ? WC_VERSION : '';
    $modern_push = $version && version_compare($version, '10.9.2', '>=');
    echo '<section class="tl-panel" id="tl-operations"><h2>מוצרים, הזמנות והתראות</h2><p>ניהול התכשיטים, ההזמנות והלקוחות נעשה באותה חנות. אין צורך בלוח נוסף או במערכת לקוחות נפרדת כדי להתחיל.</p>';
    echo '<div class="tl-actions">';
    $links = [
        ['edit_products',admin_url('edit.php?post_type=product'),'עריכת התכשיטים','שם, מחיר, תיאור, תמונות ומלאי'],
        ['edit_products',admin_url('post-new.php?post_type=product'),'הוספת תכשיט','יצירת מוצר ושמירה כטיוטה או פרסום'],
        ['edit_shop_orders',tl_operations_order_url(),'טיפול בהזמנות','פרטי הזמנה, מצב טיפול והערות שירות'],
        ['view_woocommerce_reports',admin_url('admin.php?page=wc-admin&path=%2Fcustomers'),'לקוחות החנות','רשימת לקוחות ונתוני רכישה באותה מערכת'],
        ['read',get_edit_profile_url(get_current_user_id()),'החשבון והסיסמה שלי','כל מנהל משנה את הסיסמה בחשבון האישי שלו'],
    ];
    foreach ($links as $link) {
        if (!current_user_can($link[0])) continue;
        echo '<a class="tl-action" href="'.esc_url($link[1]).'"><strong>'.esc_html($link[2]).'</strong><span>'.esc_html($link[3]).'</span></a>';
    }
    echo '</div><p>אפשר גם להעביר תכשיט לאשפה מתוך מסך המוצרים. הזמנות נשארות רשומות בחנות; שינוי מצב טיפול אינו מחליף אישור חיוב מספק הסליקה.</p>';
    echo '<h3>הודעה באימייל על הזמנה חדשה</h3>';
    if (!$email['available']) echo '<p><strong>מצב ההודעה אינו זמין.</strong> יש להפעיל WooCommerce ולבדוק את הגדרות הדואר.</p>';
    else {
        echo '<p><strong>'.($email['enabled'] ? 'ההתראה מופעלת בהגדרות WooCommerce.' : 'ההתראה כבויה בהגדרות WooCommerce.').'</strong></p>';
        if ($email['recipients']) {
            echo '<p>נמענים שמוגדרים כרגע: ';
            foreach ($email['recipients'] as $index=>$recipient) echo ($index ? ', ' : '').'<bdi>'.esc_html($recipient).'</bdi>';
            echo '.</p>';
        } else echo '<p>לא נמצאה כתובת תקינה לקבלת ההודעה.</p>';
        if ($email['invalid_recipients']) echo '<p>אחת מכתובות הנמענים אינה תקינה. יש לתקן אותה במסך הגדרות ההודעה.</p>';
    }
    if ($email['mail_blocked']) echo '<p class="tl-admin-note"><strong>שליחת דואר חסומה בסביבת הבדיקה הזאת.</strong> גם הגדרה פעילה אינה שולחת כאן הודעות אמיתיות.</p>';
    echo '<p><strong>הגדרה אינה אישור שהאימייל הגיע.</strong> לאחר חיבור האחסון ושירות השליחה, מבצעים הזמנת בדיקה ומוודאים שההודעה הגיעה לכל מנהל, גם לתיקיית הספאם. הלוח הזה אינו שולח הודעת בדיקה ואינו מסמן מסירה כמוצלחת.</p>';
    echo '<p><a class="button" href="'.esc_url($new_order_settings).'">הגדרת הודעת הזמנה חדשה</a> <a href="'.esc_url(admin_url('admin.php?page=wc-settings&tab=email')).'">כל הודעות החנות</a></p>';
    echo '<h3>התראות בטלפון</h3><p>המסלול המומלץ הוא אפליקציית WooCommerce הרשמית, לא מערכת הזמנות נוספת. כל מנהל מתחבר לחנות עם החשבון האישי שלו ומאפשר התראות בטלפון.</p>';
    if ($modern_push) echo '<p>גרסת WooCommerce המותקנת תומכת במסלול הגדרת push מתוך האפליקציה. נדרשת גם אפליקציה בגרסה 25.0.1 ומעלה; התכונה מופצת בהדרגה. אין צורך להתקין Jetpack מלא במסלול הזה, אך עדיין נוצר חיבור שירות במהלך ההגדרה.</p>';
    else echo '<p>בגרסאות WooCommerce עד 10.9.1, הגדרת push דורשת חיבור Jetpack. בגרסה 10.9.2 ומעלה ובאפליקציה 25.0.1 ומעלה קיים מסלול חיבור קל מתוך האפליקציה. את המסלול המתאים מגדירים עם המתכנת לפי הגרסאות בפועל.</p>';
    echo '<p><strong>נדרשים חיבור ובדיקת קבלה בכל טלפון.</strong> הלוח אינו מזהה חיבור לאפליקציה ואינו מוכיח שהתראה התקבלה. לא הוגדרו כאן SMS, WhatsApp אוטומטי או ספק בתשלום.</p><p><a href="https://woocommerce.com/document/woo-mobile-notifications/" target="_blank" rel="noopener noreferrer">הנחיות ההתראות הרשמיות</a></p>';
    echo '<details><summary>הכנת שני החשבונות והבדיקה המשותפת</summary><ol><li>מנהל המערכת יוצר שני משתמשים אישיים בתפקיד מנהל חנות. כל בעלים בוחר סיסמה דרך ההזמנה או מסך הפרופיל; אין סיסמה משותפת או סיסמה שנקבעת בקוד.</li><li>כל מנהל מתרגל יצירת תכשיט, שינוי מחיר, תיאור ומלאי, ועריכת תוכן המותג.</li><li>מגדירים את שתי כתובות האימייל בהודעת ההזמנה החדשה ומחברים שירות שליחה מאומת בדומיין העסק.</li><li>מחברים את האפליקציה בנפרד לכל מנהל ומבצעים בדיקה אמיתית של אימייל ו־push, לאחר פתיחת סביבת החנות המתאימה.</li></ol><p>התקנת התוסף אינה יוצרת משתמשים, הזמנות או חיבורי התראות. ניהול תוספים ותשתית נשאר אצל מנהל המערכת.</p></details></section>';
}
