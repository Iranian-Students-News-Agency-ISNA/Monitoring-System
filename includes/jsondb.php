<?php
// لایه ذخیره‌سازی ساده روی فایل JSON (بدون نیاز به دیتابیس)

// require_once فقط بار اول مقدار واقعی آرایه config.php را برمی‌گرداند و دفعات بعد true می‌دهد؛
// چون jsonPath() در هر درخواست چندین بار صدا زده می‌شود، آرایه کانفیگ را یک‌بار در static کش می‌کنیم.
function appConfig(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config.php';
    }
    return $cfg;
}

function jsonPath(string $name): string
{
    $cfg = appConfig();
    $dir = $cfg['data_dir'];
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return $dir . '/' . $name . '.json';
}

// ذخیره‌ی موقتِ محتوای فایل‌های JSON در حافظه، فقط برای طول عمر همین درخواست HTTP.
// بدون این کش، هر بار jsonRead('excel_rows') صدا زده می‌شود (که در یک بارگذاری صفحه‌ی
// «ارزیابی» ده‌ها بار اتفاق می‌افتد) کل فایل چندمگابایتی از دیسک خوانده و JSON-دیکد می‌شود؛
// همین باعث کندی/تایم‌اوت و خالی ماندن بخش‌هایی از گزارش می‌شد.
function &jsonCacheRef(): array
{
    static $store = [];
    return $store;
}

// خواندن ساده (قفل اشتراکی روی خواندن)
function jsonRead(string $name): array
{
    $cache = &jsonCacheRef();
    if (array_key_exists($name, $cache)) return $cache[$name];
    $path = jsonPath($name);
    if (!file_exists($path)) { $cache[$name] = []; return $cache[$name]; }
    $fp = fopen($path, 'r');
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if ($raw === '' || $raw === false) { $cache[$name] = []; return $cache[$name]; }
    $data = json_decode($raw, true);
    $cache[$name] = is_array($data) ? $data : [];
    return $cache[$name];
}

// خواندن + تغییر + نوشتنِ اتمی (با قفل انحصاری) تا در دسترسی همزمان داده خراب نشود.
// نوشتن ابتدا در یک فایل موقت انجام می‌شود و فقط در صورت موفقیت کامل، جای فایل اصلی
// می‌نشیند؛ به این ترتیب اگر نوشتن به هر دلیلی (مثلاً پر بودن فضای دیسک) ناقص/ناموفق باشد،
// فایل اصلی دست‌نخورده و سالم باقی می‌ماند (به‌جای این‌که وسط نوشتن خراب شود).
function jsonUpdate(string $name, callable $mutator): array
{
    $path = jsonPath($name);
    $fp = fopen($path, 'c+');
    if (!$fp) throw new RuntimeException("امکان باز کردن فایل داده {$name} نبود.");
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = ($raw === '' || $raw === false) ? [] : (json_decode($raw, true) ?? []);
    $result = $mutator($data);
    $newData = is_array($result) ? $result : $data;
    $json = json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $tmpPath = $path . '.tmp-' . uniqid('', true);
    $bytesWritten = @file_put_contents($tmpPath, $json, LOCK_EX);
    if ($bytesWritten === false || $bytesWritten !== strlen($json)) {
        if (file_exists($tmpPath)) @unlink($tmpPath);
        flock($fp, LOCK_UN);
        fclose($fp);
        throw new RuntimeException(
            "ذخیره‌سازی داده «{$name}» با خطا مواجه شد (احتمالاً فضای دیسک هاست پر است). " .
            "برای جلوگیری از خرابی داده، تغییرات ذخیره نشد؛ لطفاً فضای دیسک را آزاد کنید و دوباره تلاش کنید."
        );
    }
    if (!@rename($tmpPath, $path)) {
        // برخی هاست‌ها بین دو فایل سیستم rename را محدود می‌کنند؛ به‌عنوان جایگزین از کپی مستقیم استفاده می‌شود
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        @unlink($tmpPath);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    $cache = &jsonCacheRef();
    $cache[$name] = $newData;
    return $newData;
}

function jsonNextId(array $rows): int
{
    $max = 0;
    foreach ($rows as $r) { if ((int)($r['id'] ?? 0) > $max) $max = (int)$r['id']; }
    return $max + 1;
}

// اطمینان از این‌که config.php (و ثابت‌های APP_VERSION/APP_DEVELOPER/ADMIN_SETUP_KEY)
// همیشه در همان ابتدای هر درخواست بارگذاری شده باشد، چون بسیاری از صفحات مستقیماً
// appConfig() را صدا نمی‌زنند اما به این ثابت‌ها (مثلاً در فوتر) نیاز دارند.
appConfig();
