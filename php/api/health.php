<?php
declare(strict_types=1);

require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/_util.php';

// healthは GET/POST どちらでもOKにしたいなら require_method は使わない
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

json_response([
  'ok' => true,
  'service' => 'duxcall-php-api',
  'time' => date('c'),
  'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
]);