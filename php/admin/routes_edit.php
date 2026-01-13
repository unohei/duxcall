<?php
declare(strict_types=1);

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_db.php';

/**
 * routes_edit.php（管理画面）
 * - 1画面で「追加/編集」を統一
 * - 保存ボタンは1つに統一
 * - 戻るボタンあり（一覧へ）
 * - admin_header/admin_footer が無い環境でも落ちないようにフォールバック実装
 */

// --- fallback: admin_header / admin_footer が未定義でも動く ---
if (!function_exists('admin_header')) {
  function admin_header(string $title): void {
    // _util.php 側に h() がある想定。無い場合に備えて最低限も。
    if (!function_exists('h')) {
      function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
    }
    $t = h($title);
    echo <<<HTML
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$t}</title>
<style>
  :root{
    --bg:#f6f7fb; --card:#fff; --line:#e6e8ef; --text:#111; --muted:#666;
    --primary:#111; --primaryText:#fff;
    --radius:16px;
  }
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Noto Sans JP","Hiragino Sans";background:var(--bg);color:var(--text);}
  .wrap{max-width:980px;margin:0 auto;padding:18px;}
  .topbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px;}
  h1{margin:0;font-size:22px}
  .muted{color:var(--muted);font-size:14px;margin-top:6px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:16px;box-shadow:0 1px 8px rgba(20,20,20,.04);}
  .grid{display:grid;gap:12px}
  label{display:block;margin-bottom:6px}
  input[type="text"], input[type="number"]{
    width:100%; box-sizing:border-box;
    padding:12px 12px; border-radius:12px; border:1px solid var(--line);
    font-size:16px; background:#fff;
  }
  .help{color:var(--muted);font-size:13px;margin-top:6px;line-height:1.35}
  .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 12px;border-radius:12px;border:1px solid var(--line);
    background:#fff;color:var(--text);text-decoration:none;font-weight:800;cursor:pointer;
  }
  .btn.primary{background:var(--primary);border-color:var(--primary);color:var(--primaryText)}
  .flash{padding:12px;border:1px solid var(--line);border-radius:12px;background:#f9fafb}
</style>
</head>
<body>
<div class="wrap">
HTML;
  }

  function admin_footer(): void {
    echo "</div></body></html>";
  }
}

// --- DB / hospital fixed ---
$pdo = db();

$hs = $pdo->prepare("SELECT id, hospital_code, name FROM hospitals WHERE hospital_code=:c LIMIT 1");
$hs->execute([':c' => ADMIN_HOSPITAL_CODE]);
$hospital = $hs->fetch(PDO::FETCH_ASSOC);

if (!$hospital) {
  admin_header('ルート編集');
  echo '<div class="card"><b>病院が見つかりません</b></div>';
  admin_footer();
  exit;
}

// --- mode ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// --- default route model ---
$route = [
  'id' => 0,
  'key' => '',
  'label' => '',
  'phone' => '',
  'is_enabled' => 1,
  'sort_order' => 10,
];

// --- load if edit ---
if ($isEdit) {
  $st = $pdo->prepare("SELECT * FROM routes WHERE id=:id AND hospital_id=:hid LIMIT 1");
  $st->execute([':id' => $id, ':hid' => $hospital['id']]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    header('Location: routes_list.php');
    exit;
  }
  $route = $row;
}

$flash = '';
$error = '';

