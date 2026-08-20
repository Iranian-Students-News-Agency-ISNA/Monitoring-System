<?php
// لایه داده و منطق برای بخش «مدیریت کارها» (شبیه ترلو)
// ذخیره‌سازی روی storage/data/tasks.json با همان الگوی jsondb.php پروژه

require_once __DIR__ . '/jsondb.php';

function tasksDefaultColumns(): array
{
    return [
        ['id' => 'todo',  'title' => 'برای انجام',   'tasks' => []],
        ['id' => 'doing', 'title' => 'در حال انجام', 'tasks' => []],
        ['id' => 'review','title' => 'بررسی',        'tasks' => []],
        ['id' => 'done',  'title' => 'انجام‌شده',     'tasks' => []],
    ];
}

// نگاشت یک تسک قدیمی/جدید به فرمت یکسان (سازگاری با داده‌های قبلی که assignee تکی داشتند)
function taskNormalize(array $t): array
{
    if (!isset($t['assignees']) || !is_array($t['assignees'])) {
        $t['assignees'] = !empty($t['assignee']) ? [$t['assignee']] : [];
    }
    unset($t['assignee']);
    if (!isset($t['created_by'])) $t['created_by'] = null;
    if (!isset($t['done_note'])) $t['done_note'] = '';
    if (!isset($t['done_at'])) $t['done_at'] = null;
    return $t;
}

function tasksBoard(): array
{
    $data = jsonRead('tasks');
    if (empty($data['columns'])) {
        $data = ['columns' => tasksDefaultColumns(), 'next_id' => 1];
        jsonUpdate('tasks', function () use ($data) { return $data; });
    }
    if (!isset($data['next_id'])) $data['next_id'] = 1;
    foreach ($data['columns'] as &$col) {
        foreach ($col['tasks'] as &$t) { $t = taskNormalize($t); }
    }
    return $data;
}

function taskCreate(string $title, string $desc, array $assignees, string $priority, ?string $due, string $columnId, ?string $createdBy): array
{
    $result = jsonUpdate('tasks', function ($data) use ($title, $desc, $assignees, $priority, $due, $columnId, $createdBy, &$newTask) {
        if (empty($data['columns'])) $data = ['columns' => tasksDefaultColumns(), 'next_id' => 1];
        $id = (int)($data['next_id'] ?? 1);
        $newTask = [
            'id' => $id,
            'title' => $title,
            'desc' => $desc,
            'assignees' => array_values(array_filter($assignees)),
            'priority' => $priority, // low | medium | high
            'due' => $due,
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
            'done_note' => '',
            'done_at' => null,
        ];
        $found = false;
        foreach ($data['columns'] as &$col) {
            if ($col['id'] === $columnId) { $col['tasks'][] = $newTask; $found = true; break; }
        }
        if (!$found) { $data['columns'][0]['tasks'][] = $newTask; }
        $data['next_id'] = $id + 1;
        return $data;
    });
    return taskNormalize($newTask);
}

function taskFindColumnIndex(array $data, int $taskId): array
{
    foreach ($data['columns'] as $ci => $col) {
        foreach ($col['tasks'] as $ti => $t) {
            if ((int)$t['id'] === $taskId) return [$ci, $ti];
        }
    }
    return [-1, -1];
}

function taskMove(int $taskId, string $toColumnId, int $position = -1): bool
{
    $ok = false;
    jsonUpdate('tasks', function ($data) use ($taskId, $toColumnId, $position, &$ok) {
        [$ci, $ti] = taskFindColumnIndex($data, $taskId);
        if ($ci === -1) return $data;
        $task = $data['columns'][$ci]['tasks'][$ti];
        array_splice($data['columns'][$ci]['tasks'], $ti, 1);
        foreach ($data['columns'] as $cj => &$col) {
            if ($col['id'] === $toColumnId) {
                if ($position < 0 || $position > count($col['tasks'])) {
                    $col['tasks'][] = $task;
                } else {
                    array_splice($col['tasks'], $position, 0, [$task]);
                }
                $ok = true;
                break;
            }
        }
        return $data;
    });
    return $ok;
}

function taskUpdate(int $taskId, array $fields): bool
{
    $ok = false;
    jsonUpdate('tasks', function ($data) use ($taskId, $fields, &$ok) {
        [$ci, $ti] = taskFindColumnIndex($data, $taskId);
        if ($ci === -1) return $data;
        foreach (['title', 'desc', 'assignees', 'priority', 'due', 'done_note'] as $f) {
            if (array_key_exists($f, $fields)) $data['columns'][$ci]['tasks'][$ti][$f] = $fields[$f];
        }
        $ok = true;
        return $data;
    });
    return $ok;
}

// علامت‌گذاری به‌عنوان انجام‌شده: انتقال به ستون done + ثبت توضیح انجام‌شده
function taskComplete(int $taskId, string $doneNote): bool
{
    $ok = false;
    jsonUpdate('tasks', function ($data) use ($taskId, $doneNote, &$ok) {
        [$ci, $ti] = taskFindColumnIndex($data, $taskId);
        if ($ci === -1) return $data;
        $task = $data['columns'][$ci]['tasks'][$ti];
        $task['done_note'] = $doneNote;
        $task['done_at'] = date('Y-m-d H:i:s');
        array_splice($data['columns'][$ci]['tasks'], $ti, 1);
        foreach ($data['columns'] as &$col) {
            if ($col['id'] === 'done') { $col['tasks'][] = $task; break; }
        }
        $ok = true;
        return $data;
    });
    return $ok;
}

function taskDelete(int $taskId): bool
{
    $ok = false;
    jsonUpdate('tasks', function ($data) use ($taskId, &$ok) {
        [$ci, $ti] = taskFindColumnIndex($data, $taskId);
        if ($ci === -1) return $data;
        array_splice($data['columns'][$ci]['tasks'], $ti, 1);
        $ok = true;
        return $data;
    });
    return $ok;
}
