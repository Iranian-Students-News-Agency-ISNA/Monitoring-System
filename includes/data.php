<?php
require_once __DIR__ . '/jsondb.php';

// ===================== excel_files =====================

function excelFilesAll(): array
{
    return jsonRead('excel_files');
}

function excelFilesActiveDates(): array
{
    $activeFileIds = excelActiveFileIds();
    $dates = [];
    if (!empty($activeFileIds)) {
        foreach (jsonRead('excel_rows') as $r) {
            if (!isset($activeFileIds[(int)($r['file_id'] ?? 0)])) continue;
            $d = normalizeJalaliDate((string)($r['date'] ?? ''));
            if ($d !== null) $dates[$d] = true;
        }
    }
    $dates = array_keys($dates);
    rsort($dates);
    return $dates;
}

function excelFileInsert(array $fields): int
{
    $newId = 0;
    jsonUpdate('excel_files', function ($rows) use ($fields, &$newId) {
        $newId = jsonNextId($rows);
        $fields['id'] = $newId;
        $rows[] = $fields;
        return $rows;
    });
    return $newId;
}

function excelFileUpdate(int $id, array $fields): void
{
    jsonUpdate('excel_files', function ($rows) use ($id, $fields) {
        foreach ($rows as &$r) {
            if ((int)$r['id'] === $id) { $r = array_merge($r, $fields); break; }
        }
        return $rows;
    });
}

function excelFileGet(int $id): ?array
{
    foreach (excelFilesAll() as $f) { if ((int)$f['id'] === $id) return $f; }
    return null;
}

function excelFileRowCount(int $fileId): int
{
    $n = 0;
    foreach (jsonRead('excel_rows') as $r) { if ((int)($r['file_id'] ?? 0) === $fileId) $n++; }
    return $n;
}

// ===================== excel_rows =====================

function excelRowsInsertMany(int $fileId, array $rows): void
{
    jsonUpdate('excel_rows', function ($existing) use ($fileId, $rows) {
        foreach ($rows as $row) {
            $row['file_id'] = $fileId;
            $existing[] = $row;
        }
        return $existing;
    });
}

function excelRowsDeleteByFile(int $fileId): void
{
    jsonUpdate('excel_rows', function ($rows) use ($fileId) {
        return array_values(array_filter($rows, fn($r) => (int)($r['file_id'] ?? 0) !== $fileId));
    });
}

// جست‌وجوی یک ردیف اکسل با تاریخِ خودِ ردیف + کد خبر، در میان همه فایل‌های فعال
// (نه فقط فایل‌هایی که تاریخ فایلشان با این تاریخ برابر است - چون یک فایل می‌تواند چند روز را شامل شود)
function excelRowFind(string $date, string $code): ?array
{
    $date = normalizeJalaliDate($date) ?? $date;
    $code = normalizeDigits(trim($code));
    $activeFileIds = excelActiveFileIds();
    if (empty($activeFileIds)) return null;
    foreach (jsonRead('excel_rows') as $r) {
        if (!isset($activeFileIds[(int)($r['file_id'] ?? 0)])) continue;
        if (trim((string)($r['date'] ?? '')) !== $date) continue;
        if (normalizeDigits((string)($r['code'] ?? '')) === $code) return $r;
    }
    return null;
}

// ===================== excel_rows: پرس‌وجو برای «ثبت از پرونده» =====================

// شناسه فایل‌های اکسل فعال (بدون فیلتر تاریخ در سطح فایل - چون یک فایل می‌تواند چند روز را در بر بگیرد)
function excelActiveFileIds(): array
{
    $ids = [];
    foreach (excelFilesAll() as $f) {
        if (($f['status'] ?? 'active') === 'active') $ids[(int)$f['id']] = true;
    }
    return $ids;
}

