<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$admin=currentAdmin();
if(!in_array((string)$admin['role'],['super_admin','finance_admin'],true)){
    http_response_code(403);
    exit('Finance admin access required.');
}

require_once __DIR__ . '/../api/config/database.php';

$paymentId=(int)($_GET['payment_id']??0);
if($paymentId<=0){
    http_response_code(400);
    exit('Invalid payment.');
}

$stmt=$pdo->prepare(
    'SELECT payment_screenshot_path
     FROM local_payments
     WHERE id=?
     LIMIT 1'
);
$stmt->execute([$paymentId]);
$path=(string)($stmt->fetchColumn()??'');

if($path===''){
    http_response_code(404);
    exit('Payment screenshot not found.');
}

$relative=ltrim($path,'/');
$root=dirname(__DIR__);
$fullPath=realpath($root.'/'.$relative);
$uploadRoot=realpath($root.'/uploads');

if($fullPath===false || $uploadRoot===false || !str_starts_with($fullPath,$uploadRoot.DIRECTORY_SEPARATOR) || !is_file($fullPath) || !is_readable($fullPath)){
    http_response_code(404);
    exit('Payment screenshot not found.');
}

$mime=(new finfo(FILEINFO_MIME_TYPE))->file($fullPath) ?: 'application/octet-stream';
$allowed=['image/jpeg','image/png','image/webp'];
if(!in_array($mime,$allowed,true)){
    http_response_code(415);
    exit('Unsupported screenshot type.');
}

header('Content-Type: '.$mime);
header('Content-Length: '.(string)filesize($fullPath));
header('Content-Disposition: inline; filename="payment-'.$paymentId.'.'.($mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg')).'"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
