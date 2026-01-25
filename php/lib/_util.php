<?php
declare(strict_types=1);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): never {
  header('Location: ' . $to);
  exit;
}

function require_method(string $method): void {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if (strtoupper($m) !== strtoupper($method)) {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Method Not Allowed');
  }
}

function allow_methods(array $methods): void {
  $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
  $methods = array_map('strtoupper', $methods);

  if ($m === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
  if (!in_array($m, $methods, true)) {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Method Not Allowed');
  }
}

function json_response(array $data, int $status = 200): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function param_str(string $key, string $default = ''): string {
  $v = $_GET[$key] ?? $_POST[$key] ?? $default;
  if (!is_string($v)) return $default;
  return trim($v);
}
