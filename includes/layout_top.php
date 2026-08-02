<?php
require_once __DIR__ . '/auth.php';
requireLoginPage();
$__me = currentUser();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>سامانه نظارت خبرگزاری دانشجویان ایران (ایسنا)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
:root{
  --navy-1:#0a1a40;
  --navy-2:#123a73;
  --navy-3:#1f5aa8;
}
html, body{height:100%;}
body{
  font-family:'Vazirmatn','Tahoma',sans-serif;
  background-color:#f6f8fc;
  background-image:
    linear-gradient(rgba(18,58,115,0.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(18,58,115,0.045) 1px, transparent 1px);
  background-size:44px 44px;
  animation: jdGridDrift 70s linear infinite;
  min-height:100vh;
  display:flex;
  flex-direction:column;
}
.app-main{flex:1 0 auto;}
.app-footer{flex-shrink:0;}
@keyframes jdGridDrift{
  from{ background-position: 0 0, 0 0; }
  to{ background-position: 260px 130px, 260px 130px; }
}
.card{border-radius:14px; border:1px solid #e3e8f2;}
.app-navbar{
  background: linear-gradient(135deg, var(--navy-1) 0%, var(--navy-2) 55%, var(--navy-3) 100%);
  box-shadow:0 2px 12px rgba(10,26,64,.35);
}
.app-navbar .navbar-brand{font-weight:700; letter-spacing:.2px;}
.app-navbar .nav-link{color:rgba(255,255,255,.88) !important;}
.app-navbar .nav-link:hover{color:#fff !important;}
.app-navbar .user-pill{
  background:rgba(255,255,255,.12);
  border-radius:999px;
  padding:.35rem .9rem;
  color:#fff;
  font-size:.9rem;
  display:inline-flex;
  align-items:center;
  gap:.4rem;
}
.btn-primary{
  background: linear-gradient(135deg, var(--navy-2), var(--navy-3));
  border:none;
}
.btn-primary:hover{
  background: linear-gradient(135deg, var(--navy-1), var(--navy-2));
}
.app-footer{
  background: linear-gradient(135deg, var(--navy-1) 0%, var(--navy-2) 55%, var(--navy-3) 100%);
  color:rgba(255,255,255,.85);
}
.app-footer small{color:rgba(255,255,255,.85);}
@media (max-width:576px){.table-responsive{font-size:.85rem}}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar mb-4">
  <div class="container">
    <a class="navbar-brand" href="index.php">سامانه نظارت خبرگزاری دانشجویان ایران (ایسنا)</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav1">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav1">
      <ul class="navbar-nav ms-auto gap-2 align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="index.php">داشبورد</a></li>
        <li class="nav-item"><a class="nav-link" href="entry.php">ثبت خبر</a></li>
        <li class="nav-item"><a class="nav-link" href="file_entry.php">ثبت از پرونده</a></li>
        <li class="nav-item"><a class="nav-link" href="upload.php">آپلود اکسل روزانه</a></li>
        <li class="nav-item"><a class="nav-link" href="report.php">گزارش‌گیری</a></li>
        <li class="nav-item"><a class="nav-link" href="evaluation.php">ارزیابی</a></li>
        <?php if ($__me): ?>
        <li class="nav-item mt-2 mt-lg-0">
          <span class="user-pill">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <?= htmlspecialchars($__me['display_name']) ?>
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.php" title="خروج">خروج</a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container app-main pb-4">
