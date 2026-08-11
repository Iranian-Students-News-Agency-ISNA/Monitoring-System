<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginApi();

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) { echo json_encode(['ok'=>false,'msg'=>'داده نامعتبر است.'], JSON_UNESCAPED_UNICODE); exit; }

$entryDate = normalizeJalaliDate($in['entry_date'] ?? '') ?? '';
$newsId = normalizeDigits(trim((string)($in['news_id'] ?? '')));
if ($entryDate === '') {
    echo json_encode(['ok'=>false,'msg'=>'تاریخ الزامی است.'], JSON_UNESCAPED_UNICODE); exit;
}

// خبرنگار هرگز از ورودی کلاینت گرفته نمی‌شود (برای جلوگیری از ثبت اشتباه نام کاربری یا هر مقدار دیگر):
// اگر خبر در فایل اکسل همان تاریخ با کد مشخص پیدا شود و ستون خبرنگار داشته باشد، همان مقدار استفاده می‌شود؛
// در غیر این صورت نام کاربر واردشده به سامانه (display_name) به‌کار می‌رود.
$me = currentUser();
$excelRow = $newsId !== '' ? excelRowFind($entryDate, $newsId) : null;
$excelReporter = trim((string)($excelRow['reporter'] ?? ''));
$reporter = $excelReporter !== '' ? $excelReporter : trim((string)($me['display_name'] ?? ''));
if ($reporter === '') {
    echo json_encode(['ok'=>false,'msg'=>'تعیین خبرنگار ممکن نشد.'], JSON_UNESCAPED_UNICODE); exit;
}

$tag = trim((string)($in['tag'] ?? ''));

$data = [
    'entry_date'          => $entryDate,
    'reporter'            => $reporter,
    'news_id'             => $newsId,
    'news_link'           => trim((string)($in['news_link'] ?? '')),
    'site'                => trim((string)($excelRow['site'] ?? ($in['site'] ?? ''))),
    'publisher'           => trim((string)($in['publisher'] ?? '')),
    'title'               => trim((string)($in['title'] ?? '')),
    'news_type'           => trim((string)($in['news_type'] ?? '')),
    'service_main'        => trim((string)($in['service_main'] ?? '')),
    'service_sub'         => trim((string)($in['service_sub'] ?? '')),
    'subject'             => trim((string)($in['subject'] ?? '')),
    'real_news_type'      => trim((string)($in['real_news_type'] ?? '')),
    'interviewee'         => trim((string)($in['interviewee'] ?? '')),
    'news_elements'       => trim((string)($in['news_elements'] ?? '')),
    'source'              => trim((string)($in['source'] ?? '')),
    'description'         => trim((string)($in['description'] ?? '')),
    'tag'                 => $tag,
    'tag_note'            => trim((string)($in['tag_note'] ?? '')),
    'tag_count'           => countTags($tag),
    'related_links_count' => (int)($in['related_links_count'] ?? 0),
    'related_links_note'  => trim((string)($in['related_links_note'] ?? '')),
    'addon_count'         => (int)($in['addon_count'] ?? 0),
    'addon_note'          => trim((string)($in['addon_note'] ?? '')),
];

if (!empty($in['id'])) {
    $data['id'] = (int)$in['id'];
} else {
    // فقط هنگام ثبت اولیه، کاربر لاگین‌شده («ناظر») ثبت می‌شود؛ روی ویرایش‌های بعدی دست‌نخورده می‌ماند
    $data['entered_by'] = trim((string)($me['username'] ?? ''));
    $data['entered_by_display'] = trim((string)($me['display_name'] ?? ''));
}

try {
    $id = newsEntryUpsert($data);
    echo json_encode(['ok'=>true, 'id'=>$id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'msg'=>'خطا در ذخیره‌سازی: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
