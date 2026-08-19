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

function tasksBoard(): array
{
    $data = jsonRead('tasks');
    if (empty($data['columns'])) {
        $data = ['columns' => tasksDefaultColumns(), 'next_id' => 1];
        jsonUpdate('tasks', function () use ($data) { return $data; });
    }
    if (!isset($data['next_id'])) $data['next_id'] = 1;
    return $data;
}

function taskCreate(string $title, string $desc, ?string $assignee, string $priority, ?string $due, string $columnId): array
{
    $result = jsonUpdate('tasks', function ($data) use ($title, $desc, $assignee, $priority, $due, $columnId, &$newTask) {
        if (empty($data['columns'])) $data = ['columns' => tasksDefaultColumns(), 'next_id' => 1];
        $id = (int)($data['next_id'] ?? 1);
        $newTask = [
            'id' => $id,
            'title' => $title,
            'desc' => $desc,
            'assignee' => $assignee,
            'priority' => $priority, // low | medium | high
            'due' => $due,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $found = false;
        foreach ($data['columns'] as &$col) {
            if ($col['id'] === $columnId) { $col['tasks'][] = $newTask; $found = true; break; }
        }
        if (!$found) { $data['columns'][0]['tasks'][] = $newTask; }
        $data['next_id'] = $id + 1;
        return $data;
    });
    return $newTask;
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
        foreach (['title', 'desc', 'assignee', 'priority', 'due'] as $f) {
            if (array_key_exists($f, $fields)) $data['columns'][$ci]['tasks'][$ti][$f] = $fields[$f];
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
