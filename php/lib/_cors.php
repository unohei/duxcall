<?php
declare(strict_types=1);

/**
 * 開発用CORS
 * - Vite(5173) / ngrok 等を想定し、Origin があればそれを返す
 * - Cookieを使わない前提（Access-Control-Allow-Credentials は付けない）
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header("Vary: Origin");

header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}