<?php
if (!defined('APP_VERSION'))   define('APP_VERSION', '1.5.0');
if (!defined('APP_DEVELOPER')) define('APP_DEVELOPER', 'آیسان نظرمحمدی');

// کلید راه‌اندازی برای صفحه ساخت کاربر جدید (users_admin.php)
// پس از ساخت کاربرهای اولیه، پیشنهاد می‌شود این مقدار را تغییر داده یا صفحه را حذف کنید.
if (!defined('ADMIN_SETUP_KEY')) define('ADMIN_SETUP_KEY', 'change-me-1405');

return [
    'data_dir'       => __DIR__ . '/storage/data',
    'storage_tmp'    => __DIR__ . '/storage/tmp',
    'storage_archive'=> __DIR__ . '/storage/archive',
];
