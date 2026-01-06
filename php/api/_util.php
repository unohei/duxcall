<?php
declare(strict_types=1);

/**
 * JSONレスポンスを返して終了
 */
function json_response(array $data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * HTTPメソッド強制（OPTIONSは素通し）
 */
function require_method(string $method): void {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($m === 'OPTIONS') {
    http_response_code(204);
    exit;
  }

  if (strtoupper($m) !== strtoupper($method)) {
    json_response(['detail' => 'Method Not Allowed'], 405);
  }
}