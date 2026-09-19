<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try{
  $stmt=$pdo->query('SELECT jr.id,jr.category_id,jr.name,jr.description,jr.sort_order,lc.name AS category_name
                     FROM local_job_roles jr
                     INNER JOIN local_categories lc ON lc.id=jr.category_id
                     WHERE jr.is_active=1 AND jr.deleted_at IS NULL AND lc.is_active=1 AND lc.deleted_at IS NULL
                     ORDER BY lc.sort_order,lc.name,jr.sort_order,jr.name');
  localJson(['success'=>true,'data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]]);
}catch(Throwable $e){error_log('MWH Local job roles: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load job roles'],500);}
