<?php
declare(strict_types=1);

require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/_util.php';

allow_methods(['GET', 'POST', 'OPTIONS']);

json_response([
  'ok' => true,
  'service' => 'duxcall-php-api',
  'time' => date('c'),
  'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
]);