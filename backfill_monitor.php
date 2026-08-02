<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/jsondb.php';
requireLoginPage();

$users = jsonRead('users');
$activeDates = excelFilesActiveDates();

$from = normalizeJalaliDate($_POST['from'] ?? $_GET['from'] ?? '') ?? normalizeJalaliDate($activeDates[0] ?? '') ?? todayJalali();
$to = normalizeJalaliDate($_POST['to'] ?? $_GET['to'] ?? '') ?? $from;
$username = trim($_POST['username'] ?? '');

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $username !== '') {
    if ($from > $to) { [$from, $to] = [$to, $from]; }
    $chosenUser = null;
    foreach ($users as $u) { if (($u['username'] ?? '') === $username) { $chosenUser = $u; break; } }
    if (!$chosenUser) {
        $result = ['ok' => false, 'msg' => 'کاربر انتخاب‌شده پیدا نشد.'];
    } else {
        $display = trim((string)($chosenUser['display_name'] ?? $chosenUser['username']));
        $updated = 0;
        jsonUpdate('news_entries', function ($rows) use (&$updated, $from, $to, $username, $display) {
            foreach ($rows as &$r) {
                $d = $r['entry_date'] ?? '';
                if ($d < $from || $d > $to) continue;
                if (!empty(trim((string)($r['entered_by'] ?? '')))) continue; // خبرهایی که قبلاً ناظر مشخصی دارند دست‌نخورده می‌مانند
                $r['entered_by'] = $username;
                $r['entered_by_display'] = $display;
                $r['updated_at'] = date('Y-m-d H:i:s');
                $updated++;
            }
            return $rows;
        });
        $result = ['ok' => true, 'msg' => "$updated خبر بدون ناظرِ مشخص، به «{$display}» نسبت داده شد."];
    }
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4">
  <h5 class="mb-1">نسبت‌دادن دستی ناظر به خبرهای قدیمی</h5>
  <p class="text-muted small">
    خبرهایی که قبل از فعال‌شدن ثبت خودکار «ناظر» وارد شده‌اند، اطلاعاتی از کاربر ثبت‌کننده ندارند.
    با این فرم می‌توانید برای یک بازهٔ تاریخی مشخص کنید که آن روزها را کدام ناظر ثبت کرده است.
    این کار فقط روی خبرهایی اثر می‌گذارد که <b>هنوز ناظر مشخصی ندارند</b>؛ خبرهای جدید که خودکار ثبت شده‌اند دست‌نخورده می‌مانند.
  </p>

  <?php if ($result): ?>
    <div class="alert <?= $result['ok'] ? 'alert-success' : 'alert-danger' ?>"><?= htmlspecialchars($result['msg']) ?></div>
  <?php endif; ?>

  <form method="post" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">از تاریخ</label>
      <input type="text" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>" placeholder="1405/05/04">
    </div>
    <div class="col-md-4">
      <label class="form-label">تا تاریخ</label>
      <input type="text" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" placeholder="1405/05/04">
    </div>
    <div class="col-md-4">
      <label class="form-label">ناظر</label>
      <select name="username" class="form-select" required>
        <option value="">انتخاب کنید...</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= htmlspecialchars($u['username']) ?>" <?= $username === $u['username'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['display_name'] ?? $u['username']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">اعمال کن</button>
      <a href="index.php" class="btn btn-outline-secondary">بازگشت به داشبورد</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
