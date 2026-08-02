<?php
// این فایل باید هر ۳۰ دقیقه یک‌بار توسط کران‌جاب هاست اجرا شود، مثلاً:
// */30 * * * * /usr/bin/php /home/USERNAME/public_html/nezarat/cron_fetch_trends.php >> /home/USERNAME/public_html/nezarat/storage/tmp/trends_cron.log 2>&1
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/trends.php';

try {
    $payload = trendsFetchAndStore();
    echo date('Y-m-d H:i:s') . " OK - " . count($payload['trends']) . " ترند ذخیره شد.\n";
} catch (Throwable $e) {
    echo date('Y-m-d H:i:s') . " ERROR - " . $e->getMessage() . "\n";
    exit(1);
}
