<?php
// ورود خودکار از سامانه ارزیابی ماهانه (arzyabi) با توکن امضاشده؛ بدون TOTP.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
authStartSession();

$secretFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/.nezarat_sso_secret';
$secret = is_file($secretFile) ? trim((string)@file_get_contents($secretFile)) : '';
$ts  = (int)($_GET['ts'] ?? 0);
$sig = (string)($_GET['sig'] ?? '');
$ok  = $secret !== '' && $ts > 0 && abs(time() - $ts) <= 120
    && hash_equals(hash_hmac('sha256', 'nezarat-sso|' . $ts, $secret), $sig);
if (!$ok) { http_response_code(403); exit('لینک ورود نامعتبر یا منقضی است؛ دوباره از منوی ارزیابی کلی وارد شوید.'); }

// کاربر ورود خودکار: اولین کاربری که در users.json فیلد "sso": true دارد؛ وگرنه اولین کاربر
$user = null;
foreach (usersAll() as $u) { if (!empty($u['sso'])) { $user = $u; break; } }
if (!$user) { $all = usersAll(); $user = $all[0] ?? null; }
if (!$user) { http_response_code(500); exit('کاربری در users.json تعریف نشده است.'); }

loginUserSession($user);
$_SESSION['last_activity'] = time();
setcookie('nz_sso', '1', ['expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
header('Location: evaluation.php');
exit;
