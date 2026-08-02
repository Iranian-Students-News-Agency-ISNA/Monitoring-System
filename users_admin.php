<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
authStartSession();

$key = $_GET['key'] ?? $_POST['key'] ?? '';
if (!hash_equals(ADMIN_SETUP_KEY, (string)$key)) {
    http_response_code(403);
    die('دسترسی مجاز نیست. برای مدیریت کاربران، کلید راه‌اندازی صحیح را در آدرس وارد کنید (users_admin.php?key=...)، مقدار آن در config.php قابل تغییر است.');
}

$newUserQr = null;
$error = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_reporters'])) {
    $fixed = newsEntriesRepairReporterUsernames(usersAll());
    $notice = $fixed > 0
        ? "تعداد {$fixed} رکورد خبر که یوزرنیم به‌جای نام خبرنگار داشتند، اصلاح شد."
        : 'رکوردی برای اصلاح پیدا نشد (همه چیز از قبل درست بود).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_logout_all'])) {
    sessionEpochBump();
    $notice = 'همه‌ی نشست‌های فعال باطل شدند؛ همه باید دوباره با کد Authenticator وارد شوند (خود users.json دست‌نخورده ماند).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    if ($username === '') {
        $error = 'نام کاربری الزامی است.';
    } elseif ($displayName === '') {
        $error = 'نام نمایشی (نام واقعی خبرنگار) الزامی است؛ همین مقدار در رکورد خبرها ثبت می‌شود.';
    } elseif (userFindByUsername($username)) {
        $error = 'این نام کاربری قبلاً ثبت شده است.';
    } else {
        $secret = randomBase32Secret();
        userInsert(['username' => $username, 'display_name' => $displayName, 'totp_secret' => $secret]);
        $newUserQr = [
            'username' => $username,
            'secret' => $secret,
            'uri' => totpProvisioningUri($secret, $username),
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_display_name'])) {
    $username = trim($_POST['username'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    if ($username === '' || $displayName === '') {
        $error = 'نام کاربری و نام نمایشی جدید الزامی است.';
    } elseif (!userUpdateDisplayName($username, $displayName)) {
        $error = 'کاربر پیدا نشد.';
    } else {
        $notice = 'نام نمایشی «' . $username . '» به‌روزرسانی شد.';
    }
}

$users = usersAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>مدیریت کاربران</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<style>body{font-family:'Vazirmatn','Tahoma',sans-serif; background:#f6f8fc;} .card{border-radius:14px;}</style>
</head>
<body class="py-4">
<div class="container" style="max-width:720px;">
  <h4 class="mb-3">مدیریت کاربران (ورود با Google Authenticator)</h4>
  <div class="alert alert-warning small">این صفحه با کلید راه‌اندازی محافظت می‌شود. پس از ساخت کاربران اولیه، مقدار <code>ADMIN_SETUP_KEY</code> را در <code>config.php</code> عوض کنید یا این فایل را از روی هاست حذف کنید.</div>

  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

  <div class="card shadow-sm p-3 mb-4">
    <h6 class="mb-2">ابزارهای اصلاح (بدون هیچ تغییری در users.json)</h6>
    <div class="d-flex flex-wrap gap-2">
      <form method="post">
        <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
        <input type="hidden" name="repair_reporters" value="1">
        <button class="btn btn-outline-warning btn-sm" onclick="return confirm('رکوردهایی از news_entries که مقدار خبرنگارشان دقیقاً برابر یوزرنیم یکی از کاربران است، به نام نمایشی همان کاربر اصلاح شوند؟');">اصلاح خبرنگارهای اشتباه در خبرهای قبلی</button>
      </form>
      <form method="post">
        <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
        <input type="hidden" name="force_logout_all" value="1">
        <button class="btn btn-outline-danger btn-sm" onclick="return confirm('همه کاربران از نشست فعلی خارج شوند و مجبور به ورود دوباره با کد Authenticator شوند؟ (فقط سشن‌ها باطل می‌شوند، خود حساب‌ها دست‌نخورده می‌مانند)');">خروج اجباری از همه‌ی نشست‌ها</button>
      </form>
    </div>
    <p class="small text-muted mt-2 mb-0">دکمه اول: رکوردهای قدیمیِ خبر که به‌جای نام واقعی خبرنگار، یوزرنیم در آن‌ها ثبت شده را اصلاح می‌کند. دکمه دوم: فقط نشست‌های لاگین را باطل می‌کند تا مطمئن شوید همه با آخرین اطلاعات (نام نمایشی به‌روز) دوباره وارد می‌شوند؛ هیچ کاربری حذف نمی‌شود.</p>
  </div>

  <?php if ($newUserQr): ?>
    <div class="card shadow-sm p-3 mb-4">
      <h6>کاربر «<?= htmlspecialchars($newUserQr['username']) ?>» ساخته شد.</h6>
      <p class="small text-muted mb-2">این QR را یک‌بار با اپ Google Authenticator کاربر اسکن کنید (یا کد Secret را دستی وارد کند). این صفحه پس از خروج، دوباره این QR را نشان نمی‌دهد.</p>
      <div id="qrbox" class="mb-2"></div>
      <div class="small">Secret دستی: <code><?= htmlspecialchars($newUserQr['secret']) ?></code></div>
    </div>
    <script>
      QRCode.toCanvas(document.createElement('canvas'), <?= json_encode($newUserQr['uri']) ?>, function(err, canvas){
        if (!err) document.getElementById('qrbox').appendChild(canvas);
      });
    </script>
  <?php endif; ?>

  <div class="card shadow-sm p-3 mb-4">
    <h6 class="mb-3">افزودن کاربر جدید</h6>
    <form method="post" class="row g-2">
      <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
      <input type="hidden" name="add_user" value="1">
      <div class="col-md-5">
        <input class="form-control" name="username" placeholder="نام کاربری (انگلیسی)" required>
      </div>
      <div class="col-md-5">
        <input class="form-control" name="display_name" placeholder="نام نمایشی (نام واقعی خبرنگار — الزامی)" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100">افزودن</button>
      </div>
    </form>
  </div>

  <div class="card shadow-sm p-3">
    <h6 class="mb-3">کاربران فعلی</h6>
    <table class="table table-sm">
      <thead><tr><th>#</th><th>نام کاربری</th><th>نام نمایشی</th><th>تاریخ ساخت</th><th>ویرایش نام نمایشی</th></tr></thead>
      <tbody>
        <?php foreach ($users as $i => $u): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['display_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
          <td>
            <form method="post" class="d-flex gap-1">
              <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
              <input type="hidden" name="update_display_name" value="1">
              <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
              <input class="form-control form-control-sm" name="display_name" value="<?= htmlspecialchars($u['display_name'] ?? '') ?>" required>
              <button class="btn btn-sm btn-outline-primary">ذخیره</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
