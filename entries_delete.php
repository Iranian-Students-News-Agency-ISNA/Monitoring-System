<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginPage();

$id = (int)($_GET['id'] ?? 0);
$row = newsEntryGetById($id);
if ($row) {
    $date = $row['entry_date'];
    newsEntryDeleteById($id);
} else {
    $date = '';
}

$return = $_GET['return'] ?? '';
if ($return === 'file_entry') {
    header('Location: file_entry.php?' . http_build_query([
        'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? '',
        'service' => $_GET['service'] ?? '', 'reporter' => $_GET['reporter'] ?? '',
    ]));
    exit;
}
header('Location: entry.php' . ($date ? ('?date=' . urlencode($date)) : ''));
