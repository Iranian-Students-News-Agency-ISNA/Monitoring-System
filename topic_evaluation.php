<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/topics.php';
requireLoginPage();

$today = todayJalali();
$from = normalizeJalaliDate($_GET['from'] ?? '') ?? $today;
$to   = normalizeJalaliDate($_GET['to'] ?? '') ?? $today;
$service = trim($_GET['service'] ?? '');
$selectedRanges = array_values(array_intersect(
    (array)($_GET['ranges'] ?? []),
    array_column(viewsBuckets(), 'key')
));
$filtered = isset($_GET['filtered']);
$services = excelRowsDistinctServicesInRange($from, $to);

$rows = $filtered ? topicEvaluationRows($from, $to, $service, $selectedRanges) : [];

$backQuery = ['from' => $from, 'to' => $to, 'service' => $service, 'ranges' => $selectedRanges, 'filtered' => 1];

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ارزیابی موضوعی اخبار</h5>
    <a href="topics_manage.php" class="btn btn-outline-secondary btn-sm">مدیریت موضوعات</a>
  </div>
  <form method="get" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">از تاریخ</label>
      <input type="text" class="form-control jalali-date-input" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">تا تاریخ</label>
      <input type="text" class="form-control jalali-date-input" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">سرویس</label>
      <select class="form-select" name="service">
        <option value="">همه سرویس‌ها</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= htmlspecialchars($s) ?>" <?= $service === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label d-block">محدوده بازدید</label>
      <?php foreach (viewsBuckets() as $b): ?>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="ranges[]" id="rb_<?= $b['key'] ?>"
                 value="<?= $b['key'] ?>" <?= in_array($b['key'], $selectedRanges, true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="rb_<?= $b['key'] ?>"><?= htmlspecialchars($b['label']) ?></label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="col-12">
      <input type="hidden" name="filtered" value="1">
      <button class="btn btn-primary">اعمال فیلتر</button>
    </div>
  </form>
</div>

<?php if ($filtered): ?>
<div class="card shadow-sm p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0">نتایج (<?= count($rows) ?> خبر)</h6>
    <a class="btn btn-sm btn-outline-success" href="topic_evaluation_export.php?<?= http_build_query($backQuery) ?>">
      خروجی اکسل
    </a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle sortable-table">
      <thead>
        <tr>
          <th>تیتر</th>
          <th>سرویس</th>
          <th>زیرسرویس</th>
          <th>نوع خبر</th>
          <th>موضوع</th>
          <th>وضعیت ثبت</th>
          <th>ساعت انتشار</th>
          <th>بازدید</th>
          <th>محدوده بازدید</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php $link = trim((string)($r['news_link'] ?? '')); $title = htmlspecialchars($r['title'] ?? '') ?: '(بدون تیتر)'; ?>
              <?= $link !== '' ? '<a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener">' . $title . '</a>' : $title ?>
            </td>
            <td><?= htmlspecialchars($r['service_main'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['service_sub'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['news_type'] ?? '') ?></td>
            <td><?= $r['__topics'] ? htmlspecialchars(implode('، ', $r['__topics'])) : '<span class="text-muted">—</span>' ?></td>
            <td>
              <?= $r['__topics'] ? '<span class="badge text-bg-success">ثبت شده</span>' : '<span class="badge text-bg-secondary">ثبت نشده</span>' ?>
              <?php if (!empty($r['__meta']['updated_by'])): ?>
                <span class="badge text-bg-light text-muted border" title="آخرین ویرایش: <?= htmlspecialchars($r['__meta']['updated_at']) ?>">
                  <?= htmlspecialchars($r['__meta']['updated_by']) ?>
                </span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['pub_time'] ?? '') ?></td>
            <td><?= (int)$r['__views'] ?></td>
            <td><?= htmlspecialchars($r['__bucket']) ?></td>
            <td>
              <a class="btn btn-sm btn-outline-primary"
                 href="topic_evaluation_edit.php?<?= http_build_query(array_merge($backQuery, ['date' => $r['date'], 'code' => $r['code']])) ?>">
                ویرایش موضوع
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">خبری با این فیلترها یافت نشد.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
