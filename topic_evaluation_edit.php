<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/topics.php';
requireLoginPage();

$date = normalizeJalaliDate($_GET['date'] ?? $_POST['date'] ?? '') ?? '';
$code = normalizeDigits(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$backParams = [
    'from'     => $_GET['from'] ?? $_POST['from'] ?? '',
    'to'       => $_GET['to'] ?? $_POST['to'] ?? '',
    'service'  => $_GET['service'] ?? $_POST['service'] ?? '',
    'ranges'   => $_GET['ranges'] ?? $_POST['ranges'] ?? [],
    'filtered' => 1,
];

$excelRow = ($date !== '' && $code !== '') ? excelRowFind($date, $code, 'ایسنا') : null;
if (!$excelRow) {
    die('این خبر در فایل اکسل پیدا نشد.');
}

$topics = topicsAll();
$existingTopics = topicEvalGet($date, $code);
$existingMeta = topicEvalMeta($date, $code);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = currentUser();
    $myUsername = (string)($me['username'] ?? '');
    $myDisplayName = (string)($me['display_name'] ?? $myUsername);
    $action = (string)($_POST['action'] ?? 'save_topics');

    if ($action === 'add_note') {
        topicNoteAdd($date, $code, $myUsername, $myDisplayName, (string)($_POST['note_text'] ?? ''));
        header('Location: topic_evaluation_edit.php?' . http_build_query(array_merge($backParams, ['date' => $date, 'code' => $code])));
        exit;
    }
    if ($action === 'edit_note') {
        topicNoteEdit($date, $code, (int)($_POST['note_id'] ?? 0), $myUsername, (string)($_POST['note_text'] ?? ''));
        header('Location: topic_evaluation_edit.php?' . http_build_query(array_merge($backParams, ['date' => $date, 'code' => $code])));
        exit;
    }
    if ($action === 'delete_note') {
        topicNoteDelete($date, $code, (int)($_POST['note_id'] ?? 0), $myUsername);
        header('Location: topic_evaluation_edit.php?' . http_build_query(array_merge($backParams, ['date' => $date, 'code' => $code])));
        exit;
    }

    $selected = array_filter(array_map('trim', (array)($_POST['topics'] ?? [])), fn($t) => $t !== '');
    topicEvalSet($date, $code, $selected, $myDisplayName);
    header('Location: topic_evaluation.php?' . http_build_query($backParams));
    exit;
}

$suggestedFromTitle = topicsSuggestForTitle((string)($excelRow['title'] ?? ''), $topics);
$suggestedFromService = topicsSuggestForServiceSub((string)($excelRow['service_main'] ?? ''), (string)($excelRow['service_sub'] ?? ''));
$knownNames = array_column($topics, 'name');
$suggestedFromService = array_values(array_intersect($suggestedFromService, $knownNames));
$suggested = array_values(array_unique(array_merge($suggestedFromService, $suggestedFromTitle)));
// موضوعاتی که قبلاً برای این خبر ثبت شده ولی بعدها از لیست حذف شده‌اند، همچنان قابل مشاهده بمانند
$orphans = array_values(array_diff($existingTopics, $knownNames));
$preselected = !empty($existingTopics) ? $existingTopics : $suggested;
$notes = topicNotesGet($date, $code);
$currentUsername = (string)(currentUser()['username'] ?? '');

