<?php
declare(strict_types=1);

require_once __DIR__ . '/_util.php';

/**
 * 管理画面 共通レイアウト（超ミニマム版）
 * - routes_list / routes_edit / weekly_hours_edit から呼び出せる
 */
function layout_start(string $title, string $active = ''): void {
  header('Content-Type: text/html; charset=utf-8');

  $t = h($title);
  echo <<<HTML
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>{$t}</title>
  <style>
    body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Hiragino Kaku Gothic ProN","Meiryo",sans-serif;background:#f6f7fb;margin:0;color:#111;}
    .wrap{max-width:980px;margin:0 auto;padding:18px;}
    h1{font-size:22px;margin:0 0 6px;}
    .muted{color:#666;font-size:13px;}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
    .btn{display:inline-block;padding:10px 12px;border-radius:10px;border:1px solid #ddd;background:#fff;color:#111;text-decoration:none;font-weight:800;font-size:14px;}
    .btn.sub{font-weight:700;}
    .icon-btn{padding:8px 10px;border-radius:10px;border:1px solid #ddd;background:#fff;cursor:pointer;font-weight:900;}
    .icon-btn:disabled{opacity:.35;cursor:not-allowed;}
    .alert{padding:10px 12px;border-radius:12px;border:1px solid #e6e8ef;background:#fff;margin:12px 0;}
    .alert.ok{border-color:#cfead9;background:#effaf3;}
    .alert.err{border-color:#ffd0d0;background:#fff2f2;}
    .table-wrap{overflow:auto;border:1px solid #e6e8ef;border-radius:14px;background:#fff;margin-top:12px;}
    table{width:100%;border-collapse:separate;border-spacing:0;min-width:860px;}
    th,td{padding:10px 10px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px;}
    th{font-size:13px;color:#555;text-align:left;background:#fafafa;}
    code{background:#f3f4f6;border-radius:8px;padding:2px 6px;}
    .pill{display:inline-block;padding:2px 10px;border-radius:999px;font-weight:900;font-size:12px;}
    .pill.ok{background:#e8fff3;color:#0a7;}
    .pill.ng{background:#f3f3f3;color:#666;}
  </style>
</head>
<body>
  <div class="wrap">
HTML;
}

function layout_end(): void {
  echo <<<HTML
  </div>
</body>
</html>
HTML;
}