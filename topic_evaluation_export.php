<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/topics.php';
require_once __DIR__ . '/includes/xlsx.php';
requireLoginPage();

$from = normalizeJalaliDate($_GET['from'] ?? '') ?? '';
$to   = normalizeJalaliDate($_GET['to'] ?? '') ?? '';
$service = trim($_GET['service'] ?? '');
$ranges = array_values(array_intersect(
    (array)($_GET['ranges'] ?? []),
    array_column(viewsBuckets(), 'key')
));

if ($from === '' || $to === '') { die('پارامترهای تاریخ ناقص است.'); }

$rows = topicEvaluationRows($from, $to, $service, $ranges);

$headers = ['ردیف', 'تاریخ', 'تیتر', 'سرویس', 'زیرسرویس', 'نوع خبر', 'موضوع', 'وضعیت ثبت', 'ساعت انتشار', 'بازدید', 'محدوده بازدید'];
$dataRows = [];
foreach ($rows as $i => $r) {
    $dataRows[] = [
        $i + 1,
        $r['date'] ?? '',
        $r['title'] ?? '',
        $r['service_main'] ?? '',
        $r['service_sub'] ?? '',
        $r['news_type'] ?? '',
        $r['__topics'] ? implode('، ', $r['__topics']) : '',
        $r['__topics'] ? 'ثبت شده' : 'ثبت نشده',
        $r['pub_time'] ?? '',
        (int)$r['__views'],
        $r['__bucket'],
    ];
}

$cfg = appConfig();
if (!is_dir($cfg['storage_tmp'])) mkdir($cfg['storage_tmp'], 0775, true);
$tmpFile = $cfg['storage_tmp'] . '/topic_eval_export_' . uniqid() . '.xlsx';

try {
    xlsxWriteSimple($tmpFile, $headers, $dataRows, 'ارزیابی موضوعی');
} catch (Throwable $e) {
    die('خطا در ساخت فایل خروجی: ' . htmlspecialchars($e->getMessage()));
}

$filename = 'ارزیابی_موضوعی_' . str_replace('/', '-', $from) . '_' . str_replace('/', '-', $to) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
