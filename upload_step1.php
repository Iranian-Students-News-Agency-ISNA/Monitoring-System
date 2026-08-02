<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/jsondb.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();

$cfg = appConfig();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['excel_file'])) {
    header('Location: upload.php'); exit;
}
$f = $_FILES['excel_file'];
if ($f['error'] !== UPLOAD_ERR_OK) { die('خطا در آپلود فایل.'); }

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx'], true)) { die('فقط فایل اکسل با فرمت xlsx مجاز است (فرمت قدیمی xls پشتیبانی نمی‌شود).'); }

if (!is_dir($cfg['storage_tmp'])) mkdir($cfg['storage_tmp'], 0775, true);
$tmpPath = $cfg['storage_tmp'] . '/' . uniqid('up_') . '.' . $ext;
move_uploaded_file($f['tmp_name'], $tmpPath);

require_once __DIR__ . '/includes/xlsx.php';

try {
    $parsed = parseExcelRows($tmpPath);
} catch (Throwable $e) {
    unlink($tmpPath);
    die('خطا در خواندن فایل: ' . htmlspecialchars($e->getMessage()));
}
$detected = detectedDateFromCounts($parsed['dateCounts']) ?? todayJalali();
$range = dateRangeFromCounts($parsed['dateCounts']);

$_SESSION['pending_upload'] = [
    'tmp_path'      => $tmpPath,
    'original_name' => $f['name'],
    'detected_date' => $detected,
    'date_from'     => $range['from'] ?? $detected,
    'date_to'       => $range['to'] ?? $detected,
    'row_count'     => count($parsed['rows']),
];
header('Location: upload_confirm.php');
