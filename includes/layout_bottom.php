</div>
<footer class="app-footer py-3 mt-4">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <small>سامانه نظارت و ارزیابی خبرگزاری دانشجویان ایران (ایسنا) &mdash; نسخه <?= htmlspecialchars(APP_VERSION) ?></small>
    <small>توسعه‌دهنده: <?= htmlspecialchars(APP_DEVELOPER) ?></small>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ---- پورت jalaali-js (MIT) برای تقویم شمسی سمت کلاینت ---- */
var jalaali = (function(){
  var breaks = [-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];
  function div(a,b){ return ~~(a/b); }
  function mod(a,b){ return a - ~~(a/b)*b; }
  function jalCal(jy){
    var bl=breaks.length, gy=jy+621, leapJ=-14, jp=breaks[0], jm, jump, leap, n, i;
    if (jy<jp||jy>=breaks[bl-1]) throw new Error('bad jy');
    for(i=1;i<bl;i++){ jm=breaks[i]; jump=jm-jp; if(jy<jm) break; leapJ=leapJ+div(jump,33)*8+div(mod(jump,33),4); jp=jm; }
    n=jy-jp;
    leapJ=leapJ+div(n,33)*8+div(mod(n,33)+3,4);
    if (mod(jump,33)===4 && jump-n===4) leapJ+=1;
    var leapG=div(gy,4)-div((div(gy,100)+1)*3,4)-150;
    var march=20+leapJ-leapG;
    if (jump-n<6) n=n-jump+div(jump+4,33)*33;
    leap=mod(mod(n+1,33)-1,4);
    if (leap===-1) leap=4;
    return {leap:leap, gy:gy, march:march};
  }
  function g2d(gy,gm,gd){
    var d=div((gy+div(gm-8,6)+100100)*1461,4)+div(153*mod(gm+9,12)+2,5)+gd-34840408;
    d=d-div(div(gy+100100+div(gm-8,6),100)*3,4)+752;
    return d;
  }
  function d2g(jdn){
    var j=4*jdn+139361631;
    j=j+div(div(4*jdn+183187720,146097)*3,4)*4-3908;
    var i=div(mod(j,1461),4)*5+308;
    var gd=div(mod(i,153),5)+1;
    var gm=mod(div(i,153),12)+1;
    var gy=div(j,1461)-100100+div(8-gm,6);
    return {gy:gy,gm:gm,gd:gd};
  }
  function j2d(jy,jm,jd){
    var r=jalCal(jy);
    return g2d(r.gy,3,r.march)+(jm-1)*31-div(jm,7)*(jm-7)+jd-1;
  }
  function d2j(jdn){
    var gy=d2g(jdn).gy, jy=gy-621, r=jalCal(jy), jdn1f=g2d(gy,3,r.march), k=jdn-jdn1f, jm, jd;
    if (k>=0){
      if (k<=185){ return {jy:jy, jm:1+div(k,31), jd:mod(k,31)+1}; }
      k-=186;
    } else { jy-=1; k+=179; if (r.leap===1) k+=1; }
    return {jy:jy, jm:7+div(k,30), jd:mod(k,30)+1};
  }
  function toJalaali(gy,gm,gd){
    if (gy instanceof Date){ gd=gy.getDate(); gm=gy.getMonth()+1; gy=gy.getFullYear(); }
    return d2j(g2d(gy,gm,gd));
  }
  function toGregorian(jy,jm,jd){ return d2g(j2d(jy,jm,jd)); }
  function isLeapJalaaliYear(jy){ return jalCal(jy).leap===0; }
  function jalaaliMonthLength(jy,jm){ if(jm<=6) return 31; if(jm<=11) return 30; return isLeapJalaaliYear(jy)?30:29; }
  return {toJalaali:toJalaali, toGregorian:toGregorian, isLeapJalaaliYear:isLeapJalaaliYear, jalaaliMonthLength:jalaaliMonthLength};
})();

var JALALI_MONTHS = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

