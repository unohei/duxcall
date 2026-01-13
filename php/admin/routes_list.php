<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/_util.php';
require_once __DIR__ . '/../lib/_db.php';
require_once __DIR__ . '/../lib/_layout.php';

$pdo = db();

// 病院コード（今は1病院運用想定：未指定なら最初の病院へ寄せる）
$hospitalCode = trim((string)($_GET['code'] ?? ''));
if ($hospitalCode === '') {
  $h = $pdo->query("SELECT hospital_code FROM hospitals ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  if (!$h) {
    layout_start('Routes一覧', 'routes');
    echo '<p class="alert err">病院が登録されていません（hospitals が空です）</p>';
    layout_end();
    exit;
  }
  redirect('./routes_list.php?code=' . urlencode($h['hospital_code']));
}

// 病院名
$hstmt = $pdo->prepare("SELECT id, name FROM hospitals WHERE hospital_code = :code LIMIT 1");
$hstmt->execute([':code' => $hospitalCode]);
$hospital = $hstmt->fetch(PDO::FETCH_ASSOC);
if (!$hospital) {
  layout_start('Routes一覧', 'routes');
  echo '<p class="alert err">病院が見つかりません（code=' . h($hospitalCode) . '）</p>';
  layout_end();
  exit;
}

// Routes
$stmt = $pdo->prepare("
  SELECT id, `key`, label, phone, is_enabled, sort_order, updated_at
  FROM routes
  WHERE hospital_id = :hid
  ORDER BY sort_order ASC, id ASC
");
$stmt->execute([':hid' => (int)$hospital['id']]);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_start('Routes一覧', 'routes');
?>

<div class="row" style="justify-content:space-between; align-items:flex-end;">
  <div>
    <h1 style="margin:0 0 6px;">病院情報管理一覧</h1>
    <div class="muted">病院：<strong><?= h($hospital['name']) ?></strong>（code: <?= h($hospitalCode) ?>）</div>
  </div>

  <div class="row">
    <a class="btn" href="./routes_edit.php?code=<?= h(urlencode($hospitalCode)) ?>">＋ 項目を追加</a>
  </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
  <p class="alert ok"><?= h((string)$_GET['msg']) ?></p>
<?php endif; ?>

<?php if (!$routes): ?>
  <p class="alert">まだ入力がありません。「＋ 項目を追加」から作成してください。</p>
<?php else: ?>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th style="width:100px;">並び</th>
          <th style="width:100px;">ラベル</th>
          <!-- <th style="width:160px;">key</th> -->
          <th style="width:100px;">電話番号</th>
          <th style="width:110px;">状態</th>
          <th style="width:190px;">更新</th>
          <th style="width:280px;">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($routes as $i => $r): ?>
          <tr>
            <td>
              <div class="row" style="gap:6px;">

                <form method="POST" action="./routes_reorder.php" style="display:inline;">
                  <input type="hidden" name="code" value="<?= h($hospitalCode) ?>">
                  <input type="hidden" name="route_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="dir" value="up">
                  <button class="icon-btn" <?= $i === 0 ? 'disabled' : '' ?> title="上へ">↑</button>
                </form>

                <form method="POST" action="./routes_reorder.php" style="display:inline;">
                  <input type="hidden" name="code" value="<?= h($hospitalCode) ?>">
                  <input type="hidden" name="route_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="dir" value="down">
                  <button class="icon-btn" <?= $i === count($routes) - 1 ? 'disabled' : '' ?> title="下へ">↓</button>
                </form>
              </div>
            </td>

            <td style="font-weight:800;"><?= h((string)$r['label']) ?></td>
            <td><code><?= h((string)$r['phone']) ?></code></td>

            <td>
              <?php if ((int)$r['is_enabled'] === 1): ?>
                <span class="pill ok">有効</span>
              <?php else: ?>
                <span class="pill ng">無効</span>
              <?php endif; ?>
            </td>

            <td class="muted"><?= h((string)$r['updated_at']) ?></td>

            <td>
              <div class="row">
                <a class="btn sub" href="./routes_edit.php?code=<?= h(urlencode($hospitalCode)) ?>&id=<?= (int)$r['id'] ?>">編集</a>
                <a class="btn sub" href="./weekly_hours_edit.php?code=<?= h(urlencode($hospitalCode)) ?>&route_id=<?= (int)$r['id'] ?>">受付時間</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="muted" style="margin-top:10px;">
    ↑↓で並び順を入れ替えできます<br>
  </p>

<?php endif; ?>

<?php layout_end(); ?>