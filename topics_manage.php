<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/topics.php';
requireLoginPage();

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $def = trim((string)($_POST['definition'] ?? ''));
        $exists = false;
        foreach (topicsAll() as $t) {
            if (topicsMatchKey((string)$t['name']) === topicsMatchKey($name)) $exists = true;
        }
        if ($name === '') $err = 'نام موضوع را وارد کنید.';
        elseif ($exists) $err = 'این موضوع از قبل وجود دارد.';
        else { topicAdd($name, $def); $msg = 'موضوع «' . $name . '» اضافه شد.'; }
    } elseif ($action === 'delete') {
        $name = trim((string)($_POST['name'] ?? ''));
        if (topicDelete($name)) $msg = 'موضوع «' . $name . '» حذف شد.';
        else $err = 'موضوع پیدا نشد.';
    }
}

$topics = topicsAll();
$usage = topicUsageCounts();

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">مدیریت موضوعات ارزیابی موضوعی</h5>
    <a href="topic_evaluation.php" class="btn btn-outline-secondary btn-sm">بازگشت به ارزیابی موضوعی</a>
  </div>
  <?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post" class="row g-2 align-items-end">
    <input type="hidden" name="action" value="add">
    <div class="col-md-4">
      <label class="form-label">نام موضوع جدید</label>
      <input class="form-control" name="name" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">تعریف (اختیاری)</label>
      <input class="form-control" name="definition">
    </div>
    <div class="col-md-2"><button class="btn btn-success w-100">افزودن</button></div>
  </form>
</div>

<div class="card shadow-sm p-4">
  <div class="text-muted small mb-2">حذف یک موضوع فقط آن را از لیست انتخاب برمی‌دارد؛ ارزیابی‌های قبلاً ثبت‌شده تغییری نمی‌کنند.</div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>#</th><th>موضوع</th><th>تعریف</th><th>تعداد اخبار</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($topics as $i => $t): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td class="small text-muted"><?= nl2br(htmlspecialchars((string)($t['definition'] ?? ''))) ?></td>
          <td><?= (int)($usage[$t['name']] ?? 0) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('موضوع «<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>» حذف شود؟');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="name" value="<?= htmlspecialchars($t['name']) ?>">
              <button class="btn btn-sm btn-outline-danger">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
