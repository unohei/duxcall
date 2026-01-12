<?php
declare(strict_types=1);

/**
 * 共通設定（DB接続など）
 * XAMPP前提のデフォルト値を置きつつ、環境変数があれば優先。
 */

const APP_TZ = 'Asia/Tokyo';

// DB（必要なら環境変数で上書き）
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'medical_mvp';   // ←あなたのDB名に合わせてる（medical_mvp）
const DB_USER = 'root';
const DB_PASS = '';             // root でパスあるなら環境変数推奨

function env(string $key, ?string $default = null): ?string {
  $v = getenv($key);
  if ($v === false || $v === '') return $default;
  return $v;
}

function db_dsn(): string {
  $host = env('DUXCALL_DB_HOST', DB_HOST);
  $port = env('DUXCALL_DB_PORT', (string)DB_PORT);
  $name = env('DUXCALL_DB_NAME', DB_NAME);
  return "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
}

function db_user(): string {
  return env('DUXCALL_DB_USER', DB_USER) ?? DB_USER;
}

function db_pass(): string {
  return env('DUXCALL_DB_PASS', DB_PASS) ?? DB_PASS;
}