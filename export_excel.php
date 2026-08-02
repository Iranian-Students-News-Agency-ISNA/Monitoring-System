<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/xlsx.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();

$service  = trim($_GET['service'] ?? '');
$reporter = $_GET['reporter'] ?? '';
$from = normalizeJalaliDate($_GET['from'] ?? '') ?? '';
$to   = normalizeJalaliDate($_GET['to'] ?? '') ?? '';

if ($reporter === '' || $from === '' || $to === '') { die('پارامترها ناقص است.'); }

$rows = newsEntriesFilter($reporter, $from, $to, $service);

$headers = ['تعداد','ناشر','آدرس','تیتر','سرویس','زیرسرویس','سوژه','نوع خبر','نوع واقعی خبر','مصاحبه‌شونده',
            'عناصر خبری','منبع','توضیحات','لینک','تعداد اخبار مرتبط','توضیح اخبار مرتبط','برچسب','توضیح برچسب','تعداد افزونه','توضیح افزونه'];
$dataRows = [];
foreach ($rows as $i => $row) {
    $dataRows[] = [
        $i + 1, $row['publisher'], $row['news_link'], $row['title'],
        $row['service_main'] ?? '', $row['service_sub'] ?? '', $row['subject'],
        $row['news_type'], $row['real_news_type'], $row['interviewee'] ?? '',
        $row['news_elements'], $row['source'] ?? '', $row['description'] ?? '', $row['news_link'],
        (int)($row['related_links_count'] ?? 0), $row['related_links_note'] ?? '',
        $row['tag'], $row['tag_note'] ?? '',
        (int)($row['addon_count'] ?? 0), $row['addon_note'] ?? '',
    ];
}

$cfg = appConfig();
if (!is_dir($cfg['storage_tmp'])) mkdir($cfg['storage_tmp'], 0775, true);
$tmpFile = $cfg['storage_tmp'] . '/export_' . uniqid() . '.xlsx';

try {
    xlsxWriteSimple($tmpFile, $headers, $dataRows, 'گزارش');
} catch (Throwable $e) {
    die('خطا در ساخت فایل خروجی: ' . htmlspecialchars($e->getMessage()));
}

$filename = 'گزارش_' . $reporter . '_' . str_replace('/', '-', $from) . '_' . str_replace('/', '-', $to) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
