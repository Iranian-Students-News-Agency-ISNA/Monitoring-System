<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginPage();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$row = newsEntryGetById($id);
if (!$row) { die('ردیف پیدا نشد.'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag = trim($_POST['tag'] ?? '');
    $neSelect = trim($_POST['news_elements_select'] ?? '');
    $newsElements = $neSelect === 'سایر' ? trim($_POST['news_elements_other'] ?? '') : $neSelect;
    newsEntryUpdateById($id, [
        'reporter'            => trim($_POST['reporter'] ?? $row['reporter']),
        'publisher'           => trim($_POST['publisher'] ?? ''),
        'title'               => trim($_POST['title'] ?? ''),
        'news_type'           => trim($_POST['news_type'] ?? ''),
        'service_main'        => trim($_POST['service_main'] ?? ''),
        'service_sub'         => trim($_POST['service_sub'] ?? ''),
        'subject'             => trim($_POST['subject'] ?? ''),
        'real_news_type'      => trim($_POST['real_news_type'] ?? ''),
        'interviewee'         => trim($_POST['interviewee'] ?? ''),
        'news_elements'       => $newsElements,
        'source'              => trim($_POST['source'] ?? ''),
        'description'         => trim($_POST['description'] ?? ''),
        'news_link'           => trim($_POST['news_link'] ?? ''),
        'tag'                 => $tag,
        'tag_note'            => trim($_POST['tag_note'] ?? ''),
        'tag_count'           => countTags($tag),
        'related_links_count' => (int)($_POST['related_links_count'] ?? 0),
        'related_links_note'  => trim($_POST['related_links_note'] ?? ''),
        'addon_count'         => (int)($_POST['addon_count'] ?? 0),
        'addon_note'          => trim($_POST['addon_note'] ?? ''),
    ]);
    header('Location: entry.php?date=' . urlencode($row['entry_date'])); exit;
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4">
  <h5 class="mb-3">ویرایش خبر ثبت‌شده</h5>
  <form method="post" class="row g-3">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <div class="col-md-4"><label class="form-label">خبرنگار</label><input class="form-control" name="reporter" value="<?= htmlspecialchars($row['reporter'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">ناشر</label><input class="form-control" name="publisher" value="<?= htmlspecialchars($row['publisher'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">لینک خبر</label><input class="form-control" name="news_link" value="<?= htmlspecialchars($row['news_link'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">تیتر</label><input class="form-control" name="title" value="<?= htmlspecialchars($row['title'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">سرویس</label><input class="form-control" name="service_main" value="<?= htmlspecialchars($row['service_main'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">زیرسرویس</label><input class="form-control" name="service_sub" value="<?= htmlspecialchars($row['service_sub'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">نوع خبر</label><input class="form-control" name="news_type" value="<?= htmlspecialchars($row['news_type'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">نوع واقعی خبر</label><input class="form-control" name="real_news_type" value="<?= htmlspecialchars($row['real_news_type'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">سوژه</label><input class="form-control" name="subject" value="<?= htmlspecialchars($row['subject'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">مصاحبه‌شونده</label><input class="form-control" name="interviewee" value="<?= htmlspecialchars($row['interviewee'] ?? '') ?>"></div>
    <?php
      $neVal = trim((string)($row['news_elements'] ?? ''));
      $neFixed = ['رعایت شده است', 'رعایت نشده است'];
      $neIsFixed = in_array($neVal, $neFixed, true);
      $neIsOther = $neVal !== '' && !$neIsFixed;
    ?>
    <div class="col-12">
      <label class="form-label">عناصر خبری</label>
      <select class="form-select" name="news_elements_select" id="ee_news_elements" onchange="document.getElementById('ee_news_elements_other').style.display=(this.value==='سایر')?'':'none'">
        <option value="" <?= $neVal === '' ? 'selected' : '' ?>>— انتخاب کنید —</option>
        <option value="رعایت شده است" <?= $neVal === 'رعایت شده است' ? 'selected' : '' ?>>رعایت شده است</option>
        <option value="رعایت نشده است" <?= $neVal === 'رعایت نشده است' ? 'selected' : '' ?>>رعایت نشده است</option>
        <option value="سایر" <?= $neIsOther ? 'selected' : '' ?>>سایر</option>
      </select>
      <input type="text" class="form-control mt-2" id="ee_news_elements_other" name="news_elements_other"
             style="<?= $neIsOther ? '' : 'display:none' ?>" placeholder="توضیح مورد سایر..."
             value="<?= htmlspecialchars($neIsOther ? $neVal : '') ?>">
    </div>
    <div class="col-md-6"><label class="form-label">منبع</label><input class="form-control" name="source" value="<?= htmlspecialchars($row['source'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">توضیح کلی خبر</label><textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($row['description'] ?? '') ?></textarea></div>
    <div class="col-md-6"><label class="form-label">برچسب</label><input class="form-control" name="tag" value="<?= htmlspecialchars($row['tag'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">توضیح برچسب</label><input class="form-control" name="tag_note" value="<?= htmlspecialchars($row['tag_note'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">تعداد اخبار مرتبط</label><input type="number" class="form-control" name="related_links_count" value="<?= (int)($row['related_links_count'] ?? 0) ?>"></div>
    <div class="col-md-9"><label class="form-label">توضیح اخبار مرتبط</label><input class="form-control" name="related_links_note" value="<?= htmlspecialchars($row['related_links_note'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">تعداد افزونه</label><input type="number" class="form-control" name="addon_count" value="<?= (int)($row['addon_count'] ?? 0) ?>"></div>
    <div class="col-md-9"><label class="form-label">توضیح افزونه</label><input class="form-control" name="addon_note" value="<?= htmlspecialchars($row['addon_note'] ?? '') ?>"></div>
    <div class="col-12">
      <button class="btn btn-success">ذخیره تغییرات</button>
      <a href="entry.php?date=<?= urlencode($row['entry_date']) ?>" class="btn btn-outline-secondary">انصراف</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
