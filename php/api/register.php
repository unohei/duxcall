<?php
declare(strict_types=1);

require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_db.php';

// 開発中は GET/POST どちらでもOK
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'OPTIONS'], true)) {
  json_response(['detail' => 'Method Not Allowed'], 405);
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
  json_response(['detail' => 'hospital_code required'], 400);
}

$pdo = db();

$stmt = $pdo->prepare("
  SELECT hospital_code, name, timezone
  FROM hospitals
  WHERE hospital_code = :code AND is_active = 1
  LIMIT 1
");
$stmt->execute([':code' => $code]);
$h = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$h) {
  json_response(['detail' => 'Hospital not found'], 404);
}

// ---- ここから「登録ログ」書き込み（追加） ----
try {
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

  $ins = $pdo->prepare("
    INSERT INTO patient_registrations (hospital_code, user_agent)
    VALUES (:code, :ua)
  ");
  $ins->execute([
    ':code' => $h['hospital_code'],
    ':ua'   => $ua ? mb_substr($ua, 0, 255) : null,
  ]);
} catch (Throwable $e) {
  // ログ保存が失敗しても、登録自体（病院取得）は返したいなら 200 のままでもOK
  // きっちりしたいなら 500 を返す
  json_response(['detail' => 'failed to write registration log'], 500);
}
// ---- 追加ここまで ----

json_response([
  'hospital' => [
    'code' => $h['hospital_code'],
    'name' => $h['name'],
    'timezone' => $h['timezone'],
  ]
]);