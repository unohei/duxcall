<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_staff_login();

require_once __DIR__ . '/../lib/_util.php';
require_once __DIR__ . '/../lib/_db.php';
require_once __DIR__ . '/../lib/_layout.php';

require_method('POST');

$hospitalCode = trim((string)($_POST['code'] ?? ''));
$routeId = (int)($_POST['route_id'] ?? 0);
$dir = (string)($_POST['dir'] ?? '');

if ($hospitalCode === '' || $routeId <= 0 || !in_array($dir, ['up', 'down'], true)) {
  redirect('./routes_list.php?msg=' . urlencode('不正なリクエストです'));
}

$pdo = db();

// 対象routeがその病院のものか確認しつつ sort_order を取る
$stmt = $pdo->prepare("
  SELECT r.id, r.sort_order, r.hospital_id
  FROM routes r
  JOIN hospitals h ON h.id = r.hospital_id
  WHERE h.hospital_code = :code AND r.id = :rid
  LIMIT 1
");
$stmt->execute([':code' => $hospitalCode, ':rid' => $routeId]);
$cur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cur) {
  redirect('./routes_list.php?code=' . urlencode($hospitalCode) . '&msg=' . urlencode('対象のルートが見つかりません'));
}

$hid = (int)$cur['hospital_id'];
$curOrder = (int)$cur['sort_order'];

if ($dir === 'up') {
  $neighbor = $pdo->prepare("
    SELECT id, sort_order
    FROM routes
    WHERE hospital_id = :hid AND sort_order < :o
    ORDER BY sort_order DESC, id DESC
    LIMIT 1
  ");
  $neighbor->execute([':hid' => $hid, ':o' => $curOrder]);
} else {
  $neighbor = $pdo->prepare("
    SELECT id, sort_order
    FROM routes
    WHERE hospital_id = :hid AND sort_order > :o
    ORDER BY sort_order ASC, id ASC
    LIMIT 1
  ");
  $neighbor->execute([':hid' => $hid, ':o' => $curOrder]);
}

$nb = $neighbor->fetch(PDO::FETCH_ASSOC);
if (!$nb) {
  redirect('./routes_list.php?code=' . urlencode($hospitalCode)); // 端なら何もしない
}

$pdo->beginTransaction();
try {
  // swap
  $u1 = $pdo->prepare("UPDATE routes SET sort_order = :o WHERE id = :id");
  $u1->execute([':o' => (int)$nb['sort_order'], ':id' => $routeId]);

  $u2 = $pdo->prepare("UPDATE routes SET sort_order = :o WHERE id = :id");
  $u2->execute([':o' => $curOrder, ':id' => (int)$nb['id']]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  redirect('./routes_list.php?code=' . urlencode($hospitalCode) . '&msg=' . urlencode('並び替えに失敗しました'));
}

redirect('./routes_list.php?code=' . urlencode($hospitalCode));