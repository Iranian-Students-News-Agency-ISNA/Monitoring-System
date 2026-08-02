<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/docx.php';
require_once __DIR__ . '/includes/narrative.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();

$reporter = trim($_GET['reporter'] ?? '');
$service  = trim($_GET['service'] ?? '');
$singleDate = normalizeJalaliDate($_GET['date'] ?? '') ?? '';
$from = normalizeJalaliDate($_GET['from'] ?? '') ?? $singleDate;
$to   = normalizeJalaliDate($_GET['to'] ?? '') ?? $singleDate;

if ($reporter === '' || $from === '' || $to === '') { die('پارامترها ناقص است (خبرنگار و بازه تاریخ لازم است).'); }
if ($from > $to) { [$from, $to] = [$to, $from]; }

$rows = newsEntriesFilter($reporter, $from, $to, $service);
if (empty($rows)) { die('برای این خبرنگار در این بازه ردیفی ثبت نشده است.'); }

$narrative = buildDailyNarrative($rows, $reporter, $from, $to);

$cfg = appConfig();
if (!is_dir($cfg['storage_tmp'])) mkdir($cfg['storage_tmp'], 0775, true);
$tmpFile = $cfg['storage_tmp'] . '/lecheh_' . uniqid() . '.docx';

try {
    docxWriteSimple($tmpFile, $narrative['paragraphs'], $narrative['table_headers'], $narrative['table_rows']);
} catch (Throwable $e) {
    die('خطا در ساخت فایل Word: ' . htmlspecialchars($e->getMessage()));
}

$rangeLabel = $from === $to ? str_replace('/', '-', $from) : (str_replace('/', '-', $from) . '_تا_' . str_replace('/', '-', $to));
$filename = 'گزارش_' . $reporter . '_' . $rangeLabel . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
