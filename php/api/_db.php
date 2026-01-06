<?php
declare(strict_types=1);

function db(): PDO {
  $host = '127.0.0.1';
  $port = '3306';
  $dbname = 'medical_mvp';   // ★ここが重要
  $user = 'root';
  $pass = '';                // rootのパスワードがあるなら入れる

  $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  return $pdo;
}