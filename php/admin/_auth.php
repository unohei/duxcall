<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly' => true,
    'use_strict_mode' => true,
  ]);
}

function require_staff_login(): void {
  if (empty($_SESSION['staff_user_id'])) {
    header('Location: login.php?msg=' . urlencode('ログインしてください'));
    exit;
  }
}