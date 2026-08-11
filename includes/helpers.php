<?php
// پورت دقیق الگوریتم jalaali-js (https://github.com/jalaali/jalaali-js) - MIT
function jdiv(int $a, int $b): int { return intdiv($a, $b); }
function jmod(int $a, int $b): int { return $a - intdiv($a, $b) * $b; }

function jalCal(int $jy, bool $withoutLeap = false): array
{
    static $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    $bl = count($breaks);
    $gy = $jy + 621;
    $leapJ = -14;
    $jp = $breaks[0];
    if ($jy < $jp || $jy >= $breaks[$bl - 1]) throw new InvalidArgumentException("سال جلالی نامعتبر: {$jy}");
    $jump = 0;
    for ($i = 1; $i < $bl; $i++) {
        $jm = $breaks[$i];
        $jump = $jm - $jp;
        if ($jy < $jm) break;
        $leapJ = $leapJ + jdiv($jump, 33) * 8 + jdiv(jmod($jump, 33), 4);
        $jp = $jm;
    }
    $n = $jy - $jp;
    $leapJ = $leapJ + jdiv($n, 33) * 8 + jdiv(jmod($n, 33) + 3, 4);
    if (jmod($jump, 33) === 4 && $jump - $n === 4) $leapJ += 1;
    $leapG = jdiv($gy, 4) - jdiv((jdiv($gy, 100) + 1) * 3, 4) - 150;
    $march = 20 + $leapJ - $leapG;
    if ($withoutLeap) return ['gy' => $gy, 'march' => $march];
    if ($jump - $n < 6) $n = $n - $jump + jdiv($jump + 4, 33) * 33;
    $leap = jmod(jmod($n + 1, 33) - 1, 4);
    if ($leap === -1) $leap = 4;
    return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
}

function g2d(int $gy, int $gm, int $gd): int
{
    $d = jdiv(($gy + jdiv($gm - 8, 6) + 100100) * 1461, 4)
        + jdiv(153 * jmod($gm + 9, 12) + 2, 5)
        + $gd - 34840408;
    $d = $d - jdiv(jdiv($gy + 100100 + jdiv($gm - 8, 6), 100) * 3, 4) + 752;
    return $d;
}

function d2g(int $jdn): array
{
    $j = 4 * $jdn + 139361631;
    $j = $j + jdiv(jdiv(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    $i = jdiv(jmod($j, 1461), 4) * 5 + 308;
    $gd = jdiv(jmod($i, 153), 5) + 1;
    $gm = jmod(jdiv($i, 153), 12) + 1;
    $gy = jdiv($j, 1461) - 100100 + jdiv(8 - $gm, 6);
    return ['gy' => $gy, 'gm' => $gm, 'gd' => $gd];
}

function j2d(int $jy, int $jm, int $jd): int
{
    $r = jalCal($jy, true);
    return g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - jdiv($jm, 7) * ($jm - 7) + $jd - 1;
}

function d2j(int $jdn): array
{
    $gy = d2g($jdn)['gy'];
    $jy = $gy - 621;
    $r = jalCal($jy, false);
    $jdn1f = g2d($gy, 3, $r['march']);
    $k = $jdn - $jdn1f;
    if ($k >= 0) {
        if ($k <= 185) {
            return ['jy' => $jy, 'jm' => 1 + jdiv($k, 31), 'jd' => jmod($k, 31) + 1];
        }
        $k -= 186;
    } else {
        $jy -= 1;
        $k += 179;
        if ($r['leap'] === 1) $k += 1;
    }
    return ['jy' => $jy, 'jm' => 7 + jdiv($k, 30), 'jd' => jmod($k, 30) + 1];
}

function gregorianToJalali(int $gy, int $gm, int $gd): array
{
    $r = d2j(g2d($gy, $gm, $gd));
    return [$r['jy'], $r['jm'], $r['jd']];
}

function jalaliToGregorian(int $jy, int $jm, int $jd): array
{
    $r = d2g(j2d($jy, $jm, $jd));
    return [$r['gy'], $r['gm'], $r['gd']];
}

function isLeapJalaliYear(int $jy): bool
{
    return jalCal($jy)['leap'] === 0;
}

function jalaliMonthLength(int $jy, int $jm): int
{
    if ($jm <= 6) return 31;
    if ($jm <= 11) return 30;
    return isLeapJalaliYear($jy) ? 30 : 29;
}

// ===================== ابزارهای کمکی عمومی =====================

function normalizeDigits(string $s): string
{
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, $s));
}