function initJalaliPicker(input){
  var wrap = document.createElement('span');
  wrap.className = 'jdp-wrap';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);
  input.readOnly = true;
  input.classList.add('jdp-field');

  var popup = document.createElement('div');
  popup.className = 'jdp-popup';
  popup.style.display = 'none';
  wrap.appendChild(popup);

  var viewYear = parseInt(input.dataset.defaultYear || '', 10) || null;
  var viewMonth = parseInt(input.dataset.defaultMonth || '', 10) || null;

  function parseValue(){
    var m = (input.value || '').match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
    if (m) return {y:+m[1], m:+m[2], d:+m[3]};
    return null;
  }

  function ensureView(){
    if (viewYear && viewMonth) return;
    var val = parseValue();
    if (val){ viewYear = val.y; viewMonth = val.m; return; }
    var j = jalaali.toJalaali(new Date());
    viewYear = j.jy; viewMonth = j.jm;
  }

  function render(){
    ensureView();
    var val = parseValue();
    var html = '';
    html += '<div class="jdp-head">' +
      '<button type="button" class="jdp-nav" data-act="next">&#8249;</button>' +
      '<span class="jdp-title">' + JALALI_MONTHS[viewMonth-1] + ' ' + viewYear + '</span>' +
      '<button type="button" class="jdp-nav" data-act="prev">&#8250;</button>' +
      '</div>';
    html += '<div class="jdp-grid jdp-weekdays">' + ['ش','ی','د','س','چ','پ','ج'].map(function(d){return '<span>'+d+'</span>';}).join('') + '</div>';

    var gFirst = jalaali.toGregorian(viewYear, viewMonth, 1);
    var jsDate = new Date(gFirst.gy, gFirst.gm - 1, gFirst.gd);
    var startDow = jsDate.getDay();
    var leadBlank = (startDow + 1) % 7;
    var monthLen = jalaali.jalaaliMonthLength(viewYear, viewMonth);

    var cells = '';
    for (var i=0;i<leadBlank;i++) cells += '<span></span>';
    for (var d=1; d<=monthLen; d++){
      var isSel = val && val.y===viewYear && val.m===viewMonth && val.d===d;
      cells += '<button type="button" class="jdp-day' + (isSel?' jdp-selected':'') + '" data-d="'+d+'">' + d + '</button>';
    }
    html += '<div class="jdp-grid jdp-days">' + cells + '</div>';
    html += '<div class="jdp-foot"><button type="button" class="jdp-today" data-act="today">امروز</button></div>';
    popup.innerHTML = html;

    popup.querySelector('[data-act="prev"]').addEventListener('click', function(e){
      e.stopPropagation();
      viewMonth--; if (viewMonth < 1){ viewMonth = 12; viewYear--; }
      render();
    });
    popup.querySelector('[data-act="next"]').addEventListener('click', function(e){
      e.stopPropagation();
      viewMonth++; if (viewMonth > 12){ viewMonth = 1; viewYear++; }
      render();
    });
    popup.querySelector('[data-act="today"]').addEventListener('click', function(e){
      e.stopPropagation();
      var j = jalaali.toJalaali(new Date());
      viewYear = j.jy; viewMonth = j.jm;
      setValue(j.jy, j.jm, j.jd);
      render();
      closePopup();
    });
    popup.querySelectorAll('.jdp-day').forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.stopPropagation();
        setValue(viewYear, viewMonth, parseInt(btn.dataset.d, 10));
        closePopup();
      });
    });
  }

  function setValue(y,m,d){
    var pad2 = function(n){ return String(n).padStart(2,'0'); };
    input.value = String(y).padStart(4,'0') + '/' + pad2(m) + '/' + pad2(d);
    input.dispatchEvent(new Event('change', {bubbles:true}));
  }

  function openPopup(){
    document.querySelectorAll('.jdp-popup').forEach(function(p){ if (p!==popup) p.style.display='none'; });
    render();
    popup.style.display = 'block';
  }
  function closePopup(){ popup.style.display = 'none'; }

  input.addEventListener('click', function(e){ e.stopPropagation(); openPopup(); });
  input.addEventListener('focus', function(){ openPopup(); });

  // رفع باگ: بدون این خط، جایگزینی innerHTML در render() هنگام کلیک روی دکمه‌های
  // ناوبری باعث می‌شد کلیک به listener سراسری document برسد و popup فوراً بسته شود.
  wrap.addEventListener('click', function(e){ e.stopPropagation(); });

  document.addEventListener('click', function(e){
    if (!wrap.contains(e.target)) closePopup();
  });
}

