<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginApi();

header('Content-Type: application/json; charset=utf-8');

$date = normalizeJalaliDate($_GET['date'] ?? '') ?? '';
$code = normalizeDigits(trim($_GET['code'] ?? ''));

if ($code === '') {
    echo json_encode(['ok' => false, 'msg' => 'کد خبر وارد نشده است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$excelRow = $date !== '' ? excelRowFind($date, $code) : null;
$fallback = isnaInfoFromId($code);

$out = [
    'ok'              => true,
    'found_in_excel'  => $excelRow !== null,
    'reporter'        => $excelRow['reporter'] ?? '',
    'publisher'       => $excelRow['publisher'] ?? '',
    'title'           => $excelRow['title'] ?? '',
    'news_type'       => $excelRow['news_type'] ?? '',
    'service_main'    => $excelRow['service_main'] ?? '',
    'service_sub'     => $excelRow['service_sub'] ?? '',
    'news_link'       => ($excelRow['news_link'] ?? '') ?: $fallback['link'],
    'source'          => $excelRow['source'] ?? '',
];

echo json_encode($out, JSON_UNESCAPED_UNICODE);
