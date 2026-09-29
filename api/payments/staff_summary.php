<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try{
$user=localRequireUser($pdo);if((string)$user['role']!=='staff')localJson(['success'=>false,'message'=>'Staff access is required'],403);
$sum=$pdo->prepare('SELECT COUNT(*) total_jobs,COALESCE(SUM(p.amount),0) total_paid,COALESCE(SUM(a.payout_amount),0) total_earned FROM local_assignments a LEFT JOIN local_payments p ON p.assignment_id=a.id AND p.status="paid" WHERE a.staff_user_id=? AND a.status<>"cancelled"');$sum->execute([(int)$user['id']]);$s=$sum->fetch(PDO::FETCH_ASSOC)?:[];
$pending=max(0,(float)($s['total_earned']??0)-(float)($s['total_paid']??0));
$itemsStmt=$pdo->prepare('SELECT a.id assignment_id,r.title,COALESCE(p.payment_date,DATE(a.assigned_at)) payment_date,COALESCE(p.amount,a.payout_amount) amount,COALESCE(p.status,"pending") status FROM local_assignments a INNER JOIN local_requirements r ON r.id=a.requirement_id LEFT JOIN local_payments p ON p.assignment_id=a.id AND p.staff_user_id=a.staff_user_id WHERE a.staff_user_id=? AND a.status<>"cancelled" ORDER BY payment_date DESC,a.id DESC LIMIT 100');$itemsStmt->execute([(int)$user['id']]);
localJson(['success'=>true,'data'=>['summary'=>['total_jobs'=>(int)($s['total_jobs']??0),'total_earned'=>(float)($s['total_earned']??0),'total_paid'=>(float)($s['total_paid']??0),'pending_amount'=>$pending],'items'=>$itemsStmt->fetchAll(PDO::FETCH_ASSOC)]]);
}catch(Throwable $e){error_log('MWH Local staff earnings: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load earnings'],500);}
