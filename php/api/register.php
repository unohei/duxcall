<?php
declare(strict_types=1);

require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_db.php';

allow_methods(['GET', 'POST', 'OPTIONS']); // 開発中はGETも許可（本番ならPOSTだけに）

$code = param_str('code');
if ($code === '') json_response(['detail' => 'hospital_code required'], 400);

$pdo = db();

$stmt = $pdo->prepare("
  SELECT hospital_code, name, timezone
  FROM hospitals
  WHERE hospital_code = :code AND is_active = 1
  LIMIT 1
");
$stmt->execute([':code' => $code]);
$h = $stmt->fetch();

if (!$h) json_response(['detail' => 'Hospital not found'], 404);

// 登録ログ（テーブルがある前提）
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
try {
  $ins = $pdo->prepare("
    INSERT INTO patient_registrations (hospital_code, user_agent)
    VALUES (:code, :ua)
  ");
  $ins->execute([
    ':code' => $h['hospital_code'],
    ':ua' => $ua ? mb_substr($ua, 0, 255) : null,
  ]);
} catch (Throwable $e) {
  // ログが無い/権限が無い等でも登録自体は通す
}

json_response([
  'hospital' => [
    'code' => $h['hospital_code'],
    'name' => $h['name'],
    'timezone' => $h['timezone'],
  ],
]);