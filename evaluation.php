<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();
$today = todayJalali();
require __DIR__ . '/includes/layout_top.php';
?>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<style>.ts-wrapper .ts-control{min-height:calc(1.5em + .75rem + 2px);}</style>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <h5 class="mb-0">ارزیابی</h5>
  <div class="d-flex flex-wrap gap-2">
    <a href="backfill_views.php" class="btn btn-outline-secondary btn-sm">به‌روزرسانی بازدید داده‌های قدیمی</a>
    <a href="backfill_site.php" class="btn btn-outline-secondary btn-sm">به‌روزرسانی زبان/سایت داده‌های قدیمی</a>
  </div>
</div>

<div class="card shadow-sm p-4 mb-4">
  <div class="row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label">از تاریخ</label>
      <input type="text" id="fFrom" class="form-control jalali-date-input" value="<?= htmlspecialchars($today) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">تا تاریخ</label>
      <input type="text" id="fTo" class="form-control jalali-date-input" value="<?= htmlspecialchars($today) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">تفکیک نمودارها</label>
      <select id="fGranularity" class="form-select">
        <option value="day">روز</option>
        <option value="month">ماه</option>
      </select>
    </div>
    <div class="col-md-1 d-grid">
      <button id="btnLoad" class="btn btn-primary">نمایش</button>
    </div>
  </div>
  <div id="filterMsg" class="text-muted small mt-2"></div>
</div>

