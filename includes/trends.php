<?php
require_once __DIR__ . '/jsondb.php';
require_once __DIR__ . '/helpers.php';

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

// از بین منابع خبری یک ترند، پوشش ایسنا (اگر باشد) و پوشش تمام رقبای هدف را برمی‌گرداند
function trendsSelectCoverage(array $newsItems): array
{
    $isna = null;
    $others = [];
    $seenAgencies = [];
    foreach ($newsItems as $ni) {
        $label = trendsAgencyLabel($ni['source']);
        if ($label === null) continue;
        if ($label === 'ایسنا') {
            if ($isna === null) $isna = ['title' => $ni['title'], 'url' => $ni['url']];
            continue;
        }
        if (isset($seenAgencies[$label])) continue; // هر خبرگزاری رقیب فقط یک‌بار نمایش داده می‌شود
        $seenAgencies[$label] = true;
        $others[] = ['agency' => $label, 'title' => $ni['title'], 'url' => $ni['url']];
    }
    return ['isna' => $isna, 'others' => $others];
}

// شمارش تعداد تکرار هر منبع در یک لیست خبر (بدون محدودیت به خبرگزاری خاص)
function trendsCountSources(array $newsItems): array
{
    $bySource = [];
    foreach ($newsItems as $ni) {
        $src = trim($ni['source'] ?? '');
        if ($src === '') continue;
        if (!isset($bySource[$src])) {
            $bySource[$src] = ['count' => 0, 'title' => $ni['title'], 'url' => $ni['url']];
        }
        $bySource[$src]['count']++;
    }
    return $bySource;
}

// از بین شمارش‌های منابع، فقط منبع(هایی) با بیشترین میزان تکرار را برمی‌گرداند
function trendsTopFromCounts(array $bySource): array
{
    if (empty($bySource)) return [];
    $max = max(array_column($bySource, 'count'));
    $out = [];
    foreach ($bySource as $src => $d) {
        if ($d['count'] === $max) {
            $out[] = ['agency' => $src, 'title' => $d['title'], 'url' => $d['url']];
        }
    }
    return $out;
}

// برای گزارش «همه رسانه‌ها»: از بین منابع خبری یک ترند، فقط منبع(هایی) با بیشترین میزان تکرار را نگه می‌دارد.
function trendsSelectTopRepeatedCoverage(array $newsItems): array
{
    return trendsTopFromCounts(trendsCountSources($newsItems));
}

// ذخیره‌ی آرشیو روزانه (تاریخ شمسی) ترندها با پوشش همه‌ی رسانه‌ها، برای صفحه‌ی گزارش ترندها
// این تابع هر بار (هر ۳۰ دقیقه) فراخوانی می‌شود؛ به‌جای جایگزینی کامل روز، شمارش منابع را
// با آنچه قبلاً همان روز ذخیره شده جمع می‌زند تا هیچ ترند/خبری از چک‌های قبلی گم نشود.
function trendsArchiveDaily(array $trends): void
{
    $nowIran = new DateTime('now', new DateTimeZone('Asia/Tehran'));
    [$jy, $jm, $jd] = gregorianToJalali((int)$nowIran->format('Y'), (int)$nowIran->format('n'), (int)$nowIran->format('j'));
    $jdate = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    $fetchedAtLabel = jalaliDateLabel($jdate) . ' - ساعت ' . $nowIran->format('H:i') . ' (به وقت ایران)';

    jsonUpdate('google_trends_archive', function ($old) use ($jdate, $trends, $fetchedAtLabel) {
        $byKeyword = [];
        foreach (($old[$jdate]['trends'] ?? []) as $t) {
            $byKeyword[$t['keyword']] = $t;
        }
        foreach ($trends as $t) {
            $counts = trendsCountSources($t['news_items']);
            $kw = $t['keyword'];
            if (!isset($byKeyword[$kw])) {
                if (empty($counts)) continue; // ترند بدون هیچ خبر مرتبطی نمایش داده نمی‌شود
                $byKeyword[$kw] = ['keyword' => $kw, 'traffic' => $t['traffic'], 'source_counts' => []];
            } elseif (trendsTrafficValue($t['traffic']) > trendsTrafficValue($byKeyword[$kw]['traffic'])) {
                $byKeyword[$kw]['traffic'] = $t['traffic'];
            }
            foreach ($counts as $src => $d) {
                if (!isset($byKeyword[$kw]['source_counts'][$src])) {
                    $byKeyword[$kw]['source_counts'][$src] = $d;
                } else {
                    $byKeyword[$kw]['source_counts'][$src]['count'] += $d['count'];
                }
            }
        }
        $old[$jdate] = ['fetched_at' => $fetchedAtLabel, 'trends' => array_values($byKeyword)];
        return $old;
    });
}

// تبدیل متن میزان جست‌وجو (مثل «5000+» یا «10K+») به عدد قابل مقایسه برای سورت
function trendsTrafficValue(string $traffic): int
{
    $t = strtolower(trim(str_replace(',', '', $traffic)));
    if ($t === '') return 0;
    if (preg_match('/([\d.]+)\s*(k|m)?/', $t, $m)) {
        $num = (float)$m[1];
        $mult = ($m[2] ?? '') === 'k' ? 1000 : (($m[2] ?? '') === 'm' ? 1000000 : 1);
        return (int) round($num * $mult);
    }
    return 0;
}

