<?php
declare(strict_types=1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

/**
 * 開発用：許可するOrigin
 * - http://localhost:5173
 * - http://127.0.0.1:5173
 * - https://*.ngrok-free.dev
 */
$allowed = false;
if ($origin) {
  if (preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) $allowed = true;
  if (preg_match('#^https://[a-z0-9-]+\.ngrok-free\.dev$#i', $origin)) $allowed = true;
}

if ($allowed) {
  header("Access-Control-Allow-Origin: {$origin}");
  header("Vary: Origin");
  header("Access-Control-Allow-Credentials: true");
} else {
  // 直叩き(curl/ブラウザ直アクセス)用。厳密にするならここを落としてOK
  header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET,POST,OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(204);
  exit;
}