// یکسان‌سازی حروف عربی/فارسی مشابه (ي/ك و ...) و فاصله‌های اضافه
function normalizePersianChars(string $s): string
{
    $s = str_replace(['ي', 'ك', 'ۀ', 'إ', 'أ', 'آ'], ['ی', 'ک', 'ه', 'ا', 'ا', 'ا'], $s);
    $s = preg_replace('/[\x{200C}\x{200F}\x{200E}]/u', ' ', $s); // نویسه‌های نیم‌فاصله/جهت‌دهی نامرئی
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

// اثر انگشت نام: صرف‌نظر از ترتیب نام/نام‌خانوادگی و وجود ویرگول، برای تطبیق «نام خبرنگار» با «نام کاربری»
// مثال: «نورمحمدیان، محمدرضا» و «محمدرضا نورمحمدیان» هر دو به یک اثرانگشت یکسان می‌رسند
function personNameFingerprint(string $s): string
{
    $s = normalizePersianChars($s);
    $parts = preg_split('/[\s،,]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
    sort($parts);
    return implode(' ', $parts);
}

// نرمال‌سازی تاریخ شمسی به قالب YYYY/MM/DD ؛ در صورت نامعتبر بودن null برمی‌گرداند
function normalizeJalaliDate(?string $s): ?string
{
    if ($s === null) return null;
    $s = normalizeDigits(trim($s));
    if ($s === '') return null;
    $s = str_replace(['-', '.'], '/', $s);
    if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $s, $m)) return null;
    $jy = (int)$m[1]; $jm = (int)$m[2]; $jd = (int)$m[3];
    if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) return null;
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

function todayJalali(): string
{
    $now = getdate();
    [$jy, $jm, $jd] = gregorianToJalali((int)$now['year'], (int)$now['mon'], (int)$now['mday']);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

function persianMonthName(int $jm): string
{
    $names = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return $names[max(0, min(11, $jm - 1))];
}

function jalaliWeekdayName(int $jy, int $jm, int $jd): string
{
    [$gy, $gm, $gd] = jalaliToGregorian($jy, $jm, $jd);
    $ts = mktime(12, 0, 0, $gm, $gd, $gy);
    $w = (int)date('w', $ts); // 0=Sunday ... 6=Saturday
    $names = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
    return $names[$w];
}

function jalaliDateLabel(string $dateStr): string
{
    $norm = normalizeJalaliDate($dateStr);
    if (!$norm) return $dateStr;
    [$jy, $jm, $jd] = array_map('intval', explode('/', $norm));
    $weekday = jalaliWeekdayName($jy, $jm, $jd);
    return sprintf('%s %d %s %d', $weekday, $jd, persianMonthName($jm), $jy);
}

// عدد به حروف فارسی (ساده، برای اعداد کوچک تا چند صد کافی است)
function numberToPersianWords(int $num): string
{
    if ($num === 0) return 'صفر';
    $yekan = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    $dahgan = ['', 'ده', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    $dah_yek = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    $sadgan = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];

    if ($num < 0) return 'منفی ' . numberToPersianWords(-$num);
    if ($num < 10) return $yekan[$num];
    if ($num < 20) return $dah_yek[$num - 10];
    if ($num < 100) {
        $d = intdiv($num, 10); $y = $num % 10;
        return $y === 0 ? $dahgan[$d] : $dahgan[$d] . ' و ' . $yekan[$y];
    }
    if ($num < 1000) {
        $s = intdiv($num, 100); $r = $num % 100;
        return $r === 0 ? $sadgan[$s] : $sadgan[$s] . ' و ' . numberToPersianWords($r);
    }
    $h = intdiv($num, 1000); $r = $num % 1000;
    $hezar = ($h === 1 ? 'هزار' : numberToPersianWords($h) . ' هزار');
    return $r === 0 ? $hezar : $hezar . ' و ' . numberToPersianWords($r);
}

// استخراج لینک و تاریخ (شمسی) از روی کد خبر ایسنا: YYYYMMDD + شماره ترتیبی
function isnaInfoFromId(string $newsId): array
{
    $newsId = normalizeDigits(trim($newsId));
    if (!ctype_digit($newsId) || strlen($newsId) < 9) {
        return ['id' => $newsId, 'link' => '', 'date' => ''];
    }
    $year  = (int) substr($newsId, 0, 4);
    $month = (int) substr($newsId, 4, 2);
    $day   = (int) substr($newsId, 6, 2);
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
        return ['id' => $newsId, 'link' => '', 'date' => ''];
    }
    return [
        'id'   => $newsId,
        'link' => "https://www.isna.ir/news/{$newsId}/",
        'date' => sprintf('%04d/%02d/%02d', $year, $month, $day),
    ];
}