// ردیف‌های اکسل در بازه تاریخی که ستون خبرنگار مقدار دارد (فقط این‌ها برای «ثبت از پرونده» معتبرند).
// تاریخ هر ردیف از ستون «تاریخ انتشار» خودِ همان ردیف خوانده می‌شود، نه یک تاریخ ثابت برای کل فایل؛
// به همین دلیل فایلی که چند روز را در بر می‌گیرد و همچنین ترکیب چند فایل مختلف در یک بازه، هر دو درست کار می‌کنند.
function excelRowsInRange(string $from, string $to, string $service = '', string $reporter = ''): array
{
    $activeFileIds = excelActiveFileIds();
    if (empty($activeFileIds)) return [];
    $out = [];
    foreach (jsonRead('excel_rows') as $r) {
        if (!isset($activeFileIds[(int)($r['file_id'] ?? 0)])) continue;
        $d = trim((string)($r['date'] ?? ''));
        if ($d === '' || $d < $from || $d > $to) continue;
        $rep = trim((string)($r['reporter'] ?? ''));
        if ($rep === '') continue;
        if ($service !== '' && ($r['service_main'] ?? '') !== $service) continue;
        if ($reporter !== '' && $rep !== $reporter) continue;
        $r['reporter'] = $rep;
        $r['entry_date'] = $d;
        $out[] = $r;
    }
    usort($out, fn($a, $b) => ($a['entry_date'] <=> $b['entry_date']) ?: strcmp((string)($a['code'] ?? ''), (string)($b['code'] ?? '')));
    return $out;
}

function excelRowsDistinctServicesInRange(string $from, string $to): array
{
    $set = [];
    foreach (excelRowsInRange($from, $to) as $r) {
        $s = trim((string)($r['service_main'] ?? ''));
        if ($s !== '') $set[$s] = true;
    }
    $list = array_keys($set);
    sort($list, SORT_FLAG_CASE | SORT_STRING);
    return $list;
}

function excelRowsDistinctReportersInRange(string $from, string $to, string $service = ''): array
{
    $set = [];
    foreach (excelRowsInRange($from, $to, $service) as $r) { $set[$r['reporter']] = true; }
    $list = array_keys($set);
    sort($list, SORT_FLAG_CASE | SORT_STRING);
    return $list;
}

// اصلاح یک‌بارهٔ رکوردهای قدیمی که به‌جای نام نمایشی، یوزرنیم در آن‌ها ثبت شده بود
// (بدون هیچ تغییری در users.json). هر رکورد که مقدار «خبرنگار» آن دقیقاً برابر یوزرنیمِ
// یکی از کاربران است، به نام نمایشی همان کاربر اصلاح می‌شود.
function newsEntriesRepairReporterUsernames(array $users): int
{
    $fixed = 0;
    jsonUpdate('news_entries', function ($rows) use ($users, &$fixed) {
        foreach ($rows as &$r) {
            $rep = trim((string)($r['reporter'] ?? ''));
            if ($rep === '') continue;
            foreach ($users as $u) {
                $uname = trim((string)($u['username'] ?? ''));
                $disp  = trim((string)($u['display_name'] ?? ''));
                if ($uname !== '' && $disp !== '' && strcasecmp($rep, $uname) === 0 && $rep !== $disp) {
                    $r['reporter'] = $disp;
                    $r['updated_at'] = date('Y-m-d H:i:s');
                    $fixed++;
                    break;
                }
            }
        }
        return $rows;
    });
    return $fixed;
}

// ===================== news_entries =====================

// پیدا کردن رکورد ثبت‌شده متناظر با یک ردیف اکسل (بر اساس تاریخ + کد خبر)
function newsEntryFindByDateAndCode(string $date, string $code): ?array
{
    $code = normalizeDigits(trim($code));
    foreach (jsonRead('news_entries') as $r) {
        if (($r['entry_date'] ?? '') === $date && normalizeDigits((string)($r['news_id'] ?? '')) === $code) return $r;
    }
    return null;
}

function newsEntryUpsert(array $data): int
{
    $id = 0;
    jsonUpdate('news_entries', function ($rows) use ($data, &$id) {
        $now = date('Y-m-d H:i:s');
        if (!empty($data['id'])) {
            $found = false;
            foreach ($rows as &$r) {
                if ((int)$r['id'] === (int)$data['id']) {
                    $data['updated_at'] = $now;
                    $r = array_merge($r, $data);
                    $id = (int)$r['id'];
                    $found = true;
                    break;
                }
            }
            if ($found) return $rows;
        }
        $newId = jsonNextId($rows);
        $data['id'] = $newId;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $rows[] = $data;
        $id = $newId;
        return $rows;
    });
    return $id;
}

function newsEntryGetById(int $id): ?array
{
    foreach (jsonRead('news_entries') as $r) { if ((int)$r['id'] === $id) return $r; }
    return null;
}

