<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();

if (empty($_SESSION['pending_upload'])) { header('Location: upload.php'); exit; }
$p = $_SESSION['pending_upload'];

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4">
  <h5 class="mb-3">تأیید تاریخ فایل: <?= htmlspecialchars($p['original_name']) ?></h5>
  <p class="text-muted">سامانه با بررسی ستون تاریخ در فایل، بازه زیر را تشخیص داد؛ تاریخ هر خبر از ستون تاریخ خودش خوانده می‌شود، حتی اگر فایل بیش از یک روز را در بر بگیرد.</p>
  <p>
    <?php if ($p['date_from'] === $p['date_to']): ?>
      تاریخ فایل: <strong><?= htmlspecialchars(jalaliDateLabel($p['date_from'])) ?></strong>
    <?php else: ?>
      بازه فایل: <strong><?= htmlspecialchars(jalaliDateLabel($p['date_from'])) ?></strong> تا <strong><?= htmlspecialchars(jalaliDateLabel($p['date_to'])) ?></strong>
    <?php endif; ?>
  </p>
  <p>تعداد ردیف‌های خوانده‌شده: <strong><?= (int)$p['row_count'] ?></strong></p>
  <form method="post" action="upload_finalize.php" class="row g-3">
    <div class="col-md-5">
      <label class="form-label">تاریخ پیش‌فرض (فقط برای ردیف‌هایی که ستون تاریخشان خالی/نامعتبر است)</label>
      <input type="text" name="confirmed_date" class="form-control jalali-date-input" value="<?= htmlspecialchars($p['detected_date']) ?>">
    </div>
    <div class="col-12">
      <button class="btn btn-success">تأیید و ذخیره</button>
      <a href="upload.php" class="btn btn-outline-secondary">انصراف</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
