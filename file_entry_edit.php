<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginPage();

$date = normalizeJalaliDate($_GET['date'] ?? $_POST['date'] ?? '') ?? '';
$code = normalizeDigits(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$backParams = [
    'from' => $_GET['from'] ?? $_POST['from'] ?? '',
    'to' => $_GET['to'] ?? $_POST['to'] ?? '',
    'service' => $_GET['service'] ?? $_POST['service'] ?? '',
    'reporter' => $_GET['reporter'] ?? $_POST['reporter'] ?? '',
];

$excelRow = ($date !== '' && $code !== '') ? excelRowFind($date, $code) : null;
if (!$excelRow || trim((string)($excelRow['reporter'] ?? '')) === '') {
    die('این خبر در فایل اکسل (با خبرنگار مشخص) پیدا نشد.');
}
$existing = newsEntryFindByDateAndCode($date, $code);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag = trim($_POST['tag'] ?? '');
    $neSelect = trim($_POST['news_elements_select'] ?? '');
    $newsElements = $neSelect === 'سایر' ? trim($_POST['news_elements_other'] ?? '') : $neSelect;
    $data = [
        'entry_date'          => $date,
        'reporter'            => trim((string)$excelRow['reporter']),
        'news_id'             => $code,
        'news_link'           => trim((string)($excelRow['news_link'] ?? '')),
        'publisher'           => trim((string)($excelRow['publisher'] ?? '')),
        'title'               => trim((string)($excelRow['title'] ?? '')),
        'news_type'           => trim((string)($excelRow['news_type'] ?? '')),
        'service_main'        => trim((string)($excelRow['service_main'] ?? '')),
        'service_sub'         => trim((string)($excelRow['service_sub'] ?? '')),
        'subject'             => trim($_POST['subject'] ?? ''),
        'real_news_type'      => trim($_POST['real_news_type'] ?? ''),
        'interviewee'         => trim($_POST['interviewee'] ?? ''),
        'news_elements'       => $newsElements,
        'source'              => trim($_POST['source'] ?? ''),
        'description'         => trim($_POST['description'] ?? ''),
        'tag'                 => $tag,
        'tag_note'            => trim($_POST['tag_note'] ?? ''),
        'tag_count'           => countTags($tag),
        'related_links_count' => (int)($_POST['related_links_count'] ?? 0),
        'related_links_note'  => trim($_POST['related_links_note'] ?? ''),
        'addon_count'         => (int)($_POST['addon_count'] ?? 0),
        'addon_note'          => trim($_POST['addon_note'] ?? ''),
    ];
    if ($existing) {
        $data['id'] = (int)$existing['id'];
    } else {
        $me = currentUser();
        $data['entered_by'] = trim((string)($me['username'] ?? ''));
        $data['entered_by_display'] = trim((string)($me['display_name'] ?? ''));
    }
    newsEntryUpsert($data);
    header('Location: file_entry.php?' . http_build_query($backParams));
    exit;
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4">
  <h5 class="mb-3">اطلاعات تکمیلی خبر (ثبت از پرونده)</h5>
  <div class="row g-3 mb-2">
    <div class="col-md-4"><label class="form-label">خبرنگار</label><input class="form-control" value="<?= htmlspecialchars($excelRow['reporter']) ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">ناشر</label><input class="form-control" value="<?= htmlspecialchars($excelRow['publisher'] ?? '') ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">تاریخ</label><input class="form-control" value="<?= htmlspecialchars($date) ?>" disabled></div>
    <div class="col-12"><label class="form-label">تیتر</label><input class="form-control" value="<?= htmlspecialchars($excelRow['title'] ?? '') ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">سرویس</label><input class="form-control" value="<?= htmlspecialchars($excelRow['service_main'] ?? '') ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">زیرسرویس</label><input class="form-control" value="<?= htmlspecialchars($excelRow['service_sub'] ?? '') ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">نوع خبر</label><input class="form-control" value="<?= htmlspecialchars($excelRow['news_type'] ?? '') ?>" disabled></div>
  </div>
  <hr>
  <form method="post" class="row g-3">
    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
    <?php foreach ($backParams as $k => $v): ?>
      <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
    <?php endforeach; ?>
    <div class="col-md-6"><label class="form-label">سوژه</label><input class="form-control" name="subject" value="<?= htmlspecialchars($existing['subject'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">مصاحبه‌شونده</label><input class="form-control" name="interviewee" value="<?= htmlspecialchars($existing['interviewee'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">نوع واقعی خبر</label><input class="form-control" name="real_news_type" value="<?= htmlspecialchars($existing['real_news_type'] ?? '') ?>"></div>
    <?php
      $neVal = trim((string)($existing['news_elements'] ?? ''));
      $neFixed = ['رعایت شده است', 'رعایت نشده است'];
      $neIsFixed = in_array($neVal, $neFixed, true);
      $neIsOther = $neVal !== '' && !$neIsFixed;
    ?>
    <div class="col-12">
      <label class="form-label">عناصر خبری</label>
      <select class="form-select" name="news_elements_select" id="fe_news_elements" onchange="document.getElementById('fe_news_elements_other').style.display=(this.value==='سایر')?'':'none'">
        <option value="" <?= $neVal === '' ? 'selected' : '' ?>>— انتخاب کنید —</option>
        <option value="رعایت شده است" <?= $neVal === 'رعایت شده است' ? 'selected' : '' ?>>رعایت شده است</option>
        <option value="رعایت نشده است" <?= $neVal === 'رعایت نشده است' ? 'selected' : '' ?>>رعایت نشده است</option>
        <option value="سایر" <?= $neIsOther ? 'selected' : '' ?>>سایر</option>
      </select>
      <input type="text" class="form-control mt-2" id="fe_news_elements_other" name="news_elements_other"
             style="<?= $neIsOther ? '' : 'display:none' ?>" placeholder="توضیح مورد سایر..."
             value="<?= htmlspecialchars($neIsOther ? $neVal : '') ?>">
    </div>
    <div class="col-md-6"><label class="form-label">منبع</label><input class="form-control" name="source" value="<?= htmlspecialchars($existing['source'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">توضیح کلی خبر</label><textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($existing['description'] ?? '') ?></textarea></div>
    <div class="col-md-6"><label class="form-label">برچسب</label><input class="form-control" name="tag" value="<?= htmlspecialchars($existing['tag'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">توضیح برچسب</label><input class="form-control" name="tag_note" value="<?= htmlspecialchars($existing['tag_note'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">تعداد اخبار مرتبط</label><input type="number" class="form-control" name="related_links_count" value="<?= (int)($existing['related_links_count'] ?? 0) ?>"></div>
    <div class="col-md-9"><label class="form-label">توضیح اخبار مرتبط</label><input class="form-control" name="related_links_note" value="<?= htmlspecialchars($existing['related_links_note'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">تعداد افزونه</label><input type="number" class="form-control" name="addon_count" value="<?= (int)($existing['addon_count'] ?? 0) ?>"></div>
    <div class="col-md-9"><label class="form-label">توضیح افزونه</label><input class="form-control" name="addon_note" value="<?= htmlspecialchars($existing['addon_note'] ?? '') ?>"></div>
    <div class="col-12">
      <button class="btn btn-success">ذخیره</button>
      <a href="file_entry.php?<?= http_build_query($backParams) ?>" class="btn btn-outline-secondary">انصراف</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>