// جدا کردن "سرویس > زیرسرویس" به دو بخش
function splitServicePath(string $s): array
{
    $s = trim($s);
    if ($s === '') return ['', ''];
    $parts = preg_split('/\s*[<>»›\/]\s*/u', $s);
    $parts = array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
    if (count($parts) >= 2) return [$parts[0], implode(' > ', array_slice($parts, 1))];
    return [$parts[0] ?? $s, ''];
}

// تبدیل زمان اکسل (کسر روز یا رشته HH:MM) به قالب HH:MM
function normalizeExcelTime($value): string
{
    if ($value === null || $value === '') return '';
    if (is_numeric($value)) {
        $frac = (float)$value;
        if ($frac >= 0 && $frac < 1) {
            $totalMinutes = (int) round($frac * 24 * 60);
            $h = intdiv($totalMinutes, 60) % 24;
            $m = $totalMinutes % 60;
            return sprintf('%02d:%02d', $h, $m);
        }
        return (string)$value;
    }
    $s = normalizeDigits(trim((string)$value));
    if (preg_match('/^(\d{1,2}):(\d{2})/', $s, $m)) {
        return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }
    return $s;
}

// شمارش برچسب‌ها/آیتم‌های جداشده با ویرگول فارسی یا انگلیسی
function countTags(?string $s): int
{
    $s = trim((string)$s);
    if ($s === '') return 0;
    $parts = preg_split('/[،,]/u', $s);
    $parts = array_filter(array_map('trim', $parts), fn($p) => $p !== '');
    return count($parts);
}

// یافتن تاریخ غالب (بیشترین تکرار) در ستون تاریخ اکسل
function detectedDateFromCounts(array $dateCounts): ?string
{
    if (empty($dateCounts)) return null;
    arsort($dateCounts);
    $best = array_key_first($dateCounts);
    return normalizeJalaliDate((string)$best);
}

// بازه تاریخی واقعی ردیف‌های یک فایل (کمترین و بیشترین تاریخ معتبر یافت‌شده)
function dateRangeFromCounts(array $dateCounts): array
{
    if (empty($dateCounts)) return ['from' => null, 'to' => null];
    $dates = array_keys($dateCounts);
    sort($dates, SORT_STRING);
    return ['from' => $dates[0], 'to' => $dates[count($dates) - 1]];
}

// برای ردیف‌هایی که ستون تاریخ آن‌ها خالی/نامعتبر بوده (استثنا)، یک تاریخ پیش‌فرض جایگزین می‌شود
// تا هیچ ردیفی بدون تاریخ در سامانه نماند (چون از این پس تاریخ هر ردیف مستقل از تاریخ کل فایل است).
function fillMissingRowDates(array $rows, string $fallbackDate): array
{
    foreach ($rows as &$r) {
        if (trim((string)($r['date'] ?? '')) === '') { $r['date'] = $fallbackDate; }
    }
    return $rows;
}

