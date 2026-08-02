<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginApi();

header('Content-Type: application/json; charset=utf-8');
$date = normalizeJalaliDate($_GET['date'] ?? '') ?? '';
$rows = $date !== '' ? newsEntriesByDate($date) : [];
echo json_encode(['ok'=>true, 'rows'=>$rows], JSON_UNESCAPED_UNICODE);
