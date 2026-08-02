<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginPage();

$service  = trim($_GET['service'] ?? '');
$reporter = trim($_GET['reporter'] ?? '');
$from = normalizeJalaliDate($_GET['from'] ?? '') ?? '';
$to   = normalizeJalaliDate($_GET['to'] ?? '') ?? '';

$services = newsEntriesDistinctServices();
$reporters = $service !== '' ? newsEntriesReportersByService($service) : newsEntriesDistinctReporters();

$rows = [];
if ($reporter !== '' && $from !== '' && $to !== '') {
    $rows = newsEntriesFilter($reporter, $from, $to, $service);
}

$todayY = (int)date('Y'); [$defY, $defM, ] = array_map('intval', explode('/', todayJalali()));

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4 mb-4">
  <h5 class="mb-3">گزارش‌گیری</h5>
  <form method="get" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">سرویس</label>
      <select name="service" class="form-select" onchange="this.form.submit()">
        <option value="">همه سرویس‌ها</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= htmlspecialchars($s) ?>" <?= $s === $service ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">خبرنگار</label>
      <select name="reporter" class="form-select" required>
        <option value="">انتخاب کنید</option>
        <?php foreach ($reporters as $r): ?>
          <option value="<?= htmlspecialchars($r) ?>" <?= $r === $reporter ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">از تاریخ</label>
      <input type="text" name="from" class="form-control jalali-date-input" data-default-year="<?= $defY ?>" data-default-month="<?= $defM ?>" value="<?= htmlspecialchars($from) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">تا تاریخ</label>
      <input type="text" name="to" class="form-control jalali-date-input" data-default-year="<?= $defY ?>" data-default-month="<?= $defM ?>" value="<?= htmlspecialchars($to) ?>" required>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">جست‌وجو</button>
      <?php if ($reporter !== '' && $from !== '' && $to !== ''): ?>
        <a class="btn btn-outline-success" href="export_excel.php?<?= http_build_query(['service'=>$service,'reporter'=>$reporter,'from'=>$from,'to'=>$to]) ?>">خروجی اکسل</a>
        <a class="btn btn-outline-primary" href="export_word.php?<?= http_build_query(['reporter'=>$reporter,'service'=>$service,'from'=>$from,'to'=>$to]) ?>">خروجی Word (گزارش نظارت <?= $from === $to ? 'روزانه' : 'دوره‌ای' ?>)</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if (!empty($rows)): ?>
<div class="card shadow-sm p-4">
  <h6 class="mb-3">نتایج (<?= count($rows) ?> مورد)</h6>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th>تعداد</th><th>ناشر</th><th>آدرس</th><th>تیتر</th><th>سرویس</th><th>زیرسرویس</th>
          <th>سوژه</th><th>نوع خبر</th><th>نوع واقعی خبر</th><th>مصاحبه‌شونده</th><th>عناصر خبری</th>
          <th>لینک</th><th>اخبار مرتبط</th><th>برچسب</th><th>توضیح برچسب</th><th>افزونه</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($r['publisher'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['news_link'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['title'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['service_main'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['service_sub'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['subject'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['news_type'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['real_news_type'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['interviewee'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['news_elements'] ?? '') ?></td>
          <td><?= $r['news_link'] ? '<a href="'.htmlspecialchars($r['news_link']).'" target="_blank">لینک</a>' : '' ?></td>
          <td><?= (int)($r['related_links_count'] ?? 0) ?></td>
          <td><?= htmlspecialchars($r['tag'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['tag_note'] ?? '') ?></td>
          <td><?= (int)($r['addon_count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif ($reporter !== ''): ?>
  <div class="alert alert-info">موردی برای این فیلترها پیدا نشد.</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
