<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tasks.php';
requireLoginPage();
$__me = currentUser();
$board = tasksBoard();
$users = usersAll();

function taskUserLabel(array $users, ?string $username): string
{
    if (!$username) return '';
    foreach ($users as $u) {
        if (($u['username'] ?? '') === $username) return $u['display_name'] ?: $u['username'];
    }
    return $username;
}

$allTags = [];
foreach ($board['columns'] as $col) {
    foreach ($col['tasks'] as $t) {
        foreach (($t['tags'] ?? []) as $tg) { $allTags[$tg] = true; }
    }
}
$allTags = array_keys($allTags);
sort($allTags);

require __DIR__ . '/includes/layout_top.php';
?>
<style>
body{
  background-image:
    linear-gradient(rgba(18,58,115,0.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(18,58,115,0.045) 1px, transparent 1px),
    linear-gradient(160deg, rgba(6,54,42,.35), rgba(10,90,70,.2)),
    url('assets/img/wallpapers.jpg') !important;
  background-size: 44px 44px, 44px 44px, cover, cover !important;
  background-position: 0 0, 0 0, center, center !important;
  background-attachment: scroll, scroll, fixed, fixed !important;
  background-repeat: repeat, repeat, no-repeat, no-repeat !important;
}
.task-wrap{
  background: transparent;
  border-radius: 18px;
  padding: 22px 0;
}
.task-board-title{color:#0d2338; font-weight:800; margin-bottom:4px;}
.task-board-sub{color:#2c3e50; margin-bottom:16px;}

/* نوار فیلتر شیشه‌ای */
.task-filters{
  position: relative; z-index: 20;
  background: rgba(255,255,255,.55);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,.6);
  border-radius: 14px; padding: 12px; margin-bottom: 16px;
  display:flex; flex-wrap:wrap; gap:10px; align-items:end;
  box-shadow: 0 4px 20px rgba(0,0,0,.08);
}
.task-filters label{color:#17324d; font-size:.78rem; margin-bottom:2px;}
.task-filters .form-select, .task-filters .form-control{ min-width:150px; }
.task-filters .btn-clear{ background:rgba(255,255,255,.9); border:none; }

.task-cols{display:flex; gap:14px; flex-wrap:wrap; padding-bottom:8px; position:relative; z-index:1;}
.task-col{
  background: rgba(255,255,255,.5);
  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  border: 1px solid rgba(255,255,255,.5);
  border-radius: 14px;
  flex: 1 1 240px;
  min-width: 240px;
  padding: 10px;
  display:flex; flex-direction:column;
  max-height: 72vh;
  box-shadow: 0 4px 20px rgba(0,0,0,.12);
}
.task-col.drag-over{ outline:2px dashed #0079bf; outline-offset:-4px; background:rgba(238,246,255,.85); }
.task-col-head{display:flex; justify-content:space-between; align-items:center; padding:4px 6px 10px;}
.task-col-head b{color:#0d2338; font-size:.95rem;}
.task-col-count{background:rgba(255,255,255,.85); color:#17324d; border-radius:10px; padding:1px 8px; font-size:.75rem; font-weight:600;}
.task-cards{overflow-y:auto; flex:1; padding:2px;}
.task-card{
  background:rgba(255,255,255,.88); border-radius:10px; padding:10px 12px; margin-bottom:8px;
  box-shadow:0 1px 3px rgba(9,30,66,.2);
  cursor:grab; border-left:4px solid var(--pc,#0079bf); position:relative;
  transition: transform .12s ease, box-shadow .12s ease;
}
.task-card:hover{ box-shadow:0 4px 10px rgba(9,30,66,.25); transform: translateY(-1px); }
.task-card.dragging{ opacity:.4; }
.task-card.task-hidden{ display:none; }
.task-card-title{font-size:.92rem; font-weight:600; color:#172b4d; margin-bottom:6px;}
.task-card-meta{display:flex; justify-content:space-between; align-items:center; font-size:.75rem; color:#5e6c84; flex-wrap:wrap; gap:4px;}
.task-card-assignees{font-size:.72rem; color:#0079bf; font-weight:600;}
.task-card-creator{font-size:.68rem; color:#8993a4; margin-top:4px;}
.task-card-donenote{font-size:.68rem; color:#1a7f4e; background:#e3fcef; border-radius:8px; padding:2px 8px; margin-top:6px; display:inline-block;}
.task-card-reviewnote{font-size:.68rem; color:#4a2e9c; background:#eee6ff; border-radius:8px; padding:2px 8px; margin-top:6px; margin-right:4px; display:inline-block;}
.task-card-tags{display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;}
.task-tag-badge{background:#1a7f4e; color:#fff; border-radius:8px; padding:1px 8px; font-size:.66rem;}
.task-review-btn{font-size:.68rem; margin-top:6px;}
.task-show-more{width:100%; margin-top:4px;}
.task-round-check{
  appearance:none; -webkit-appearance:none; margin:0;
  position:absolute; top:8px; left:8px;
  width:20px; height:20px; border-radius:50%;
  border:2px solid #61bd4f; background:#fff; cursor:pointer;
}
.task-round-check:checked{ background:#61bd4f; }
.task-round-check:checked::after{
  content:'✓'; color:#fff; font-size:12px; font-weight:700;
  position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
}
.task-round-check:disabled{ opacity:.85; cursor:default; }
.task-card{ padding-left:34px; }
.task-assignee-list{
  max-height:150px; overflow-y:auto; border:1px solid #dee2e6; border-radius:8px; padding:8px;
}
.task-assignee-list .form-check{ margin-bottom:4px; }
.task-add-col-btn{
  background:rgba(255,255,255,.18); border:1px dashed rgba(255,255,255,.6); color:#fff;
  border-radius:14px; min-width:220px; flex-shrink:0; padding:10px; align-self:flex-start;
}
.task-add-col-btn:hover{ background:rgba(255,255,255,.28); color:#fff; }
.priority-low{--pc:#61bd4f;}
.priority-medium{--pc:#f2d600;}
.priority-high{--pc:#eb5a46;}
.badge-priority-low{background:#e3fcef;color:#1a7f4e;}
.badge-priority-medium{background:#fff8c5;color:#8a6d00;}
.badge-priority-high{background:#ffe3e0;color:#c0392b;}
</style>

<div class="task-wrap mb-4">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <div class="task-board-title">📋 مدیریت کارها</div>
      <div class="task-board-sub">کارت‌ها را بین ستون‌ها جابه‌جا کنید تا وضعیت تغییر کند.</div>
    </div>
    <button class="btn btn-light fw-bold" data-bs-toggle="modal" data-bs-target="#taskAddModal">+ کار جدید</button>
  </div>

  <div class="task-filters">
    <div>
      <label class="d-block">اساین‌شده</label>
      <select class="form-select form-select-sm" id="fltAssignee">
        <option value="">همه</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['display_name'] ?: $u['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="d-block">تگ</label>
      <select class="form-select form-select-sm" id="fltTag">
        <option value="">همه</option>
        <?php foreach ($allTags as $tg): ?>
          <option value="<?= htmlspecialchars($tg) ?>"><?= htmlspecialchars($tg) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="d-block">از تاریخ سررسید</label>
      <input type="text" class="form-control form-control-sm jalali-date-input" id="fltFrom" placeholder="از">
    </div>
    <div>
      <label class="d-block">تا تاریخ سررسید</label>
      <input type="text" class="form-control form-control-sm jalali-date-input" id="fltTo" placeholder="تا">
    </div>
    <div>
      <button class="btn btn-sm btn-clear" id="fltClear">پاک کردن فیلترها</button>
    </div>
  </div>

  <div class="task-cols" id="taskCols">
    <?php foreach ($board['columns'] as $col): ?>
      <div class="task-col" data-column-id="<?= htmlspecialchars($col['id']) ?>">
        <div class="task-col-head">
          <b><?= htmlspecialchars($col['title']) ?></b>
          <span class="task-col-count"><?= count($col['tasks']) ?></span>
        </div>
        <div class="task-cards" data-column-id="<?= htmlspecialchars($col['id']) ?>">
          <?php foreach ($col['tasks'] as $idx => $t):
            $assigneeNames = array_map(fn($u) => taskUserLabel($users, $u), $t['assignees']);
            $creatorName = taskUserLabel($users, $t['created_by']);
            $tags = $t['tags'] ?? [];
          ?>
          <div class="task-card priority-<?= htmlspecialchars($t['priority'] ?? 'medium') ?><?= $idx >= 20 ? ' task-hidden task-extra' : '' ?>"
               draggable="true" data-task-id="<?= (int)$t['id'] ?>"
               data-title="<?= htmlspecialchars($t['title']) ?>"
               data-desc="<?= htmlspecialchars($t['desc'] ?? '') ?>"
               data-assignees="<?= htmlspecialchars(json_encode($t['assignees'], JSON_UNESCAPED_UNICODE)) ?>"
               data-priority="<?= htmlspecialchars($t['priority'] ?? 'medium') ?>"
               data-due="<?= htmlspecialchars($t['due'] ?? '') ?>"
               data-done-note="<?= htmlspecialchars($t['done_note'] ?? '') ?>"
               data-review-note="<?= htmlspecialchars($t['review_note'] ?? '') ?>"
               data-tags="<?= htmlspecialchars(json_encode($tags, JSON_UNESCAPED_UNICODE)) ?>">
            <input type="checkbox" class="task-round-check" title="انجام شد"
                   data-complete-id="<?= (int)$t['id'] ?>"
                   <?= $col['id'] === 'done' ? 'checked disabled' : '' ?>>
            <div class="task-card-title"><?= htmlspecialchars($t['title']) ?></div>
            <div class="task-card-meta">
              <span class="badge badge-priority-<?= htmlspecialchars($t['priority'] ?? 'medium') ?>">
                <?= ['low'=>'کم','medium'=>'متوسط','high'=>'زیاد'][$t['priority'] ?? 'medium'] ?? 'متوسط' ?>
              </span>
              <span class="task-card-assignees">
                <?= $assigneeNames ? htmlspecialchars(implode('، ', $assigneeNames)) : '<span class="text-muted">بدون اساین</span>' ?>
              </span>
            </div>
            <?php if ($tags): ?>
              <div class="task-card-tags">
                <?php foreach ($tags as $tg): ?><span class="task-tag-badge">#<?= htmlspecialchars($tg) ?></span><?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($creatorName): ?>
              <div class="task-card-creator">ساخته‌شده توسط: <?= htmlspecialchars($creatorName) ?></div>
            <?php endif; ?>
            <?php if (!empty($t['done_note'])): ?>
              <div class="task-card-donenote">✅ یادداشت</div>
            <?php endif; ?>
            <?php if (!empty($t['review_note'])): ?>
              <div class="task-card-reviewnote">📝 یادداشت بررسی</div>
            <?php endif; ?>
            <?php if (in_array($col['id'], ['review', 'done'], true)): ?>
              <button type="button" class="btn btn-sm btn-outline-secondary task-review-btn" data-review-id="<?= (int)$t['id'] ?>">📝 یادداشت بررسی</button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if (count($col['tasks']) > 20): ?>
            <button type="button" class="btn btn-sm btn-outline-light task-show-more">نمایش بیشتر (<?= count($col['tasks']) - 20 ?> مورد دیگر)</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- مودال افزودن/ویرایش کار -->
<div class="modal fade" id="taskAddModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskModalTitle">کار جدید</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="taskId" value="">
        <div class="mb-2">
          <label class="form-label">عنوان</label>
          <input type="text" class="form-control" id="taskTitle">
        </div>
        <div class="mb-2">
          <label class="form-label">توضیح</label>
          <textarea class="form-control" id="taskDesc" rows="2"></textarea>
        </div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">اساین به (چند نفر را تیک بزنید)</label>
            <div class="task-assignee-list" id="taskAssigneesBox">
              <?php foreach ($users as $u): ?>
                <div class="form-check">
                  <input class="form-check-input task-assignee-chk" type="checkbox" value="<?= htmlspecialchars($u['username']) ?>" id="asg_<?= htmlspecialchars($u['username']) ?>">
                  <label class="form-check-label" for="asg_<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['display_name'] ?: $u['username']) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label">اولویت</label>
            <select class="form-select" id="taskPriority">
              <option value="low">کم</option>
              <option value="medium" selected>متوسط</option>
              <option value="high">زیاد</option>
            </select>
          </div>
        </div>
        <div class="mb-2 mt-2">
          <label class="form-label">تاریخ سررسید (اختیاری)</label>
          <input type="text" class="form-control jalali-date-input" id="taskDue" placeholder="انتخاب تاریخ">
        </div>
        <div class="mb-2">
          <label class="form-label">تگ‌ها (با کاما جدا کنید)</label>
          <input type="text" class="form-control" id="taskTags" list="taskTagsList" placeholder="مثلاً فوری، خبر، ادیت">
          <datalist id="taskTagsList">
            <?php foreach ($allTags as $tg): ?><option value="<?= htmlspecialchars($tg) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="mb-2">
          <label class="form-label">ستون</label>
          <select class="form-select" id="taskColumn">
            <?php foreach ($board['columns'] as $col): ?>
              <option value="<?= htmlspecialchars($col['id']) ?>"><?= htmlspecialchars($col['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">توضیحات انجام‌شده (توسط انجام‌دهنده)</label>
          <textarea class="form-control" id="taskDoneNote" rows="2" placeholder="در صورت انجام کار، توضیح دهید چه کاری انجام شد"></textarea>
        </div>
        <div class="mb-2 border-top pt-2 d-none" id="taskReviewBox">
          <label class="form-label">یادداشت بررسی (توسط مدیر/بررسی‌کننده)</label>
          <textarea class="form-control" id="taskReviewNote" rows="2" placeholder="نظر/بررسی مدیر روی این کار"></textarea>
          <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="taskReviewSaveBtn">ثبت یادداشت بررسی</button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger d-none" id="taskDeleteBtn">حذف</button>
        <button type="button" class="btn btn-primary" id="taskSaveBtn">ذخیره</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const modalEl = document.getElementById('taskAddModal');
  const modal = new bootstrap.Modal(modalEl);
  const els = {
    id: document.getElementById('taskId'),
    title: document.getElementById('taskTitle'),
    desc: document.getElementById('taskDesc'),
    assigneesBox: document.getElementById('taskAssigneesBox'),
    priority: document.getElementById('taskPriority'),
    due: document.getElementById('taskDue'),
    column: document.getElementById('taskColumn'),
    doneNote: document.getElementById('taskDoneNote'),
    tags: document.getElementById('taskTags'),
    reviewBox: document.getElementById('taskReviewBox'),
    reviewNote: document.getElementById('taskReviewNote'),
  };
  const modalTitle = document.getElementById('taskModalTitle');
  const deleteBtn = document.getElementById('taskDeleteBtn');

  function setAssignees(list){
    const set = new Set(list || []);
    els.assigneesBox.querySelectorAll('.task-assignee-chk').forEach(chk => { chk.checked = set.has(chk.value); });
  }

  function resetForm(defaultColumn){
    els.id.value=''; els.title.value=''; els.desc.value='';
    setAssignees([]);
    els.priority.value='medium'; els.due.value=''; els.doneNote.value='';
    els.tags.value=''; els.reviewNote.value=''; els.reviewBox.classList.add('d-none');
    if (defaultColumn) els.column.value = defaultColumn;
    modalTitle.textContent = 'کار جدید';
    deleteBtn.classList.add('d-none');
  }
  document.querySelector('[data-bs-target="#taskAddModal"]').addEventListener('click', function(){ resetForm('todo'); });

  document.querySelectorAll('.task-card').forEach(card => {
    card.addEventListener('click', function(e){
      if (e.target.closest('[data-complete-id]')) return; // دکمه done جدا مدیریت می‌شود
      els.id.value = this.dataset.taskId;
      els.title.value = this.dataset.title;
      els.desc.value = this.dataset.desc;
      setAssignees(JSON.parse(this.dataset.assignees || '[]'));
      els.priority.value = this.dataset.priority;
      els.due.value = this.dataset.due;
      els.doneNote.value = this.dataset.doneNote || '';
      els.tags.value = (JSON.parse(this.dataset.tags || '[]')).join('، ');
      els.reviewNote.value = this.dataset.reviewNote || '';
      els.reviewBox.classList.remove('d-none');
      els.column.value = this.closest('.task-col').dataset.columnId;
      modalTitle.textContent = 'ویرایش کار';
      deleteBtn.classList.remove('d-none');
      modal.show();
    });
  });

  // دکمه‌ی سریع «یادداشت بررسی» روی کارت
  document.querySelectorAll('.task-review-btn').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      this.closest('.task-card').click();
      setTimeout(() => els.reviewNote.focus(), 300);
    });
  });

  document.getElementById('taskReviewSaveBtn').addEventListener('click', function(){
    if (!els.id.value) return;
    const fd = new FormData();
    fd.append('action','review');
    fd.append('task_id', els.id.value);
    fd.append('review_note', els.reviewNote.value.trim());
    fetch('task_actions.php', {method:'POST', body: fd})
      .then(r=>r.json())
      .then(res=>{
        if(res.ok){ location.reload(); }
        else { alert('ثبت نشد: ' + (res.msg || res.error || 'خطای نامشخص')); }
      })
      .catch(err=>{ alert('خطا در ارتباط با سرور: ' + err.message); });
  });

  document.getElementById('taskSaveBtn').addEventListener('click', function(){
    const isEdit = !!els.id.value;
    const fd = new FormData();
    fd.append('action', isEdit ? 'update' : 'create');
    if (isEdit) fd.append('task_id', els.id.value);
    fd.append('title', els.title.value.trim());
    fd.append('desc', els.desc.value.trim());
    els.assigneesBox.querySelectorAll('.task-assignee-chk:checked').forEach(chk => fd.append('assignees[]', chk.value));
    fd.append('priority', els.priority.value);
    fd.append('due', els.due.value.trim());
    fd.append('column_id', els.column.value);
    fd.append('done_note', els.doneNote.value.trim());
    fd.append('tags_text', els.tags.value.trim());
    if (!els.title.value.trim()) { els.title.focus(); return; }
    fetch('task_actions.php', {method:'POST', body: fd})
      .then(r=>r.json())
      .then(res=>{
        if(res.ok){ location.reload(); }
        else { alert('ذخیره نشد: ' + (res.msg || res.error || 'خطای نامشخص')); }
      })
      .catch(err=>{ alert('خطا در ارتباط با سرور: ' + err.message); });
  });

  deleteBtn.addEventListener('click', function(){
    if (!els.id.value) return;
    if (!confirm('این کار حذف شود؟')) return;
    const fd = new FormData();
    fd.append('action','delete'); fd.append('task_id', els.id.value);
    fetch('task_actions.php', {method:'POST', body: fd})
      .then(r=>r.json())
      .then(res=>{
        if(res.ok){ location.reload(); }
        else { alert('حذف نشد: ' + (res.msg || res.error || 'خطای نامشخص')); }
      })
      .catch(err=>{ alert('خطا در ارتباط با سرور: ' + err.message); });
  });

  // دایره تیک «انجام شد» روی کارت
  document.querySelectorAll('.task-round-check[data-complete-id]').forEach(chk => {
    chk.addEventListener('click', function(e){ e.stopPropagation(); });
    chk.addEventListener('change', function(){
      if (!this.checked) return; // فقط تیک‌زدن باعث تکمیل می‌شود
      const card = this.closest('.task-card');
      let note = card.dataset.doneNote || '';
      if (!note) {
        const entered = prompt('توضیحات انجام‌شده را وارد کنید (اختیاری):', '');
        if (entered === null) { this.checked = false; return; } // انصراف
        note = entered.trim();
      }
      const fd = new FormData();
      fd.append('action','complete');
      fd.append('task_id', this.dataset.completeId);
      fd.append('done_note', note);
      fetch('task_actions.php', {method:'POST', body: fd})
        .then(r=>r.json())
        .then(res=>{
          if(res.ok){ location.reload(); }
          else { this.checked = false; alert('ثبت نشد: ' + (res.msg || res.error || 'خطای نامشخص')); }
        })
        .catch(err=>{ this.checked = false; alert('خطا در ارتباط با سرور: ' + err.message); });
    });
  });

  // درگ‌ودراپ ساده با HTML5 API
  let dragged = null;
  document.querySelectorAll('.task-card').forEach(card=>{
    card.addEventListener('dragstart', function(){ dragged = this; this.classList.add('dragging'); });
    card.addEventListener('dragend', function(){ this.classList.remove('dragging'); });
  });
  document.querySelectorAll('.task-cards').forEach(zone=>{
    zone.addEventListener('dragover', function(e){
      e.preventDefault();
      this.closest('.task-col').classList.add('drag-over');
    });
    zone.addEventListener('dragleave', function(){ this.closest('.task-col').classList.remove('drag-over'); });
    zone.addEventListener('drop', function(e){
      e.preventDefault();
      this.closest('.task-col').classList.remove('drag-over');
      if (!dragged) return;
      const columnId = this.dataset.columnId;
      this.appendChild(dragged);
      const fd = new FormData();
      fd.append('action','move');
      fd.append('task_id', dragged.dataset.taskId);
      fd.append('column_id', columnId);
      fetch('task_actions.php', {method:'POST', body: fd})
        .then(r=>r.json())
        .then(res=>{ if(!res.ok){ alert('جابه‌جایی ذخیره نشد: ' + (res.msg || res.error || 'خطای نامشخص')); location.reload(); } })
        .catch(err=>{ alert('خطا در ارتباط با سرور: ' + err.message); location.reload(); });
    });
  });

  // نمایش بیشتر (برای ستون‌های شلوغ)
  document.querySelectorAll('.task-show-more').forEach(btn => {
    btn.addEventListener('click', function(){
      this.closest('.task-cards').querySelectorAll('.task-extra').forEach(c => c.classList.remove('task-hidden'));
      this.remove();
    });
  });

  // فیلترها: اساین‌شده، تگ، بازه تاریخ سررسید
  const fltAssignee = document.getElementById('fltAssignee');
  const fltTag = document.getElementById('fltTag');
  const fltFrom = document.getElementById('fltFrom');
  const fltTo = document.getElementById('fltTo');

  function applyFilters(){
    const asg = fltAssignee.value, tag = fltTag.value, from = fltFrom.value.trim(), to = fltTo.value.trim();
    document.querySelectorAll('.task-card').forEach(card => {
      let show = true;
      if (asg) {
        const list = JSON.parse(card.dataset.assignees || '[]');
        if (!list.includes(asg)) show = false;
      }
      if (show && tag) {
        const tags = JSON.parse(card.dataset.tags || '[]');
        if (!tags.includes(tag)) show = false;
      }
      if (show && (from || to)) {
        const due = card.dataset.due || '';
        if (!due) { show = false; }
        else {
          if (from && due < from) show = false;
          if (to && due > to) show = false;
        }
      }
      card.classList.toggle('task-hidden', !show);
      if (show) card.classList.remove('task-extra'); // فیلتر فعال یعنی پیجینیشن کنار می‌رود
    });
    document.querySelectorAll('.task-cards').forEach(zone => {
      const visible = zone.querySelectorAll('.task-card:not(.task-hidden)').length;
      const countEl = zone.closest('.task-col').querySelector('.task-col-count');
      countEl.textContent = visible;
    });
  }
  [fltAssignee, fltTag, fltFrom, fltTo].forEach(el => el.addEventListener('change', applyFilters));
  document.getElementById('fltClear').addEventListener('click', function(){
    fltAssignee.value=''; fltTag.value=''; fltFrom.value=''; fltTo.value='';
    document.querySelectorAll('.task-card').forEach(c => c.classList.remove('task-hidden'));
    document.querySelectorAll('.task-cards').forEach(zone => {
      const countEl = zone.closest('.task-col').querySelector('.task-col-count');
      countEl.textContent = zone.querySelectorAll('.task-card').length;
    });
  });
});
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
