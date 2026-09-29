<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
try{
$user=localRequireUser($pdo);if((string)$user['role']!=='staff')localJson(['success'=>false,'message'=>'Staff access is required'],403);
$sum=$pdo->prepare('SELECT COUNT(*) total_jobs,COALESCE(SUM(sp.amount),0) total_earned,COALESCE(SUM(CASE WHEN sp.status="paid" THEN sp.amount ELSE 0 END),0) total_paid FROM local_assignments a LEFT JOIN local_staff_payouts sp ON sp.assignment_id=a.id WHERE a.staff_user_id=? AND a.status="completed"');$sum->execute([(int)$user['id']]);$s=$sum->fetch(PDO::FETCH_ASSOC)?:[];
$pending=max(0,(float)($s['total_earned']??0)-(float)($s['total_paid']??0));
$itemsStmt=$pdo->prepare('SELECT a.id assignment_id,r.title,COALESCE(sp.paid_at,sp.created_at) payment_date,sp.amount amount,sp.status status FROM local_staff_payouts sp INNER JOIN local_assignments a ON a.id=sp.assignment_id INNER JOIN local_requirements r ON r.id=a.requirement_id WHERE sp.staff_user_id=? ORDER BY payment_date DESC,a.id DESC LIMIT 100');$itemsStmt->execute([(int)$user['id']]);
localJson(['success'=>true,'data'=>['summary'=>['total_jobs'=>(int)($s['total_jobs']??0),'total_earned'=>(float)($s['total_earned']??0),'total_paid'=>(float)($s['total_paid']??0),'pending_amount'=>$pending],'items'=>$itemsStmt->fetchAll(PDO::FETCH_ASSOC)]]);
}catch(Throwable $e){error_log('MWH Local staff earnings: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load earnings'],500);}
