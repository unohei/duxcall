<?php
declare(strict_types=1);

require_once __DIR__ . '/_config.php';

// PHP側タイムゾーンは「読み込み時に1回」セットしておくのが安全
date_default_timezone_set(APP_TZ);

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $pdo = new PDO(db_dsn(), db_user(), db_pass(), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  // MySQL側タイムゾーン（動けばラッキー、ダメでも致命じゃない）
  try {
    $pdo->exec("SET time_zone = '+09:00'");
  } catch (Throwable $e) {
    // さくら等で権限/設定により失敗してもOK
  }

  return $pdo;
}