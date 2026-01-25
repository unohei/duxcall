<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_staff_login();

require_once __DIR__ . '/../lib/_util.php';
require_once __DIR__ . '/../lib/_db.php';
require_once __DIR__ . '/../lib/_layout.php';

$pdo = db();

$hospitalCode = trim((string)($_GET['code'] ?? ($_POST['code'] ?? '')));
$routeId = (int)($_GET['route_id'] ?? ($_POST['route_id'] ?? 0));

if ($hospitalCode === '' || $routeId <= 0) {
  redirect('./routes_list.php?msg=' . urlencode('URLが不正です'));
}

// 病院&ルート確認
$stmt = $pdo->prepare("
  SELECT h.id AS hospital_id, h.name AS hospital_name, r.id AS route_id, r.label, r.`key`
  FROM hospitals h
  JOIN routes r ON r.hospital_id = h.id
  WHERE h.hospital_code = :code AND r.id = :rid
  LIMIT 1
");
$stmt->execute([':code' => $hospitalCode, ':rid' => $routeId]);
$ctx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ctx) {
  redirect('./routes_list.php?code=' . urlencode($hospitalCode) . '&msg=' . urlencode('ルートが見つかりません'));
}

$dowLabels = [
  0 => '月', 1 => '火', 2 => '水', 3 => '木', 4 => '金', 5 => '土', 6 => '日'
];

function hm_or_empty(?string $t): string {
  // DBの TIME は "HH:MM:SS" で来ることが多いので HH:MM に整形
  if (!$t) return '';
  if (preg_match('/^\d{2}:\d{2}/', $t, $m)) return substr($t, 0, 5);
  return $t;
}

// 既存を読む（無ければ空として扱う）
$rows = $pdo->prepare("
  SELECT dow, open_time, close_time, is_closed
  FROM route_weekly_hours
  WHERE route_id = :rid
");
$rows->execute([':rid' => $routeId]);

$byDow = [];
foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $byDow[(int)$r['dow']] = [
    'open' => hm_or_empty($r['open_time']),
    'close' => hm_or_empty($r['close_time']),
    'is_closed' => (int)$r['is_closed'] === 1,
  ];
}

// POST保存
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  require_method('POST');

  $pdo->beginTransaction();
  try {
    $upsert = $pdo->prepare("
      INSERT INTO route_weekly_hours (route_id, dow, open_time, close_time, is_closed)
      VALUES (:rid, :dow, :open, :close, :closed)
      ON DUPLICATE KEY UPDATE
        open_time = VALUES(open_time),
        close_time = VALUES(close_time),
        is_closed = VALUES(is_closed)
    ");

    for ($dow = 0; $dow <= 6; $dow++) {
      $closed = isset($_POST['closed'][$dow]) ? 1 : 0;
      $open = trim((string)($_POST['open'][$dow] ?? ''));
      $close = trim((string)($_POST['close'][$dow] ?? ''));

      if ($closed === 1) {
        $open = '';
        $close = '';
      } else {
        // open/close は両方セットを推奨（どっちか欠けたら閉鎖扱いに寄せる）
        if ($open === '' || $close === '') {
          $closed = 1;
          $open = '';
          $close = '';
        }
      }

      $upsert->execute([
        ':rid' => $routeId,
        ':dow' => $dow,
        ':open' => $open === '' ? null : ($open . ':00'),
        ':close' => $close === '' ? null : ($close . ':00'),
        ':closed' => $closed,
      ]);
    }

    $pdo->commit();
    redirect('./weekly_hours_edit.php?code=' . urlencode($hospitalCode) . '&route_id=' . $routeId . '&msg=' . urlencode('保存しました'));
  } catch (Throwable $e) {
    $pdo->rollBack();
    redirect('./weekly_hours_edit.php?code=' . urlencode($hospitalCode) . '&route_id=' . $routeId . '&msg=' . urlencode('保存に失敗しました'));
  }
}

layout_start('受付時間', 'routes');
?>

<div class="row" style="justify-content:space-between; align-items:flex-end;">
  <div>
    <h1 style="margin:0 0 6px;">受付時間</h1>
    <div class="muted">
      病院：<strong><?= h((string)$ctx['hospital_name']) ?></strong> / ルート：<strong><?= h((string)$ctx['label']) ?></strong>
      <span class="muted">（key: <?= h((string)$ctx['key']) ?>）</span>
    </div>
  </div>

  <div class="row">
    <a class="btn sub" href="./routes_list.php?code=<?= h(urlencode($hospitalCode)) ?>">一覧へ戻る</a>
  </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
  <p class="alert ok"><?= h((string)$_GET['msg']) ?></p>
<?php endif; ?>

<form method="POST" action="./weekly_hours_edit.php">
  <input type="hidden" name="code" value="<?= h($hospitalCode) ?>">
  <input type="hidden" name="route_id" value="<?= (int)$routeId ?>">

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th style="width:90px;">曜日</th>
          <th style="width:110px;">休み</th>
          <th style="width:160px;">開始</th>
          <th style="width:160px;">終了</th>
          <th>メモ</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($dow = 0; $dow <= 6; $dow++):
          $v = $byDow[$dow] ?? ['open' => '', 'close' => '', 'is_closed' => true];
          $isClosed = $v['is_closed'];
        ?>
          <tr>
            <td style="font-weight:900;"><?= h($dowLabels[$dow]) ?></td>

            <td>
              <label class="row" style="gap:8px;">
                <input type="checkbox" name="closed[<?= $dow ?>]" <?= $isClosed ? 'checked' : '' ?>>
                <span class="muted">休み</span>
              </label>
            </td>

            <td>
              <input class="input"
                    type="time"
                    name="open[<?= $dow ?>]"
                    value="<?= h((string)$v['open']) ?>"
                    <?= $isClosed ? 'disabled' : '' ?>>
            </td>

            <td>
              <input class="input"
                    type="time"
                    name="close[<?= $dow ?>]"
                    value="<?= h((string)$v['close']) ?>"
                    <?= $isClosed ? 'disabled' : '' ?>>
            </td>

            <td class="muted">※ 時間が片方だけの場合は「休み」扱いで保存します</td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <div class="row" style="margin-top:12px; justify-content:flex-end;">
    <button class="btn" type="submit">保存</button>
  </div>
</form>

<script>
  // 「休み」チェックで time入力のdisabledを切り替える
  document.querySelectorAll('input[type="checkbox"][name^="closed"]').forEach((chk) => {
    chk.addEventListener('change', () => {
      const tr = chk.closest('tr');
      if (!tr) return;
      const times = tr.querySelectorAll('input[type="time"]');
      times.forEach((t) => {
        t.disabled = chk.checked;
        if (chk.checked) t.value = '';
      });
    });
  });
</script>

<?php layout_end(); ?>