// تمام تاریخ‌های شمسی بین دو تاریخ (شامل خودشان) را برمی‌گرداند
function trendsJalaliDateRange(string $from, string $to): array
{
    [$fy, $fm, $fd] = array_map('intval', explode('/', $from));
    [$ty, $tm, $td] = array_map('intval', explode('/', $to));
    $start = j2d($fy, $fm, $fd);
    $end = j2d($ty, $tm, $td);
    if ($end < $start) { [$start, $end] = [$end, $start]; }
    $out = [];
    for ($jdn = $start; $jdn <= $end; $jdn++) {
        $d = d2j($jdn);
        $out[] = sprintf('%04d/%02d/%02d', $d['jy'], $d['jm'], $d['jd']);
    }
    return $out;
}

// ادغام ترندهای آرشیوشده در یک بازه‌ی تاریخی: هر ترند یک‌بار، پوشش رسانه‌ها از همه‌ی روزها جمع می‌شود
// (خروجی بر اساس میزان جست‌وجو، نزولی مرتب شده است)
function trendsGetRangeMerged(string $from, string $to): array
{
    $archive = jsonRead('google_trends_archive');
    $dates = trendsJalaliDateRange($from, $to);
    $merged = [];
    foreach ($dates as $d) {
        $day = $archive[$d] ?? null;
        if (!$day) continue;
        foreach ($day['trends'] ?? [] as $t) {
            $kw = $t['keyword'] ?? '';
            if ($kw === '') continue;
            if (!isset($merged[$kw])) {
                $merged[$kw] = ['keyword' => $kw, 'date' => $d, 'traffic' => $t['traffic'] ?? '', 'source_counts' => []];
            } elseif (trendsTrafficValue($t['traffic'] ?? '') > trendsTrafficValue($merged[$kw]['traffic'])) {
                $merged[$kw]['traffic'] = $t['traffic'];
            }
            foreach ($t['source_counts'] ?? [] as $src => $d2) {
                if (!isset($merged[$kw]['source_counts'][$src])) {
                    $merged[$kw]['source_counts'][$src] = $d2;
                } else {
                    $merged[$kw]['source_counts'][$src]['count'] += $d2['count'];
                }
            }
        }
    }
    $out = array_values($merged);
    usort($out, fn($a, $b) => trendsTrafficValue($b['traffic']) <=> trendsTrafficValue($a['traffic']));
    foreach ($out as &$t) {
        $t['coverage'] = trendsTopFromCounts($t['source_counts']);
        unset($t['source_counts']);
    }
    unset($t);
    return $out;
}

// حذف پوشش رسانه‌های غیر از ایسنا برای روزهای قدیمی‌تر از ۸ روز (فراخوانی از کران‌جاب)
function trendsPruneOldNonIsna(): void
{
    $cutoff = new DateTime('now', new DateTimeZone('Asia/Tehran'));
    $cutoff->modify('-8 days');
    [$cy, $cm, $cd] = gregorianToJalali((int)$cutoff->format('Y'), (int)$cutoff->format('n'), (int)$cutoff->format('j'));
    $cutoffJdn = j2d($cy, $cm, $cd);

    jsonUpdate('google_trends_archive', function ($archive) use ($cutoffJdn) {
        foreach ($archive as $jdate => $day) {
            [$jy, $jm, $jd] = array_map('intval', explode('/', $jdate));
            if (j2d($jy, $jm, $jd) > $cutoffJdn) continue; // فقط روزهای قدیمی‌تر از ۸ روز
            $newTrends = [];
            foreach ($day['trends'] ?? [] as $t) {
                $isnaOnly = array_filter(
                    $t['source_counts'] ?? [],
                    fn($src) => mb_strpos($src, 'ایسنا') !== false,
                    ARRAY_FILTER_USE_KEY
                );
                if (empty($isnaOnly)) continue; // ترندی که ایسنا پوشش نداده، از آرشیو قدیمی حذف می‌شود
                $t['source_counts'] = $isnaOnly;
                $newTrends[] = $t;
            }
            $archive[$jdate]['trends'] = $newTrends;
        }
        return $archive;
    });
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
    trendsArchiveDaily($trends); // آرشیو روزانه‌ی همه‌رسانه‌ها؛ رفتار فعلی داشبورد را تغییر نمی‌دهد
    $result = [];
    foreach ($trends as $t) {
        $cov = trendsSelectCoverage($t['news_items']);
        if ($cov['isna'] === null && empty($cov['others'])) continue; // ترندی که هیچ‌کدام از خبرگزاری‌های مدنظر پوشش نداده‌اند، نمایش داده نمی‌شود
        $result[] = [
            'keyword' => $t['keyword'],
            'traffic' => $t['traffic'],
            'isna'    => $cov['isna'],
            'others'  => $cov['others'],
        ];
    }

    $payload = ['fetched_at' => date('Y-m-d H:i:s'), 'trends' => $result];
    jsonUpdate('google_trends', function ($old) use ($payload) { return $payload; });
    return $payload;
}