document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.jalali-date-input').forEach(initJalaliPicker);
});
</script>
<style>
.jdp-wrap{position:relative; display:inline-block; width:100%;}
.jdp-field{cursor:pointer; background:#fff;}
.jdp-popup{
  position:absolute; z-index:2000; top:calc(100% + 4px); right:0;
  background:#fff; border:1px solid #dfe4ee; border-radius:12px;
  box-shadow:0 12px 32px rgba(10,26,64,.2); padding:12px; width:250px;
  font-size:.9rem;
}
.jdp-head{display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;}
.jdp-title{font-weight:700; color:var(--navy-2);}
.jdp-nav{border:none; background:#eef2fa; border-radius:8px; width:28px; height:28px; line-height:1; font-size:1.1rem; color:var(--navy-2);}
.jdp-nav:hover{background:#dde6f7;}
.jdp-grid{display:grid; grid-template-columns:repeat(7,1fr); gap:3px; text-align:center;}
.jdp-weekdays span{color:#8a93a6; font-size:.78rem; padding:2px 0;}
.jdp-day{border:none; background:transparent; border-radius:8px; padding:6px 0; cursor:pointer;}
.jdp-day:hover{background:#eef2fa;}
.jdp-selected{background:linear-gradient(135deg, var(--navy-2), var(--navy-3)) !important; color:#fff !important;}
.jdp-foot{text-align:center; margin-top:8px; border-top:1px solid #eef1f7; padding-top:8px;}
.jdp-today{border:none; background:none; color:var(--navy-2); font-size:.82rem; cursor:pointer;}
.jdp-today:hover{text-decoration:underline;}
</style>
<script>
/* ---- سورت عمومی جداول: روی هر th داخل جدولی با کلاس sortable-table کلیک شود، بر اساس آن ستون مرتب می‌شود ---- */
(function(){
  function parseCell(text){
    var t = (text || '').trim().replace(/,/g, '').replace(/٬/g, '');
    var persianDigits = '۰۱۲۳۴۵۶۷۸۹';
    t = t.replace(/[۰-۹]/g, function(d){ return persianDigits.indexOf(d); });
    var num = parseFloat(t.replace('%', ''));
    if (!isNaN(num) && /^-?[\d.]+%?$/.test(t)) return {num:num, isNum:true};
    return {text:(text||'').trim(), isNum:false};
  }
  function sortTable(table, colIndex, dir){
    var tbody = table.tBodies[0];
    if (!tbody) return;
    var rows = Array.prototype.slice.call(tbody.rows);
    rows.sort(function(r1, r2){
      var c1 = parseCell(r1.cells[colIndex] ? r1.cells[colIndex].innerText : '');
      var c2 = parseCell(r2.cells[colIndex] ? r2.cells[colIndex].innerText : '');
      var cmp;
      if (c1.isNum && c2.isNum) cmp = c1.num - c2.num;
      else cmp = String(c1.text||'').localeCompare(String(c2.text||''), 'fa');
      return dir === 'asc' ? cmp : -cmp;
    });
    rows.forEach(function(r){ tbody.appendChild(r); });
  }
  function initSortable(table){
    if (table.dataset.sortableInit) return;
    table.dataset.sortableInit = '1';
    var ths = table.querySelectorAll('thead th');
    ths.forEach(function(th, idx){
      th.style.cursor = 'pointer';
      th.style.userSelect = 'none';
      th.addEventListener('click', function(){
        var curDir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
        ths.forEach(function(t){ t.removeAttribute('data-dir'); t.innerHTML = t.innerHTML.replace(/ ?[▲▼]$/, ''); });
        th.setAttribute('data-dir', curDir);
        th.innerHTML = th.innerHTML.replace(/ ?[▲▼]$/, '') + (curDir === 'asc' ? ' ▲' : ' ▼');
        sortTable(table, idx, curDir);
      });
    });
  }
  function scanAndInit(){
    document.querySelectorAll('table.sortable-table').forEach(initSortable);
  }
  document.addEventListener('DOMContentLoaded', scanAndInit);
  // برای جداولی که بعداً با جاوااسکریپت (fetch/AJAX) رندر می‌شوند، هر ۱.۵ ثانیه یک بار هم بررسی می‌شود
  setInterval(scanAndInit, 1500);
})();
</script>
</body>
</html>
