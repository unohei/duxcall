<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_db.php';     // 既存を流用（パスはあなたの構成に合わせて調整）
require_once __DIR__ . '/../api/_util.php';   // json_responseとかあるなら流用してOK

// --- 設定：まずは tokyo-clinic 固定でOK（課題提出用） ---
$hospital_code = $_GET['hospital_code'] ?? 'tokyo-clinic';

// DB
$pdo = db();

// 病院取得
$stmt = $pdo->prepare("SELECT id, hospital_code, name FROM hospitals WHERE hospital_code = :code LIMIT 1");
$stmt->execute([':code' => $hospital_code]);
$hospital = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hospital) {
  http_response_code(404);
  echo "Hospital not found";
  exit;
}

// POST: 保存
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  // CSRFとかは課題なら省略でOK（本番は必要）
  $routes = $_POST['routes'] ?? [];

  $pdo->beginTransaction();
  try {
    foreach ($routes as $idx => $r) {
      $key = trim((string)($r['key'] ?? ''));
      $label = trim((string)($r['label'] ?? ''));
      $phone = trim((string)($r['phone'] ?? ''));
      $sort_order = (int)($r['sort_order'] ?? 10);
      $is_enabled = isset($r['is_enabled']) ? 1 : 0;

      if ($key === '' || $label === '' || $phone === '') {
        continue; // 空行は無視
      }

      // route upsert（hospital_id + key がユニーク）
      $up = $pdo->prepare("
        INSERT INTO routes (hospital_id, `key`, label, phone, is_enabled, sort_order)
        VALUES (:hid, :key, :label, :phone, :enabled, :sort)
        ON DUPLICATE KEY UPDATE
          label = VALUES(label),
          phone = VALUES(phone),
          is_enabled = VALUES(is_enabled),
          sort_order = VALUES(sort_order)
      ");
      $up->execute([
        ':hid' => $hospital['id'],
        ':key' => $key,
        ':label' => $label,
        ':phone' => $phone,
        ':enabled' => $is_enabled,
        ':sort' => $sort_order,
      ]);

      // route_id を取得
      $ridStmt = $pdo->prepare("SELECT id FROM routes WHERE hospital_id = :hid AND `key` = :key LIMIT 1");
      $ridStmt->execute([':hid' => $hospital['id'], ':key' => $key]);
      $routeRow = $ridStmt->fetch(PDO::FETCH_ASSOC);
      if (!$routeRow) continue;
      $route_id = (int)$routeRow['id'];

      // weekly hours（0=Mon..6=Sun）
      $weekly = $r['weekly'] ?? [];
      for ($dow = 0; $dow <= 6; $dow++) {
        $w = $weekly[(string)$dow] ?? [];
        $is_closed = isset($w['is_closed']) ? 1 : 0;
        $open = trim((string)($w['open'] ?? ''));
        $close = trim((string)($w['close'] ?? ''));

        // closed のときは時間をNULLへ
        $open_val = ($is_closed || $open === '') ? null : $open;
        $close_val = ($is_closed || $close === '') ? null : $close;

        $wu = $pdo->prepare("
          INSERT INTO route_weekly_hours (route_id, dow, open_time, close_time, is_closed)
          VALUES (:rid, :dow, :open, :close, :closed)
          ON DUPLICATE KEY UPDATE
            open_time = VALUES(open_time),
            close_time = VALUES(close_time),
            is_closed = VALUES(is_closed)
        ");
        $wu->execute([
          ':rid' => $route_id,
          ':dow' => $dow,
          ':open' => $open_val,
          ':close' => $close_val,
          ':closed' => $is_closed,
        ]);
      }
    }

    $pdo->commit();
    header("Location: routes_edit.php?hospital_code=" . urlencode($hospital_code) . "&saved=1");
    exit;
  } catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Save failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
  }
}

