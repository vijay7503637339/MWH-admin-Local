<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try{
  $user=localRequireUser($pdo);
  if((string)$user['role']!=='contractor')localJson(['success'=>false,'message'=>'Contractor access is required'],403);
  $d=localInput();$assignmentId=(int)($d['assignment_id']??0);$amount=(float)($d['amount']??0);$method=trim((string)($d['payment_method']??'upi'));$reference=trim((string)($d['transaction_reference']??''));
  if($assignmentId<=0||$amount<=0)localJson(['success'=>false,'message'=>'Valid assignment and amount are required'],422);
  if(!in_array($method,['upi','bank_transfer','cash','other'],true))localJson(['success'=>false,'message'=>'Invalid payment method'],422);
  $q=$pdo->prepare('SELECT a.id,a.staff_user_id,a.contractor_user_id,a.payout_amount,a.status FROM local_assignments a WHERE a.id=? AND a.contractor_user_id=? LIMIT 1');$q->execute([$assignmentId,(int)$user['id']]);$a=$q->fetch(PDO::FETCH_ASSOC);
  if(!$a)localJson(['success'=>false,'message'=>'Assignment not found'],404);
  if((string)$a['status']!=='completed')localJson(['success'=>false,'message'=>'Payment can be created after the duty is completed'],409);
  $paid=$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM local_payments WHERE assignment_id=? AND status IN ("pending","paid")');$paid->execute([$assignmentId]);$already=(float)$paid->fetchColumn();
  $remaining=round((float)$a['payout_amount']-$already,2);
  if($remaining<=0)localJson(['success'=>false,'message'=>'This assignment is already fully paid'],409);
  if($amount>$remaining)localJson(['success'=>false,'message'=>'Payment exceeds the remaining amount'],422);
  $stmt=$pdo->prepare('INSERT INTO local_payments(assignment_id,staff_user_id,contractor_user_id,amount,payment_date,payment_method,transaction_reference,status) VALUES(?,?,?,?,UTC_DATE(),?,?,?)');
  $stmt->execute([$assignmentId,(int)$a['staff_user_id'],(int)$user['id'],$amount,$method,$reference!==''?$reference:null,'pending']);
  localJson(['success'=>true,'message'=>'Payment submitted for admin approval','data'=>['payment_id'=>(int)$pdo->lastInsertId(),'status'=>'pending','amount'=>$amount]]);
}catch(Throwable $e){error_log('MWH Local contractor payment create: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to submit payment'],500);}
