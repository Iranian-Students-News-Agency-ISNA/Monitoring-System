<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tasks.php';
requireLoginApi();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $t = taskCreate(
                trim((string)($_POST['title'] ?? '')),
                trim((string)($_POST['desc'] ?? '')),
                $_POST['assignee'] ?: null,
                $_POST['priority'] ?: 'medium',
                $_POST['due'] ?: null,
                (string)($_POST['column_id'] ?? 'todo')
            );
            echo json_encode(['ok' => true, 'task' => $t], JSON_UNESCAPED_UNICODE);
            break;

        case 'move':
            $ok = taskMove((int)($_POST['task_id'] ?? 0), (string)($_POST['column_id'] ?? ''), (int)($_POST['position'] ?? -1));
            echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
            break;

        case 'update':
            $fields = [];
            foreach (['title', 'desc', 'assignee', 'priority', 'due'] as $f) {
                if (isset($_POST[$f])) $fields[$f] = $_POST[$f];
            }
            $ok = taskUpdate((int)($_POST['task_id'] ?? 0), $fields);
            echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            $ok = taskDelete((int)($_POST['task_id'] ?? 0));
            echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'نامعتبر'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
