<?php
declare(strict_types=1);

/**
 * 共通ユーティリティ
 * - json_response()
 * - require_method()
 * - redirect()
 */

function json_response(array $data, int $status = 200): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  // JSON以外を混ぜない（warningが出ると壊れるので、ここで必ず終了）
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function require_method(string $method): void {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'OPTIONS') return; // preflightは通す（CORS側で処理）
  if (strtoupper($m) !== strtoupper($method)) {
    json_response(['detail' => 'Method Not Allowed'], 405);
  }
}

function redirect(string $to): never {
  header('Location: ' . $to, true, 302);
  exit;
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function qs(array $params): string {
  return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}