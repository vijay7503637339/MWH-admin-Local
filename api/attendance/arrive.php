<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') localJson(['success'=>false,'message'=>'POST request required'],405);
try{
  $user=localRequireUser($pdo);
  if((string)$user['role']!=='staff') localJson(['success'=>false,'message'=>'Staff access is required'],403);
  $d=localInput();$id=(int)($d['assignment_id']??0);
  if($id<=0)localJson(['success'=>false,'message'=>'assignment_id is required'],422);
  $q=$pdo->prepare('SELECT id,status,start_at,arrival_at FROM local_assignments WHERE id=? AND staff_user_id=? LIMIT 1');$q->execute([$id,(int)$user['id']]);$a=$q->fetch(PDO::FETCH_ASSOC);
  if(!$a)localJson(['success'=>false,'message'=>'Assignment not found'],404);
  if(!in_array((string)$a['status'],['assigned','arrived'],true))localJson(['success'=>false,'message'=>'Arrival cannot be marked for this assignment'],409);
  $arrivalAt = $a['arrival_at'] ?: gmdate('Y-m-d H:i:s');
  $pdo->prepare('UPDATE local_assignments SET status="arrived",arrival_at=COALESCE(arrival_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=? AND staff_user_id=?')->execute([$id,(int)$user['id']]);
  localJson(['success'=>true,'message'=>'Arrival marked','data'=>['assignment_id'=>$id,'status'=>'arrived','arrival_at'=>$arrivalAt]]);
}catch(Throwable $e){error_log('MWH Local arrive: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to mark arrival'],500);}
