<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly' => true,
    'use_strict_mode' => true,
  ]);
}

require_once __DIR__ . '/../lib/_db.php';
require_once __DIR__ . '/../lib/_util.php';

// すでにログイン済み
if (!empty($_SESSION['staff_user_id'])) {
  redirect('routes_list.php');
}

$msg = trim((string)($_GET['msg'] ?? ''));
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($email === '' || $password === '') {
    $error = 'メールアドレスとパスワードを入力してください';
  } else {
    $pdo = db();

    $st = $pdo->prepare("
      SELECT id, hospital_id, email, password_hash, name, is_active
      FROM staff_users
      WHERE email = :e
      LIMIT 1
    ");
    $st->execute([':e' => $email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    $ok = false;

    if ($u && (int)$u['is_active'] === 1) {
      $stored = (string)$u['password_hash'];

      // 先頭が $ なら password_hash() のハッシュとみなす（bcrypt/argon2）
      $looksHashed = ($stored !== '' && $stored[0] === '$');

      if ($looksHashed) {
        $ok = password_verify($password, $stored);
      } else {
        // いまの平文運用にも対応
        $ok = hash_equals($stored, $password);
      }
    }

    if ($ok) {
      session_regenerate_id(true);

      $_SESSION['staff_user_id']  = (int)$u['id'];
      $_SESSION['hospital_id']    = (int)$u['hospital_id'];
      $_SESSION['staff_email']    = (string)$u['email'];
      $_SESSION['staff_name']     = (string)$u['name'];

      redirect('routes_list.php');
    }

    $error = 'メールアドレスまたはパスワードが違います（または無効アカウントです）';
  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>職員ログイン</title>
  <style>
:root { color-scheme: light; }

* { box-sizing: border-box; }

html, body { height: 100%; }

body{
  font-family: system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  background:#f6f7fb;
  margin:0;
  overflow-x: hidden; /* 横はみ出し対策 */
}

.wrap{
  width: min(520px, 100%);
  margin: 40px auto;
  padding: 0 16px; /* 端末で確実に余白確保 */
}

.card{
  width: 100%;
  background:#fff;
  border:1px solid #e6e8ef;
  border-radius:14px;
  padding:18px;
  box-shadow: 0 8px 20px rgba(16,24,40,.06);
}

h1{ font-size:20px; margin:0 0 12px; }

.muted{ color:#667085; font-size:13px; }

label{ display:block; font-weight:800; margin:14px 0 6px; }

input{
  display:block;
  width:100%;
  max-width:100%; /* 重要：親幅を超えない */
  padding:12px;
  border:1px solid #d0d5dd;
  border-radius:12px;
  font-size:16px;
}

.btn{
  margin-top:16px;
  width:100%;
  padding:12px;
  border-radius:12px;
  border:1px solid #111;
  background:#111;
  color:#fff;
  font-weight:900;
  font-size:16px;
  cursor:pointer;
}

.err{
  margin-top:12px;
  background:#fff5f5;
  border:1px solid #ffd0d0;
  color:#b42318;
  border-radius:12px;
  padding:10px;
  font-weight:800;
  word-break: break-word; /* エラー文が長くてもはみ出さない */
}

.ok{
  margin-top:12px;
  background:#f0fdf4;
  border:1px solid #bbf7d0;
  color:#166534;
  border-radius:12px;
  padding:10px;
  font-weight:800;
  word-break: break-word;
}

@media (max-width: 420px){
  .wrap{ margin: 20px auto; padding: 0 12px; }
  .card{ padding: 14px; }
}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>職員ログイン</h1>
      <div class="muted">管理画面に入るためのログインです。</div>

      <?php if ($msg !== ''): ?>
        <div class="ok"><?= h($msg) ?></div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="err"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <label>メールアドレス</label>
        <input type="email" name="email"
               value="<?= h((string)($_POST['email'] ?? '')) ?>"
               placeholder="例: unoki@gz.jp" required />

        <label>パスワード</label>
        <input type="password" name="password" required />

        <button class="btn" type="submit">ログイン</button>
      </form>

      <div class="muted" style="margin-top:12px;">
      </div>
    </div>
  </div>
</body>
</html>