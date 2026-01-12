<?php
declare(strict_types=1);

require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_db.php';

allow_methods(['GET', 'OPTIONS']);

$code = param_str('code');
if ($code === '') json_response(['detail' => 'hospital_code required'], 400);

$pdo = db();

// hospital
$hs = $pdo->prepare("
  SELECT id, hospital_code, name, timezone
  FROM hospitals
  WHERE hospital_code = :code AND is_active = 1
  LIMIT 1
");
$hs->execute([':code' => $code]);
$hospital = $hs->fetch();
if (!$hospital) json_response(['detail' => 'Hospital not found'], 404);

// news
$newsStmt = $pdo->prepare("
  SELECT title, body, priority, updated_at
  FROM news
  WHERE hospital_id = :hid AND is_published = 1
  ORDER BY updated_at DESC
  LIMIT 10
");
$newsStmt->execute([':hid' => $hospital['id']]);
$news = $newsStmt->fetchAll();

// routes
$routesStmt = $pdo->prepare("
  SELECT id, `key`, label, phone, sort_order
  FROM routes
  WHERE hospital_id = :hid AND is_enabled = 1
  ORDER BY sort_order ASC
");
$routesStmt->execute([':hid' => $hospital['id']]);
$routes = $routesStmt->fetchAll();

$tz = new DateTimeZone($hospital['timezone'] ?: 'Asia/Tokyo');
$now = new DateTimeImmutable('now', $tz);

function time_to_hm(?string $t): ?string {
  if (!$t) return null;
  // "HH:MM:SS" -> "HH:MM"
  return substr($t, 0, 5);
}

function combine_dt(DateTimeImmutable $d, string $hm, DateTimeZone $tz): DateTimeImmutable {
  // $hm: "HH:MM"
  return new DateTimeImmutable($d->format('Y-m-d') . ' ' . $hm . ':00', $tz);
}

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
  $exRow = $ex->fetch();

  if ($exRow) {
    $eh = $pdo->prepare("
      SELECT is_closed, open_time, close_time
      FROM route_exception_hours
      WHERE exception_id = :eid AND dow = :dow
      LIMIT 1
    ");
    $eh->execute([':eid' => $exRow['id'], ':dow' => $dow]);
    $ehRow = $eh->fetch();

    if ($ehRow) {
      return [
        'source' => 'exception',
        'title' => $exRow['title'],
        'is_closed' => (int)$ehRow['is_closed'] === 1,
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
  $whRow = $wh->fetch();

  if ($whRow) {
    return [
      'source' => 'weekly',
      'title' => null,
      'is_closed' => (int)$whRow['is_closed'] === 1,
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

  $isClosed = $sch['is_closed'];
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
  $end = combine_dt($today, $closeHm, $tz);

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

$outRoutes = [];
foreach ($routes as $r) {
  $outRoutes[] = [
    'key' => $r['key'],
    'label' => $r['label'],
    'phone' => $r['phone'],
    'today' => compute_today($pdo, (int)$r['id'], $now, $tz),
  ];
}

json_response([
  'hospital' => [
    'code' => $hospital['hospital_code'],
    'name' => $hospital['name'],
    'timezone' => $hospital['timezone'],
  ],
  'news' => array_map(fn($n) => [
    'title' => $n['title'],
    'body' => $n['body'],
    'priority' => $n['priority'],
    'updated_at' => (new DateTimeImmutable($n['updated_at'], $tz))->format(DateTimeInterface::ATOM),
  ], $news),
  'routes' => $outRoutes,
]);