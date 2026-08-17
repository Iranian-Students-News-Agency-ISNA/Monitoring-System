<?php
require_once __DIR__ . '/jsondb.php';
require_once __DIR__ . '/totp.php';

function authStartSession(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
}

// ===================== کاربران (تعریف‌شده در storage/data/users.json) =====================
// هر ردیف: {"username":"...", "display_name":"...", "totp_secret": null یا رشته Base32}
// نام‌های کاربری را مدیر سایت از قبل داخل این فایل تعریف می‌کند؛ totp_secret را کاربر با
// اولین ورود خودش می‌سازد (نیازی به ساخت دستی Secret نیست).

function usersAll(): array
{
    return jsonRead('users');
}

function userFindByUsername(string $username): ?array
{
    $username = trim($username);
    foreach (usersAll() as $u) {
        if (strcasecmp((string)($u['username'] ?? ''), $username) === 0) return $u;
    }
    return null;
}

function userNeedsSetup(array $user): bool
{
    return empty($user['totp_secret']);
}

// افزودن کاربر جدید به users.json (این تابع قبلاً در users_admin.php صدا زده می‌شد بدون اینکه تعریف شده باشد)
function userInsert(array $fields): void
{
    jsonUpdate('users', function ($rows) use ($fields) {
        $fields['created_at'] = date('Y-m-d H:i:s');
        $rows[] = $fields;
        return $rows;
    });
}

// اصلاح نام نمایشی یک کاربر موجود (بدون نیاز به ساخت مجدد کاربر و از دست رفتن Secret او)
function userUpdateDisplayName(string $username, string $displayName): bool
{
    $found = false;
    jsonUpdate('users', function ($rows) use ($username, $displayName, &$found) {
        foreach ($rows as &$r) {
            if (strcasecmp((string)($r['username'] ?? ''), $username) === 0) {
                $r['display_name'] = $displayName;
                $found = true;
                break;
            }
        }
        return $rows;
    });
    return $found;
}

// ثبت Secret تولیدشده برای یک کاربر تعریف‌شده (اولین بار که صفحه ورود را باز می‌کند)
function userSetSecret(string $username, string $secret): void
{
    jsonUpdate('users', function ($rows) use ($username, $secret) {
        foreach ($rows as &$r) {
            if (strcasecmp((string)($r['username'] ?? ''), $username) === 0) {
                $r['totp_secret'] = $secret;
                $r['secret_set_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        return $rows;
    });
}

// ===================== نشست ورود =====================

function currentUser(): ?array
{
    authStartSession();
    $username = $_SESSION['auth_user']['username'] ?? null;
    if (!$username) return null;
    $sessEpoch = (int)($_SESSION['auth_user']['epoch'] ?? -1);
    if ($sessEpoch !== sessionEpochGet()) return null; // نشست به‌خاطر «خروج اجباری همه» باطل شده

    // خروج خودکار پس از ۲۰ دقیقه عدم فعالیت
    $inactivityLimit = 1200; // ثانیه
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivityLimit)) {
        session_destroy();
        return null;
    }
    $_SESSION['last_activity'] = time();

    // نام نمایشی همیشه زنده از users.json خوانده می‌شود (نه از سشن)، تا اگر مدیر آن را
    // بعداً اصلاح کرد، بدون نیاز به خروج/ورود دوباره کاربر، فوراً روی رکوردهای جدید اثر بگذارد.
    $u = userFindByUsername($username);
    if (!$u) return null; // کاربر ممکن است حذف شده باشد
    $displayName = trim((string)($u['display_name'] ?? ''));
    return [
        'username'     => $u['username'],
        'display_name' => $displayName !== '' ? $displayName : $u['username'],
    ];
}

function loginUserSession(array $user): void
{
    authStartSession();
    session_regenerate_id(true);
    $_SESSION['auth_user'] = ['username' => $user['username'], 'epoch' => sessionEpochGet()];
}

// مکانیزم «خروج اجباری همه‌ی نشست‌ها» بدون دست‌زدن به users.json:
// یک شمارنده جدا (storage/data/meta.json) نگه می‌داریم؛ هر نشست، مقدار آن را لحظه ورود در خودش
// ذخیره می‌کند. اگر مدیر این شمارنده را افزایش دهد، همه‌ی نشست‌های قدیمی بلافاصله نامعتبر می‌شوند
// (کاربر باید دوباره با همان یوزرنیم/کد Authenticator وارد شود) بدون اینکه هیچ کاربری از users.json حذف شود.
function sessionEpochGet(): int
{
    $meta = jsonRead('meta');
    return (int)($meta['session_epoch'] ?? 0);
}

function sessionEpochBump(): int
{
    jsonUpdate('meta', function ($meta) {
        $meta = is_array($meta) ? $meta : [];
        $meta['session_epoch'] = (int)($meta['session_epoch'] ?? 0) + 1;
        return $meta;
    });
    return sessionEpochGet();
}

function logoutUserSession(): void
{
    authStartSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// برای صفحات معمولی: اگر لاگین نیست، به صفحه ورود هدایت می‌شود
function requireLoginPage(): void
{
    authStartSession();
    if (!currentUser()) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? 'entry.php');
        header('Location: login.php?next=' . $next);
        exit;
    }
}

// برای نقاط پایانی AJAX/JSON: در صورت عدم ورود، خروجی JSON با کد ۴۰۱
function requireLoginApi(): void
{
    authStartSession();
    if (!currentUser()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'unauthenticated', 'msg' => 'نشست شما منقضی شده است، صفحه را رفرش کنید.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
