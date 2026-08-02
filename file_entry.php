<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginPage();

$from     = normalizeJalaliDate($_GET['from'] ?? '') ?? '';
$to       = normalizeJalaliDate($_GET['to'] ?? '') ?? '';
$service  = trim($_GET['service'] ?? '');
$reporter = trim($_GET['reporter'] ?? '');

$services = ($from !== '' && $to !== '') ? excelRowsDistinctServicesInRange($from, $to) : [];
$reporters = ($from !== '' && $to !== '' && $service !== '') ? excelRowsDistinctReportersInRange($from, $to, $service) : [];

$rows = [];
if ($from !== '' && $to !== '' && $service !== '' && $reporter !== '') {
    $rows = excelRowsInRange($from, $to, $service, $reporter);
    foreach ($rows as &$r) {
        $entry = newsEntryFindByDateAndCode($r['entry_date'], $r['code'] ?? '');
        $r['_entry'] = $entry;
    }
    unset($r);
}

[$defY, $defM, ] = array_map('intval', explode('/', todayJalali()));

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4 mb-4">
  <h5 class="mb-3">ثبت از پرونده</h5>
  <form method="get" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">از تاریخ</label>
      <input type="text" name="from" class="form-control jalali-date-input" data-default-year="<?= $defY ?>" data-default-month="<?= $defM ?>" value="<?= htmlspecialchars($from) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">تا تاریخ</label>
      <input type="text" name="to" class="form-control jalali-date-input" data-default-year="<?= $defY ?>" data-default-month="<?= $defM ?>" value="<?= htmlspecialchars($to) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">سرویس</label>
      <select name="service" class="form-select" <?= empty($services) ? 'disabled' : '' ?> onchange="this.form.submit()">
        <option value="">انتخاب کنید</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= htmlspecialchars($s) ?>" <?= $s === $service ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">خبرنگار</label>
      <select name="reporter" class="form-select" <?= empty($reporters) ? 'disabled' : '' ?> onchange="this.form.submit()">
        <option value="">انتخاب کنید</option>
        <?php foreach ($reporters as $r): ?>
          <option value="<?= htmlspecialchars($r) ?>" <?= $r === $reporter ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">جست‌وجو</button>
      <?php if ($from === '' || $to === ''): ?>
        <span class="text-muted small">ابتدا بازه تاریخ را وارد و جست‌وجو کنید تا سرویس‌ها نمایش داده شود.</span>
      <?php elseif ($service === ''): ?>
        <span class="text-muted small">حالا سرویس را انتخاب کنید.</span>
      <?php elseif ($reporter === ''): ?>
        <span class="text-muted small">حالا خبرنگار را انتخاب کنید.</span>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if ($from !== '' && $to !== '' && $service !== '' && $reporter !== ''): ?>
<div class="card shadow-sm p-4">
  <h6 class="mb-3">خبرهای «<?= htmlspecialchars($reporter) ?>» (<?= count($rows) ?> مورد)</h6>
  <?php if (empty($rows)): ?>
    <div class="alert alert-info">موردی برای این فیلترها پیدا نشد.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th>#</th><th>تاریخ</th><th>ناشر</th><th>تیتر</th><th>سرویس</th><th>زیرسرویس</th>
          <th>سوژه</th><th>نوع خبر/واقعی</th><th>عناصر خبری</th><th>منبع</th><th>توضیح کلی</th><th>لینک</th><th>برچسب</th><th>افزونه</th><th>وضعیت</th><th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r): $e = $r['_entry']; ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($r['entry_date']) ?></td>
          <td><?= htmlspecialchars($r['publisher'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['title'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['service_main'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['service_sub'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['subject'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['news_type'] ?? '') ?><?= !empty($e['real_news_type']) ? ' / '.htmlspecialchars($e['real_news_type']) : '' ?></td>
          <td><?= htmlspecialchars($e['news_elements'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['source'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['description'] ?? '') ?></td>
          <td><?= !empty($r['news_link']) ? '<a href="'.htmlspecialchars($r['news_link']).'" target="_blank">لینک</a>' : '' ?></td>
          <td><?= htmlspecialchars($e['tag'] ?? '') ?></td>
          <td><?= (int)($e['addon_count'] ?? 0) ?></td>
          <td><?= $e ? '<span class="badge text-bg-success">ثبت‌شده</span>' : '<span class="badge text-bg-secondary">ثبت‌نشده</span>' ?></td>
          <td>
            <a class="btn btn-sm btn-outline-primary" href="file_entry_edit.php?<?= http_build_query(['date'=>$r['entry_date'],'code'=>$r['code'],'from'=>$from,'to'=>$to,'service'=>$service,'reporter'=>$reporter]) ?>">ویرایش</a>
            <?php if ($e): ?>
              <a class="btn btn-sm btn-outline-danger" href="entries_delete.php?<?= http_build_query(['id'=>$e['id'],'return'=>'file_entry','from'=>$from,'to'=>$to,'service'=>$service,'reporter'=>$reporter]) ?>" onclick="return confirm('حذف شود؟');">حذف</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