<div id="reportArea" style="display:none">

  <!-- آمار کلی -->
  <div class="card shadow-sm p-4 mb-4">
    <div class="row g-3 align-items-end mb-2">
      <div class="col-md-6"><h6 class="mb-0">آمار کلی</h6></div>
      <div class="col-md-6">
        <label class="form-label">زبان</label>
        <select id="ovcSite" class="form-select"><option value="">همه</option></select>
      </div>
    </div>
    <div class="row g-3 align-items-end mb-3">
      <div class="col-md-6"></div>
      <div class="col-md-6">
        <label class="form-label">سرویس</label>
        <select id="ovcService" class="form-select"><option value="">همه سرویس‌ها (کل ایسنا)</option></select>
      </div>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-sm-2"><div class="p-2 bg-light rounded text-center">تعداد کل اخبار<br><strong id="ovcTotalAll">-</strong></div></div>
      <div class="col-sm-2"><div class="p-2 bg-light rounded text-center">تعداد اخبار سرویس انتخابی<br><strong id="ovcTotalScope">-</strong></div></div>
      <div class="col-sm-2"><div class="p-2 bg-light rounded text-center">سهم از کل<br><strong id="ovcShare">-</strong></div></div>
      <div class="col-sm-2"><div class="p-2 bg-light rounded text-center">میانگین بازدید<br><strong id="ovcAvgViews">-</strong></div></div>
      <div class="col-sm-2"><div class="p-2 bg-light rounded text-center">مجموع بازدید<br><strong id="ovcSumViews">-</strong></div></div>
      <div class="col-sm-2"><div class="p-2 bg-light rounded text-center">سهم از بازدید کل<br><strong id="ovcViewsShare">-</strong></div></div>
    </div>
    <canvas id="svcChart" height="90"></canvas>
    <div class="row g-4 mt-1">
      <div class="col-md-5">
        <h6 class="mb-2">سهم انواع خبر</h6>
        <canvas id="typePie" height="220"></canvas>
      </div>
      <div class="col-md-7">
        <h6 class="mb-2">میانگین بازدید به تفکیک نوع خبر</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered sortable-table">
            <thead><tr><th>نوع خبر</th><th>تعداد</th><th>میانگین بازدید</th></tr></thead>
            <tbody id="typeAvgViewsTable"></tbody>
          </table>
        </div>
      </div>
      <div class="col-md-5">
        <h6 class="mb-2">پرکارترین خبرنگار در هر نوع خبر</h6>
        <canvas id="reporterTypePie" height="220"></canvas>
      </div>
    </div>
    <hr class="my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
      <h6 class="mb-0">توزیع ساعتی ارسال خبر (۰ تا ۲۳)</h6>
      <div style="min-width:220px">
        <select id="hourlyType" class="form-select form-select-sm"><option value="">همه انواع خبر</option></select>
      </div>
    </div>
    <canvas id="hourlyChart" height="90"></canvas>
  </div>

  <!-- زیرسرویس -->
  <div class="card shadow-sm p-4 mb-4">
    <h6 class="mb-3">عملکرد زیرسرویس‌ها</h6>
    <div id="subNeedService" class="text-muted small">برای مشاهده زیرسرویس‌ها، ابتدا یک سرویس مشخص (نه «همه سرویس‌ها») را از بخش آمار کلی انتخاب کنید.</div>
    <div id="subSelectors" class="row g-3 mb-3" style="display:none">
      <div class="col-md-6">
        <label class="form-label">زیرسرویس</label>
        <select id="subName" class="form-select"><option value="">— انتخاب کنید —</option></select>
      </div>
      <div class="col-md-6">
        <label class="form-label">فیلتر نوع خبر (اختیاری)</label>
        <select id="subType" class="form-select"><option value="">همه انواع خبر</option></select>
      </div>
    </div>
    <div id="subEmpty" class="text-muted small" style="display:none">برای مشاهده نمودار، یک زیرسرویس انتخاب کنید.</div>
    <div id="subBody" style="display:none">
      <div class="row g-2 mb-2">
        <div class="col-sm-6"><div class="p-2 bg-light rounded text-center">تعداد اخبار (با فیلتر نوع): <strong id="subTotalCount">-</strong></div></div>
        <div class="col-sm-6"><div class="p-2 bg-light rounded text-center">میانگین بازدید (با فیلتر نوع): <strong id="subTotalViews">-</strong></div></div>
      </div>
      <canvas id="subChart" height="90"></canvas>
      <h6 class="mt-4 mb-2">سهم انواع خبر (بدون اعمال فیلتر نوع خبر)</h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered sortable-table">
          <thead><tr><th>نوع خبر</th><th>تعداد</th><th>درصد</th></tr></thead>
          <tbody id="subTypeTable"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- پربازدیدترین اخبار -->
  <div class="card shadow-sm p-4 mb-4">
    <div class="row g-3 align-items-end mb-3">
      <div class="col-md-4"><h6 class="mb-0">پربازدیدترین خبرها</h6></div>
      <div class="col-md-2">
        <label class="form-label">تعداد</label>
        <select id="topLimit" class="form-select">
          <option value="5">۵</option>
          <option value="10" selected>۱۰</option>
          <option value="15">۱۵</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">فیلتر نوع خبر</label>
        <select id="topType" class="form-select"><option value="">همه انواع خبر</option></select>
      </div>
      <div class="col-md-3">
        <label class="form-label">فیلتر زیرسرویس</label>
        <select id="topSubservice" class="form-select"><option value="">همه زیرسرویس‌ها</option></select>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle sortable-table">
        <thead><tr><th>#</th><th>تیتر</th><th>خبرنگار</th><th>ناشر</th><th>زیرسرویس</th><th>نوع خبر</th><th>بازدید</th></tr></thead>
        <tbody id="topNewsTable"></tbody>
      </table>
    </div>
  </div>

  <!-- خبرنگاران -->
  <div class="card shadow-sm p-4 mb-4">
    <h6 class="mb-3">عملکرد خبرنگاران</h6>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">خبرنگار</label>
        <select id="repName" class="form-select"><option value="">— انتخاب کنید —</option></select>
      </div>
      <div class="col-md-6">
        <label class="form-label">فیلتر نوع خبر (اختیاری)</label>
        <select id="repType" class="form-select"><option value="">همه انواع خبر</option></select>
      </div>
    </div>
    <div id="repEmpty" class="text-muted small">برای مشاهده نمودار، یک خبرنگار انتخاب کنید.</div>
    <div id="repBody" style="display:none">
      <div class="row g-2 mb-2">
        <div class="col-sm-6"><div class="p-2 bg-light rounded text-center">تعداد اخبار (با فیلتر نوع): <strong id="repTotalCount">-</strong></div></div>
        <div class="col-sm-6"><div class="p-2 bg-light rounded text-center">میانگین بازدید (با فیلتر نوع): <strong id="repTotalViews">-</strong></div></div>
      </div>
      <canvas id="repChart" height="90"></canvas>
      <h6 class="mt-4 mb-2">سهم انواع خبر (بدون اعمال فیلتر نوع خبر)</h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered sortable-table">
          <thead><tr><th>نوع خبر</th><th>تعداد</th><th>درصد</th></tr></thead>
          <tbody id="repTypeTable"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ناشران -->
  <div class="card shadow-sm p-4 mb-4">
    <h6 class="mb-3">عملکرد ناشران</h6>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">ناشر</label>
        <select id="pubName" class="form-select"><option value="">— انتخاب کنید —</option></select>
      </div>
      <div class="col-md-6">
        <label class="form-label">فیلتر نوع خبر (اختیاری)</label>
        <select id="pubType" class="form-select"><option value="">همه انواع خبر</option></select>
      </div>
    </div>
    <div id="pubEmpty" class="text-muted small">برای مشاهده نمودار، یک ناشر انتخاب کنید.</div>
    <div id="pubBody" style="display:none">
      <div class="row g-2 mb-2">
        <div class="col-sm-6"><div class="p-2 bg-light rounded text-center">تعداد اخبار (با فیلتر نوع): <strong id="pubTotalCount">-</strong></div></div>
        <div class="col-sm-6"><div class="p-2 bg-light rounded text-center">میانگین بازدید (با فیلتر نوع): <strong id="pubTotalViews">-</strong></div></div>
      </div>
      <canvas id="pubChart" height="90"></canvas>
      <h6 class="mt-4 mb-2">سهم انواع خبر (بدون اعمال فیلتر نوع خبر)</h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered sortable-table">
          <thead><tr><th>نوع خبر</th><th>تعداد</th><th>درصد</th></tr></thead>
          <tbody id="pubTypeTable"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- بررسی کیفی -->
  <div class="card shadow-sm p-4 mb-4">
    <h6 class="mb-3">بررسی کیفی (خروجی بررسی‌های نظارت)</h6>
    <p class="text-muted small">این بخش از داده‌های ثبت‌شده در بخش «نظارت» (ثبت خبر روزانه) استفاده می‌کند و بازه/سرویسِ بالای صفحه روی آن هم اعمال می‌شود.</p>
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label class="form-label">فیلتر زیرسرویس</label>
        <select id="qcSubservice" class="form-select"><option value="">همه زیرسرویس‌ها</option></select>
      </div>
      <div class="col-md-4">
        <label class="form-label">فیلتر خبرنگار</label>
        <select id="qcReporter" class="form-select"><option value="">همه خبرنگاران</option></select>
      </div>
      <div class="col-md-4">
        <label class="form-label">فیلتر نوع خبر</label>
        <select id="qcNewsType" class="form-select"><option value="">همه انواع خبر</option></select>
      </div>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-sm-4"><div class="p-2 bg-light rounded text-center">تعداد بررسی‌شده<br><strong id="qcReviewed">-</strong></div></div>
      <div class="col-sm-4"><div class="p-2 bg-light rounded text-center">تعداد کل اخبار همان سرویس/بازه<br><strong id="qcTotal">-</strong></div></div>
      <div class="col-sm-4"><div class="p-2 bg-light rounded text-center">درصد پوشش بررسی<br><strong id="qcCoverage">-</strong></div></div>
    </div>
    <canvas id="qcCoverageChart" height="70"></canvas>

    <div class="row g-4 mt-3">
      <div class="col-md-6">
        <h6 class="mb-2">تطابق نوع خبر با نوع واقعی خبر</h6>
        <canvas id="qcMatchChart" height="220"></canvas>
      </div>
      <div class="col-md-6">
        <h6 class="mb-2">وضعیت عناصر خبری</h6>
        <canvas id="qcElementsChart" height="220"></canvas>
      </div>
    </div>

    <h6 class="mt-4 mb-2">لیست خبرهای بررسی‌شده</h6>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle sortable-table">
        <thead><tr><th>تیتر</th><th>سرویس</th><th>زیرسرویس</th><th>نوع خبر</th><th>نوع خبر واقعی</th><th>بازدید</th><th>خبرنگار</th><th>ناشر</th></tr></thead>
        <tbody id="qcItemsTable"></tbody>
      </table>
    </div>
  </div>

