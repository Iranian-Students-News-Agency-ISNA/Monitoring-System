<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
authStartSession();

if (currentUser()) { header('Location: entry.php'); exit; }

$next = $_GET['next'] ?? $_POST['next'] ?? 'entry.php';
if (!preg_match('/^[a-zA-Z0-9_\.\/\?=&%-]+$/', $next)) $next = 'entry.php';

$error = null;
$stage = 'identify';   // identify | setup | verify
$username = '';
$qr = null;             // ['uri'=>..., 'secret'=>...] وقتی کاربر تازه Secret گرفته

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $justSetup = !empty($_POST['just_setup']);
    $user = $username !== '' ? userFindByUsername($username) : null;

    if ($username === '') {
        $error = 'نام کاربری را وارد کنید.';
    } elseif (!$user) {
        $error = 'این نام کاربری در سامانه تعریف نشده است.';
    } elseif (userNeedsSetup($user) && $code === '') {
        // اولین باری که این کاربر وارد می‌شود: یک Secret تازه بساز و QR راه‌اندازی را نشان بده
        $secret = randomBase32Secret();
        userSetSecret($username, $secret);
        $stage = 'setup';
        $qr = ['uri' => totpProvisioningUri($secret, $username), 'secret' => $secret];
    } elseif ($code === '') {
        $stage = 'verify';
    } else {
        // کاربر (تازه‌راه‌انداز یا قدیمی) کد را وارد کرده - تأیید شود
        $freshUser = userFindByUsername($username); // دوباره بخوان تا Secret تازه‌ذخیره‌شده را ببیند
        if ($freshUser && !empty($freshUser['totp_secret']) && totpVerify($freshUser['totp_secret'], $code)) {
            loginUserSession($freshUser);
            header('Location: ' . $next);
            exit;
        }
        $error = 'کد یک‌بارمصرف نادرست است.';
        if ($justSetup && $freshUser && !empty($freshUser['totp_secret'])) {
            // در همان جلسه راه‌اندازی هستیم؛ QR را با همان Secret ذخیره‌شده دوباره نشان بده (بدون ساخت Secret جدید)
            $stage = 'setup';
            $qr = ['uri' => totpProvisioningUri($freshUser['totp_secret'], $username), 'secret' => $freshUser['totp_secret']];
        } else {
            $stage = 'verify';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود &mdash; سامانه نظارت خبرگزاری دانشجویان ایران (ایسنا)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<style>
:root{
  --deep-1:#0b0620; --deep-2:#1c0f4a; --deep-3:#3a1470; --violet:#7b2ff7; --pink:#c026d3;
}
html,body{height:100%; margin:0;}
body{
  font-family:'Vazirmatn','Tahoma',sans-serif;
  min-height:100vh; display:flex; align-items:center; justify-content:center;
  background:radial-gradient(ellipse at top, var(--deep-3) 0%, var(--deep-2) 45%, var(--deep-1) 100%);
  overflow:hidden; position:relative;
}
.nebula{ position:absolute; border-radius:50%; filter:blur(60px); opacity:.35; pointer-events:none; animation: nebulaFloat 22s ease-in-out infinite; }
.nebula.n1{ width:420px; height:420px; background:var(--violet); top:-120px; right:-100px; }
.nebula.n2{ width:360px; height:360px; background:var(--pink); bottom:-140px; left:-80px; animation-delay:-9s; }
.nebula.n3{ width:260px; height:260px; background:#3fa7ff; top:40%; left:20%; animation-delay:-4s; }
@keyframes nebulaFloat{ 0%,100%{ transform:translate(0,0) scale(1); } 50%{ transform:translate(30px,-20px) scale(1.08); } }
@keyframes twinkle{ 0%,100%{ opacity:.25; } 50%{ opacity:1; } }
.star{ position:absolute; background:#fff; border-radius:50%; animation: twinkle 3s ease-in-out infinite; }
.login-card{
  position:relative; z-index:5; width:100%; max-width:420px;
  background:rgba(20,12,45,.55); border:1px solid rgba(255,255,255,.12); border-radius:20px;
  box-shadow:0 20px 60px rgba(0,0,0,.45); backdrop-filter: blur(14px);
  padding:2.2rem 1.8rem; color:#fff; margin:1rem;
}
.login-card h1{ font-size:1.15rem; font-weight:700; text-align:center; margin-bottom:.25rem; }
.login-card p.sub{ text-align:center; color:rgba(255,255,255,.65); font-size:.85rem; margin-bottom:1.5rem; }
.form-label{ color:rgba(255,255,255,.85); font-size:.88rem; }
.form-control{ background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.18); color:#fff; border-radius:10px; }
.form-control:focus{ background:rgba(255,255,255,.12); border-color:var(--violet); color:#fff; box-shadow:0 0 0 .2rem rgba(123,47,247,.25); }
.form-control::placeholder{ color:rgba(255,255,255,.4); }
.btn-space{ background:linear-gradient(135deg, var(--violet), var(--pink)); border:none; color:#fff; font-weight:700; border-radius:10px; padding:.6rem; }
.btn-space:hover{ filter:brightness(1.12); color:#fff; }
.otp-input{ letter-spacing:.5em; text-align:center; font-size:1.3rem; }
.qr-box{ background:#fff; border-radius:12px; padding:10px; display:inline-block; }
.secret-code{ direction:ltr; display:inline-block; background:rgba(255,255,255,.1); padding:.2rem .5rem; border-radius:6px; letter-spacing:.15em; }
</style>
</head>
<body>
<div class="nebula n1"></div><div class="nebula n2"></div><div class="nebula n3"></div>
<div id="starfield" style="position:absolute;inset:0;"></div>

<div class="login-card">
  <h1>سامانه نظارت خبرگزاری دانشجویان ایران (ایسنا)</h1>

  <?php if ($stage === 'setup' && $qr): ?>
    <p class="sub">اولین ورود شما &mdash; راه‌اندازی Google Authenticator</p>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <p class="small text-center mb-2">این QR را یک‌بار با اپ Google Authenticator اسکن کنید (Add account → Scan QR code):</p>
    <div class="text-center mb-3"><div id="qrbox" class="qr-box"></div></div>
    <p class="small text-center mb-3">یا Secret را دستی وارد کنید: <span class="secret-code"><?= htmlspecialchars($qr['secret']) ?></span></p>
    <form method="post">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
      <input type="hidden" name="just_setup" value="1">
      <div class="mb-3">
        <label class="form-label">کد نمایش‌داده‌شده در اپ را وارد کنید</label>
        <input type="text" name="code" class="form-control otp-input" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus placeholder="------">
      </div>
      <button class="btn btn-space w-100">تأیید و ورود</button>
    </form>
    <script>
      QRCode.toCanvas(document.createElement('canvas'), <?= json_encode($qr['uri']) ?>, function(err, canvas){
        if (!err) document.getElementById('qrbox').appendChild(canvas);
      });
    </script>

  <?php elseif ($stage === 'verify'): ?>
    <p class="sub">کد یک‌بارمصرف Google Authenticator را وارد کنید</p>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
      <div class="mb-3">
        <label class="form-label">کد یک‌بارمصرف</label>
        <input type="text" name="code" class="form-control otp-input" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus placeholder="------">
      </div>
      <button class="btn btn-space w-100">ورود</button>
      <a href="login.php" class="d-block text-center small mt-2" style="color:rgba(255,255,255,.6)">نام کاربری اشتباه است؟</a>
    </form>

  <?php else: ?>
    <p class="sub">ورود با نام کاربری</p>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <div class="mb-3">
        <label class="form-label">نام کاربری</label>
        <input type="text" name="username" class="form-control" required autofocus placeholder="مثلاً: sara.reza" value="<?= htmlspecialchars($username) ?>">
      </div>
      <button class="btn btn-space w-100">ادامه</button>
    </form>
  <?php endif; ?>
</div>

<script>
(function(){
  const field = document.getElementById('starfield');
  const N = 90; let html='';
  for(let i=0;i<N;i++){
    const size = Math.random()*2 + 1, top = Math.random()*100, left = Math.random()*100;
    const delay = (Math.random()*3).toFixed(2), dur = (2 + Math.random()*3).toFixed(2);
    html += '<span class="star" style="width:'+size+'px;height:'+size+'px;top:'+top+'%;left:'+left+'%;animation-duration:'+dur+'s;animation-delay:'+delay+'s;"></span>';
  }
  field.innerHTML = html;
})();
</script>
</body>
</html>