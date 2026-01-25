<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_staff_login(); 

require_once __DIR__ . '/../lib/_util.php';
require_once __DIR__ . '/../lib/_db.php';
require_once __DIR__ . '/../lib/_layout.php';

$pdo = db();

// すでにハッシュならスキップ、平文なら hash 化
$st = $pdo->query("SELECT id, password_hash FROM staff_users");
$users = $st->fetchAll(PDO::FETCH_ASSOC);

$up = $pdo->prepare("UPDATE staff_users SET password_hash = :h WHERE id = :id");

header('Content-Type: text/plain; charset=utf-8');

foreach ($users as $u) {
  $cur = (string)$u['password_hash'];
  $looksHashed = ($cur !== '' && $cur[0] === '$');
  if ($looksHashed) continue;

  $hash = password_hash($cur, PASSWORD_DEFAULT);
  $up->execute([':h' => $hash, ':id' => (int)$u['id']]);
  echo "updated staff_users.id=" . (int)$u['id'] . "\n";
}

echo "done\n";