// --- save ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $key = trim((string)($_POST['key'] ?? ''));
  $label = trim((string)($_POST['label'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $sort = (int)($_POST['sort_order'] ?? 10);
  $enabled = isset($_POST['is_enabled']) ? 1 : 0;

  // normalize
  $key = preg_replace('/\s+/', '', $key) ?? $key;

  if ($key === '' || $label === '' || $phone === '') {
    $error = '入力が足りません（キー / 表示名 / 電話 は必須です）';
  } elseif (!preg_match('/^[a-zA-Z0-9\-_]+$/', $key)) {
    $error = 'キーは英数字と - _ のみで入力してください';
  } else {
    try {
      if ($isEdit) {
        // key uniqueness per hospital
        $chk = $pdo->prepare("SELECT id FROM routes WHERE hospital_id=:hid AND `key`=:k AND id<>:id LIMIT 1");
        $chk->execute([':hid'=>$hospital['id'], ':k'=>$key, ':id'=>$id]);
        if ($chk->fetch()) {
          $error = 'このキーは既に使われています（別のキーにしてください）';
        } else {
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
        }
      } else {
        // key uniqueness per hospital
        $chk = $pdo->prepare("SELECT id FROM routes WHERE hospital_id=:hid AND `key`=:k LIMIT 1");
        $chk->execute([':hid'=>$hospital['id'], ':k'=>$key]);
        if ($chk->fetch()) {
          $error = 'このキーは既に使われています（別のキーにしてください）';
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
      }

      // reload
      if ($error === '') {
        $st = $pdo->prepare("SELECT * FROM routes WHERE id=:id AND hospital_id=:hid LIMIT 1");
        $st->execute([':id' => $id, ':hid' => $hospital['id']]);
        $route = $st->fetch(PDO::FETCH_ASSOC) ?: $route;
      } else {
        // keep posted values on screen
        $route['key'] = $key;
        $route['label'] = $label;
        $route['phone'] = $phone;
        $route['sort_order'] = $sort;
        $route['is_enabled'] = $enabled;
      }
    } catch (Throwable $e) {
      $error = '保存に失敗しました。入力内容を確認してください。';
    }
  }
}

// --- render ---
admin_header($isEdit ? '項目編集' : '項目追加');
?>
<div class="topbar">
  <div>
    <h1><?= $isEdit ? '項目編集' : '項目追加' ?>（<?= h((string)$hospital['name']) ?>）</h1>
    <div class="muted">患者アプリに表示される用件です。必要な項目だけ埋めればOK。</div>
  </div>
  <div class="row">
    <a class="btn" href="routes_list.php">一覧に戻る</a>
  </div>
</div>

<div class="card grid">
  <?php if ($flash !== ''): ?>
    <div class="flash"><b><?= h($flash) ?></b></div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="flash" style="border-color:#f2c7c7;background:#fff5f5">
      <b style="color:#b00"><?= h($error) ?></b>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div class="grid">
      <div>
        <label><b>キー（英数字）</b></label>
        <input type="text" name="key" value="<?= h((string)($route['key'] ?? '')) ?>" placeholder="例: reservation / visit" required>
        <div class="help">
          システム内部で使う識別子です（例：reservation、visit）
        </div>
      </div>

      <div>
        <label><b>表示名</b></label>
        <input type="text" name="label" value="<?= h((string)($route['label'] ?? '')) ?>" placeholder="例: 予約 / 面会" required>
      </div>

      <div>
        <label><b>電話番号</b></label>
        <input type="text" name="phone" value="<?= h((string)($route['phone'] ?? '')) ?>" placeholder="例: 0312345678" required>
        <div class="help">ハイフン無し</div>
      </div>

      <div>
        <label><b>並び順</b>（小さいほど上）</label>
        <input type="number" name="sort_order" value="<?= (int)($route['sort_order'] ?? 10) ?>" min="0" step="1">
        <div class="help">おすすめ：10 / 20 / 30 …（間に差し込みたい時は 15 などもOK）</div>
      </div>

      <div>
        <label class="row" style="gap:10px">
          <input type="checkbox" name="is_enabled" value="1" <?= ((int)($route['is_enabled'] ?? 1) === 1) ? 'checked' : '' ?>>
          <b>このルートを有効にする</b>
        </label>
        <div class="help">無効にすると患者アプリに表示されません。</div>
      </div>

      <div class="row" style="justify-content:flex-end">
        <a class="btn" href="routes_list.php">戻る</a>
        <button class="btn primary" type="submit">保存</button>
      </div>
    </div>
  </form>
</div>

<?php admin_footer(); ?>