function newsEntryUpdateById(int $id, array $fields): void
{
    jsonUpdate('news_entries', function ($rows) use ($id, $fields) {
        foreach ($rows as &$r) {
            if ((int)$r['id'] === $id) {
                $fields['updated_at'] = date('Y-m-d H:i:s');
                $r = array_merge($r, $fields);
                break;
            }
        }
        return $rows;
    });
}

function newsEntryDeleteById(int $id): void
{
    jsonUpdate('news_entries', function ($rows) use ($id) {
        return array_values(array_filter($rows, fn($r) => (int)$r['id'] !== $id));
    });
}

function newsEntriesByDate(string $date): array
{
    $date = normalizeJalaliDate($date) ?? $date;
    $out = array_values(array_filter(jsonRead('news_entries'), fn($r) => ($r['entry_date'] ?? '') === $date));
    usort($out, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
    return $out;
}

function newsEntriesDistinctReporters(): array
{
    $set = [];
    foreach (jsonRead('news_entries') as $r) {
        $rep = trim((string)($r['reporter'] ?? ''));
        if ($rep !== '') $set[$rep] = true;
    }
    $list = array_keys($set);
    sort($list, SORT_FLAG_CASE | SORT_STRING);
    return $list;
}

function newsEntriesDistinctServices(): array
{
    $set = [];
    foreach (jsonRead('news_entries') as $r) {
        $s = trim((string)($r['service_main'] ?? ''));
        if ($s !== '') $set[$s] = true;
    }
    $list = array_keys($set);
    sort($list, SORT_FLAG_CASE | SORT_STRING);
    return $list;
}

function newsEntriesReportersByService(string $service): array
{
    $set = [];
    foreach (jsonRead('news_entries') as $r) {
        if (($r['service_main'] ?? '') === $service) {
            $rep = trim((string)($r['reporter'] ?? ''));
            if ($rep !== '') $set[$rep] = true;
        }
    }
    $list = array_keys($set);
    sort($list, SORT_FLAG_CASE | SORT_STRING);
    return $list;
}

function newsEntriesFilter(string $reporter, string $from, string $to, string $service = ''): array
{
    $from = normalizeJalaliDate($from) ?? $from;
    $to   = normalizeJalaliDate($to) ?? $to;
    $out = array_values(array_filter(jsonRead('news_entries'), function ($r) use ($reporter, $from, $to, $service) {
        if ($reporter !== '' && ($r['reporter'] ?? '') !== $reporter) return false;
        if ($service !== '' && ($r['service_main'] ?? '') !== $service) return false;
        $d = $r['entry_date'] ?? '';
        return $d >= $from && $d <= $to;
    }));
    usort($out, fn($a, $b) => ($a['entry_date'] ?? '') <=> ($b['entry_date'] ?? '') ?: ($a['id'] ?? 0) <=> ($b['id'] ?? 0));
    return $out;
}

// ===================== ارزیابی: آمار عملکرد سرویس‌ها (بر پایه excel_rows، شامل بازدید) =====================
// این توابع از پروژه «ایسنا» پورت شده‌اند و روی همان excel_rows نسخه نظارت کار می‌کنند.

function rowsInRange(string $from, string $to, string $service = '', string $role = '', string $name = '', string $newsType = '', string $subservice = '', string $site = '', array $titleKeywords = [], string $keywordMode = 'and'): array
{
    $activeFileIds = excelActiveFileIds();
    if (empty($activeFileIds)) return [];
    $kws = array_values(array_filter(array_map('trim', $titleKeywords), fn($w) => $w !== ''));
    $out = [];
    foreach (jsonRead('excel_rows') as $r) {
        if (!isset($activeFileIds[(int)($r['file_id'] ?? 0)])) continue;
        $d = trim((string)($r['date'] ?? ''));
        if ($d === '' || $d < $from || $d > $to) continue;
        $title = trim((string)($r['title'] ?? ''));
        if ($title === '') continue;
        if ($site !== '' && ($r['site'] ?? '') !== $site) continue;
        if ($service !== '' && ($r['service_main'] ?? '') !== $service) continue;
        if ($subservice !== '' && ($r['service_sub'] ?? '') !== $subservice) continue;
        if ($role !== '' && $name !== '') {
            $field = $role === 'publisher' ? 'publisher' : 'reporter';
            if (trim((string)($r[$field] ?? '')) !== $name) continue;
        }
        if ($newsType !== '' && ($r['news_type'] ?? '') !== $newsType) continue;
        if (!empty($kws) && !titleMatchesKeywords($title, $kws, $keywordMode)) continue;
        $out[] = $r;
    }
    return $out;
}

// بررسی می‌کند تیتر با مجموعه کلمات/عبارات کاربر مطابقت دارد یا نه (mode: 'and' یا 'or')
function titleMatchesKeywords(string $title, array $kws, string $mode): bool
{
    if ($mode === 'or') {
        foreach ($kws as $kw) {
            if (mb_stripos($title, $kw) !== false) return true;
        }
        return false;
    }
    foreach ($kws as $kw) {
        if (mb_stripos($title, $kw) === false) return false;
    }
    return true;
}

function distinctValuesInRange(string $from, string $to, string $field, string $service = '', string $site = ''): array
{
    $set = [];
    foreach (rowsInRange($from, $to, $service, '', '', '', '', $site) as $r) {
        $v = trim((string)($r[$field] ?? ''));
        if ($v !== '') $set[$v] = true;
    }
    $list = array_keys($set);
    sort($list, SORT_FLAG_CASE | SORT_STRING);
    return $list;
}

// گروه‌بندی سری زمانی: تعداد خبر + میانگین بازدید به تفکیک روز یا ماه
function buildSeries(array $rows, string $granularity): array
{
    $buckets = [];
    foreach ($rows as $r) {
        $d = (string)($r['date'] ?? '');
        $norm = normalizeJalaliDate($d);
        if (!$norm) continue;
        $period = $granularity === 'month' ? substr($norm, 0, 7) : $norm;
        if (!isset($buckets[$period])) $buckets[$period] = ['count' => 0, 'views' => 0];
        $buckets[$period]['count']++;
        $buckets[$period]['views'] += (int)($r['views'] ?? 0);
    }
    ksort($buckets, SORT_STRING);
    $out = [];
    foreach ($buckets as $period => $b) {
        if ($granularity === 'month') {
            [$jy, $jm] = array_map('intval', explode('/', $period));
            $label = persianMonthName($jm) . ' ' . $jy;
        } else {
            $label = jalaliDateLabelShort($period);
        }
        $out[] = ['period' => $period, 'label' => $label, 'count' => $b['count'],
                  'avg_views' => $b['count'] > 0 ? round($b['views'] / $b['count'], 1) : 0];
    }
    return $out;
}

function jalaliDateLabelShort(string $dateStr): string
{
    $norm = normalizeJalaliDate($dateStr);
    if (!$norm) return $dateStr;
    [$jy, $jm, $jd] = array_map('intval', explode('/', $norm));
    return sprintf('%d %s', $jd, persianMonthName($jm));
}

function typeBreakdownTable(array $rows): array
{
    $total = count($rows);
    if ($total === 0) return [];
    $counts = [];
    foreach ($rows as $r) {
        $t = trim((string)($r['news_type'] ?? '')) ?: 'نامشخص';
        $counts[$t] = ($counts[$t] ?? 0) + 1;
    }
    arsort($counts);
    $out = [];
    foreach ($counts as $type => $cnt) { $out[] = ['type' => $type, 'count' => $cnt, 'percent' => round($cnt * 100 / $total, 1)]; }
    return $out;
}

function typeAvgViewsTable(array $rows): array
{
    $agg = [];
    foreach ($rows as $r) {
        $t = trim((string)($r['news_type'] ?? '')) ?: 'نامشخص';
        if (!isset($agg[$t])) $agg[$t] = ['count' => 0, 'views' => 0];
        $agg[$t]['count']++;
        $agg[$t]['views'] += (int)($r['views'] ?? 0);
    }
    $out = [];
    foreach ($agg as $type => $a) { $out[] = ['type' => $type, 'count' => $a['count'], 'avg_views' => $a['count'] > 0 ? round($a['views'] / $a['count'], 1) : 0]; }
    usort($out, fn($a, $b) => $b['avg_views'] <=> $a['avg_views']);
    return $out;
}

// آیا مقدار وارد شده یک نام واقعی خبرنگار محسوب می‌شود؟ (خالی یا فقط خط‌تیره/نامشخص در نظر گرفته نمی‌شود)
function isValidReporterName(string $rep): bool
{
    $rep = trim($rep);
    if ($rep === '' || $rep === 'نامشخص') return false;
    // اگر هیچ حرف یا رقمی نداشته باشد (مثلاً فقط «-»)، نام معتبر نیست
    return (bool)preg_match('/[\p{L}\p{N}]/u', $rep);
}

// برای هر نوع خبر، خبرنگاری که بیشترین تعداد را در آن نوع داشته (خبرنگارهای بدون نام نادیده گرفته می‌شوند)
function typeTopReporterPie(array $rows): array
{
    $agg = [];
    foreach ($rows as $r) {
        $rep = trim((string)($r['reporter'] ?? ''));
        if (!isValidReporterName($rep)) continue;
        $t = trim((string)($r['news_type'] ?? '')) ?: 'نامشخص';
        if (!isset($agg[$t])) $agg[$t] = [];
        $agg[$t][$rep] = ($agg[$t][$rep] ?? 0) + 1;
    }
    $out = [];
    foreach ($agg as $type => $reps) {
        arsort($reps);
        $topReporter = array_key_first($reps);
        $out[] = ['type' => $type, 'reporter' => $topReporter, 'count' => $reps[$topReporter]];
    }
    usort($out, fn($a, $b) => $b['count'] <=> $a['count']);
    return $out;
}

// ۵ خبرنگار پرکارتر (بدون نام‌های خالی) به همراه سهم درصدی هر نوع خبر از اخبار آن‌ها
function topReportersTypeBreakdown(array $rows, int $limit = 10): array
{
    $repTotals = [];
    $repTypeCounts = [];
    $allTypes = [];
    foreach ($rows as $r) {
        $rep = trim((string)($r['reporter'] ?? ''));
        if (!isValidReporterName($rep)) continue;
        $t = trim((string)($r['news_type'] ?? '')) ?: 'نامشخص';
        $repTotals[$rep] = ($repTotals[$rep] ?? 0) + 1;
        $repTypeCounts[$rep][$t] = ($repTypeCounts[$rep][$t] ?? 0) + 1;
        $allTypes[$t] = true;
    }
    arsort($repTotals);
    $topReps = array_slice(array_keys($repTotals), 0, $limit);
    $types = array_keys($allTypes);
    $series = [];
    foreach ($types as $t) {
        $percents = [];
        foreach ($topReps as $rep) {
            $total = $repTotals[$rep];
            $cnt = $repTypeCounts[$rep][$t] ?? 0;
            $percents[] = $total > 0 ? round($cnt * 100 / $total, 1) : 0;
        }
        $series[] = ['type' => $t, 'percents' => $percents];
    }
    return [
        'reporters' => $topReps,
        'totals'    => array_map(fn($rep) => $repTotals[$rep], $topReps),
        'types'     => $types,
        'series'    => $series,
    ];
}

function buildHourlySeries(array $rows): array
{
    $buckets = [];
    for ($h = 0; $h < 24; $h++) { $buckets[$h] = ['count' => 0, 'views' => 0]; }
    foreach ($rows as $r) {
        $t = trim((string)($r['pub_time'] ?? ''));
        if (!preg_match('/^(\d{1,2}):/', $t, $m)) continue;
        $h = (int)$m[1];
        if ($h < 0 || $h > 23) continue;
        $buckets[$h]['count']++;
        $buckets[$h]['views'] += (int)($r['views'] ?? 0);
    }
    $out = [];
    foreach ($buckets as $h => $b) {
        $out[] = ['hour' => sprintf('%02d', $h), 'label' => sprintf('%02d', $h), 'count' => $b['count'],
                  'avg_views' => $b['count'] > 0 ? round($b['views'] / $b['count'], 1) : 0];
    }
    return $out;
}

function topViewedNews(array $rows, int $limit): array
{
    usort($rows, fn($a, $b) => (int)($b['views'] ?? 0) <=> (int)($a['views'] ?? 0));
    $rows = array_slice($rows, 0, $limit);
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['title' => $r['title'] ?? '', 'reporter' => $r['reporter'] ?? '', 'publisher' => $r['publisher'] ?? '',
                  'news_type' => $r['news_type'] ?? '', 'service_sub' => $r['service_sub'] ?? '', 'date' => $r['date'] ?? '',
                  'views' => (int)($r['views'] ?? 0), 'link' => $r['news_link'] ?? ''];
    }
    return $out;
}

