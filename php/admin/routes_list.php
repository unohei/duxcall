<?php
declare(strict_types=1);

require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_util.php';

$hospitalCode = str_param('hospital_code');
if ($hospitalCode === '') {
  // まず病院コード入力を促す（簡易）
  ?>
  <!doctype html>
  <html lang="ja">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Routes 一覧</title>
    <style>
      body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:24px;}
      input,button{font-size:16px;padding:10px;border-radius:10px;border:1px solid #ddd;}
      button{background:#111;color:#fff;border-color:#111;font-weight:800;cursor:pointer;}
      .card{border:1px solid #ddd;border-radius:14px;padding:14px;max-width:560px;}
    </style>
  </head>
  <body>
    <h2>Routes 一覧</h2>
    <div class="card">
      <p>hospital_code を指定してください</p>
      <form method="get">
        <input name="hospital_code" placeholder="tokyo-clinic" />
        <button type="submit">表示</button>
      </form>
    </div>
  </body>
  </html>
  <?php
  exit;
}

$pdo = db();

// 病院取得
$st = $pdo->prepare("SELECT id, hospital_code, name FROM hospitals WHERE hospital_code = :code LIMIT 1");
$st->execute([':code' => $hospitalCode]);
$hospital = $st->fetch();
if (!$hospital) {
  http_response_code(404);
  echo "Hospital not found: " . h($hospitalCode);
  exit;
}

// routes一覧
$st = $pdo->prepare("
  SELECT id, `key`, label, phone, is_enabled, sort_order, updated_at
  FROM routes
  WHERE hospital_id = :hid
  ORDER BY sort_order ASC, id ASC
");
$st->execute([':hid' => (int)$hospital['id']]);
$routes = $st->fetchAll();

// 週次スケジュール数（ざっくり確認用）
$st = $pdo->prepare("
  SELECT r.id AS route_id, COUNT(w.id) AS cnt
  FROM routes r
  LEFT JOIN route_weekly_hours w ON w.route_id = r.id
  WHERE r.hospital_id = :hid
  GROUP BY r.id
");
$st->execute([':hid' => (int)$hospital['id']]);
$weeklyCountByRoute = [];
foreach ($st->fetchAll() as $row) {
  $weeklyCountByRoute[(int)$row['route_id']] = (int)$row['cnt'];
}

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Routes一覧 - <?= h((string)$hospital['name']) ?></title>
  <style>
    body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:24px;}
    .wrap{max-width:720px;margin:0 auto;}
    .top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}
    .muted{color:#666;font-size:13px;}
    .btn{display:inline-block;padding:10px 12px;border-radius:10px;text-decoration:none;font-weight:800;}
    .btn-primary{background:#111;color:#fff;border:1px solid #111;}
    .btn-ghost{background:#fff;color:#111;border:1px solid #ddd;}
    .grid{display:grid;gap:10px;margin-top:14px;}
    .card{border:1px solid #ddd;border-radius:14px;padding:14px;}
    .row{display:flex;justify-content:space-between;gap:10px;align-items:center;}
    .tag{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:800;}
    .on{background:#e8fff6;color:#0a7;border:1px solid #b7f3dc;}
    .off{background:#fff2f2;color:#b00;border:1px solid #f7c7c7;}
    code{background:#f6f6f6;padding:2px 6px;border-radius:6px;}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <h2 style="margin:0;">Routes一覧</h2>
        <div class="muted">
          病院：<?= h((string)$hospital['name']) ?> / code: <code><?= h((string)$hospital['hospital_code']) ?></code>
        </div>
      </div>
      <div>
        <!-- まだ routes_edit.php を作ってないなら一旦このリンクは保留でもOK -->
        <a class="btn btn-primary" href="routes_edit.php?hospital_code=<?= urlencode((string)$hospital['hospital_code']) ?>">
          ＋ Routesを追加
        </a>
      </div>
    </div>

    <?php if (count($routes) === 0): ?>
      <div class="card" style="margin-top:14px;">
        <div style="font-weight:900;">まだ routes がありません</div>
        <div class="muted" style="margin-top:6px;">
          まずは 1件（例：予約）だけ登録すると、患者側の「連絡先」が一気に動きます。
        </div>
      </div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($routes as $r): ?>
          <?php
            $enabled = (int)$r['is_enabled'] === 1;
            $cnt = $weeklyCountByRoute[(int)$r['id']] ?? 0;
          ?>
          <div class="card">
            <div class="row">
              <div style="font-size:18px;font-weight:900;"><?= h((string)$r['label']) ?></div>
              <span class="tag <?= $enabled ? 'on' : 'off' ?>">
                <?= $enabled ? '有効' : '無効' ?>
              </span>
            </div>

            <div class="muted" style="margin-top:6px;">
              key: <code><?= h((string)$r['key']) ?></code> /
              phone: <code><?= h((string)$r['phone']) ?></code> /
              sort: <?= (int)$r['sort_order'] ?> /
              weekly_rows: <?= (int)$cnt ?>
            </div>

            <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
              <a class="btn btn-ghost" href="routes_edit.php?hospital_code=<?= urlencode((string)$hospital['hospital_code']) ?>&route_id=<?= (int)$r['id'] ?>">
                編集
              </a>
              <!-- 削除や無効化は後で追加でOK -->
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="muted" style="margin-top:16px;">
      動作確認：患者側のAPI（React経由）で routes が空でなくなればOK。<br/>
      例）<code>GET /api/patient/hospitals/<?= h((string)$hospital['hospital_code']) ?></code>
    </div>
  </div>
</body>
</html>