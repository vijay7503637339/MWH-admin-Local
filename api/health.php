<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'success'=>true,
  'service'=>'MWH Local API',
  'database'=>'connected',
  'timestamp'=>gmdate('c'),
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
