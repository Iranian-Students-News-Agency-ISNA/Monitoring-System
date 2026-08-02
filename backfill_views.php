<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/jsondb.php';
require_once __DIR__ . '/includes/xlsx.php';
requireLoginPage();

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $files = excelFilesAll();
    $viewsMap = []; // "fileId|code" => views
    $filesRead = 0; $filesSkipped = 0;

    foreach ($files as $f) {
        $fileId = (int)($f['id'] ?? 0);
        $path = $f['stored_path'] ?? '';
        if ($fileId <= 0 || $path === '' || !is_file($path)) { $filesSkipped++; continue; }
        try {
            $parsed = parseExcelRows($path);
        } catch (Throwable $e) { $filesSkipped++; continue; }
        foreach ($parsed['rows'] as $r) {
            $code = trim((string)($r['code'] ?? ''));
            if ($code === '') continue;
            $viewsMap[$fileId . '|' . $code] = (int)($r['views'] ?? 0);
        }
        $filesRead++;
    }

    $updated = 0;
    jsonUpdate('excel_rows', function ($rows) use ($viewsMap, &$updated) {
        foreach ($rows as &$r) {
            $fid = (int)($r['file_id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            if ($code === '') continue;
            $key = $fid . '|' . $code;
            if (!isset($viewsMap[$key])) continue;
            $newViews = $viewsMap[$key];
            if ((int)($r['views'] ?? 0) !== $newViews) {
                $r['views'] = $newViews;
                $updated++;
            }
        }
        return $rows;
    });

    $result = "بررسی شد: {$filesRead} فایل آرشیوشده خوانده شد" . ($filesSkipped ? "، {$filesSkipped} فایل قابل‌خواندن نبود" : '') . ". تعداد {$updated} ردیف با مقدار بازدید به‌روزرسانی شد.";
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4">
  <h5 class="mb-3">به‌روزرسانی بازدید داده‌های قدیمی</h5>
  <p class="text-muted small">
    این ابزار فایل‌های اکسل اصلی که قبلاً آپلود و آرشیو شده‌اند را دوباره می‌خواند و فقط مقدار «بازدید» ردیف‌های موجود
    (بر اساس تطبیق کد خبر در همان فایل) را در سامانه به‌روزرسانی می‌کند؛ هیچ ردیف یا فایل جدیدی اضافه نمی‌شود و
    نیازی به آپلود دوباره نیست. اجرای این ابزار بی‌خطر است و می‌توان چند بار آن را اجرا کرد.
  </p>
  <?php if ($result): ?>
    <div class="alert alert-success"><?= htmlspecialchars($result) ?></div>
  <?php endif; ?>
  <form method="post">
    <button class="btn btn-primary">اجرای به‌روزرسانی بازدید</button>
    <a href="evaluation.php" class="btn btn-outline-secondary">بازگشت به ارزیابی</a>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
