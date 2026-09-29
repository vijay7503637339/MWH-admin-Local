<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try{
$user=localRequireUser($pdo);
$stmt=$pdo->prepare('SELECT id,type,title,message,data_json,read_at,created_at FROM local_notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$stmt->execute([(int)$user['id']]);
localJson(['success'=>true,'data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]]);
}catch(Throwable $e){error_log('MWH Local notifications: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load notifications'],500);}