</div>

<script>
const API = 'evaluation_api.php';
let svcChart=null, hourlyChart=null, subChart=null, repChart=null, pubChart=null, typePieChart=null, reporterTypePieChart=null;
let qcCoverageChart=null, qcMatchChart=null, qcElementsChart=null;
const PALETTE = ['#123a73','#1f5aa8','#e0a800','#c0392b','#16a085','#8e44ad','#d35400','#2c3e50','#27ae60','#7f8c8d','#e67e22','#2980b9','#c2185b'];

function qs(id){ return document.getElementById(id); }
function currentRange(){ return { from: qs('fFrom').value.trim(), to: qs('fTo').value.trim(), granularity: qs('fGranularity').value }; }
function currentService(){ return qs('ovcService').value; }
function currentSite(){ return qs('ovcSite').value; }

async function fetchJson(params){
  const url = API + '?' + new URLSearchParams(params).toString();
  const res = await fetch(url);
  return res.json();
}

function fillSelect(sel, items, placeholder, keepValue){
  const prev = keepValue ? sel.value : null;
  if (sel.tomselect){
    const ts = sel.tomselect;
    ts.clearOptions();
    ts.addOption({value:'', text: placeholder});
    items.forEach(v => ts.addOption({value:v, text:v}));
    ts.refreshOptions(false);
    ts.setValue((prev && items.includes(prev)) ? prev : '', true);
    return;
  }
  sel.innerHTML = '<option value="">' + placeholder + '</option>';
  items.forEach(v => { const o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o); });
  if (prev && items.includes(prev)) sel.value = prev;
}

