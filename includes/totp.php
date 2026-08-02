<?php
// پیاده‌سازی خالص PHP از الگوریتم TOTP (RFC 6238) سازگار با Google Authenticator
// بدون هیچ کتابخانه خارجی - فقط HMAC-SHA1 داخلی PHP

function base32Encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $byte) { $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT); }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0');
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function base32Decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $char) {
        $val = strpos($alphabet, $char);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) < 8) continue;
        $bytes .= chr(bindec($byte));
    }
    return $bytes;
}

function randomBase32Secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = '';
    for ($i = 0; $i < $length; $i++) { $s .= $alphabet[random_int(0, 31)]; }
    return $s;
}

function totpGenerate(string $secretBase32, ?int $time = null, int $digits = 6, int $period = 30): string
{
    $time = $time ?? time();
    $counter = intdiv($time, $period);
    $secret = base32Decode($secretBase32);
    $binCounter = pack('N*', 0, $counter); // شمارنده ۸ بایتی big-endian
    $hash = hash_hmac('sha1', $binCounter, $secret, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);
    $code = $truncated % (10 ** $digits);
    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}

// بررسی کد وارد شده با کمی رواداری زمانی (یک گام قبل/بعد، برای اختلاف ساعت گوشی)
function totpVerify(string $secretBase32, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', trim($code));
    if ($code === '') return false;
    for ($i = -$window; $i <= $window; $i++) {
        $t = time() + ($i * 30);
        if (hash_equals(totpGenerate($secretBase32, $t), $code)) return true;
    }
    return false;
}

// ساخت آدرس otpauth:// برای تولید QR Code جهت افزودن به Google Authenticator
function totpProvisioningUri(string $secretBase32, string $username, string $issuer = 'نظارت ایسنا'): string
{
    $label = rawurlencode($issuer) . ':' . rawurlencode($username);
    $params = http_build_query([
        'secret' => $secretBase32,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);
    return "otpauth://totp/{$label}?{$params}";
}
