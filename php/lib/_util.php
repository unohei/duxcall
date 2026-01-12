<?php
declare(strict_types=1);

/**
 * HTMLエスケープ
 */
function h(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/**
 * JSONレスポンス
 */
function json_response(array $data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * メソッド制限
 */
function require_method(string $method): void {
  $req = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($req !== $method) {
    json_response(['detail' => 'Method Not Allowed'], 405);
  }
}

/**
 * リダイレクト（←★ 今回のエラー原因）
 */
function redirect(string $url): void {
  header('Location: ' . $url);
  exit;
}

/**
 * 管理画面 共通レイアウト開始
 */
function layout_start(string $title, string $active = ''): void {
  ?>
  <!doctype html>
  <html lang="ja">
  <head>
    <meta charset="utf-8">
    <title><?= h($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      body { font-family: system-ui, sans-serif; background:#f7f7f7; margin:0; }
      .container { max-width: 960px; margin: 0 auto; padding: 20px; background:#fff; }
      .row { display:flex; gap:10px; align-items:center; }
      .btn { padding:10px 14px; border-radius:8px; background:#111; color:#fff; text-decoration:none; font-weight:700; border:none; }
      .btn.sub { background:#eee; color:#111; }
      .icon-btn { padding:6px 10px; border-radius:6px; border:1px solid #ccc; background:#fff; cursor:pointer; }
      .table { width:100%; border-collapse:collapse; margin-top:12px; }
      .table th, .table td { border-bottom:1px solid #ddd; padding:10px; text-align:left; }
      .badge { background:#eee; padding:4px 8px; border-radius:6px; font-weight:700; }
      .pill.ok { color:#0a7; font-weight:700; }
      .pill.ng { color:#b00; font-weight:700; }
      .alert { margin:12px 0; padding:10px 12px; background:#f0f0f0; border-radius:8px; }
      .alert.ok { background:#e7f6ef; }
      .alert.err { background:#fdeaea; }
      .muted { color:#666; font-size:13px; }
      .input { padding:8px; border-radius:6px; border:1px solid #ccc; }
    </style>
  </head>
  <body>
    <div class="container">
  <?php
}

/**
 * 管理画面 共通レイアウト終了
 */
function layout_end(): void {
  ?>
    </div>
  </body>
  </html>
  <?php
}