require __DIR__ . '/includes/layout_top.php';
?>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<div class="card shadow-sm p-4">
  <h5 class="mb-3">تعیین موضوع خبر (ارزیابی موضوعی)</h5>
  <?php if (!empty($existingMeta['updated_by'])): ?>
    <div class="alert alert-light border py-2 mb-3">
      آخرین ویرایش توسط <strong><?= htmlspecialchars($existingMeta['updated_by']) ?></strong>
      در <?= htmlspecialchars($existingMeta['updated_at']) ?>
    </div>
  <?php endif; ?>
  <div class="row g-3 mb-2">
    <div class="col-md-4"><label class="form-label">سرویس</label><input class="form-control" value="<?= htmlspecialchars($excelRow['service_main'] ?? '') ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">زیرسرویس</label><input class="form-control" value="<?= htmlspecialchars($excelRow['service_sub'] ?? '') ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">نوع خبر</label><input class="form-control" value="<?= htmlspecialchars($excelRow['news_type'] ?? '') ?>" disabled></div>
    <div class="col-12"><label class="form-label">تیتر</label>
      <div class="form-control bg-body-secondary">
        <?php $link = trim((string)($excelRow['news_link'] ?? '')); $title = htmlspecialchars($excelRow['title'] ?? '') ?: '(بدون تیتر)'; ?>
        <?= $link !== '' ? '<a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener">' . $title . '</a>' : $title ?>
      </div>
    </div>
    <div class="col-md-4"><label class="form-label">ساعت انتشار</label><input class="form-control" value="<?= htmlspecialchars($excelRow['pub_time'] ?? '') ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">بازدید</label><input class="form-control" value="<?= (int)($excelRow['views'] ?? 0) ?>" disabled></div>
  </div>
  <hr>
  <form method="post" class="row g-3">
    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
    <input type="hidden" name="from" value="<?= htmlspecialchars($backParams['from']) ?>">
    <input type="hidden" name="to" value="<?= htmlspecialchars($backParams['to']) ?>">
    <input type="hidden" name="service" value="<?= htmlspecialchars($backParams['service']) ?>">
    <?php foreach ((array)$backParams['ranges'] as $rg): ?>
      <input type="hidden" name="ranges[]" value="<?= htmlspecialchars($rg) ?>">
    <?php endforeach; ?>

    <div class="col-12">
      <label class="form-label">موضوع</label>
      <select class="form-select" name="topics[]" id="topicSelect" multiple>
        <?php foreach ($topics as $t): ?>
          <option value="<?= htmlspecialchars($t['name']) ?>" title="<?= htmlspecialchars($t['definition']) ?>"
            <?= in_array($t['name'], $preselected, true) ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['name']) ?>
          </option>
        <?php endforeach; ?>
        <?php foreach ($orphans as $o): ?>
          <option value="<?= htmlspecialchars($o) ?>" selected><?= htmlspecialchars($o) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text"><a href="topics_manage.php" target="_blank">مدیریت موضوعات (افزودن / حذف)</a></div>
      <?php if (!empty($suggested)): ?>
        <div class="form-text">پیشنهاد خودکار بر اساس سرویس/زیرسرویس و تیتر: <?= htmlspecialchars(implode('، ', $suggested)) ?></div>
      <?php endif; ?>
    </div>

    <div class="col-12">
      <button class="btn btn-success">ذخیره</button>
      <a href="topic_evaluation.php?<?= http_build_query($backParams) ?>" class="btn btn-outline-secondary">انصراف</a>
    </div>
  </form>

  <hr>
  <h6 class="mb-3">یادداشت‌ها</h6>
  <div class="mb-3">
    <?php if (empty($notes)): ?>
      <div class="text-muted small">هنوز یادداشتی ثبت نشده.</div>
    <?php endif; ?>
    <?php foreach ($notes as $n): ?>
      <?php $isMine = strcasecmp((string)($n['username'] ?? ''), $currentUsername) === 0; ?>
      <div class="border rounded p-2 mb-2" id="note-<?= (int)$n['id'] ?>">
        <div class="d-flex justify-content-between align-items-start">
          <div class="small text-muted">
            <strong><?= htmlspecialchars($n['display_name'] ?? $n['username'] ?? '') ?></strong>
            — <?= htmlspecialchars($n['updated_at'] ?? $n['created_at'] ?? '') ?>
            <?= ($n['updated_at'] ?? '') !== ($n['created_at'] ?? '') ? '<span class="fst-italic">(ویرایش شده)</span>' : '' ?>
          </div>
          <?php if ($isMine): ?>
            <div>
              <button type="button" class="btn btn-sm btn-link p-0 me-2" onclick="toggleNoteEdit(<?= (int)$n['id'] ?>)">ویرایش</button>
              <form method="post" class="d-inline" onsubmit="return confirm('یادداشت حذف شود؟');">
                <input type="hidden" name="action" value="delete_note">
                <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                <button class="btn btn-sm btn-link text-danger p-0">حذف</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
        <div class="mt-1" id="note-view-<?= (int)$n['id'] ?>"><?= nl2br(htmlspecialchars($n['text'] ?? '')) ?></div>
        <?php if ($isMine): ?>
          <form method="post" class="mt-2 d-none" id="note-form-<?= (int)$n['id'] ?>">
            <input type="hidden" name="action" value="edit_note">
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
            <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
            <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
            <textarea class="form-control mb-1" name="note_text" rows="2"><?= htmlspecialchars($n['text'] ?? '') ?></textarea>
            <button class="btn btn-sm btn-primary">ذخیره یادداشت</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleNoteEdit(<?= (int)$n['id'] ?>)">انصراف</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" class="row g-2">
    <input type="hidden" name="action" value="add_note">
    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
    <div class="col-12">
      <textarea class="form-control" name="note_text" rows="2" placeholder="یادداشت جدید..." required></textarea>
    </div>
    <div class="col-12">
      <button class="btn btn-outline-primary btn-sm">افزودن یادداشت</button>
    </div>
  </form>
</div>

<script>
function toggleNoteEdit(id) {
  document.getElementById('note-view-' + id).classList.toggle('d-none');
  document.getElementById('note-form-' + id).classList.toggle('d-none');
}
</script>

<script>
new TomSelect('#topicSelect', {
  create: false,
  plugins: ['remove_button'],
  maxOptions: 1000,
  score: function(search){
    const words = search.toLowerCase().trim().split(/\s+/).filter(Boolean);
    return function(item){
      const text = (item.text || '').toLowerCase();
      return words.length === 0 || words.some(w => text.includes(w)) ? 1 : 0;
    };
  }
});
</script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
