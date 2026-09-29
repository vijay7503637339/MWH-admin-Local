<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/pricing.php';
try{
$user=localRequireUser($pdo);if((string)$user['role']!=='staff')localJson(['success'=>false,'message'=>'Staff access is required'],403);
$stmt=$pdo->prepare('SELECT la.id,la.status AS application_status,la.applied_at,lr.id AS requirement_id,lr.title,lr.description,lr.openings_count,lr.work_location,lr.work_address,lr.shift_date,lr.shift_start,lr.shift_end,lr.minimum_experience,lr.payout_amount,lr.payout_period,u.name AS contractor_name,c.name AS category_name,jr.name AS job_role_name,jr.job_amount,jr.amount_period,COALESCE(a.status,"") AS assignment_status FROM local_applications la INNER JOIN local_requirements lr ON lr.id=la.requirement_id INNER JOIN local_users u ON u.id=lr.contractor_user_id LEFT JOIN local_categories c ON c.id=lr.category_id LEFT JOIN local_job_roles jr ON jr.id=lr.job_role_id LEFT JOIN local_assignments a ON a.application_id=la.id AND a.staff_user_id=la.staff_user_id WHERE la.staff_user_id=? ORDER BY la.applied_at DESC');
$stmt->execute([(int)$user['id']]);$items=$stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($items as &$item){
  $grossPerStaff=(int)($item['openings_count']??0)>0?(float)$item['payout_amount']/(int)$item['openings_count']:(float)$item['payout_amount'];
  $item['staff_payout_amount']=localStaffNetAmount($grossPerStaff);
  $item['staff_payout_period']=(string)($item['amount_period']??$item['payout_period']??'per_day');
}
$sum=$pdo->prepare('SELECT COUNT(*) total,SUM(status="applied") applied,SUM(status="shortlisted") shortlisted,SUM(status="selected") selected,SUM(status="rejected") rejected FROM local_applications WHERE staff_user_id=?');$sum->execute([(int)$user['id']]);$s=$sum->fetch(PDO::FETCH_ASSOC)?:[];
localJson(['success'=>true,'data'=>['items'=>$items,'summary'=>['total'=>(int)($s['total']??0),'applied'=>(int)($s['applied']??0),'shortlisted'=>(int)($s['shortlisted']??0),'selected'=>(int)($s['selected']??0),'rejected'=>(int)($s['rejected']??0)]]]);
}catch(Throwable $e){error_log('MWH Local staff applications: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load applications'],500);}
