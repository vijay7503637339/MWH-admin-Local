<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try {
    $user=localRequireUser($pdo);
    $q=$pdo->query('SELECT qr_code_path FROM local_payment_settings WHERE is_active=1 ORDER BY id DESC LIMIT 1');
    $path=(string)($q->fetchColumn()?:'');
    if($path==='') { http_response_code(404); exit; }
    $full=dirname(__DIR__,2).'/'.$path;
    if(!is_file($full)){http_response_code(404);exit;}
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($full);
    $allowed=['image/jpeg','image/png','image/webp'];
    if(!in_array($mime,$allowed,true)){http_response_code(415);exit;}
    header('Content-Type: '.$mime);
    header('Cache-Control: private, max-age=300');
    readfile($full);
} catch(Throwable $e) { http_response_code(500); exit; }