function niceBounds(values){
  const nums = values.filter(v => typeof v === 'number' && !isNaN(v));
  if (!nums.length) return { min: 0, max: 1 };
  const min = Math.min(...nums), max = Math.max(...nums);
  if (min === max){ const pad = min === 0 ? 1 : min * 0.3; return { min: Math.max(0, min - pad), max: max + pad }; }
  const pad = (max - min) * 0.2;
  return { min: Math.max(0, Math.floor(min - pad)), max: Math.ceil(max + pad) };
}

function makeComboChart(canvasId, series, countLabel, viewsLabel){
  const ctx = document.getElementById(canvasId).getContext('2d');
  const labels = series.map(s => s.label);
  const viewsBounds = niceBounds(series.map(s => s.avg_views));
  return new Chart(ctx, {
    data: { labels: labels, datasets: [
      { type:'bar', label: countLabel, data: series.map(s => s.count), backgroundColor:'rgba(31,90,168,0.55)', yAxisID:'y' },
      { type:'line', label: viewsLabel, data: series.map(s => s.avg_views), borderColor:'#e0a800', backgroundColor:'#e0a800', tension:0.3, yAxisID:'y1' }
    ]},
    options: { responsive:true, interaction:{mode:'index', intersect:false}, scales:{
      y:  { position:'left', title:{display:true, text:countLabel}, beginAtZero:true },
      y1: { position:'right', title:{display:true, text:viewsLabel}, min: viewsBounds.min, max: viewsBounds.max, grid:{drawOnChartArea:false} }
    }}
  });
}

function makePieChart(canvasId, items){
  const ctx = document.getElementById(canvasId).getContext('2d');
  return new Chart(ctx, { type:'doughnut', data: {
    labels: items.map(i => i.type), datasets: [{ data: items.map(i => i.count), backgroundColor: items.map((_,idx)=>PALETTE[idx % PALETTE.length]) }]
  }, options: { responsive:true, plugins:{ legend:{position:'bottom'}, tooltip:{callbacks:{label:(c)=>{
    const item = items[c.dataIndex]; return `${item.type}: ${item.count} (${item.percent}%)`;
  }}}}}});
}

