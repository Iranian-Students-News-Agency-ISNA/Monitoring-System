<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/topics.php';
require_once __DIR__ . '/includes/xlsx.php';
requireLoginPage();

$today   = todayJalali();
$from    = normalizeJalaliDate($_GET['from'] ?? '') ?? $today;
$to      = normalizeJalaliDate($_GET['to'] ?? '') ?? $today;
$service = trim($_GET['service'] ?? '');
$selectedRanges = array_values(array_intersect(
    (array)($_GET['ranges'] ?? []),
    array_column(viewsBuckets(), 'key')
));
$services = excelRowsDistinctServicesInRange($from, $to);

// تجمیع: مجموع بازدید و تعداد خبر برای هر موضوع (اخبار ایسنا با حداقل ۱۰۰۰ بازدید)
$agg = [];
$untagged = 0;
foreach (topicEvaluationRows($from, $to, $service, $selectedRanges) as $r) {
    if (empty($r['__topics'])) { $untagged++; continue; }
    foreach ($r['__topics'] as $t) {
        $t = ltrim(trim((string)$t), '*');
        if ($t === '') continue;
        if (!isset($agg[$t])) $agg[$t] = ['views' => 0, 'count' => 0];
        $agg[$t]['views'] += (int)$r['__views'];
        $agg[$t]['count']++;
    }
}
uasort($agg, fn($a, $b) => $b['views'] <=> $a['views']);
$allTopics = $agg;
$agg = array_slice($agg, 0, 20, true);