// GET: 表示データ取得
$routesStmt = $pdo->prepare("
  SELECT id, `key`, label, phone, is_enabled, sort_order
  FROM routes
  WHERE hospital_id = :hid
  ORDER BY sort_order ASC
");
$routesStmt->execute([':hid' => $hospital['id']]);
$routesDb = $routesStmt->fetchAll(PDO::FETCH_ASSOC);

// weekly を route_id でまとめる
$weeklyMap = [];
if ($routesDb) {
  $ids = array_map(fn($r) => (int)$r['id'], $routesDb);
  $in = implode(',', array_fill(0, count($ids), '?'));
  $wStmt = $pdo->prepare("
    SELECT route_id, dow, open_time, close_time, is_closed
    FROM route_weekly_hours
    WHERE route_id IN ($in)
  ");
  $wStmt->execute($ids);
  $ws = $wStmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($ws as $w) {
    $rid = (int)$w['route_id'];
    $dow = (int)$w['dow'];
    $weeklyMap[$rid][$dow] = $w;
  }
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$dowLabels = ['月','火','水','木','金','土','日'];
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>DuxCall Admin - Routes</title>
  <style>
    body { font-family: sans-serif; margin: 0; background: #f5f7fa; }
    header { background:#111; color:#fff; padding:16px; }
    main { max-width: 980px; margin: 0 auto; padding: 16px; }
    .card { background:#fff; border:1px solid #ddd; border-radius:12px; padding:12px; margin-bottom: 12px; }
    .row { display:flex; gap:10px; flex-wrap: wrap; }
    .row > div { flex: 1; min-width: 160px; }
    input[type=text], input[type=number], input[type=time] { width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; }
    table { width:100%; border-collapse: collapse; }
    th, td { border-top:1px solid #eee; padding: 8px; text-align: left; font-size: 14px; }
    .btn { display:inline-block; padding:10px 12px; border-radius:10px; border:1px solid #111; background:#111; color:#fff; font-weight:800; cursor:pointer; }
    .btn-sub { border:1px solid #ddd; background:#fff; color:#111; }
    .muted { color:#666; font-size: 13px; }
    .ok { background:#e8fff6; border:1px solid #bdebd7; padding:10px; border-radius:10px; margin-bottom: 10px; }
  </style>
</head>
<body>
<header>
  <div style="max-width:980px;margin:0 auto;">
    <div style="font-weight:900;">DuxCall 管理（課題提出用）</div>
    <div class="muted" style="color:#bbb;">病院：<?=h($hospital['name'])?> / code=<?=h($hospital['hospital_code'])?></div>
  </div>
</header>

<main>
  <?php if (isset($_GET['saved'])): ?>
    <div class="ok">✅ 保存しました（患者側の連絡先一覧に反映されます）</div>
  <?php endif; ?>

  <form method="POST">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <div style="font-weight:900;">ルート（電話窓口）</div>
        <button class="btn" type="submit">保存</button>
      </div>
      <div class="muted" style="margin-top:6px;">
        例：予約 / 面会 など。key は <b>reservation</b> / <b>visit</b> みたいに英数で。
      </div>
    </div>

    <?php
      // 既存 + 追加用に空行を2つ
      $rows = $routesDb;
      $rows[] = ['id'=>0,'key'=>'','label'=>'','phone'=>'','is_enabled'=>1,'sort_order'=>30];
      $rows[] = ['id'=>0,'key'=>'','label'=>'','phone'=>'','is_enabled'=>1,'sort_order'=>40];
    ?>

    <?php foreach ($rows as $i => $r): ?>
      <div class="card">
        <div class="row">
          <div>
            <div class="muted">key</div>
            <input type="text" name="routes[<?= $i ?>][key]" value="<?= h($r['key'] ?? '') ?>" placeholder="reservation" />
          </div>
          <div>
            <div class="muted">表示名</div>
            <input type="text" name="routes[<?= $i ?>][label]" value="<?= h($r['label'] ?? '') ?>" placeholder="予約" />
          </div>
          <div>
            <div class="muted">電話番号</div>
            <input type="text" name="routes[<?= $i ?>][phone]" value="<?= h($r['phone'] ?? '') ?>" placeholder="0312345678" />
          </div>
          <div>
            <div class="muted">並び順</div>
            <input type="number" name="routes[<?= $i ?>][sort_order]" value="<?= h($r['sort_order'] ?? 10) ?>" />
          </div>
          <div style="min-width:120px;">
            <div class="muted">有効</div>
            <label style="display:flex; gap:8px; align-items:center; padding-top:10px;">
              <input type="checkbox" name="routes[<?= $i ?>][is_enabled]" <?= ((int)($r['is_enabled'] ?? 1) === 1) ? 'checked' : '' ?> />
              ON
            </label>
          </div>
        </div>

        <?php
          $rid = (int)($r['id'] ?? 0);
          $w = $rid ? ($weeklyMap[$rid] ?? []) : [];
        ?>
        <div style="margin-top:10px;">
          <div style="font-weight:800; margin-bottom:6px;">受付時間（曜日別）</div>
          <table>
            <thead>
              <tr>
                <th>曜日</th>
                <th>休</th>
                <th>開始</th>
                <th>終了</th>
              </tr>
            </thead>
            <tbody>
              <?php for ($dow=0; $dow<=6; $dow++): 
                $row = $w[$dow] ?? null;
                $is_closed = $row ? (int)$row['is_closed'] : 0;
                $open = $row && $row['open_time'] ? substr((string)$row['open_time'],0,5) : '';
                $close = $row && $row['close_time'] ? substr((string)$row['close_time'],0,5) : '';
              ?>
                <tr>
                  <td><?= $dowLabels[$dow] ?></td>
                  <td>
                    <input type="checkbox" name="routes[<?= $i ?>][weekly][<?= $dow ?>][is_closed]" <?= $is_closed ? 'checked' : '' ?> />
                  </td>
                  <td><input type="time" name="routes[<?= $i ?>][weekly][<?= $dow ?>][open]" value="<?= h($open) ?>" /></td>
                  <td><input type="time" name="routes[<?= $i ?>][weekly][<?= $dow ?>][close]" value="<?= h($close) ?>" /></td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
          <div class="muted" style="margin-top:6px;">
            ※「休」にチェックすると開始/終了は無視されます。
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="display:flex; gap:10px; justify-content:flex-end;">
      <button class="btn" type="submit">保存</button>
      <a class="btn btn-sub" href="routes_edit.php?hospital_code=<?= urlencode($hospital_code) ?>">更新</a>
    </div>
  </form>
</main>
</body>
</html>