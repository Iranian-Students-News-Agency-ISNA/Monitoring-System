<?php
require_once __DIR__ . '/includes/auth.php';
logoutUserSession();
header('Location: login.php');