// خروجی اکسل جدول موضوعات
if (($_GET['export'] ?? '') === '1') {
    $headers = ['ردیف', 'موضوع', 'تعداد خبر', 'مجموع بازدید', 'میانگین بازدید'];
    $dataRows = [];
    $i = 0;
    foreach ($allTopics as $name => $a) {
        $i++;
        $dataRows[] = [$i, $name, (int)$a['count'], (int)$a['views'], (int)round($a['views'] / max(1, $a['count']))];
    }
    $sumC = array_sum(array_column($allTopics, 'count')); $sumV = array_sum(array_column($allTopics, 'views'));
    $dataRows[] = ['', 'جمع', (int)$sumC, (int)$sumV, (int)round($sumV / max(1, $sumC))];
    $cfg = appConfig();
    if (!is_dir($cfg['storage_tmp'])) mkdir($cfg['storage_tmp'], 0775, true);
    $tmpFile = $cfg['storage_tmp'] . '/topic_report_' . uniqid() . '.xlsx';
    try {
        xlsxWriteSimple($tmpFile, $headers, $dataRows, 'گزارش موضوعات');
    } catch (Throwable $e) {
        die('خطا در ساخت فایل خروجی: ' . htmlspecialchars($e->getMessage()));
    }
    $filename = 'گزارش_موضوعات_' . str_replace('/', '-', $from) . '_' . str_replace('/', '-', $to) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

$labels = array_keys($agg);
$views  = array_column($agg, 'views');
$counts = array_column($agg, 'count');
$n = count($labels);
// طیف سبز: پررنگ (رتبه ۱) ← کم‌رنگ (آخرین رتبه)
$colors = [];
foreach ($labels as $i => $_) {
    $f = $n > 1 ? $i / ($n - 1) : 0;
    $colors[] = sprintf('hsl(142, %d%%, %d%%)', 70 - (int)round($f * 25), 24 + (int)round($f * 52));
}

// ماتریس موضوع × سرویس (مستقل از فیلتر سرویس بالا تا همهٔ سرویس‌ها دیده شوند)
$mx = []; $svcSet = []; $mt = []; $typeSet = []; $mr = []; $rangeSet = [];
foreach (topicEvaluationRows($from, $to, '', $selectedRanges) as $r) {
    $sv = trim((string)($r['service_main'] ?? '')); if ($sv === '') $sv = 'نامشخص';
    foreach (($r['__topics'] ?? []) as $t) {
        $t = ltrim(trim((string)$t), '*'); if ($t === '') continue;
        $mx[$t][$sv]['count'] = ($mx[$t][$sv]['count'] ?? 0) + 1;
        $mx[$t][$sv]['views'] = ($mx[$t][$sv]['views'] ?? 0) + (int)$r['__views'];
        $svcSet[$sv] = true;
        $nt = trim((string)($r['news_type'] ?? '')); if ($nt === '') $nt = 'نامشخص';
        $mt[$t][$nt]['count'] = ($mt[$t][$nt]['count'] ?? 0) + 1;
        $mt[$t][$nt]['views'] = ($mt[$t][$nt]['views'] ?? 0) + (int)$r['__views'];
        $typeSet[$nt] = true;
        $bk = viewsBucketFor((int)$r['__views']); $rl = $bk ? $bk['label'] : 'کمتر از هزار';
        $mr[$t][$rl]['count'] = ($mr[$t][$rl]['count'] ?? 0) + 1;
        $mr[$t][$rl]['views'] = ($mr[$t][$rl]['views'] ?? 0) + (int)$r['__views'];
        $rangeSet[$rl] = true;
    }
}
$typeCols = array_keys($typeSet); sort($typeCols, SORT_STRING);
$svcCols = array_keys($svcSet); sort($svcCols, SORT_STRING);
$rangeCols = [];
foreach (array_merge(['کمتر از هزار'], array_column(viewsBuckets(), 'label')) as $rl0) if (isset($rangeSet[$rl0])) $rangeCols[] = $rl0;
// ---- اختصاصی / غیراختصاصی (پیش‌فرض غیراختصاصی: ترجمه، پوششی، دریافتی؛ قابل تغییر از پنجرهٔ تنظیمات) ----
$defaultNonEx = ['ترجمه', 'پوششی', 'دریافتی'];
if (isset($_GET['nonex_set'])) {
    $nonEx = array_values(array_intersect((array)($_GET['nonex'] ?? []), $typeCols));
} else {
    $nonEx = [];
    foreach ($typeCols as $tc0) foreach ($defaultNonEx as $d0) if (mb_strpos($tc0, $d0) !== false) { $nonEx[] = $tc0; break; }
}
$exRows = []; $exSumE = 0; $exSumN = 0;
foreach ($mt as $t => $cells) {
    $e = 0; $ne = 0;
    foreach ($cells as $c => $cell) { if (in_array($c, $nonEx, true)) $ne += $cell['count']; else $e += $cell['count']; }
    $exRows[$t] = [$e, $ne];
    $exSumE += $e; $exSumN += $ne;
}
uasort($exRows, fn($a, $b) => ($b[0] + $b[1]) <=> ($a[0] + $a[1]));

// ---- داده نمودار ستونی انباشته (۱۰ موضوع پرخبر × ۶ سرویس/نوع پرتعداد + «سایر») ----
function stackedBar(array $M, array $cols, int $topT = 10, int $topS = 6): array
{
    $tot = [];
    foreach ($M as $t => $cells) $tot[$t] = array_sum(array_column($cells, 'count'));
    arsort($tot);
    $topics = array_slice(array_keys($tot), 0, $topT);
    $ct = array_fill_keys($cols, 0);
    foreach ($topics as $t) foreach ($cols as $c) $ct[$c] += $M[$t][$c]['count'] ?? 0;
    arsort($ct);
    $main = array_slice(array_keys($ct), 0, $topS);
    $series = [];
    foreach ($main as $c) { $series[] = ['label' => $c, 'data' => array_map(fn($t) => (int)($M[$t][$c]['count'] ?? 0), $topics)]; }
    if (count($ct) > $topS) {
        $oth = array_map(function ($t) use ($M, $main) { $x = 0; foreach ($M[$t] as $c => $cell) if (!in_array($c, $main, true)) $x += $cell['count']; return $x; }, $topics);
        if (array_sum($oth) > 0) $series[] = ['label' => 'سایر', 'data' => $oth];
    }
    return ['labels' => $topics, 'series' => $series];
}
function barSummary(array $M, array $cols, string $what): array
{
    $tot = []; $all = 0; $ct = [];
    foreach ($M as $t => $cells) { $tot[$t] = array_sum(array_column($cells, 'count')); $all += $tot[$t]; foreach ($cells as $c => $cell) $ct[$c] = ($ct[$c] ?? 0) + $cell['count']; }
    if ($all <= 0) return [];
    arsort($tot); arsort($ct);
    $top10 = array_sum(array_slice($tot, 0, 10));
    $t1 = array_key_first($tot); $c1 = array_key_first($ct);
    return [
        'موضوع «' . htmlspecialchars($t1) . '» با ' . number_format($tot[$t1]) . ' خبر در صدر است و ' . $what . ' «' . htmlspecialchars($c1) . '» با ' . round($ct[$c1] / $all * 100) . '٪ سهم بیشترین حجم را دارد.',
        '۱۰ موضوع برتر در مجموع ' . round($top10 / $all * 100) . '٪ از کل اخبار موضوع‌دار را پوشش می‌دهند.',
    ];
}
$barSvc = stackedBar($mx, $svcCols);
$barType = stackedBar($mt, $typeCols);
$barRange = stackedBar($mr, $rangeCols, 10, 9);

// خروجی اکسل ماتریس‌ها: ?export=mx_svc_count | mx_svc_views | mx_type_count
$mxExports = [
    'mx_svc_count'  => [&$mx, &$svcCols,  'count', 'موضوع × سرویس (تعداد خبر)'],
    'mx_svc_views'  => [&$mx, &$svcCols,  'views', 'موضوع × سرویس (بازدید)'],
    'mx_type_count' => [&$mt, &$typeCols, 'count', 'موضوع × نوع خبر (تعداد خبر)'],
    'mx_range_count' => [&$mr, &$rangeCols, 'count', 'موضوع × محدوده بازدید (تعداد خبر)'],
];
if (isset($_GET['export']) && isset($mxExports[$_GET['export']])) {
    [$M, $C, $metric, $title] = $mxExports[$_GET['export']];
    $tot = [];
    foreach ($M as $t => $cells) $tot[$t] = array_sum(array_column($cells, $metric));
    arsort($tot);
    $dataRows = [];
    foreach (array_slice(array_keys($tot), 0, 20) as $t) {
        $vals = [];
        foreach ($C as $c) $vals[] = (int)($M[$t][$c][$metric] ?? 0);
        $dataRows[] = [$t, $vals];
    }
    $cfg = appConfig();
    if (!is_dir($cfg['storage_tmp'])) mkdir($cfg['storage_tmp'], 0775, true);
    $tmpFile = $cfg['storage_tmp'] . '/topic_mx_' . uniqid() . '.xlsx';
    try {
        xlsxWriteMatrix($tmpFile, mb_substr(str_replace(['×','(',')'], ['-','',''], $title), 0, 31), $C, $dataRows);
    } catch (Throwable $e) {
        die('خطا در ساخت فایل خروجی: ' . htmlspecialchars($e->getMessage()));
    }
    $filename = 'ماتریس_' . str_replace(['/', ' ', '×', '(', ')'], ['-', '_', 'x', '', ''], $title) . '_' . str_replace('/', '-', $from) . '_' . str_replace('/', '-', $to) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}
function mxXlsxBtn(string $key, string $from, string $to, string $service, array $ranges = []): void
{
    $q = htmlspecialchars(http_build_query(['from' => $from, 'to' => $to, 'service' => $service, 'ranges' => $ranges, 'export' => $key]));
    echo '<a class="btn btn-sm btn-outline-success py-0 px-2" title="خروجی اکسل ماتریس" href="topic_report.php?' . $q . '">اکسل</a>';
}
function pngBtn(string $canvasId, string $name): void
{
    echo '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-png="' . htmlspecialchars($canvasId) . '" data-name="' . htmlspecialchars($name) . '" title="دانلود نمودار به‌صورت تصویر">تصویر PNG</button>';
}
function matrixRender(array $mx, array $cols, string $metric, int $limit = 20): void
{
    $tot = [];
    foreach ($mx as $t => $cells) $tot[$t] = array_sum(array_column($cells, $metric));
    arsort($tot);
    $topics = array_slice(array_keys($tot), 0, $limit);
    $max = 0;
    foreach ($topics as $t) foreach ($cols as $c) $max = max($max, $mx[$t][$c][$metric] ?? 0);
    echo '<div class="table-responsive"><table class="table table-sm table-bordered text-center align-middle mb-0" style="font-size:.85rem"><thead><tr><th class="text-start">موضوع</th>';
    foreach ($cols as $c) echo '<th>' . htmlspecialchars($c) . '</th>';
    echo '<th class="table-light">جمع</th></tr></thead><tbody>';
    $colTot = array_fill_keys($cols, 0);
    foreach ($topics as $t) {
        echo '<tr><th class="text-start fw-normal text-nowrap">' . htmlspecialchars($t) . '</th>';
        $rowTot = 0;
        foreach ($cols as $c) {
            $v = $mx[$t][$c][$metric] ?? 0;
            $rowTot += $v; $colTot[$c] += $v;
            if ($v <= 0 || $max <= 0) { echo '<td class="text-muted">–</td>'; continue; }
            $f = $v / $max;
            $l = 94 - (int)round($f * 62);
            $tip = 'تعداد خبر: ' . number_format($mx[$t][$c]['count'] ?? 0) . ' | مجموع بازدید: ' . number_format($mx[$t][$c]['views'] ?? 0);
            echo '<td title="' . htmlspecialchars($tip) . '" style="background:hsl(142,60%,' . $l . '%);color:' . ($l < 55 ? '#fff' : '#12351f') . '">' . number_format($v) . '</td>';
        }
        echo '<td class="fw-bold table-light">' . number_format($rowTot) . '</td></tr>';
    }
    echo '</tbody><tfoot class="table-light fw-bold"><tr><th class="text-start">جمع</th>';
    foreach ($cols as $c) echo '<td>' . number_format($colTot[$c]) . '</td>';
    echo '<td>' . number_format(array_sum($colTot)) . '</td></tr></tfoot></table></div>';
}

// داده‌های نمودار حبابی موضوع × سرویس (۲۰ موضوع پربازدید)
$bTot = [];
foreach ($mx as $t => $cells) $bTot[$t] = array_sum(array_column($cells, 'views'));
arsort($bTot);
$bTopics = array_slice(array_keys($bTot), 0, 20);
$bPoints = []; $bMaxV = 0; $bMaxC = 0;
foreach ($bTopics as $yi => $t) {
    foreach ($svcCols as $xi => $c) {
        $cell = $mx[$t][$c] ?? null;
        if (!$cell) continue;
        $bPoints[] = ['x' => $xi, 'y' => $yi, 'c' => (int)$cell['count'], 'v' => (int)$cell['views']];
        $bMaxV = max($bMaxV, $cell['views']); $bMaxC = max($bMaxC, $cell['count']);
    }
}

// ===================== روایت خودکار نمودارها (متن‌های قالبی از روی داده) =====================
function nfa($v): string { return number_format((float)$v); }
function qn(string $s): string { return '«' . htmlspecialchars($s) . '»'; }
function narrBox(array $sentences): void
{
    $sentences = array_filter($sentences);
    if (!$sentences) return;
    echo '<div class="alert alert-light border small mt-2 mb-0 py-2"><strong>روایت داده:</strong> ' . implode(' ', $sentences) . '</div>';
}
function topKeysBy(array $mx, string $metric, int $limit = 20): array
{
    $tot = [];
    foreach ($mx as $t => $cells) $tot[$t] = array_sum(array_column($cells, $metric));
    arsort($tot);
    return array_slice(array_keys($tot), 0, $limit);
}
// بهترین خانهٔ ماتریس (بیشترین مقدار شاخص)
function bestCell(array $mx, array $topics, array $cols, string $metric): ?array
{
    $best = null;
    foreach ($topics as $t) foreach ($cols as $c) {
        $v = $mx[$t][$c][$metric] ?? 0;
        if ($v > 0 && ($best === null || $v > $best['v'])) $best = ['t' => $t, 'c' => $c, 'v' => $v];
    }
    return $best;
}
// موضوعی (با حداقل ۳ خبر) که بیشترین سهم را در یک ستون دارد
function concentratedTopic(array $mx, array $cols): ?array
{
    $best = null;
    foreach ($mx as $t => $cells) {
        $tot = array_sum(array_column($cells, 'count'));
        if ($tot < 3) continue;
        foreach ($cells as $c => $cell) {
            $sh = $cell['count'] / $tot;
            if ($best === null || $sh > $best['sh']) $best = ['t' => $t, 'c' => $c, 'sh' => $sh, 'n' => $tot];
        }
    }
    return ($best && $best['sh'] >= 0.8 && count($cols) >= 2) ? $best : null;
}

function narrBar(array $allTopics): array
{
    if (!$allTopics) return [];
    $total = array_sum(array_column($allTopics, 'views'));
    if ($total <= 0) return [];
    $names = array_keys($allTopics);
    $first = $names[0];
    $out = ['در این بازه، موضوع ' . qn($first) . ' با ' . nfa($allTopics[$first]['views']) . ' بازدید در صدر است و ' . round($allTopics[$first]['views'] / $total * 100) . '٪ از مجموع بازدید موضوعات را دارد.'];
    if (count($names) >= 4) {
        $top3 = 0; foreach (array_slice($names, 0, 3) as $nm) $top3 += $allTopics[$nm]['views'];
        $p = round($top3 / $total * 100);
        $out[] = 'سه موضوع نخست روی هم ' . $p . '٪ بازدید را دارند' . ($p >= 50 ? '؛ یعنی توجه مخاطب روی تعداد کمی موضوع متمرکز بوده است.' : '؛ یعنی بازدید بین موضوعات پراکنده‌تر بوده است.');
    }
    return $out;
}
function narrTable(array $allTopics): array
{
    if (!$allTopics) return [];
    $avg = fn($a) => $a['views'] / max(1, $a['count']);
    $mostN = null;
    foreach ($allTopics as $t => $a) if ($mostN === null || $a['count'] > $allTopics[$mostN]['count'] || ($a['count'] === $allTopics[$mostN]['count'] && $a['views'] > $allTopics[$mostN]['views'])) $mostN = $t;
    $out = ['از ' . nfa(count($allTopics)) . ' موضوع ثبت‌شده، ' . qn($mostN) . ' بیشترین تعداد خبر (' . nfa($allTopics[$mostN]['count']) . ') را دارد و میانگین بازدیدش ' . nfa(round($avg($allTopics[$mostN]))) . ' است.'];
    $pool = array_filter($allTopics, fn($a) => $a['count'] >= 2);
    $note = ' (در میان موضوعات دارای دست‌کم ۲ خبر)';
    if (!$pool) { $pool = $allTopics; $note = ''; }
    $hiAvg = null;
    foreach ($pool as $t => $a) if ($hiAvg === null || $avg($a) > $avg($pool[$hiAvg])) $hiAvg = $t;
    if ($hiAvg !== null && $hiAvg !== $mostN) {
        $s = 'بالاترین میانگین بازدید' . $note . ' مربوط به ' . qn($hiAvg) . ' با ' . nfa(round($avg($pool[$hiAvg]))) . ' است (از ' . nfa($pool[$hiAvg]['count']) . ' خبر)';
        $s .= $avg($pool[$hiAvg]) > $avg($allTopics[$mostN]) ? '؛ یعنی پرتولید بودن یک موضوع لزوماً به معنی پربازدید بودن آن نیست.' : '.';
        $out[] = $s;
    } elseif ($hiAvg === $mostN && count($allTopics) > 1) {
        $out[] = 'همین موضوع بالاترین میانگین بازدید را هم دارد؛ یعنی هم پرتولید بوده و هم پراستقبال.';
    }
    return $out;
}
function narrMxCount(array $mx, array $cols): array
{
    if (!$mx || !$cols) return [];
    $top = topKeysBy($mx, 'count');
    $b = bestCell($mx, $top, $cols, 'count');
    $out = [];
    if ($b) $out[] = 'بیشترین تراکم خبر در تلاقی ' . qn($b['t']) . ' و سرویس ' . qn($b['c']) . ' (' . nfa($b['v']) . ' خبر) دیده می‌شود.';
    $wide = null;
    foreach ($top as $t) { $k = count($mx[$t]); if ($wide === null || $k > count($mx[$wide])) $wide = $t; }
    if ($wide !== null && count($mx[$wide]) >= 2) $out[] = 'موضوع ' . qn($wide) . ' در ' . nfa(count($mx[$wide])) . ' سرویس مختلف پوشش داده شده است.';
    $cn = concentratedTopic($mx, $cols);
    if ($cn) $out[] = 'در مقابل، ' . round($cn['sh'] * 100) . '٪ اخبار ' . qn($cn['t']) . ' فقط در سرویس ' . qn($cn['c']) . ' منتشر شده است.';
    return $out;
}
function narrMxViews(array $mx, array $cols): array
{
    if (!$mx || !$cols) return [];
    $top = topKeysBy($mx, 'views');
    $b = bestCell($mx, $top, $cols, 'views');
    $out = [];
    if ($b) $out[] = 'بیشترین بازدید در تلاقی ' . qn($b['t']) . ' و سرویس ' . qn($b['c']) . ' (' . nfa($b['v']) . ' بازدید) ثبت شده است.';
    $sc = []; $sv = [];
    foreach ($mx as $cells) foreach ($cells as $c => $cell) { $sc[$c] = ($sc[$c] ?? 0) + $cell['count']; $sv[$c] = ($sv[$c] ?? 0) + $cell['views']; }
    if (count($sc) >= 2) {
        arsort($sc); arsort($sv);
        $ac = array_key_first($sc); $av = array_key_first($sv);
        $out[] = $ac !== $av
            ? 'سرویس ' . qn($ac) . ' بیشترین تعداد خبر را دارد، اما بیشترین بازدید از سرویس ' . qn($av) . ' آمده؛ پس حجم تولید و میزان استقبال مخاطب در همهٔ سرویس‌ها یکسان نیست.'
            : 'سرویس ' . qn($ac) . ' هم از نظر تعداد خبر و هم از نظر بازدید در صدر است.';
    }
    return $out;
}
function narrBubble(array $pts, array $topics, array $cols, int $maxC, int $maxV): array
{
    if (!$pts || $maxC <= 0 || $maxV <= 0) return [];
    $pick = function (callable $f, string $key) use ($pts) {
        $best = null;
        foreach ($pts as $p) if ($f($p) && ($best === null || $p[$key] > $best[$key])) $best = $p;
        return $best;
    };
    $lbl = fn($p) => qn($topics[$p['y']]) . ' در سرویس ' . qn($cols[$p['x']]);
    $out = [];
    $a = $pick(fn($p) => $p['c'] >= 0.5 * $maxC && $p['v'] >= 0.5 * $maxV, 'v');
    if ($a) $out[] = 'حباب‌های بزرگ و پررنگ یعنی هم تولید زیاد و هم بازدید زیاد، مثل ' . $lbl($a) . ' (' . nfa($a['c']) . ' خبر، ' . nfa($a['v']) . ' بازدید).';
    $b = $pick(fn($p) => $p['c'] >= 0.5 * $maxC && $p['v'] < 0.35 * $maxV, 'c');
    if ($b) $out[] = 'حباب بزرگ ولی کم‌رنگ یعنی تولید زیاد با بازدید نسبتاً کم، مثل ' . $lbl($b) . ' (' . nfa($b['c']) . ' خبر، ' . nfa($b['v']) . ' بازدید).';
    $c = $pick(fn($p) => $p['c'] <= 0.3 * $maxC && $p['v'] >= 0.5 * $maxV, 'v');
    if ($c) $out[] = 'حباب کوچک ولی پررنگ یعنی با خبر کم بازدید خوب گرفته شده، مثل ' . $lbl($c) . ' (' . nfa($c['c']) . ' خبر، ' . nfa($c['v']) . ' بازدید).';
    if (!$out) $out[] = 'تفاوت چشمگیری میان حباب‌ها دیده نمی‌شود؛ تعداد خبر و بازدید در سرویس‌ها نسبتاً متناسب است.';
    return $out;
}
function narrMxType(array $mt, array $cols): array
{
    if (!$mt || !$cols) return [];
    $top = topKeysBy($mt, 'count');
    $b = bestCell($mt, $top, $cols, 'count');
    $out = [];
    if ($b) $out[] = 'بیشترین تعداد خبر در تلاقی ' . qn($b['t']) . ' و نوع خبر ' . qn($b['c']) . ' (' . nfa($b['v']) . ' خبر) است.';
    $tc = []; $all = 0;
    foreach ($mt as $cells) foreach ($cells as $c => $cell) { $tc[$c] = ($tc[$c] ?? 0) + $cell['count']; $all += $cell['count']; }
    if ($tc && $all > 0) { arsort($tc); $k = array_key_first($tc); $out[] = 'در مجموع، نوع ' . qn($k) . ' با ' . round($tc[$k] / $all * 100) . '٪ رایج‌ترین قالب خبری موضوع‌دار است.'; }
    $cn = concentratedTopic($mt, $cols);
    if ($cn) $out[] = round($cn['sh'] * 100) . '٪ اخبار ' . qn($cn['t']) . ' از نوع ' . qn($cn['c']) . ' است؛ یعنی این موضوع قالب خبری مشخصی دارد.';
    return $out;
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">گزارش پربازدیدترین موضوعات</h5>
    <a href="topic_evaluation.php?<?= htmlspecialchars(http_build_query(['from' => $from, 'to' => $to, 'service' => $service, 'ranges' => $selectedRanges, 'filtered' => 1])) ?>" class="btn btn-outline-secondary btn-sm">بازگشت به ارزیابی موضوعی</a>
  </div>
  <form method="get" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">سرویس</label>
      <select class="form-select" name="service">
        <option value="">همه سرویس‌ها</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= htmlspecialchars($s) ?>" <?= $service === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">از تاریخ</label>
      <input type="text" class="form-control jalali-date-input" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">تا تاریخ</label>
      <input type="text" class="form-control jalali-date-input" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-12">
      <label class="form-label d-block">محدوده بازدید <span class="text-muted small">(بدون انتخاب = همه محدوده‌ها؛ روی کل گزارش اعمال می‌شود)</span></label>
      <?php foreach (viewsBuckets() as $b): ?>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="ranges[]" id="rb_<?= $b['key'] ?>"
                 value="<?= $b['key'] ?>" <?= in_array($b['key'], $selectedRanges, true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="rb_<?= $b['key'] ?>"><?= htmlspecialchars($b['label']) ?></label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary w-100">نمایش گزارش</button>
    </div>
  </form>
</div>

<div class="card shadow-sm p-4">
  <h6 class="mb-3">
    موضوعات پربازدید
    <span class="text-muted ms-1" style="cursor:help" data-bs-toggle="tooltip" data-bs-placement="bottom"
          title="مجموع بازدید خبرهای ثبت‌شده برای هر موضوع در بازه و سرویس انتخابی (اخبار بالای ۱۰۰۰ بازدید، در محدوده‌های بازدید انتخابی)؛ خبر چندموضوعی برای هر موضوع جداگانه حساب می‌شود.">ⓘ</span>
  </h6>
  <?php if ($n === 0): ?>
    <div class="text-muted text-center py-4">در این بازه موضوعی ثبت نشده است.</div>
  <?php else: ?>
    <div class="text-end mb-1"><?php pngBtn('topicChart', 'نمودار_پربازدیدترین_موضوعات'); ?></div>
    <div style="position:relative; height:<?= max(260, $n * 34 + 60) ?>px"><canvas id="topicChart"></canvas></div>
    <?php if ($untagged > 0): ?>
      <p class="text-muted small mt-3 mb-0"><?= number_format($untagged) ?> خبر در این بازه هنوز موضوعی ثبت نشده و در نمودار لحاظ نشده است.</p>
    <?php endif; ?>
    <?php narrBox(narrBar($allTopics)); ?>

    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
      <h6 class="mb-0">جدول موضوعات</h6>
      <a class="btn btn-sm btn-outline-success d-inline-flex align-items-center p-1" title="خروجی اکسل"
         href="topic_report.php?<?= htmlspecialchars(http_build_query(['from' => $from, 'to' => $to, 'service' => $service, 'ranges' => $selectedRanges, 'export' => 1])) ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="2" width="18" height="20" rx="2.5" fill="#1d6f42"/><path d="M8 7.5l8 9M16 7.5l-8 9" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
      </a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered table-hover align-middle sortable-table">
        <thead><tr><th>#</th><th>موضوع</th><th>تعداد خبر</th><th>مجموع بازدید</th><th>میانگین بازدید</th></tr></thead>
        <tbody>
          <?php $i = 0; foreach ($allTopics as $name => $a): $i++; ?>
            <tr>
              <td><?= $i ?></td>
              <td><?= htmlspecialchars($name) ?></td>
              <td><?= number_format($a['count']) ?></td>
              <td><?= number_format($a['views']) ?></td>
              <td><?= number_format((int)round($a['views'] / max(1, $a['count']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <?php $sumC = array_sum(array_column($allTopics, 'count')); $sumV = array_sum(array_column($allTopics, 'views')); ?>
        <tfoot class="table-light fw-bold">
          <tr><td></td><td>جمع</td><td><?= number_format($sumC) ?></td><td><?= number_format($sumV) ?></td><td><?= number_format((int)round($sumV / max(1, $sumC))) ?></td></tr>
        </tfoot>
      </table>
    </div>
    <?php narrBox(narrTable($allTopics)); ?>

    <?php if ($typeCols): ?>
      <div class="d-flex justify-content-between align-items-center mt-4 mb-1">
        <h6 class="mb-0">اخبار اختصاصی و غیراختصاصی به تفکیک موضوع</h6>
        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#exModal">⚙ تعیین انواع غیراختصاصی</button>
      </div>
      <p class="text-muted small mb-2">غیراختصاصی: <?= $nonEx ? htmlspecialchars(implode('، ', $nonEx)) : '— (هیچ‌کدام)' ?> | سایر انواع خبر اختصاصی محسوب می‌شوند.</p>
      <div class="table-responsive">
        <table class="table table-sm table-bordered text-center align-middle mb-0">
          <thead style="background:#4f81bd;color:#fff"><tr><th class="text-white" style="background:#4f81bd">موضوع</th><th class="text-white" style="background:#4f81bd">اختصاصی</th><th class="text-white" style="background:#4f81bd">غیراختصاصی</th><th class="text-white" style="background:#4f81bd">جمع</th></tr></thead>
          <tbody>
            <?php foreach ($exRows as $t => [$e, $ne]): ?>
              <tr><th class="fw-bold"><?= htmlspecialchars($t) ?></th><td><?= number_format($e) ?></td><td><?= number_format($ne) ?></td><td class="fw-bold"><?= number_format($e + $ne) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light fw-bold"><tr><td>جمع</td><td><?= number_format($exSumE) ?></td><td><?= number_format($exSumN) ?></td><td><?= number_format($exSumE + $exSumN) ?></td></tr></tfoot>
        </table>
      </div>
      <?php
        $exAll = max(1, $exSumE + $exSumN);
        $bestEx = null; $bestNe = null;
        foreach ($exRows as $t => [$e, $ne]) {
            if (($bestEx === null || $e > $exRows[$bestEx][0])) $bestEx = $t;
            if (($bestNe === null || $ne > $exRows[$bestNe][1])) $bestNe = $t;
        }
        narrBox([
            '<strong>جمع‌بندی:</strong> از ' . nfa($exSumE + $exSumN) . ' خبر موضوع‌دار، ' . nfa($exSumE) . ' خبر (' . round($exSumE / $exAll * 100) . '٪) اختصاصی و ' . nfa($exSumN) . ' خبر (' . round($exSumN / $exAll * 100) . '٪) غیراختصاصی است.',
            $bestEx !== null ? 'بیشترین خبر اختصاصی در موضوع ' . qn($bestEx) . ' (' . nfa($exRows[$bestEx][0]) . ') و بیشترین خبر غیراختصاصی در موضوع ' . qn($bestNe) . ' (' . nfa($exRows[$bestNe][1]) . ') است.' : '',
        ]);
      ?>

      <div class="modal fade" id="exModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form method="get" class="modal-content">
            <div class="modal-header"><h6 class="modal-title">انواع خبر غیراختصاصی</h6><button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <p class="text-muted small">انواع خبر موجود در بازهٔ انتخابی؛ موارد تیک‌خورده «غیراختصاصی» و بقیه «اختصاصی» حساب می‌شوند.</p>
              <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
              <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
              <input type="hidden" name="service" value="<?= htmlspecialchars($service) ?>">
              <?php foreach ($selectedRanges as $rg): ?><input type="hidden" name="ranges[]" value="<?= htmlspecialchars($rg) ?>"><?php endforeach; ?>
              <input type="hidden" name="nonex_set" value="1">
              <?php foreach ($typeCols as $i => $tc): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="nonex[]" id="nx_<?= $i ?>" value="<?= htmlspecialchars($tc) ?>" <?= in_array($tc, $nonEx, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="nx_<?= $i ?>"><?= htmlspecialchars($tc) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">اعمال</button><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">انصراف</button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($svcCols): ?>
      <div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">ماتریس موضوع × سرویس: تعداد خبر</h6><?php mxXlsxBtn('mx_svc_count', $from, $to, $service, $selectedRanges); ?></div>
      <p class="text-muted small mb-2">هر چه سبز پررنگ‌تر، تعداد خبر بیشتر (۲۰ موضوع پرخبر؛ همهٔ سرویس‌ها، مستقل از فیلتر سرویس).</p>
      <?php matrixRender($mx, $svcCols, 'count'); narrBox(narrMxCount($mx, $svcCols)); ?>

      <?php if ($barSvc['series']): ?>
        <div class="d-flex justify-content-between align-items-center mt-3 mb-1"><h6 class="mb-0">نمودار ستونی موضوع × سرویس (۱۰ موضوع پرخبر)</h6><?php pngBtn('svcBar', 'نمودار_ستونی_موضوع_سرویس'); ?></div>
        <div style="position:relative;height:340px"><canvas id="svcBar"></canvas></div>
        <?php narrBox(array_merge(['<strong>جمع‌بندی:</strong>'], barSummary($mx, $svcCols, 'سرویس'))); ?>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">ماتریس موضوع × سرویس: مجموع بازدید</h6><?php mxXlsxBtn('mx_svc_views', $from, $to, $service, $selectedRanges); ?></div>
      <p class="text-muted small mb-2">هر چه سبز پررنگ‌تر، مجموع بازدید بیشتر (۲۰ موضوع پربازدید؛ همهٔ سرویس‌ها، مستقل از فیلتر سرویس).</p>
      <?php matrixRender($mx, $svcCols, 'views'); narrBox(narrMxViews($mx, $svcCols)); ?>

      <div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">نمودار حبابی موضوع × سرویس</h6><?php pngBtn('bubbleChart', 'نمودار_حبابی_موضوع_سرویس'); ?></div>
      <p class="text-muted small mb-2">اندازهٔ حباب = تعداد خبر، رنگ سبز (پررنگ = بازدید بیشتر) = مجموع بازدید (۲۰ موضوع پربازدید).</p>
      <div style="position:relative; height:<?= max(300, count($bTopics) * 34 + 120) ?>px"><canvas id="bubbleChart"></canvas></div>
      <?php narrBox(narrBubble($bPoints, $bTopics, $svcCols, (int)$bMaxC, (int)$bMaxV)); ?>

      <div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">ماتریس موضوع × نوع خبر: تعداد خبر</h6><?php mxXlsxBtn('mx_type_count', $from, $to, $service, $selectedRanges); ?></div>
      <p class="text-muted small mb-2">هر چه سبز پررنگ‌تر، تعداد خبر بیشتر (۲۰ موضوع پرخبر؛ همهٔ سرویس‌ها، مستقل از فیلتر سرویس). با نگه داشتن ماوس روی هر خانه، مجموع بازدید هم دیده می‌شود.</p>
      <?php matrixRender($mt, $typeCols, 'count'); narrBox(narrMxType($mt, $typeCols)); ?>

      <?php if ($barType['series']): ?>
        <div class="d-flex justify-content-between align-items-center mt-3 mb-1"><h6 class="mb-0">نمودار ستونی موضوع × نوع خبر (۱۰ موضوع پرخبر)</h6><?php pngBtn('typeBar', 'نمودار_ستونی_موضوع_نوع_خبر'); ?></div>
        <div style="position:relative;height:340px"><canvas id="typeBar"></canvas></div>
        <?php narrBox(array_merge(['<strong>جمع‌بندی:</strong>'], barSummary($mt, $typeCols, 'نوع'))); ?>
      <?php endif; ?>

      <?php if ($rangeCols): ?>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">ماتریس موضوع × محدوده بازدید: تعداد خبر</h6><?php mxXlsxBtn('mx_range_count', $from, $to, $service, $selectedRanges); ?></div>
        <p class="text-muted small mb-2">هر چه سبز پررنگ‌تر، تعداد خبر بیشتر (۲۰ موضوع پرخبر؛ همهٔ سرویس‌ها). با نگه داشتن ماوس روی هر خانه، مجموع بازدید هم دیده می‌شود.</p>
        <?php matrixRender($mr, $rangeCols, 'count'); ?>
        <?php if ($barRange['series']): ?>
          <div class="d-flex justify-content-between align-items-center mt-3 mb-1"><h6 class="mb-0">نمودار ستونی موضوع × محدوده بازدید (۱۰ موضوع پرخبر)</h6><?php pngBtn('rangeBar', 'نمودار_ستونی_موضوع_محدوده_بازدید'); ?></div>
          <div style="position:relative;height:340px"><canvas id="rangeBar"></canvas></div>
          <?php narrBox(array_merge(['<strong>جمع‌بندی:</strong>'], barSummary($mr, $rangeCols, 'محدودهٔ'))); ?>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
document.querySelectorAll('[data-png]').forEach(function (b) {
  b.addEventListener('click', function () {
    var src = document.getElementById(b.dataset.png); if (!src) return;
    var c = document.createElement('canvas'); c.width = src.width; c.height = src.height;
    var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(src, 0, 0);
    var a = document.createElement('a'); a.href = c.toDataURL('image/png');
    a.download = b.dataset.name + '_<?= str_replace('/', '-', $from) ?>_<?= str_replace('/', '-', $to) ?>.png'; a.click();
  });
});
(function () {
  var fa = function (v) { return Number(v).toLocaleString('fa-IR'); };
  var pal = ['#2e7d32','#1976d2','#f9a825','#c62828','#6a1b9a','#00838f','#9e9e9e'];
  function stacked(id, d) {
    var el = document.getElementById(id); if (!el || !d.series.length) return;
    new Chart(el, {
      type: 'bar',
      data: { labels: d.labels, datasets: d.series.map(function (s, i) { return { label: s.label, data: s.data, backgroundColor: s.label === 'سایر' ? '#9e9e9e' : pal[i % pal.length] }; }) },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + fa(c.raw) + ' خبر'; } } } },
        scales: { x: { stacked: true, ticks: { autoSkip: false, maxRotation: 45 } }, y: { stacked: true, ticks: { callback: fa } } } }
    });
  }
  stacked('svcBar', <?= json_encode($barSvc, JSON_UNESCAPED_UNICODE) ?>);
  stacked('rangeBar', <?= json_encode($barRange, JSON_UNESCAPED_UNICODE) ?>);
  stacked('typeBar', <?= json_encode($barType, JSON_UNESCAPED_UNICODE) ?>);
})();
<?php if ($n > 0 && $bPoints): ?>
(function () {
  var topics = <?= json_encode($bTopics, JSON_UNESCAPED_UNICODE) ?>;
  var svcs   = <?= json_encode($svcCols, JSON_UNESCAPED_UNICODE) ?>;
  var pts    = <?= json_encode($bPoints) ?>;
  var maxV = <?= (int)$bMaxV ?>, maxC = <?= (int)$bMaxC ?>;
  var fa = function (v) { return Number(v).toLocaleString('fa-IR'); };
  var data = pts.map(function (p) {
    return { x: p.x, y: p.y, r: 4 + 16 * Math.sqrt(p.c / maxC), c: p.c, v: p.v };
  });
  var colors = pts.map(function (p) { return 'hsl(142,60%,' + (80 - Math.round((p.v / maxV) * 55)) + '%)'; });
  new Chart(document.getElementById('bubbleChart'), {
    type: 'bubble',
    data: { datasets: [{ data: data, backgroundColor: colors, borderColor: 'hsl(142,60%,22%)', borderWidth: 1 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: {
          title: function (c) { var d = c[0].raw; return topics[d.y] + ' — ' + svcs[d.x]; },
          label: function (c) { return ['تعداد خبر: ' + fa(c.raw.c), 'مجموع بازدید: ' + fa(c.raw.v)]; }
        } }
      },
      scales: {
        x: { min: -0.6, max: svcs.length - 0.4, ticks: { stepSize: 1, callback: function (v) { return svcs[v] || ''; } }, grid: { color: 'rgba(0,0,0,.06)' } },
        y: { reverse: true, min: -0.6, max: topics.length - 0.4, ticks: { stepSize: 1, autoSkip: false, callback: function (v) { return topics[v] || ''; } }, grid: { color: 'rgba(0,0,0,.06)' } }
      }
    }
  });
})();
<?php endif; ?>
<?php if ($n > 0): ?>
(function () {
  var labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  var views  = <?= json_encode($views) ?>;
  var counts = <?= json_encode($counts) ?>;
  var colors = <?= json_encode($colors) ?>;
  var fa = function (v) { return Number(v).toLocaleString('fa-IR'); };
  new Chart(document.getElementById('topicChart'), {
    type: 'bar',
    data: { labels: labels, datasets: [{ data: views, backgroundColor: colors, borderRadius: 4 }] },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: function (c) { return 'مجموع بازدید: ' + fa(c.raw) + ' | تعداد خبر: ' + fa(counts[c.dataIndex]); } } }
      },
      scales: { x: { ticks: { callback: fa } }, y: { ticks: { autoSkip: false } } }
    }
  });
})();
<?php endif; ?>
</script>