// ===================== ارزیابی: بررسی کیفی (بر پایه news_entries نظارت) =====================

// ردیف‌های نظارت (news_entries) در یک بازه، با فیلتر سرویس/زیرسرویس/خبرنگار/نوع خبر
function newsEntriesInRange(string $from, string $to, string $service = '', string $subservice = '', string $reporter = '', string $newsType = '', string $site = '', array $titleKeywords = [], string $keywordMode = 'and'): array
{
    $kws = array_values(array_filter(array_map('trim', $titleKeywords), fn($w) => $w !== ''));
    $out = array_values(array_filter(jsonRead('news_entries'), function ($r) use ($from, $to, $service, $subservice, $reporter, $newsType, $site, $kws, $keywordMode) {
        $d = $r['entry_date'] ?? '';
        if ($d === '' || $d < $from || $d > $to) return false;
        if ($site !== '' && ($r['site'] ?? '') !== $site) return false;
        if ($service !== '' && ($r['service_main'] ?? '') !== $service) return false;
        if ($subservice !== '' && ($r['service_sub'] ?? '') !== $subservice) return false;
        if ($reporter !== '' && ($r['reporter'] ?? '') !== $reporter) return false;
        if ($newsType !== '' && ($r['news_type'] ?? '') !== $newsType) return false;
        if (!empty($kws) && !titleMatchesKeywords((string)($r['title'] ?? ''), $kws, $keywordMode)) return false;
        return true;
    }));
    usort($out, fn($a, $b) => ($b['entry_date'] ?? '') <=> ($a['entry_date'] ?? '') ?: ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
    return $out;
}

// آیا نوع خبر (news_type) با نوع واقعی خبر (real_news_type، متن آزاد) هم‌خوانی دارد؛ مقایسه به‌صورت فازی (شامل‌بودن رشته)
function newsElementsMatchType(array $row): bool
{
    $nt = trim(mb_strtolower((string)($row['news_type'] ?? '')));
    $rt = trim(mb_strtolower((string)($row['real_news_type'] ?? '')));
    if ($nt === '' || $rt === '') return false;
    return mb_strpos($rt, $nt) !== false || mb_strpos($nt, $rt) !== false;
}

// وضعیت «عناصر خبری» یک ردیف نظارت را به یکی از سه دسته ثابت نگاشت می‌کند
function newsElementsStatus(array $row): string
{
    $v = trim((string)($row['news_elements'] ?? ''));
    if ($v === 'رعایت شده است') return 'رعایت شده است';
    if ($v === 'رعایت نشده است') return 'رعایت نشده است';
    if ($v === '') return 'ثبت نشده';
    return 'سایر';
}

// نگاشت یک‌باره‌ی «تاریخ|کد خبر» -> بازدید، برای جلوگیری از اسکن کامل excel_rows به ازای هر ردیفِ نظارت
// (قبلاً newsEntryViews() برای هر آیتم یک اسکن کامل انجام می‌داد که با حجم زیاد داده بسیار کند بود)
function excelViewsIndex(): array
{
    static $index = null;
    if ($index !== null) return $index;
    $activeFileIds = excelActiveFileIds();
    $index = [];
    if (empty($activeFileIds)) return $index;
    foreach (jsonRead('excel_rows') as $r) {
        if (!isset($activeFileIds[(int)($r['file_id'] ?? 0)])) continue;
        $d = trim((string)($r['date'] ?? ''));
        $c = normalizeDigits(trim((string)($r['code'] ?? '')));
        if ($d === '' || $c === '') continue;
        $index[$d . '|' . $c] = (int)($r['views'] ?? 0);
    }
    return $index;
}

// بازدید یک ردیف نظارت را از excel_rows متناظر (بر اساس تاریخ + کد خبر) پیدا می‌کند
function newsEntryViews(array $row): int
{
    $date = (string)($row['entry_date'] ?? '');
    $code = normalizeDigits(trim((string)($row['news_id'] ?? '')));
    if ($date === '' || $code === '') return 0;
    return excelViewsIndex()[$date . '|' . $code] ?? 0;
}
