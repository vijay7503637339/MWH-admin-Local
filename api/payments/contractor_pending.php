<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try{
  $user=localRequireUser($pdo);
  if((string)$user['role']!=='contractor')localJson(['success'=>false,'message'=>'Contractor access is required'],403);
  $stmt=$pdo->prepare('SELECT a.id assignment_id,a.staff_user_id,a.payout_amount,r.title,r.work_location,r.shift_date,s.name AS staff_name,COALESCE(SUM(CASE WHEN p.status="paid" THEN p.amount ELSE 0 END),0) paid_amount,COALESCE(SUM(CASE WHEN p.status="pending" THEN p.amount ELSE 0 END),0) pending_amount FROM local_assignments a INNER JOIN local_requirements r ON r.id=a.requirement_id INNER JOIN local_users s ON s.id=a.staff_user_id LEFT JOIN local_payments p ON p.assignment_id=a.id WHERE a.contractor_user_id=? AND a.status IN ("assigned","arrived","active","completed") GROUP BY a.id ORDER BY r.shift_date ASC,a.id ASC');
  $stmt->execute([(int)$user['id']]);
  $items=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $settings=[];
  try{$q=$pdo->query("SELECT company_name,upi_id,account_number,account_name,ifsc_code,qr_code_path,updated_at FROM local_payment_settings WHERE is_active=1 ORDER BY id DESC LIMIT 1");$settings=$q->fetch(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
  $settings['qr_code_url']='payments/qr.php';
  localJson(['success'=>true,'data'=>['settings'=>$settings,'items'=>$items]]);
}catch(Throwable $e){error_log('MWH Local contractor pending payment: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load payment details'],500);}
