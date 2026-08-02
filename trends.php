<?php
require_once __DIR__ . '/jsondb.php';

// اولویت منابع مورد نظر: ابتدا ایسنا، سپس بقیه خبرگزاری‌های اصلی
function trendsAgencyLabel(string $sourceText): ?string
{
    $s = trim($sourceText);
    if ($s === '') return null;
    if (mb_strpos($s, 'ایسنا') !== false) return 'ایسنا';
    if (mb_strpos($s, 'مهر') !== false) return 'مهر';
    if (mb_strpos($s, 'فارس') !== false) return 'فارس';
    if (mb_strpos($s, 'ایرنا') !== false) return 'ایرنا';
    if (mb_strpos($s, 'تسنیم') !== false) return 'تسنیم';
    return null;
}

// خواندن RSS ترندهای گوگل و تبدیل به آرایه ساده
function trendsParseRss(string $xml): array
{
    libxml_use_internal_errors(true);
    $sx = simplexml_load_string($xml);
    if ($sx === false || !isset($sx->channel)) {
        throw new RuntimeException('پاسخ دریافتی از گوگل ترندز معتبر نبود (شاید فیلتر/بلاک شده یا دامنه در دسترس نیست).');
    }
    $ht = 'https://trends.google.com/trending/rss';

    $out = [];
    foreach ($sx->channel->item as $item) {
        $htChildren = $item->children($ht);
        $keyword = trim((string)$item->title);
        $traffic = trim((string)$htChildren->approx_traffic);

        $newsItems = [];
        foreach ($htChildren->news_item as $ni) {
            $newsItems[] = [
                'title'  => trim((string)$ni->news_item_title),
                'url'    => trim((string)$ni->news_item_url),
                'source' => trim((string)$ni->news_item_source),
            ];
        }
        if ($keyword === '') continue;
        $out[] = ['keyword' => $keyword, 'traffic' => $traffic, 'news_items' => $newsItems];
    }
    return $out;
}

// از بین منابع خبری یک ترند، بهترین پوشش (ترجیحاً ایسنا) را انتخاب می‌کند
function trendsSelectCoverage(array $newsItems): array
{
    foreach ($newsItems as $ni) {
        if (trendsAgencyLabel($ni['source']) === 'ایسنا') {
            return ['is_isna' => true, 'agency' => 'ایسنا', 'title' => $ni['title'], 'url' => $ni['url']];
        }
    }
    foreach ($newsItems as $ni) {
        $label = trendsAgencyLabel($ni['source']);
        if ($label !== null) {
            return ['is_isna' => false, 'agency' => $label, 'title' => $ni['title'], 'url' => $ni['url']];
        }
    }
    return ['is_isna' => false, 'agency' => null, 'title' => null, 'url' => null];
}

// دریافت RSS از گوگل ترندز و ذخیره خروجی پردازش‌شده (برای اجرا توسط کران‌جاب)
function trendsFetchAndStore(): array
{
    $url = 'https://trends.google.com/trending/rss?geo=IR';
    $xml = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NezaratBot/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $xml = curl_exec($ch);
        curl_close($ch);
    }
    if ($xml === false || $xml === '') {
        $ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0 (compatible; NezaratBot/1.0)\r\n", 'timeout' => 20]]);
        $xml = @file_get_contents($url, false, $ctx);
    }
    if ($xml === false || $xml === '') {
        throw new RuntimeException('دریافت فید ترندهای گوگل ناموفق بود.');
    }

    $trends = trendsParseRss($xml);
    $result = [];
    foreach ($trends as $t) {
        $cov = trendsSelectCoverage($t['news_items']);
        if (!$cov['is_isna'] && $cov['agency'] === null) continue; // ترندی که هیچ‌کدام از خبرگزاری‌های مدنظر پوشش نداده‌اند، نمایش داده نمی‌شود
        $result[] = [
            'keyword' => $t['keyword'],
            'traffic' => $t['traffic'],
            'is_isna' => $cov['is_isna'],
            'agency'  => $cov['agency'],
            'title'   => $cov['title'],
            'url'     => $cov['url'],
        ];
    }

    $payload = ['fetched_at' => date('Y-m-d H:i:s'), 'trends' => $result];
    jsonUpdate('google_trends', fn($old) => $payload);
    return $payload;
}
