<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')localJson(['success'=>false,'message'=>'POST request required'],405);
try{
  $user=localRequireUser($pdo);if((string)$user['role']!=='staff')localJson(['success'=>false,'message'=>'Staff access is required'],403);
  $d=localInput();$id=(int)($d['assignment_id']??0);if($id<=0)localJson(['success'=>false,'message'=>'assignment_id is required'],422);
  $q=$pdo->prepare('SELECT id,status,payout_amount FROM local_assignments WHERE id=? AND staff_user_id=? LIMIT 1');$q->execute([$id,(int)$user['id']]);$a=$q->fetch(PDO::FETCH_ASSOC);
  if(!$a)localJson(['success'=>false,'message'=>'Assignment not found'],404);
  if((string)$a['status']!=='active')localJson(['success'=>false,'message'=>'Duty is not active'],409);
  $pdo->beginTransaction();
  $pdo->prepare('UPDATE local_attendance SET check_out_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE assignment_id=? AND staff_user_id=? AND check_out_at IS NULL')->execute([$id,(int)$user['id']]);
  $pdo->prepare('UPDATE local_assignments SET status="completed",end_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
  $payout=$pdo->prepare('INSERT INTO local_staff_payouts(assignment_id,staff_user_id,amount,status) VALUES(?,?,?,"pending") ON DUPLICATE KEY UPDATE amount=VALUES(amount),updated_at=UTC_TIMESTAMP()');
  $payout->execute([$id,(int)$user['id'],(float)$a['payout_amount']]);
  $pdo->commit();
  localJson(['success'=>true,'message'=>'Duty completed','data'=>['assignment_id'=>$id,'status'=>'completed']]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('MWH Local completion: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to complete duty'],500);}