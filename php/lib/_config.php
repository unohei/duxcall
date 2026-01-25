<?php
declare(strict_types=1);

/**
 * 共通設定（DB接続など）
 * - ローカルはデフォルト値で動く
 * - さくらは環境変数(SetEnv)で上書きして切り替える
 */

const APP_TZ = 'Asia/Tokyo';
date_default_timezone_set(APP_TZ);

// ローカル（XAMPP）デフォルト
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'medical_mvp';
const DB_USER = 'root';
const DB_PASS = '';

function env(string $key, ?string $default = null): ?string {
  $v = getenv($key);
  if ($v === false) return $default;
  $v = trim((string)$v);
  return $v === '' ? $default : $v;
}

function db_host(): string {
  return env('DUXCALL_DB_HOST', DB_HOST) ?? DB_HOST;
}

function db_port(): int {
  $p = env('DUXCALL_DB_PORT', (string)DB_PORT);
  return (int)($p ?: DB_PORT);
}

function db_name(): string {
  return env('DUXCALL_DB_NAME', DB_NAME) ?? DB_NAME;
}

function db_user(): string {
  return env('DUXCALL_DB_USER', DB_USER) ?? DB_USER;
}

function db_pass(): string {
  return env('DUXCALL_DB_PASS', DB_PASS) ?? DB_PASS;
}

function db_dsn(): string {
  $host = db_host();
  $port = db_port();
  $name = db_name();
  return "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
}