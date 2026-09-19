<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try{
  $stmt=$pdo->query('SELECT id,name,description,sort_order FROM local_categories WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order,name');
  localJson(['success'=>true,'data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]]);
}catch(Throwable $e){error_log('MWH Local categories: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load categories'],500);}
