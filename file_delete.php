<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    excelFileUpdate($id, ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')]);
}
header('Location: upload.php?ok=1');
