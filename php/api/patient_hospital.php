<?php
declare(strict_types=1);

require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_db.php';

// GET/POST/OPTIONS だけ許可（開発中）
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'OPTIONS'], true)) {
  json_response(['detail' => 'Method Not Allowed'], 405);
}
if ($method === 'OPTIONS') {
  // CORS preflight
  json_response(['ok' => true], 200);
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
  json_response(['detail' => 'hospital_code required'], 400);
}

$pdo = db();

// 1) hospital
$st = $pdo->prepare("
  SELECT id, hospital_code, name, timezone
  FROM hospitals
  WHERE hospital_code = :code AND is_active = 1
  LIMIT 1
");
$st->execute([':code' => $code]);
$hospital = $st->fetch(PDO::FETCH_ASSOC);
if (!$hospital) {
  json_response(['detail' => 'Hospital not found'], 404);
}

$hid = (int)$hospital['id'];
$tzName = (string)($hospital['timezone'] ?? 'Asia/Tokyo');
$tz = new DateTimeZone($tzName);

// 2) routes
$st = $pdo->prepare("
  SELECT id, `key`, label, phone, sort_order
  FROM routes
  WHERE hospital_id = :hid AND is_enabled = 1
  ORDER BY sort_order ASC
");
$st->execute([':hid' => $hid]);
$routes = $st->fetchAll(PDO::FETCH_ASSOC);

$routeIds = array_map(fn($r) => (int)$r['id'], $routes);

$weeklyByRoute = []; // [route_id][dow] => row
if (count($routeIds) > 0) {
  $in = implode(',', array_fill(0, count($routeIds), '?'));
  $st = $pdo->prepare("
    SELECT route_id, dow, open_time, close_time, is_closed
    FROM route_weekly_hours
    WHERE route_id IN ($in)
  ");
  $st->execute($routeIds);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $w) {
    $rid = (int)$w['route_id'];
    $dow = (int)$w['dow']; // Mon=0..Sun=6
    if (!isset($weeklyByRoute[$rid])) $weeklyByRoute[$rid] = [];
    $weeklyByRoute[$rid][$dow] = $w;
  }
}

// 3) news（必要なら）
$st = $pdo->prepare("
  SELECT title, body, priority, updated_at
  FROM news
  WHERE hospital_id = :hid AND is_published = 1
  ORDER BY updated_at DESC
  LIMIT 10
");
$st->execute([':hid' => $hid]);
$news = $st->fetchAll(PDO::FETCH_ASSOC);

// ---- helpers ----
$now = new DateTimeImmutable('now', $tz);

/**
 * DBの TIME "09:00:00" を "09:00" にする
 */
function hm(?string $t): ?string {
  if ($t === null || $t === '') return null;
  return substr($t, 0, 5);
}

/**
 * 日付 + "09:00:00" を Asia/Tokyo の DateTime にする
 */
function combine_dt(DateTimeImmutable $day, string $timeStr, DateTimeZone $tz): DateTimeImmutable {
  // "09:00:00" -> 9,0,0
  [$H,$M,$S] = array_map('intval', explode(':', $timeStr));
  return (new DateTimeImmutable($day->format('Y-m-d'), $tz))->setTime($H, $M, $S);
}

/**
 * 次に開く日時（weeklyのみ）を探す：今日含め最大8日
 */
function find_next_open_at(int $routeId, array $weeklyByRoute, DateTimeImmutable $now, DateTimeZone $tz): ?string {
  $map = $weeklyByRoute[$routeId] ?? [];
  for ($i=0; $i<=7; $i++) {
    $day = $now->modify("+$i day");
    $dow = (int)$day->format('N') - 1; // Mon=0..Sun=6

    if (!isset($map[$dow])) continue;
    $w = $map[$dow];

    $isClosed = (int)$w['is_closed'] === 1;
    $open = $w['open_time'] ?? null;
    $close = $w['close_time'] ?? null;

    if ($isClosed || !$open || !$close) continue;

    $openDt = combine_dt($day, (string)$open, $tz);
    $closeDt = combine_dt($day, (string)$close, $tz);

    // 今日なら「今より後の開始」だけ
    if ($i === 0) {
      if ($now < $openDt) return $openDt->format(DATE_ATOM);
      continue;
    }

    // 通常
    if ($openDt < $closeDt) return $openDt->format(DATE_ATOM);
  }
  return null;
}

/**
 * today status を作る（weeklyのみ）
 */
function compute_today(int $routeId, array $weeklyByRoute, DateTimeImmutable $now, DateTimeZone $tz): array {
  $map = $weeklyByRoute[$routeId] ?? [];

  $dow = (int)$now->format('N') - 1; // Mon=0..Sun=6
  $w = $map[$dow] ?? null;

  if (!$w) {
    return [
      'is_open' => false,
      'reason' => 'closed',
      'source' => 'none',
      'window' => null,
      'next_open_at' => find_next_open_at($routeId, $weeklyByRoute, $now, $tz),
    ];
  }

  $isClosed = (int)$w['is_closed'] === 1;
  $open = $w['open_time'] ?? null;
  $close = $w['close_time'] ?? null;

  $window = null;
  if (!$isClosed && $open && $close) {
    $window = ['open' => hm((string)$open), 'close' => hm((string)$close)];
  }

  if ($isClosed || !$open || !$close) {
    return [
      'is_open' => false,
      'reason' => 'closed',
      'source' => 'weekly',
      'window' => $window,
      'next_open_at' => find_next_open_at($routeId, $weeklyByRoute, $now, $tz),
    ];
  }

  $startDt = combine_dt($now, (string)$open, $tz);
  $endDt   = combine_dt($now, (string)$close, $tz);

  if ($startDt <= $now && $now < $endDt) {
    return [
      'is_open' => true,
      'reason' => 'open',
      'source' => 'weekly',
      'window' => $window,
      'next_open_at' => null,
    ];
  }

  if ($now < $startDt) {
    return [
      'is_open' => false,
      'reason' => 'before_open',
      'source' => 'weekly',
      'window' => $window,
      'next_open_at' => $startDt->format(DATE_ATOM),
    ];
  }

  return [
    'is_open' => false,
    'reason' => 'after_close',
    'source' => 'weekly',
    'window' => $window,
    'next_open_at' => find_next_open_at($routeId, $weeklyByRoute, $now, $tz),
  ];
}

// 4) response
$outRoutes = [];
foreach ($routes as $r) {
  $rid = (int)$r['id'];
  $outRoutes[] = [
    'key' => (string)$r['key'],
    'label' => (string)$r['label'],
    'phone' => (string)$r['phone'],
    'today' => compute_today($rid, $weeklyByRoute, $now, $tz),
  ];
}

$outNews = [];
foreach ($news as $n) {
  $outNews[] = [
    'title' => (string)$n['title'],
    'body' => $n['body'],
    'priority' => (string)$n['priority'],
    'updated_at' => (new DateTimeImmutable((string)$n['updated_at'], $tz))->format(DATE_ATOM),
  ];
}

json_response([
  'hospital' => [
    'code' => (string)$hospital['hospital_code'],
    'name' => (string)$hospital['name'],
    'timezone' => $tzName,
  ],
  'news' => $outNews,
  'routes' => $outRoutes,
]);