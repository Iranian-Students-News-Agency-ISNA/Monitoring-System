<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginApi();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$from = normalizeJalaliDate($_GET['from'] ?? '') ?? '';
$to   = normalizeJalaliDate($_GET['to'] ?? '') ?? '';
$site        = trim($_GET['site'] ?? '');
$service     = trim($_GET['service'] ?? '');
$subservice  = trim($_GET['subservice'] ?? '');
$granularity = ($_GET['granularity'] ?? 'day') === 'month' ? 'month' : 'day';
$role     = ($_GET['role'] ?? '') === 'publisher' ? 'publisher' : 'reporter';
$name     = trim($_GET['name'] ?? '');
$reporter = trim($_GET['reporter'] ?? '');
$newsType = trim($_GET['news_type'] ?? '');
$limit    = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [5, 10, 15], true)) $limit = 10;

if ($from === '' || $to === '') {
    echo json_encode(['ok' => false, 'error' => 'بازه تاریخ نامعتبر است.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($from > $to) { [$from, $to] = [$to, $from]; }

switch ($action) {

    // ===== آمار عملکرد سرویس‌ها (پورت‌شده از ایسنا) =====

    case 'sites':
        echo json_encode(['ok' => true, 'items' => distinctValuesInRange($from, $to, 'site')], JSON_UNESCAPED_UNICODE);
        break;

    case 'services':
        echo json_encode(['ok' => true, 'items' => distinctValuesInRange($from, $to, 'service_main', '', $site)], JSON_UNESCAPED_UNICODE);
        break;

    case 'news_types':
        echo json_encode(['ok' => true, 'items' => distinctValuesInRange($from, $to, 'news_type', $service, $site)], JSON_UNESCAPED_UNICODE);
        break;

    case 'subservices':
        if ($service === '') { echo json_encode(['ok' => false, 'error' => 'ابتدا یک سرویس مشخص انتخاب کنید.'], JSON_UNESCAPED_UNICODE); break; }
        echo json_encode(['ok' => true, 'items' => distinctValuesInRange($from, $to, 'service_sub', $service, $site)], JSON_UNESCAPED_UNICODE);
        break;

    case 'persons':
        $rows = rowsInRange($from, $to, $service, '', '', '', '', $site);
        $field = $role === 'publisher' ? 'publisher' : 'reporter';
        $list = [];
        foreach ($rows as $r) {
            $v = trim((string)($r[$field] ?? ''));
            if ($v !== '') $list[$v] = ($list[$v] ?? 0) + 1;
        }
        arsort($list);
        echo json_encode(['ok' => true, 'items' => array_keys($list)], JSON_UNESCAPED_UNICODE);
        break;

    case 'overview':
        $allRows = rowsInRange($from, $to, '', '', '', '', '', $site);
        $scopeRows = $service !== '' ? rowsInRange($from, $to, $service, '', '', '', '', $site) : $allRows;
        $totalAll = count($allRows);
        $totalScope = count($scopeRows);
        $sumViews = array_sum(array_column($scopeRows, 'views'));
        $sumViewsAll = array_sum(array_column($allRows, 'views'));
        echo json_encode([
            'ok' => true,
            'total_all'      => $totalAll,
            'total_scope'    => $totalScope,
            'share_percent'  => ($service !== '' && $totalAll > 0) ? round($totalScope * 100 / $totalAll, 1) : null,
            'avg_views'      => $totalScope > 0 ? round($sumViews / $totalScope, 1) : 0,
            'sum_views'      => $sumViews,
            'sum_views_share_percent' => ($service !== '' && $sumViewsAll > 0) ? round($sumViews * 100 / $sumViewsAll, 1) : null,
            'series'         => buildSeries($scopeRows, $granularity),
            'type_pie'       => typeBreakdownTable($scopeRows),
            'type_avg_views' => typeAvgViewsTable($scopeRows),
            'type_top_reporter_pie' => typeTopReporterPie($scopeRows),
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'hourly':
        $rows = rowsInRange($from, $to, $service, '', '', $newsType, '', $site);
        echo json_encode(['ok' => true, 'series' => buildHourlySeries($rows)], JSON_UNESCAPED_UNICODE);
        break;

    case 'subservice_series':
        if ($service === '' || $subservice === '') { echo json_encode(['ok' => false, 'error' => 'سرویس یا زیرسرویس انتخاب نشده است.'], JSON_UNESCAPED_UNICODE); break; }
        $allRows = rowsInRange($from, $to, $service, '', '', '', $subservice, $site);
        $chartRows = $newsType !== '' ? array_values(array_filter($allRows, fn($r) => ($r['news_type'] ?? '') === $newsType)) : $allRows;
        echo json_encode([
            'ok' => true,
            'series' => buildSeries($chartRows, $granularity),
            'type_table' => typeBreakdownTable($allRows),
            'total_count' => count($chartRows),
            'total_avg_views' => count($chartRows) ? round(array_sum(array_column($chartRows, 'views')) / count($chartRows), 1) : 0,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'person_series':
        if ($name === '') { echo json_encode(['ok' => false, 'error' => 'نامی انتخاب نشده است.'], JSON_UNESCAPED_UNICODE); break; }
        $allRows = rowsInRange($from, $to, $service, $role, $name, '', '', $site);
        $chartRows = $newsType !== '' ? array_values(array_filter($allRows, fn($r) => ($r['news_type'] ?? '') === $newsType)) : $allRows;
        echo json_encode([
            'ok' => true,
            'series' => buildSeries($chartRows, $granularity),
            'type_table' => typeBreakdownTable($allRows),
            'total_count' => count($chartRows),
            'total_avg_views' => count($chartRows) ? round(array_sum(array_column($chartRows, 'views')) / count($chartRows), 1) : 0,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'top_news':
        $rows = rowsInRange($from, $to, $service, '', '', $newsType, $subservice, $site);
        echo json_encode(['ok' => true, 'items' => topViewedNews($rows, $limit)], JSON_UNESCAPED_UNICODE);
        break;

    // ===== بررسی کیفی (بر پایه داده‌های نظارت / news_entries) =====

    case 'qc_reporters':
        $set = [];
        foreach (newsEntriesInRange($from, $to, $service, '', '', '', $site) as $r) {
            $v = trim((string)($r['reporter'] ?? ''));
            if ($v !== '') $set[$v] = true;
        }
        $list = array_keys($set);
        sort($list, SORT_FLAG_CASE | SORT_STRING);
        echo json_encode(['ok' => true, 'items' => $list], JSON_UNESCAPED_UNICODE);
        break;

    case 'qc_news_types':
        $set = [];
        foreach (newsEntriesInRange($from, $to, $service, '', '', '', $site) as $r) {
            $v = trim((string)($r['news_type'] ?? ''));
            if ($v !== '') $set[$v] = true;
        }
        $list = array_keys($set);
        sort($list, SORT_FLAG_CASE | SORT_STRING);
        echo json_encode(['ok' => true, 'items' => $list], JSON_UNESCAPED_UNICODE);
        break;

    case 'qc_summary':
        $reviewed = newsEntriesInRange($from, $to, $service, $subservice, $reporter, $newsType, $site);
        $totalRows = rowsInRange($from, $to, $service, $reporter !== '' ? 'reporter' : '', $reporter, $newsType, $subservice, $site);
        $reviewedCount = count($reviewed);
        $totalCount = count($totalRows);
        echo json_encode([
            'ok' => true,
            'reviewed_count' => $reviewedCount,
            'total_count'    => $totalCount,
            'coverage_percent' => $totalCount > 0 ? round($reviewedCount * 100 / $totalCount, 1) : null,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'qc_items':
        $reviewed = newsEntriesInRange($from, $to, $service, $subservice, $reporter, $newsType, $site);
        $items = [];
        foreach ($reviewed as $r) {
            $items[] = [
                'title'          => $r['title'] ?? '',
                'site'           => $r['site'] ?? '',
                'service_main'   => $r['service_main'] ?? '',
                'service_sub'    => $r['service_sub'] ?? '',
                'news_type'      => $r['news_type'] ?? '',
                'real_news_type' => $r['real_news_type'] ?? '',
                'views'          => newsEntryViews($r),
                'reporter'       => $r['reporter'] ?? '',
                'publisher'      => $r['publisher'] ?? '',
                'link'           => $r['news_link'] ?? '',
                'date'           => $r['entry_date'] ?? '',
            ];
        }
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        break;

    case 'qc_match':
        $reviewed = newsEntriesInRange($from, $to, $service, $subservice, $reporter, $newsType, $site);
        $match = 0; $mismatch = 0; $incomplete = 0;
        foreach ($reviewed as $r) {
            $nt = trim((string)($r['news_type'] ?? ''));
            $rt = trim((string)($r['real_news_type'] ?? ''));
            if ($nt === '' || $rt === '') { $incomplete++; continue; }
            if (newsElementsMatchType($r)) $match++; else $mismatch++;
        }
        echo json_encode(['ok' => true, 'items' => [
            ['label' => 'تطابق دارد', 'count' => $match],
            ['label' => 'تطابق ندارد', 'count' => $mismatch],
            ['label' => 'اطلاعات ناقص', 'count' => $incomplete],
        ]], JSON_UNESCAPED_UNICODE);
        break;

    case 'qc_elements':
        $reviewed = newsEntriesInRange($from, $to, $service, $subservice, $reporter, $newsType, $site);
        $counts = ['رعایت شده است' => 0, 'رعایت نشده است' => 0, 'سایر' => 0, 'ثبت نشده' => 0];
        foreach ($reviewed as $r) { $counts[newsElementsStatus($r)]++; }
        $items = [];
        foreach ($counts as $label => $cnt) { $items[] = ['label' => $label, 'count' => $cnt]; }
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'عملیات نامعتبر.'], JSON_UNESCAPED_UNICODE);
}
