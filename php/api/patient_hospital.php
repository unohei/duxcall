<?php
declare(strict_types=1);

require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_db.php';

/**
 * patient_hospital.php
 * GET /api/patient_hospital.php?code=xxx
 * - hospital / news / routes(today status) を返す
 * - CORS: OPTIONS は即返す
 */

// --- Method guard（allow_methods が無い環境でも落ちないように） ---
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Preflight は即OK
if ($method === 'OPTIONS') {
  // _cors.php がヘッダを付けている想定
  http_response_code(200);
  exit;
}

if (function_exists('allow_methods')) {
  allow_methods(['GET', 'OPTIONS']);
} else {
  // フォールバック
  if ($method !== 'GET') {
    if (function_exists('json_response')) {
      json_response(['detail' => 'Method Not Allowed'], 405);
    }
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['detail' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// --- Param ---
$code = '';
if (function_exists('param_str')) {
  $code = param_str('code');
} else {
  $code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
}

if ($code === '') {
  json_response(['detail' => 'hospital_code required'], 400);
}

$pdo = db();

// --- hospital ---
$hs = $pdo->prepare("
  SELECT id, hospital_code, name, timezone
  FROM hospitals
  WHERE hospital_code = :code AND is_active = 1
  LIMIT 1
");
$hs->execute([':code' => $code]);
$hospital = $hs->fetch(PDO::FETCH_ASSOC);

if (!$hospital) {
  json_response(['detail' => 'Hospital not found'], 404);
}

// timezone（不正でも落ちないように）
$tzName = (string)($hospital['timezone'] ?? 'Asia/Tokyo');
try {
  $tz = new DateTimeZone($tzName ?: 'Asia/Tokyo');
} catch (Throwable $e) {
  $tz = new DateTimeZone('Asia/Tokyo');
}

$now = new DateTimeImmutable('now', $tz);

// --- helper functions ---
function time_to_hm(?string $t): ?string {
  if (!$t) return null;
  // "HH:MM:SS" -> "HH:MM"
  return substr($t, 0, 5);
}

function combine_dt(DateTimeImmutable $d, string $hm, DateTimeZone $tz): DateTimeImmutable {
  // $hm: "HH:MM"
  return new DateTimeImmutable($d->format('Y-m-d') . ' ' . $hm . ':00', $tz);
}

/**
 * その日のスケジュールを返す
 * - exception があれば優先
 * - なければ weekly
 */
function get_schedule_for_date(PDO $pdo, int $routeId, DateTimeImmutable $date, DateTimeZone $tz): array {
  $dow = (int)$date->format('N') - 1; // Mon=0..Sun=6
  $d = $date->format('Y-m-d');

  // exception（新しい created_at 優先）
  $ex = $pdo->prepare("
    SELECT id, title
    FROM route_exceptions
    WHERE route_id = :rid
      AND start_date <= :d
      AND end_date >= :d
    ORDER BY created_at DESC
    LIMIT 1
  ");
  $ex->execute([':rid' => $routeId, ':d' => $d]);
  $exRow = $ex->fetch(PDO::FETCH_ASSOC);

  if ($exRow) {
    $eh = $pdo->prepare("
      SELECT is_closed, open_time, close_time
      FROM route_exception_hours
      WHERE exception_id = :eid AND dow = :dow
      LIMIT 1
    ");
    $eh->execute([':eid' => $exRow['id'], ':dow' => $dow]);
    $ehRow = $eh->fetch(PDO::FETCH_ASSOC);

    if ($ehRow) {
      return [
        'source' => 'exception',
        'title' => $exRow['title'] ?? null,
        'is_closed' => (int)($ehRow['is_closed'] ?? 1) === 1,
        'open_hm' => time_to_hm($ehRow['open_time'] ?? null),
        'close_hm' => time_to_hm($ehRow['close_time'] ?? null),
      ];
    }
  }

  // weekly
  $wh = $pdo->prepare("
    SELECT is_closed, open_time, close_time
    FROM route_weekly_hours
    WHERE route_id = :rid AND dow = :dow
    LIMIT 1
  ");
  $wh->execute([':rid' => $routeId, ':dow' => $dow]);
  $whRow = $wh->fetch(PDO::FETCH_ASSOC);

  if ($whRow) {
    return [
      'source' => 'weekly',
      'title' => null,
      'is_closed' => (int)($whRow['is_closed'] ?? 1) === 1,
      'open_hm' => time_to_hm($whRow['open_time'] ?? null),
      'close_hm' => time_to_hm($whRow['close_time'] ?? null),
    ];
  }

  return [
    'source' => 'none',
    'title' => null,
    'is_closed' => true,
    'open_hm' => null,
    'close_hm' => null,
  ];
}

function find_next_open_at(PDO $pdo, int $routeId, DateTimeImmutable $now, DateTimeZone $tz): ?string {
  for ($i = 0; $i <= 7; $i++) {
    $d = $now->modify("+{$i} days");
    $sch = get_schedule_for_date($pdo, $routeId, $d, $tz);
    if ($sch['is_closed'] || !$sch['open_hm'] || !$sch['close_hm']) continue;

    $open = combine_dt($d, $sch['open_hm'], $tz);
    $close = combine_dt($d, $sch['close_hm'], $tz);

    if ($i === 0) {
      if ($now < $open) return $open->format(DateTimeInterface::ATOM);
      continue;
    }
    if ($open < $close) return $open->format(DateTimeInterface::ATOM);
  }
  return null;
}

function compute_today(PDO $pdo, int $routeId, DateTimeImmutable $now, DateTimeZone $tz): array {
  $today = new DateTimeImmutable($now->format('Y-m-d'), $tz);
  $sch = get_schedule_for_date($pdo, $routeId, $today, $tz);

  $isClosed = (bool)$sch['is_closed'];
  $openHm = $sch['open_hm'];
  $closeHm = $sch['close_hm'];

  $window = null;
  if (!$isClosed && $openHm && $closeHm) {
    $window = ['open' => $openHm, 'close' => $closeHm];
  }

  if ($isClosed || !$openHm || !$closeHm) {
    return [
      'is_open' => false,
      'reason' => 'closed',
      'source' => $sch['source'],
      'window' => $window,
      'next_open_at' => find_next_open_at($pdo, $routeId, $now, $tz),
    ];
  }

  $start = combine_dt($today, $openHm, $tz);
  $end   = combine_dt($today, $closeHm, $tz);

  if ($start <= $now && $now < $end) {
    return [
      'is_open' => true,
      'reason' => 'open',
      'source' => $sch['source'],
      'window' => $window,
      'next_open_at' => null,
    ];
  }

  if ($now < $start) {
    return [
      'is_open' => false,
      'reason' => 'before_open',
      'source' => $sch['source'],
      'window' => $window,
      'next_open_at' => $start->format(DateTimeInterface::ATOM),
    ];
  }

  return [
    'is_open' => false,
    'reason' => 'after_close',
    'source' => $sch['source'],
    'window' => $window,
    'next_open_at' => find_next_open_at($pdo, $routeId, $now, $tz),
  ];
}

// --- news ---
$newsStmt = $pdo->prepare("
  SELECT title, body, priority, updated_at
  FROM news
  WHERE hospital_id = :hid AND is_published = 1
  ORDER BY updated_at DESC
  LIMIT 10
");
$newsStmt->execute([':hid' => $hospital['id']]);
$news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);

// --- routes ---
$routesStmt = $pdo->prepare("
  SELECT id, `key`, label, phone, sort_order
  FROM routes
  WHERE hospital_id = :hid AND is_enabled = 1
  ORDER BY sort_order ASC
");
$routesStmt->execute([':hid' => $hospital['id']]);
$routes = $routesStmt->fetchAll(PDO::FETCH_ASSOC);

$outRoutes = [];
foreach ($routes as $r) {
  $outRoutes[] = [
    'key' => (string)($r['key'] ?? ''),
    'label' => (string)($r['label'] ?? ''),
    'phone' => (string)($r['phone'] ?? ''),
    'today' => compute_today($pdo, (int)$r['id'], $now, $tz),
  ];
}

json_response([
  'hospital' => [
    'code' => (string)$hospital['hospital_code'],
    'name' => (string)$hospital['name'],
    'timezone' => (string)($hospital['timezone'] ?? 'Asia/Tokyo'),
  ],
  'news' => array_map(function($n) use ($tz) {
    $updated = $n['updated_at'] ?? null;
    $atom = null;
    if ($updated) {
      try {
        $atom = (new DateTimeImmutable((string)$updated, $tz))->format(DateTimeInterface::ATOM);
      } catch (Throwable $e) {
        $atom = null;
      }
    }
    return [
      'title' => (string)($n['title'] ?? ''),
      'body' => $n['body'] ?? null,
      'priority' => (($n['priority'] ?? 'normal') === 'high') ? 'high' : 'normal',
      'updated_at' => $atom,
    ];
  }, $news),
  'routes' => $outRoutes,
]);