function makeLabeledPieChart(canvasId, items){
  const ctx = document.getElementById(canvasId).getContext('2d');
  const total = items.reduce((s,i)=>s+i.count,0) || 1;
  return new Chart(ctx, { type:'doughnut', data: {
    labels: items.map(i => i.label), datasets: [{ data: items.map(i => i.count), backgroundColor: items.map((_,idx)=>PALETTE[idx % PALETTE.length]) }]
  }, options: { responsive:true, plugins:{ legend:{position:'bottom'}, tooltip:{callbacks:{label:(c)=>{
    const item = items[c.dataIndex]; const pct = Math.round(item.count*1000/total)/10;
    return `${item.label}: ${item.count} (${pct}%)`;
  }}}}}});
}

function renderTypeTable(tbody, rows){
  tbody.innerHTML = '';
  if (!rows.length){ tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">داده‌ای نیست</td></tr>'; return; }
  rows.forEach(r => { const tr = document.createElement('tr'); tr.innerHTML = `<td>${r.type}</td><td>${r.count}</td><td>${r.percent}%</td>`; tbody.appendChild(tr); });
}
function renderAvgViewsTable(tbody, rows){
  tbody.innerHTML = '';
  if (!rows.length){ tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">داده‌ای نیست</td></tr>'; return; }
  rows.forEach(r => { const tr = document.createElement('tr'); tr.innerHTML = `<td>${r.type}</td><td>${r.count}</td><td>${r.avg_views}</td>`; tbody.appendChild(tr); });
}
function renderTopNewsTable(items){
  const tbody = qs('topNewsTable'); tbody.innerHTML = '';
  if (!items.length){ tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">داده‌ای نیست</td></tr>'; return; }
  items.forEach((it, idx) => {
    const tr = document.createElement('tr');
    const titleCell = it.link ? `<a href="${it.link}" target="_blank" rel="noopener">${it.title || '(بدون تیتر)'}</a>` : (it.title || '(بدون تیتر)');
    tr.innerHTML = `<td>${idx+1}</td><td>${titleCell}</td><td>${it.reporter||'-'}</td><td>${it.publisher||'-'}</td><td>${it.service_sub||'-'}</td><td>${it.news_type||'-'}</td><td>${it.views.toLocaleString()}</td>`;
    tbody.appendChild(tr);
  });
}

// ===================== آمار کلی =====================

async function loadSiteOptions(){
  const {from, to} = currentRange();
  const data = await fetchJson({action:'sites', from, to});
  const sel = qs('ovcSite');
  sel.innerHTML = '<option value="">همه</option>';
  if (data.ok) data.items.forEach(v => { const o = document.createElement('option'); o.value=v; o.textContent=v; sel.appendChild(o); });
}

async function loadServiceOptions(){
  const {from, to} = currentRange();
  const site = currentSite();
  const data = await fetchJson({action:'services', from, to, site});
  const sel = qs('ovcService');
  sel.innerHTML = '<option value="">همه سرویس‌ها (کل ایسنا)</option>';
  if (data.ok) data.items.forEach(v => { const o = document.createElement('option'); o.value=v; o.textContent=v; sel.appendChild(o); });
}

async function loadOverview(){
  const {from, to, granularity} = currentRange();
  const service = currentService();
  const site = currentSite();
  const data = await fetchJson({action:'overview', from, to, service, granularity, site});
  if (!data.ok) return;
  qs('ovcTotalAll').textContent = data.total_all;
  qs('ovcTotalScope').textContent = data.total_scope;
  qs('ovcShare').textContent = data.share_percent === null ? '۱۰۰٪' : data.share_percent + '%';
  qs('ovcAvgViews').textContent = data.avg_views;
  qs('ovcSumViews').textContent = data.sum_views.toLocaleString();
  qs('ovcViewsShare').textContent = data.sum_views_share_percent === null ? '۱۰۰٪' : data.sum_views_share_percent + '%';
  if (svcChart) svcChart.destroy();
  svcChart = makeComboChart('svcChart', data.series, 'تعداد اخبار', 'میانگین بازدید');
  if (typePieChart) typePieChart.destroy();
  typePieChart = makePieChart('typePie', data.type_pie);
  renderAvgViewsTable(qs('typeAvgViewsTable'), data.type_avg_views);
  if (reporterTypePieChart) reporterTypePieChart.destroy();
  reporterTypePieChart = makeLabeledPieChart('reporterTypePie', data.type_top_reporter_pie.map(it => ({ label: `${it.type} — ${it.reporter}`, count: it.count })));
}

async function loadHourly(){
  const {from, to} = currentRange();
  const service = currentService();
  const site = currentSite();
  const newsType = qs('hourlyType').value;
  const data = await fetchJson({action:'hourly', from, to, service, news_type:newsType, site});
  if (!data.ok) return;
  if (hourlyChart) hourlyChart.destroy();
  hourlyChart = makeComboChart('hourlyChart', data.series, 'تعداد اخبار', 'میانگین بازدید');
}

// ===================== زیرسرویس =====================

async function loadSubserviceOptions(){
  const service = currentService();
  if (!service){
    qs('subNeedService').style.display=''; qs('subSelectors').style.display='none';
    qs('subEmpty').style.display='none'; qs('subBody').style.display='none';
    return;
  }
  qs('subNeedService').style.display='none'; qs('subSelectors').style.display='';
  qs('subEmpty').style.display=''; qs('subBody').style.display='none';
  const {from, to} = currentRange();
  const site = currentSite();
  const data = await fetchJson({action:'subservices', from, to, service, site});
  if (data.ok) fillSelect(qs('subName'), data.items, '— انتخاب کنید —');
}

async function loadSubserviceChart(){
  const {from, to, granularity} = currentRange();
  const service = currentService();
  const site = currentSite();
  const subservice = qs('subName').value;
  const newsType = qs('subType').value;
  if (!subservice){ qs('subBody').style.display='none'; qs('subEmpty').style.display=''; return; }
  const data = await fetchJson({action:'subservice_series', from, to, service, subservice, granularity, news_type:newsType, site});
  if (!data.ok) return;
  qs('subEmpty').style.display='none'; qs('subBody').style.display='';
  qs('subTotalCount').textContent = data.total_count;
  qs('subTotalViews').textContent = data.total_avg_views;
  if (subChart) subChart.destroy();
  subChart = makeComboChart('subChart', data.series, 'تعداد اخبار', 'میانگین بازدید');
  renderTypeTable(qs('subTypeTable'), data.type_table);
}

// ===================== پربازدیدترین اخبار =====================

async function loadTopSubserviceOptions(){
  const service = currentService();
  const site = currentSite();
  const {from, to} = currentRange();
  if (!service){ fillSelect(qs('topSubservice'), [], 'همه زیرسرویس‌ها'); return; }
  const data = await fetchJson({action:'subservices', from, to, service, site});
  if (data.ok) fillSelect(qs('topSubservice'), data.items, 'همه زیرسرویس‌ها');
}

async function loadTopNews(){
  const {from, to} = currentRange();
  const service = currentService();
  const site = currentSite();
  const limit = qs('topLimit').value;
  const newsType = qs('topType').value;
  const subservice = qs('topSubservice').value;
  const data = await fetchJson({action:'top_news', from, to, service, limit, news_type:newsType, subservice, site});
  if (data.ok) renderTopNewsTable(data.items);
}

// ===================== خبرنگار / ناشر =====================

async function loadPersonOptions(role){
  const {from, to} = currentRange();
  const service = currentService();
  const site = currentSite();
  const data = await fetchJson({action:'persons', from, to, service, role, site});
  const sel = role === 'reporter' ? qs('repName') : qs('pubName');
  if (data.ok) fillSelect(sel, data.items, '— انتخاب کنید —');
  if (role === 'reporter'){ qs('repBody').style.display='none'; qs('repEmpty').style.display=''; }
  else { qs('pubBody').style.display='none'; qs('pubEmpty').style.display=''; }
}

async function loadPersonSection(role){
  const {from, to, granularity} = currentRange();
  const service = currentService();
  const site = currentSite();
  const nameSel = role === 'reporter' ? qs('repName') : qs('pubName');
  const typeSel = role === 'reporter' ? qs('repType') : qs('pubType');
  const name = nameSel.value; const newsType = typeSel.value;
  const bodyEl = role === 'reporter' ? qs('repBody') : qs('pubBody');
  const emptyEl = role === 'reporter' ? qs('repEmpty') : qs('pubEmpty');
  if (!name){ bodyEl.style.display='none'; emptyEl.style.display=''; return; }
  const data = await fetchJson({action:'person_series', from, to, service, role, name, granularity, news_type:newsType, site});
  if (!data.ok) return;
  emptyEl.style.display='none'; bodyEl.style.display='';
  if (role === 'reporter'){
    qs('repTotalCount').textContent = data.total_count; qs('repTotalViews').textContent = data.total_avg_views;
    if (repChart) repChart.destroy();
    repChart = makeComboChart('repChart', data.series, 'تعداد اخبار', 'میانگین بازدید');
    renderTypeTable(qs('repTypeTable'), data.type_table);
  } else {
    qs('pubTotalCount').textContent = data.total_count; qs('pubTotalViews').textContent = data.total_avg_views;
    if (pubChart) pubChart.destroy();
    pubChart = makeComboChart('pubChart', data.series, 'تعداد اخبار', 'میانگین بازدید');
    renderTypeTable(qs('pubTypeTable'), data.type_table);
  }
}

// ===================== بررسی کیفی =====================

async function loadQcOptions(){
  const {from, to} = currentRange();
  const service = currentService();
  const site = currentSite();
  const [subRes, repRes, typeRes] = await Promise.all([
    service ? fetchJson({action:'subservices', from, to, service, site}) : Promise.resolve({ok:false, items:[]}),
    fetchJson({action:'qc_reporters', from, to, service, site}),
    fetchJson({action:'qc_news_types', from, to, service, site}),
  ]);
  fillSelect(qs('qcSubservice'), subRes.ok ? subRes.items : [], 'همه زیرسرویس‌ها');
  fillSelect(qs('qcReporter'), repRes.ok ? repRes.items : [], 'همه خبرنگاران');
  fillSelect(qs('qcNewsType'), typeRes.ok ? typeRes.items : [], 'همه انواع خبر');
}

function renderQcItemsTable(items){
  const tbody = qs('qcItemsTable'); tbody.innerHTML='';
  if (!items.length){ tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">داده‌ای نیست</td></tr>'; return; }
  items.forEach(it => {
    const tr = document.createElement('tr');
    const titleCell = it.link ? `<a href="${it.link}" target="_blank" rel="noopener">${it.title || '(بدون تیتر)'}</a>` : (it.title || '(بدون تیتر)');
    tr.innerHTML = `<td>${titleCell}</td><td>${it.service_main||'-'}</td><td>${it.service_sub||'-'}</td><td>${it.news_type||'-'}</td><td>${it.real_news_type||'-'}</td><td>${(it.views||0).toLocaleString()}</td><td>${it.reporter||'-'}</td><td>${it.publisher||'-'}</td>`;
    tbody.appendChild(tr);
  });
}

async function loadQcSection(){
  const {from, to} = currentRange();
  const service = currentService();
  const site = currentSite();
  const subservice = qs('qcSubservice').value;
  const reporter = qs('qcReporter').value;
  const newsType = qs('qcNewsType').value;
  const params = {from, to, service, subservice, reporter, news_type:newsType, site};

  const [summary, items, match, elements] = await Promise.all([
    fetchJson({action:'qc_summary', ...params}),
    fetchJson({action:'qc_items', ...params}),
    fetchJson({action:'qc_match', ...params}),
    fetchJson({action:'qc_elements', ...params}),
  ]);

  if (summary.ok){
    qs('qcReviewed').textContent = summary.reviewed_count;
    qs('qcTotal').textContent = summary.total_count;
    qs('qcCoverage').textContent = summary.coverage_percent === null ? '-' : summary.coverage_percent + '%';
    if (qcCoverageChart) qcCoverageChart.destroy();
    qcCoverageChart = new Chart(document.getElementById('qcCoverageChart').getContext('2d'), {
      type:'bar',
      data:{ labels:['بررسی‌شده','کل اخبار سرویس/بازه'], datasets:[{ data:[summary.reviewed_count, summary.total_count], backgroundColor:['#1f5aa8','#c9d3e6'] }] },
      options:{ indexAxis:'y', responsive:true, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true}} }
    });
  }
  if (items.ok) renderQcItemsTable(items.items);
  if (match.ok){
    if (qcMatchChart) qcMatchChart.destroy();
    qcMatchChart = makeLabeledPieChart('qcMatchChart', match.items);
  }
  if (elements.ok){
    if (qcElementsChart) qcElementsChart.destroy();
    qcElementsChart = makeLabeledPieChart('qcElementsChart', elements.items);
  }
}

// ===================== هماهنگ‌کننده کلی =====================

async function loadNewsTypeOptionsForScope(){
  const {from, to} = currentRange();
  const service = currentService();
  const site = currentSite();
  const data = await fetchJson({action:'news_types', from, to, service, site});
  if (!data.ok) return;
  [qs('hourlyType'), qs('repType'), qs('pubType'), qs('subType'), qs('topType')].forEach(sel => { fillSelect(sel, data.items, 'همه انواع خبر'); });
}

async function refreshScope(){
  qs('reportArea').style.display = '';
  await loadNewsTypeOptionsForScope();
  await Promise.all([
    loadOverview(), loadHourly(), loadSubserviceOptions(), loadTopSubserviceOptions(),
    loadPersonOptions('reporter'), loadPersonOptions('publisher'), loadTopNews(),
    loadQcOptions().then(loadQcSection),
  ]);
}

async function onSiteChange(){
  await loadServiceOptions();
  await refreshScope();
}

async function fullReload(){
  const {from, to} = currentRange();
  if (!from || !to){ qs('filterMsg').textContent = 'لطفاً بازه تاریخ را انتخاب کنید.'; return; }
  qs('filterMsg').textContent = '';
  await loadSiteOptions();
  await loadServiceOptions();
  await refreshScope();
}

qs('btnLoad').addEventListener('click', fullReload);
qs('fFrom').addEventListener('change', fullReload);
qs('fTo').addEventListener('change', fullReload);
qs('ovcSite').addEventListener('change', onSiteChange);
qs('ovcService').addEventListener('change', refreshScope);

qs('fGranularity').addEventListener('change', () => {
  if (qs('reportArea').style.display === 'none') return;
  loadOverview(); loadHourly();
  if (qs('subName').value) loadSubserviceChart();
  if (qs('repName').value) loadPersonSection('reporter');
  if (qs('pubName').value) loadPersonSection('publisher');
});

qs('hourlyType').addEventListener('change', loadHourly);
qs('subName').addEventListener('change', loadSubserviceChart);
qs('subType').addEventListener('change', loadSubserviceChart);
qs('topLimit').addEventListener('change', loadTopNews);
qs('topType').addEventListener('change', loadTopNews);
qs('topSubservice').addEventListener('change', loadTopNews);
qs('repName').addEventListener('change', () => loadPersonSection('reporter'));
qs('repType').addEventListener('change', () => loadPersonSection('reporter'));
qs('pubName').addEventListener('change', () => loadPersonSection('publisher'));
qs('pubType').addEventListener('change', () => loadPersonSection('publisher'));
qs('qcSubservice').addEventListener('change', loadQcSection);
qs('qcReporter').addEventListener('change', loadQcSection);
qs('qcNewsType').addEventListener('change', loadQcSection);

function attachSelectSearch(selectId){
  const sel = qs(selectId);
  if (!sel || sel.tomselect) return;
  new TomSelect(sel, {
    create: false,
    maxOptions: 1000,
    allowEmptyOption: true,
    onChange: function(){ sel.dispatchEvent(new Event('change')); },
    score: function(search){
      const words = search.toLowerCase().trim().split(/\s+/).filter(Boolean);
      return function(item){
        const text = (item.text || '').toLowerCase();
        return words.length === 0 || words.some(w => text.includes(w)) ? 1 : 0;
      };
    }
  });
}
['hourlyType','subName','subType','topType','topSubservice','repName','repType','pubName','pubType','qcSubservice','qcReporter','qcNewsType']
  .forEach(attachSelectSearch);

document.addEventListener('DOMContentLoaded', fullReload);
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
