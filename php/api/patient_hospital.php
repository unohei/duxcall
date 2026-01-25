<?php
declare(strict_types=1);

// 例外が起きたら必ずファイルに吐く（サクラで最強）
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/_php_error.log');
error_reporting(E_ALL);

require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_db.php';

/**
 * patient_hospital.php
 * GET /api/patient_hospital.php?code=xxx
 */

// ---- fallback (環境差で関数が無いときでも落とさない) ----
if (!function_exists('json_response')) {
  function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('param_str')) {
  function param_str(string $key): string {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : '';
  }
}

// ---- 例外はログに出して 500 を返す（さくら対策の要） ----
function api_fatal(Throwable $e): void {
  @file_put_contents(
    __DIR__ . '/_php_error.log',
    "[" . date('c') . "] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
    FILE_APPEND
  );
  json_response(['detail' => 'Internal Server Error'], 500);
}

try {
  // --- Method guard ---
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
  }

  if (function_exists('allow_methods')) {
    allow_methods(['GET', 'OPTIONS']);
  } else {
    if ($method !== 'GET') json_response(['detail' => 'Method Not Allowed'], 405);
  }

  // --- Param ---
  $code = param_str('code');
  if ($code === '') json_response(['detail' => 'hospital_code required'], 400);

  $pdo = db();

  // --- hospital ---
  $hs = $pdo->prepare("
    SELECT id, hospital_code, name, timezone
    FROM hospitals
    WHERE hospital_code = :code AND is_active = 1
    LIMIT 1
  ");
  // ★ execute のキーはコロン無しで統一（環境差でHY093回避）
  $hs->execute(['code' => $code]);

  $hospital = $hs->fetch(PDO::FETCH_ASSOC);
  if (!$hospital) json_response(['detail' => 'Hospital not found'], 404);

  // timezone（不正でも落ちない）
  $tzName = (string)($hospital['timezone'] ?? 'Asia/Tokyo');
  try {
    $tz = new DateTimeZone($tzName ?: 'Asia/Tokyo');
  } catch (Throwable $e) {
    $tz = new DateTimeZone('Asia/Tokyo');
  }
  $now = new DateTimeImmutable('now', $tz);

  // --- helpers ---
  $time_to_hm = function (?string $t): ?string {
    if (!$t) return null;
    return substr($t, 0, 5);
  };

  $combine_dt = function (DateTimeImmutable $d, string $hm, DateTimeZone $tz): DateTimeImmutable {
    return new DateTimeImmutable($d->format('Y-m-d') . ' ' . $hm . ':00', $tz);
  };

  $get_schedule_for_date = function (PDO $pdo, int $routeId, DateTimeImmutable $date) use ($time_to_hm): array {
    $dow = (int)$date->format('N') - 1; // Mon=0..Sun=6
    $d = $date->format('Y-m-d');

    // exception（テーブル未作成でもweeklyへフォールバック）
    try {
      $ex = $pdo->prepare("
        SELECT id, title
        FROM route_exceptions
        WHERE route_id = :rid
          AND start_date <= :d
          AND end_date >= :d
        ORDER BY created_at DESC
        LIMIT 1
      ");
      // ★ コロン無し
      $ex->execute(['rid' => $routeId, 'd' => $d]);
      $exRow = $ex->fetch(PDO::FETCH_ASSOC);

      if ($exRow) {
        $eh = $pdo->prepare("
          SELECT is_closed, open_time, close_time
          FROM route_exception_hours
          WHERE exception_id = :eid AND dow = :dow
          LIMIT 1
        ");
        // ★ コロン無し
        $eh->execute(['eid' => (int)$exRow['id'], 'dow' => $dow]);
        $ehRow = $eh->fetch(PDO::FETCH_ASSOC);

        if ($ehRow) {
          return [
            'source' => 'exception',
            'title' => $exRow['title'] ?? null,
            'is_closed' => (int)($ehRow['is_closed'] ?? 1) === 1,
            'open_hm' => $time_to_hm($ehRow['open_time'] ?? null),
            'close_hm' => $time_to_hm($ehRow['close_time'] ?? null),
          ];
        }
      }
    } catch (Throwable $e) {
      // 例外テーブルが無い / 権限 / 参照エラーなどは weekly で継続
      // 必要ならログに残してもOK（大量に出るのでコメントアウト推奨）
      // @file_put_contents(__DIR__ . '/_php_error.log', "[".date('c')."] ".$e->getMessage()."\n", FILE_APPEND);
    }

    // weekly
    $wh = $pdo->prepare("
      SELECT is_closed, open_time, close_time
      FROM route_weekly_hours
      WHERE route_id = :rid AND dow = :dow
      LIMIT 1
    ");
    // ★ コロン無し
    $wh->execute(['rid' => $routeId, 'dow' => $dow]);
    $whRow = $wh->fetch(PDO::FETCH_ASSOC);

    if ($whRow) {
      return [
        'source' => 'weekly',
        'title' => null,
        'is_closed' => (int)($whRow['is_closed'] ?? 1) === 1,
        'open_hm' => $time_to_hm($whRow['open_time'] ?? null),
        'close_hm' => $time_to_hm($whRow['close_time'] ?? null),
      ];
    }

    return [
      'source' => 'none',
      'title' => null,
      'is_closed' => true,
      'open_hm' => null,
      'close_hm' => null,
    ];
  };

  $find_next_open_at = function (PDO $pdo, int $routeId, DateTimeImmutable $now, DateTimeZone $tz)
    use ($get_schedule_for_date, $combine_dt): ?string {
      for ($i = 0; $i <= 14; $i++) {
        $d = $now->modify("+{$i} days");
        $sch = $get_schedule_for_date($pdo, $routeId, $d);
        if ($sch['is_closed'] || !$sch['open_hm'] || !$sch['close_hm']) continue;

        $open = $combine_dt($d, $sch['open_hm'], $tz);
        $close = $combine_dt($d, $sch['close_hm'], $tz);
        if ($open >= $close) continue;

        if ($i === 0) {
          if ($now < $open) return $open->format(DateTimeInterface::ATOM);
          continue;
        }
        return $open->format(DateTimeInterface::ATOM);
      }
      return null;
    };

  $compute_today = function (PDO $pdo, int $routeId, DateTimeImmutable $now, DateTimeZone $tz)
    use ($get_schedule_for_date, $combine_dt, $find_next_open_at): array {

      $today = new DateTimeImmutable($now->format('Y-m-d'), $tz);
      $sch = $get_schedule_for_date($pdo, $routeId, $today);

      $isClosed = (bool)$sch['is_closed'];
      $openHm = $sch['open_hm'];
      $closeHm = $sch['close_hm'];

      $window = null;
      if (!$isClosed && $openHm && $closeHm) $window = ['open' => $openHm, 'close' => $closeHm];

      if ($isClosed || !$openHm || !$closeHm) {
        return [
          'is_open' => false,
          'reason' => 'closed',
          'source' => $sch['source'],
          'window' => $window,
          'next_open_at' => $find_next_open_at($pdo, $routeId, $now, $tz),
        ];
      }

      $start = $combine_dt($today, $openHm, $tz);
      $end   = $combine_dt($today, $closeHm, $tz);

      if ($start <= $now && $now < $end) {
        return ['is_open' => true, 'reason' => 'open', 'source' => $sch['source'], 'window' => $window, 'next_open_at' => null];
      }
      if ($now < $start) {
        return ['is_open' => false, 'reason' => 'before_open', 'source' => $sch['source'], 'window' => $window, 'next_open_at' => $start->format(DateTimeInterface::ATOM)];
      }
      return [
        'is_open' => false,
        'reason' => 'after_close',
        'source' => $sch['source'],
        'window' => $window,
        'next_open_at' => $find_next_open_at($pdo, $routeId, $now, $tz),
      ];
    };

  // --- news ---
  $newsStmt = $pdo->prepare("
    SELECT title, body, priority, updated_at
    FROM news
    WHERE hospital_id = :hid AND is_published = 1
    ORDER BY updated_at DESC
    LIMIT 10
  ");
  // ★ コロン無し
  $newsStmt->execute(['hid' => (int)$hospital['id']]);
  $news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);

  // --- routes ---
  $routesStmt = $pdo->prepare("
    SELECT id, `key`, label, phone, sort_order
    FROM routes
    WHERE hospital_id = :hid AND is_enabled = 1
    ORDER BY sort_order ASC, id ASC
  ");
  // ★ コロン無し
  $routesStmt->execute(['hid' => (int)$hospital['id']]);
  $routes = $routesStmt->fetchAll(PDO::FETCH_ASSOC);

  $outRoutes = [];
  foreach ($routes as $r) {
    $outRoutes[] = [
      'key' => (string)($r['key'] ?? ''),
      'label' => (string)($r['label'] ?? ''),
      'phone' => (string)($r['phone'] ?? ''),
      'today' => $compute_today($pdo, (int)$r['id'], $now, $tz),
    ];
  }

  $outNews = array_map(function($n) use ($tz) {
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
  }, $news);

  json_response([
    'hospital' => [
      'code' => (string)$hospital['hospital_code'],
      'name' => (string)$hospital['name'],
      'timezone' => (string)($hospital['timezone'] ?? 'Asia/Tokyo'),
    ],
    'news' => $outNews,
    'routes' => $outRoutes,
  ]);

} catch (Throwable $e) {
  api_fatal($e);
}