<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/xlsx.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();
$cfg = appConfig();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$file = excelFileGet($id);
if (!$file) { die('فایل پیدا نشد.'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newDate = normalizeJalaliDate($_POST['report_date'] ?? '') ?: $file['report_date'];

    if (!empty($_FILES['new_file']['name'])) {
        $f = $_FILES['new_file'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx'], true)) {
            $error = 'فقط فایل اکسل مجاز است.';
        } else {
            if (!is_dir($cfg['storage_archive'])) mkdir($cfg['storage_archive'], 0775, true);
            $newPath = $cfg['storage_archive'] . '/' . date('Ymd_His') . '_' . basename($f['name']);
            move_uploaded_file($f['tmp_name'], $newPath);
            try {
                $parsed = parseExcelRows($newPath);
                $rows = fillMissingRowDates($parsed['rows'], $newDate);
                $range = dateRangeFromCounts(array_count_values(array_column($rows, 'date')));
                excelRowsDeleteByFile($id);
                excelFileUpdate($id, [
                    'original_name' => $f['name'],
                    'stored_path'   => $newPath,
                    'report_date'   => $newDate,
                    'detected_date' => detectedDateFromCounts($parsed['dateCounts']) ?? $newDate,
                    'date_from'     => $range['from'] ?? $newDate,
                    'date_to'       => $range['to'] ?? $newDate,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                excelRowsInsertMany($id, $rows);
                header('Location: upload.php?ok=1'); exit;
            } catch (Throwable $e) {
                $error = 'خطا: ' . $e->getMessage();
            }
        }
    } else {
        excelFileUpdate($id, ['report_date' => $newDate, 'updated_at' => date('Y-m-d H:i:s')]);
        header('Location: upload.php?ok=1'); exit;
    }
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4">
  <h5 class="mb-3">ویرایش فایل: <?= htmlspecialchars($file['original_name']) ?></h5>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="id" value="<?= (int)$file['id'] ?>">
    <div class="col-md-4">
      <label class="form-label">تاریخ پیش‌فرض فایل</label>
      <input class="form-control jalali-date-input" name="report_date" value="<?= htmlspecialchars($file['report_date']) ?>">
      <?php $df = $file['date_from'] ?? $file['report_date']; $dt = $file['date_to'] ?? $file['report_date']; if ($df !== $dt): ?>
        <div class="form-text">بازه واقعی ردیف‌های این فایل: <?= htmlspecialchars(jalaliDateLabel($df)) ?> تا <?= htmlspecialchars(jalaliDateLabel($dt)) ?> (این فقط برای ردیف‌های بدون تاریخ معتبر استفاده می‌شود؛ با آپلود فایل جدید در همین صفحه دوباره محاسبه می‌شود)</div>
      <?php endif; ?>
    </div>
    <div class="col-md-8">
      <label class="form-label">جایگزینی فایل اکسل (اختیاری)</label>
      <input type="file" class="form-control" name="new_file" accept=".xlsx">
    </div>
    <div class="col-12">
      <button class="btn btn-success">ذخیره</button>
      <a href="upload.php" class="btn btn-outline-secondary">انصراف</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
