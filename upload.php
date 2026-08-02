<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginPage();

$files = excelFilesAll();
usort($files, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
$ok = isset($_GET['ok']);

require __DIR__ . '/includes/layout_top.php';
?>
<?php if ($ok): ?><div class="alert alert-success">عملیات با موفقیت انجام شد.</div><?php endif; ?>

<div class="card shadow-sm p-4 mb-4">
  <h5 class="mb-3">آپلود اکسل روزانه</h5>
  <form method="post" action="upload_step1.php" enctype="multipart/form-data" class="row g-3">
    <div class="col-md-8">
      <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
    </div>
    <div class="col-md-4">
      <button class="btn btn-primary w-100">بارگذاری و بررسی تاریخ</button>
    </div>
  </form>
</div>

<div class="card shadow-sm p-4">
  <h5 class="mb-3">فایل‌های آرشیوشده</h5>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle sortable-table">
      <thead><tr><th>#</th><th>نام فایل</th><th>تاریخ گزارش</th><th>تعداد ردیف</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
        <?php foreach ($files as $i => $f): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($f['original_name'] ?? '') ?></td>
          <td>
            <?php $df = $f['date_from'] ?? $f['report_date'] ?? ''; $dt = $f['date_to'] ?? $f['report_date'] ?? ''; ?>
            <?= $df === $dt || $dt === ''
                ? htmlspecialchars(jalaliDateLabel($f['report_date'] ?? ''))
                : htmlspecialchars(jalaliDateLabel($df)) . ' تا ' . htmlspecialchars(jalaliDateLabel($dt)) ?>
          </td>
          <td><?= excelFileRowCount((int)$f['id']) ?></td>
          <td><?= ($f['status'] ?? 'active') === 'active' ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">حذف‌شده</span>' ?></td>
          <td>
            <a class="btn btn-sm btn-outline-primary" href="file_edit.php?id=<?= (int)$f['id'] ?>">ویرایش</a>
            <?php if (($f['status'] ?? 'active') === 'active'): ?>
            <a class="btn btn-sm btn-outline-danger" href="file_delete.php?id=<?= (int)$f['id'] ?>" onclick="return confirm('این فایل حذف (غیرفعال) شود؟')">حذف</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($files)): ?>
          <tr><td colspan="6" class="text-center text-muted">فایلی آپلود نشده است.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
