<?php
declare(strict_types=1);

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_db.php';

$pdo = db();

$hs = $pdo->prepare("SELECT id, hospital_code, name FROM hospitals WHERE hospital_code=:c LIMIT 1");
$hs->execute([':c' => ADMIN_HOSPITAL_CODE]);
$hospital = $hs->fetch();
if (!$hospital) {
  admin_header('ルート編集');
  echo '<div class="card"><b>病院が見つかりません</b></div>';
  admin_footer();
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$route = [
  'id' => 0,
  'key' => '',
  'label' => '',
  'phone' => '',
  'is_enabled' => 1,
  'sort_order' => 10,
];

if ($isEdit) {
  $st = $pdo->prepare("SELECT * FROM routes WHERE id=:id AND hospital_id=:hid LIMIT 1");
  $st->execute([':id' => $id, ':hid' => $hospital['id']]);
  $row = $st->fetch();
  if (!$row) {
    header('Location: routes_list.php');
    exit;
  }
  $route = $row;
}

$flash = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $key = trim((string)($_POST['key'] ?? ''));
  $label = trim((string)($_POST['label'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $sort = (int)($_POST['sort_order'] ?? 10);
  $enabled = isset($_POST['is_enabled']) ? 1 : 0;

  if ($key === '' || $label === '' || $phone === '') {
    $flash = '入力が足りません（キー / 表示名 / 電話 は必須です）';
  } else {
    if ($isEdit) {
      $up = $pdo->prepare("
        UPDATE routes
        SET `key`=:k, label=:l, phone=:p, sort_order=:s, is_enabled=:e
        WHERE id=:id AND hospital_id=:hid
      ");
      $up->execute([
        ':k'=>$key, ':l'=>$label, ':p'=>$phone, ':s'=>$sort, ':e'=>$enabled,
        ':id'=>$id, ':hid'=>$hospital['id'],
      ]);
      $flash = '保存しました。';
    } else {
      $ins = $pdo->prepare("
        INSERT INTO routes (hospital_id, `key`, label, phone, is_enabled, sort_order)
        VALUES (:hid, :k, :l, :p, :e, :s)
      ");
      $ins->execute([
        ':hid'=>$hospital['id'],
        ':k'=>$key, ':l'=>$label, ':p'=>$phone, ':e'=>$enabled, ':s'=>$sort,
      ]);
      $id = (int)$pdo->lastInsertId();
      $isEdit = true;
      $flash = '保存しました。';
    }

    // 再取得（表示更新）
    $st = $pdo->prepare("SELECT * FROM routes WHERE id=:id AND hospital_id=:hid LIMIT 1");
    $st->execute([':id' => $id, ':hid' => $hospital['id']]);
    $route = $st->fetch() ?: $route;
  }
}

admin_header($isEdit ? 'ルート編集' : 'ルート追加');
?>
<div class="topbar">
  <div>
    <h1><?= $isEdit ? 'ルート編集' : 'ルート追加' ?>（<?=h($hospital['name'])?>）</h1>
    <div class="muted">患者アプリに表示される用件です。必要な項目だけ埋めればOK。</div>
  </div>
  <div class="row">
    <a class="btn" href="routes_list.php">← 一覧に戻る</a>
  </div>
</div>

<div class="card grid">
  <?php if ($flash !== ''): ?>
    <div style="padding:12px;border:1px solid #e6e8ef;border-radius:12px;background:#f9fafb">
      <b><?=h($flash)?></b>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div class="grid">
      <div>
        <label><b>キー（英数字）</b></label>
        <input type="text" name="key" value="<?=h((string)$route['key'])?>" placeholder="例: reservation / visit">
        <div class="help">システム内部で使う識別子です（例：reservation、visit）。一度決めたらなるべく変えないのがおすすめ。</div>
      </div>

      <div>
        <label><b>表示名</b></label>
        <input type="text" name="label" value="<?=h((string)$route['label'])?>" placeholder="例: 予約 / 面会">
      </div>

      <div>
        <label><b>電話番号</b></label>
        <input type="text" name="phone" value="<?=h((string)$route['phone'])?>" placeholder="例: 0312345678">
      </div>

      <div>
        <label><b>並び順</b>（小さいほど上）</label>
        <input type="number" name="sort_order" value="<?= (int)$route['sort_order'] ?>" min="0" step="1">
        <div class="help">おすすめ：10 / 20 / 30 …（あとから間に差し込みたい時は 15 などもOK）</div>
      </div>

      <div>
        <label>
          <input type="checkbox" name="is_enabled" value="1" <?= ((int)$route['is_enabled'] === 1) ? 'checked' : '' ?>>
          <b>このルートを有効にする</b>
        </label>
        <div class="help">無効にすると患者アプリに表示されません。</div>
      </div>

      <div class="row">
        <a class="btn" href="routes_list.php">戻る</a>
        <button class="btn primary" type="submit">保存</button>
      </div>
    </div>
  </form>
</div>

<?php admin_footer(); ?>