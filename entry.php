<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
requireLoginPage();

$me = currentUser();
$reporter = $me['display_name'];

$activeDates = excelFilesActiveDates();
$date = normalizeJalaliDate($_GET['date'] ?? '') ?? normalizeJalaliDate($activeDates[0] ?? '') ?? todayJalali();

[$jy, $jm, ] = array_map('intval', explode('/', $date));

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h5 class="mb-0">ثبت خبر روزانه</h5>
    <form method="get" class="d-flex gap-2 align-items-center">
      <label class="form-label mb-0 small text-muted">تاریخ:</label>
      <select name="date" class="form-select form-select-sm" style="min-width:170px" onchange="this.form.submit()">
        <?php if (!in_array($date, $activeDates, true)): ?>
          <option value="<?= htmlspecialchars($date) ?>" selected><?= htmlspecialchars($date) ?> (بدون فایل اکسل)</option>
        <?php endif; ?>
        <?php foreach ($activeDates as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $d === $date ? 'selected' : '' ?>><?= htmlspecialchars(jalaliDateLabel($d)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="row g-3 mb-2">
    <div class="col-md-4">
      <label class="form-label">خبرنگار</label>
      <input type="text" id="f_reporter" class="form-control" value="<?= htmlspecialchars($reporter) ?>" readonly autocomplete="off">
      <div class="form-text">به‌صورت خودکار از فایل اکسل (بر اساس کد خبر) یا نام کاربر واردشده در سامانه تعیین می‌شود.</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">کد خبر</label>
      <div class="input-group">
        <input type="text" id="f_code" class="form-control" placeholder="مثلاً 1405050100365">
        <button type="button" id="btnLookup" class="btn btn-primary">دریافت اطلاعات</button>
      </div>
    </div>
    <div class="col-md-4">
      <label class="form-label">لینک خبر</label>
      <input type="text" id="f_link" class="form-control" readonly>
    </div>
  </div>

  <div id="lookupStatus" class="small text-muted mb-2"></div>

  <form id="entryForm" class="row g-3">
    <input type="hidden" id="f_entry_date" value="<?= htmlspecialchars($date) ?>">
    <input type="hidden" id="f_publisher">
    <input type="hidden" id="f_title">
    <input type="hidden" id="f_news_type">
    <input type="hidden" id="f_service_main">
    <input type="hidden" id="f_service_sub">

    <div class="col-md-4">
      <label class="form-label">سوژه</label>
      <input type="text" id="f_subject" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">نوع واقعی خبر</label>
      <input type="text" id="f_real_news_type" class="form-control" placeholder="خبر / گزارش / مصاحبه / ...">
    </div>
    <div class="col-md-4">
      <label class="form-label">مصاحبه‌شونده</label>
      <input type="text" id="f_interviewee" class="form-control">
    </div>

    <div class="col-12">
      <label class="form-label">عناصر خبری</label>
      <select id="f_news_elements" class="form-select">
        <option value="">— انتخاب کنید —</option>
        <option value="رعایت شده است">رعایت شده است</option>
        <option value="رعایت نشده است">رعایت نشده است</option>
        <option value="سایر">سایر</option>
      </select>
      <input type="text" id="f_news_elements_other" class="form-control mt-2" style="display:none" placeholder="توضیح مورد سایر...">
    </div>

    <div class="col-md-6">
      <label class="form-label">منبع</label>
      <input type="text" id="f_source" class="form-control">
    </div>

    <div class="col-12">
      <label class="form-label">توضیح کلی خبر</label>
      <textarea id="f_description" class="form-control" rows="2"></textarea>
    </div>

    <div class="col-md-6">
      <label class="form-label">برچسب <span class="text-muted small">(با ویرگول جدا شود)</span></label>
      <input type="text" id="f_tag" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">تعداد برچسب</label>
      <input type="number" id="f_tag_count" class="form-control" readonly>
    </div>
    <div class="col-md-3">
      <label class="form-label">توضیح برچسب</label>
      <input type="text" id="f_tag_note" class="form-control">
    </div>

    <div class="col-md-3">
      <label class="form-label">تعداد اخبار مرتبط</label>
      <input type="number" id="f_related_count" class="form-control" min="0" value="0">
    </div>
    <div class="col-md-9">
      <label class="form-label">توضیح اخبار مرتبط</label>
      <input type="text" id="f_related_note" class="form-control">
    </div>

    <div class="col-md-3">
      <label class="form-label">تعداد افزونه</label>
      <input type="number" id="f_addon_count" class="form-control" min="0" value="0">
    </div>
    <div class="col-md-9">
      <label class="form-label">توضیح افزونه</label>
      <input type="text" id="f_addon_note" class="form-control">
    </div>

    <div class="col-12">
      <input type="hidden" id="f_edit_id" value="">
      <button type="submit" class="btn btn-success">ثبت خبر</button>
      <button type="button" id="btnResetForm" class="btn btn-outline-secondary">پاک کردن فرم</button>
    </div>
  </form>
</div>

<div class="card shadow-sm p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h5 class="mb-0">ثبت‌شده‌های <?= htmlspecialchars(jalaliDateLabel($date)) ?></h5>
    <div class="d-flex align-items-center gap-2">
      <label class="form-label mb-0 small text-muted">فیلتر سرویس:</label>
      <select id="serviceFilter" class="form-select form-select-sm" style="min-width:160px">
        <option value="">همه سرویس‌ها</option>
      </select>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle sortable-table">
      <thead>
        <tr>
          <th>#</th><th>خبرنگار</th><th>ناشر</th><th>تیتر</th><th>سرویس</th><th>زیرسرویس</th>
          <th>سوژه</th><th>نوع خبر/واقعی</th><th>عناصر خبری</th><th>منبع</th><th>توضیح کلی</th><th>لینک</th><th>برچسب</th><th>افزونه</th><th>عملیات</th>
        </tr>
      </thead>
      <tbody id="entriesBody"><tr><td colspan="15" class="text-center text-muted">در حال بارگذاری...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
const ENTRY_DATE = <?= json_encode($date) ?>;
let currentEntries = [];

function resetForm(){
  ['f_edit_id','f_code','f_link','f_publisher','f_title','f_news_type','f_service_main','f_service_sub',
   'f_subject','f_real_news_type','f_interviewee','f_news_elements','f_news_elements_other','f_source','f_description','f_tag','f_tag_note','f_tag_count',
   'f_related_note','f_addon_note'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
  document.getElementById('f_related_count').value = 0;
  document.getElementById('f_addon_count').value = 0;
  document.getElementById('f_news_elements_other').style.display = 'none';
  document.getElementById('lookupStatus').textContent = '';
}
document.getElementById('btnResetForm').addEventListener('click', resetForm);

document.getElementById('f_news_elements').addEventListener('change', function(){
  document.getElementById('f_news_elements_other').style.display = this.value === 'سایر' ? '' : 'none';
});

function recomputeTagCount(){
  const v = document.getElementById('f_tag').value.trim();
  const n = v === '' ? 0 : v.split(/[،,]/).map(s=>s.trim()).filter(s=>s!=='').length;
  document.getElementById('f_tag_count').value = n;
}
document.getElementById('f_tag').addEventListener('input', recomputeTagCount);

document.getElementById('btnLookup').addEventListener('click', async function(){
  const code = document.getElementById('f_code').value.trim();
  if (!code){ return; }
  document.getElementById('lookupStatus').textContent = 'در حال دریافت اطلاعات...';
  try {
    const res = await fetch('api_lookup.php?date=' + encodeURIComponent(ENTRY_DATE) + '&code=' + encodeURIComponent(code));
    const data = await res.json();
    if (!data.ok){ document.getElementById('lookupStatus').textContent = 'خبری با این کد پیدا نشد؛ لطفاً فیلدها را دستی وارد کنید.'; return; }

    if (data.reporter){ document.getElementById('f_reporter').value = data.reporter; }
    document.getElementById('f_link').value = data.news_link || '';
    document.getElementById('f_publisher').value = data.publisher || '';
    document.getElementById('f_title').value = data.title || '';
    document.getElementById('f_news_type').value = data.news_type || '';
    document.getElementById('f_service_main').value = data.service_main || '';
    document.getElementById('f_service_sub').value = data.service_sub || '';
    if (data.source){ document.getElementById('f_source').value = data.source; }

    document.getElementById('lookupStatus').textContent = data.found_in_excel
      ? 'اطلاعات از فایل اکسل این تاریخ دریافت شد.'
      : 'این کد در فایل اکسل پیدا نشد؛ لطفاً فیلدهای ناشر/تیتر/سرویس را دستی تکمیل کنید.';
  } catch (e) {
    document.getElementById('lookupStatus').textContent = 'خطا در ارتباط با سرور.';
  }
});

document.getElementById('entryForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const payload = {
    id: document.getElementById('f_edit_id').value,
    entry_date: ENTRY_DATE,
    reporter: document.getElementById('f_reporter').value,
    news_id: document.getElementById('f_code').value.trim(),
    news_link: document.getElementById('f_link').value,
    publisher: document.getElementById('f_publisher').value,
    title: document.getElementById('f_title').value,
    news_type: document.getElementById('f_news_type').value,
    service_main: document.getElementById('f_service_main').value,
    service_sub: document.getElementById('f_service_sub').value,
    subject: document.getElementById('f_subject').value,
    real_news_type: document.getElementById('f_real_news_type').value,
    interviewee: document.getElementById('f_interviewee').value,
    news_elements: document.getElementById('f_news_elements').value === 'سایر'
      ? document.getElementById('f_news_elements_other').value.trim()
      : document.getElementById('f_news_elements').value,
    source: document.getElementById('f_source').value,
    description: document.getElementById('f_description').value,
    tag: document.getElementById('f_tag').value,
    tag_note: document.getElementById('f_tag_note').value,
    related_links_count: document.getElementById('f_related_count').value,
    related_links_note: document.getElementById('f_related_note').value,
    addon_count: document.getElementById('f_addon_count').value,
    addon_note: document.getElementById('f_addon_note').value,
  };
  const res = await fetch('save_entry.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
  const data = await res.json();
  if (data.ok){ resetForm(); loadEntries(); } else { alert(data.msg || 'خطا در ذخیره‌سازی.'); }
});

function renderTable(){
  const svc = document.getElementById('serviceFilter').value;
  const rows = svc ? currentEntries.filter(r => r.service_main === svc) : currentEntries;
  const body = document.getElementById('entriesBody');
  if (!rows.length){ body.innerHTML = '<tr><td colspan="15" class="text-center text-muted">موردی ثبت نشده است.</td></tr>'; return; }
  body.innerHTML = rows.map((r,i) => `
    <tr>
      <td>${i+1}</td>
      <td>${escapeHtml(r.reporter||'')}</td>
      <td>${escapeHtml(r.publisher||'')}</td>
      <td>${escapeHtml(r.title||'')}</td>
      <td>${escapeHtml(r.service_main||'')}</td>
      <td>${escapeHtml(r.service_sub||'')}</td>
      <td>${escapeHtml(r.subject||'')}</td>
      <td>${escapeHtml(r.news_type||'')}${r.real_news_type ? ' / '+escapeHtml(r.real_news_type):''}</td>
      <td>${escapeHtml(r.news_elements||'')}</td>
      <td>${escapeHtml(r.source||'')}</td>
      <td>${escapeHtml(r.description||'')}</td>
      <td>${r.news_link ? '<a href="'+escapeHtml(r.news_link)+'" target="_blank">لینک</a>' : ''}</td>
      <td>${escapeHtml(r.tag||'')}</td>
      <td>${r.addon_count||0}</td>
      <td>
        <a class="btn btn-sm btn-outline-primary" href="entries_edit.php?id=${r.id}">ویرایش</a>
        <a class="btn btn-sm btn-outline-danger" href="entries_delete.php?id=${r.id}" onclick="return confirm('حذف شود؟')">حذف</a>
      </td>
    </tr>`).join('');
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

document.getElementById('serviceFilter').addEventListener('change', renderTable);

async function loadEntries(){
  const res = await fetch('list_entries.php?date=' + encodeURIComponent(ENTRY_DATE));
  const data = await res.json();
  currentEntries = data.rows || [];
  const services = [...new Set(currentEntries.map(r => r.service_main).filter(Boolean))].sort();
  const sel = document.getElementById('serviceFilter');
  const prev = sel.value;
  sel.innerHTML = '<option value="">همه سرویس‌ها</option>' + services.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join('');
  sel.value = services.includes(prev) ? prev : '';
  renderTable();
}
loadEntries();
</script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>