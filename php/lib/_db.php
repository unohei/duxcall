<?php
declare(strict_types=1);

require_once __DIR__ . '/_config.php';

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $pdo = new PDO(
    db_dsn(),
    db_user(),
    db_pass(),
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );

  return $pdo;
}