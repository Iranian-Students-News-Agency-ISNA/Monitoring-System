<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/xlsx.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();

if (empty($_SESSION['pending_upload'])) { header('Location: upload.php'); exit; }
$p = $_SESSION['pending_upload'];
$cfg = appConfig();

$confirmedDate = normalizeJalaliDate($_POST['confirmed_date'] ?? '') ?: $p['detected_date'];

if (!is_dir($cfg['storage_archive'])) mkdir($cfg['storage_archive'], 0775, true);
$finalPath = $cfg['storage_archive'] . '/' . date('Ymd_His') . '_' . basename($p['tmp_path']);
rename($p['tmp_path'], $finalPath);

try {
    $parsed = parseExcelRows($finalPath);
    $rows = fillMissingRowDates($parsed['rows'], $confirmedDate);
    $range = dateRangeFromCounts(array_count_values(array_column($rows, 'date')));

    $fileId = excelFileInsert([
        'original_name' => $p['original_name'],
        'stored_path'   => $finalPath,
        'report_date'   => $confirmedDate,
        'detected_date' => $p['detected_date'],
        'date_from'     => $range['from'] ?? $confirmedDate,
        'date_to'       => $range['to'] ?? $confirmedDate,
        'status'        => 'active',
        'uploaded_at'   => date('Y-m-d H:i:s'),
        'updated_at'    => null,
    ]);

    excelRowsInsertMany($fileId, $rows);
} catch (Throwable $e) {
    die('خطا در ذخیره‌سازی: ' . htmlspecialchars($e->getMessage()));
}

unset($_SESSION['pending_upload']);
header('Location: upload.php?ok=1');
