<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/jsondb.php';
require_once __DIR__ . '/includes/xlsx.php';
requireLoginPage();

// --- حالت AJAX: هر درخواست فقط یک فایل آرشیوشده را پردازش می‌کند تا روی هاست اشتراکی تایم‌اوت/۵۰۳ رخ ندهد ---
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_GET['ajax'] === 'list') {
        $ids = [];
        foreach (excelFilesAll() as $f) { $ids[] = (int)($f['id'] ?? 0); }
        echo json_encode(['ok' => true, 'ids' => array_values(array_filter($ids))], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['ajax'] === 'process') {
        $fileId = (int)($_GET['file_id'] ?? 0);
        $updatedRows = 0; $updatedEntries = 0;
        try {
            $file = null;
            foreach (excelFilesAll() as $f) { if ((int)($f['id'] ?? 0) === $fileId) { $file = $f; break; } }
            $path = $file['stored_path'] ?? '';
            if (!$file || $path === '' || !is_file($path)) {
                echo json_encode(['ok' => true, 'skipped' => true], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $parsed = parseExcelRows($path);
            $fallbackDate = (string)($file['report_date'] ?? $file['detected_date'] ?? '');
            $rows = fillMissingRowDates($parsed['rows'], $fallbackDate);

            $siteByCode = [];     // code => site (در همین فایل)
            $siteByDateCode = []; // "date|code" => site
            foreach ($rows as $r) {
                $code = trim((string)($r['code'] ?? ''));
                $site = trim((string)($r['site'] ?? ''));
                if ($code === '' || $site === '') continue;
                $siteByCode[$code] = $site;
                $date = trim((string)($r['date'] ?? ''));
                if ($date !== '') $siteByDateCode[$date . '|' . $code] = $site;
            }

            jsonUpdate('excel_rows', function ($all) use ($fileId, $siteByCode, &$updatedRows) {
                foreach ($all as &$r) {
                    if ((int)($r['file_id'] ?? 0) !== $fileId) continue;
                    if (trim((string)($r['site'] ?? '')) !== '') continue;
                    $code = trim((string)($r['code'] ?? ''));
                    if ($code === '' || !isset($siteByCode[$code])) continue;
                    $r['site'] = $siteByCode[$code];
                    $updatedRows++;
                }
                return $all;
            });

            jsonUpdate('news_entries', function ($all) use ($siteByDateCode, &$updatedEntries) {
                foreach ($all as &$r) {
                    if (trim((string)($r['site'] ?? '')) !== '') continue;
                    $date = trim((string)($r['entry_date'] ?? ''));
                    $code = normalizeDigits(trim((string)($r['news_id'] ?? '')));
                    if ($date === '' || $code === '') continue;
                    $key = $date . '|' . $code;
                    if (!isset($siteByDateCode[$key])) continue;
                    $r['site'] = $siteByDateCode[$key];
                    $updatedEntries++;
                }
                return $all;
            });

            echo json_encode(['ok' => true, 'updated_rows' => $updatedRows, 'updated_entries' => $updatedEntries], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'نامعتبر']);
    exit;
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4">
  <h5 class="mb-3">به‌روزرسانی زبان/سایت داده‌های قدیمی</h5>
  <p class="text-muted small">
    این ابزار فایل‌های اکسل اصلی که قبلاً آپلود و آرشیو شده‌اند را یکی‌یکی (بدون تایم‌اوت روی هاست) دوباره می‌خواند
    و فقط مقدار «سایت» (زبان) ردیف‌هایی که این مقدار را ندارند، بر اساس تطبیق کد خبر پر می‌کند؛ هیچ ردیف یا فایلی
    حذف/اضافه نمی‌شود و اجرای چندباره‌اش بی‌خطر است.
  </p>
  <div id="bfMsg" class="alert alert-info" style="display:none;"></div>
  <div class="progress mb-3" style="height:20px; display:none;" id="bfProgressWrap">
    <div id="bfProgress" class="progress-bar" style="width:0%">0%</div>
  </div>
  <button id="bfBtn" class="btn btn-primary">اجرای به‌روزرسانی زبان/سایت</button>
  <a href="evaluation.php" class="btn btn-outline-secondary">بازگشت به ارزیابی</a>
</div>
<script>
document.getElementById('bfBtn').addEventListener('click', async function () {
  const btn = this;
  const msg = document.getElementById('bfMsg');
  const wrap = document.getElementById('bfProgressWrap');
  const bar = document.getElementById('bfProgress');
  btn.disabled = true;
  msg.style.display = ''; msg.className = 'alert alert-info'; msg.textContent = 'در حال دریافت فهرست فایل‌ها...';
  wrap.style.display = '';

  let ids = [];
  try {
    const res = await fetch('backfill_site.php?ajax=list');
    const data = await res.json();
    if (!data.ok) throw new Error('خطا در دریافت فهرست فایل‌ها');
    ids = data.ids;
  } catch (e) {
    msg.className = 'alert alert-danger'; msg.textContent = 'خطا: ' + e.message; btn.disabled = false; return;
  }

  if (ids.length === 0) {
    msg.className = 'alert alert-warning'; msg.textContent = 'هیچ فایل آرشیوشده‌ای یافت نشد.'; btn.disabled = false; return;
  }

  let done = 0, filesOk = 0, filesFailed = 0, totalRows = 0, totalEntries = 0;
  for (const id of ids) {
    try {
      const res = await fetch('backfill_site.php?ajax=process&file_id=' + id);
      const data = await res.json();
      if (data.ok) {
        filesOk++;
        totalRows += (data.updated_rows || 0);
        totalEntries += (data.updated_entries || 0);
      } else {
        filesFailed++;
      }
    } catch (e) {
      filesFailed++;
    }
    done++;
    const pct = Math.round((done / ids.length) * 100);
    bar.style.width = pct + '%'; bar.textContent = pct + '%';
    msg.textContent = `در حال پردازش... (${done} از ${ids.length} فایل)`;
  }

  msg.className = 'alert alert-success';
  msg.textContent = `پایان یافت: ${filesOk} فایل پردازش شد` + (filesFailed ? `، ${filesFailed} فایل با خطا مواجه شد` : '') +
    `. تعداد ${totalRows} ردیف در «آمار عملکرد» و ${totalEntries} ردیف در «بررسی کیفی» به‌روزرسانی شد.`;
  btn.disabled = false;
});
</script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