/**
 * خواندن ردیف‌های اکسل روزانه و نگاشت ستون‌ها به فیلدهای استاندارد.
 * ستون‌های موردانتظار (نام هدر ممکن است کمی متفاوت باشد؛ تطبیق فازی انجام می‌شود):
 * کد خبر، ناشر، تیتر، نوع خبر، سرویس (به‌صورت "سرویس > زیرسرویس")، تاریخ، ساعت انتشار
 */
function parseExcelRows(string $path): array
{
    $sheet = xlsxReadRows($path);
    if (empty($sheet)) return ['rows' => [], 'dateCounts' => []];

    $header = array_map(fn($h) => trim((string)$h), $sheet[0]);
    $map = []; // index => normalized key
    $used = []; // key => true، برای اینکه فقط اولین ستون منطبق با هر فیلد اعتبار داشته باشد (نه آخرین)
    $assign = function (int $idx, string $key) use (&$map, &$used) {
        if (isset($used[$key])) return; // این فیلد قبلاً از ستون دیگری پر شده؛ نادیده می‌گیریم
        $map[$idx] = $key;
        $used[$key] = true;
    };
    foreach ($header as $idx => $h) {
        $hn = mb_strtolower($h);
        if ($h === 'خبرنگار') { $assign($idx, 'reporter'); continue; }
        if (str_starts_with($hn, 'کد')) { $assign($idx, 'code'); continue; } // نه 'کلیدواژه'
        if (str_contains($hn, 'سایت')) { $assign($idx, 'site'); continue; }
        if (str_contains($hn, 'ناشر')) { $assign($idx, 'publisher'); continue; }
        if (str_contains($hn, 'تیتر') || str_contains($hn, 'عنوان')) { $assign($idx, 'title'); continue; }
        if (str_contains($hn, 'نوع')) { $assign($idx, 'news_type'); continue; }
        if (str_contains($hn, 'سرویس') || str_contains($hn, 'زیرسرویس')) { $assign($idx, 'service'); continue; }
        if (str_contains($hn, 'تاریخ')) { $assign($idx, 'date'); continue; }
        if (str_contains($hn, 'ساعت') || str_contains($hn, 'زمان')) { $assign($idx, 'pub_time'); continue; }
        if (str_contains($hn, 'بازدید')) { $assign($idx, 'views'); continue; }
        if (str_contains($hn, 'آدرس') || str_contains($hn, 'لینک')) { $assign($idx, 'news_link'); continue; }
        if (str_contains($hn, 'منبع')) { $assign($idx, 'source'); continue; }
    }

    $rows = [];
    $dateCounts = [];
    for ($r = 1; $r < count($sheet); $r++) {
        $line = $sheet[$r];
        if (empty(array_filter($line, fn($c) => trim((string)$c) !== ''))) continue;
        $row = ['code' => '', 'site' => '', 'publisher' => '', 'title' => '', 'news_type' => '', 'reporter' => '',
                'service_main' => '', 'service_sub' => '', 'date' => '', 'pub_time' => '', 'views' => 0,
                'news_link' => '', 'source' => ''];
        foreach ($map as $idx => $key) {
            $val = $line[$idx] ?? '';
            if ($key === 'service') {
                [$row['service_main'], $row['service_sub']] = splitServicePath((string)$val);
            } elseif ($key === 'pub_time') {
                $row['pub_time'] = normalizeExcelTime($val);
            } elseif ($key === 'date') {
                $row['date'] = normalizeJalaliDate(normalizeDigits((string)$val)) ?? trim((string)$val);
            } elseif ($key === 'code') {
                $row['code'] = normalizeDigits(trim((string)$val));
            } elseif ($key === 'views') {
                $n = normalizeDigits(trim((string)$val));
                $row['views'] = is_numeric($n) ? (int)round((float)$n) : 0;
            } else {
                $row[$key] = trim((string)$val);
            }
        }
        if ($row['date'] !== '') {
            $dateCounts[$row['date']] = ($dateCounts[$row['date']] ?? 0) + 1;
        }
        $rows[] = $row;
    }

    return ['rows' => $rows, 'dateCounts' => $dateCounts];
}
