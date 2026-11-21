<?php
require_once __DIR__ . '/includes/auth.php';
logoutUserSession();
setcookie('nz_sso', '', ['expires' => time() - 3600, 'path' => '/']); // ورود خودکار از ارزیابی ماهانه تا ورود بعدیِ همان مسیر غیرفعال
header('Location: login.php');
