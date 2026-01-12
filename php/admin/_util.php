<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/_util.php';

function admin_header(string $title): void {
  echo '<!doctype html><html lang="ja"><head>';
  echo '<meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<title>' . h($title) . '</title>';
  echo '<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Hiragino Kaku Gothic ProN","Noto Sans JP",sans-serif;background:#f6f7fb;margin:0}
    .wrap{max-width:980px;margin:0 auto;padding:18px}
    .card{background:#fff;border:1px solid #e6e8ef;border-radius:14px;padding:16px}
    h1{font-size:20px;margin:0 0 10px}
    .muted{color:#667085}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #eef0f6;text-align:left;vertical-align:top}
    th{color:#475467;font-weight:800;font-size:13px}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-block;padding:10px 12px;border-radius:10px;border:1px solid #d0d5dd;background:#fff;color:#111;text-decoration:none;font-weight:800;cursor:pointer}
    .btn.primary{background:#111;color:#fff;border-color:#111}
    .btn.danger{background:#fff;color:#b42318;border-color:#f2c5c2}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:800}
    .pill.on{background:#ecfdf3;color:#027a48}
    .pill.off{background:#fffbeb;color:#b54708}
    input[type=text],input[type=number]{width:100%;padding:10px;border:1px solid #d0d5dd;border-radius:10px;font-size:15px}
    .help{font-size:12px;color:#667085;margin-top:6px}
    .grid{display:grid;gap:12px}
    .topbar{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px}
  </style>';
  echo '</head><body><div class="wrap">';
}

function admin_footer(): void {
  echo '</div></body></html>';
}