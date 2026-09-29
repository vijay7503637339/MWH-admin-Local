<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/pricing.php';
try{
$user=localRequireUser($pdo);if((string)$user['role']!=='staff') localJson(['success'=>false,'message'=>'Staff access is required'],403);
$id=(int)($_GET['requirement_id']??$_GET['id']??0);if($id<=0)localJson(['success'=>false,'message'=>'requirement_id is required'],422);
$stmt=$pdo->prepare('SELECT lr.*,u.name AS contractor_name,c.name AS category_name,jr.name AS job_role_name,jr.job_amount AS role_master_amount,jr.amount_period AS role_amount_period,COALESCE(la.status,"") AS application_status,COALESCE(a.status,"") AS assignment_status FROM local_requirements lr INNER JOIN local_users u ON u.id=lr.contractor_user_id LEFT JOIN local_categories c ON c.id=lr.category_id LEFT JOIN local_job_roles jr ON jr.id=lr.job_role_id LEFT JOIN local_applications la ON la.requirement_id=lr.id AND la.staff_user_id=? LEFT JOIN local_assignments a ON a.requirement_id=lr.id AND a.staff_user_id=? WHERE lr.id=? AND lr.deleted_at IS NULL LIMIT 1');
$stmt->execute([(int)$user['id'],(int)$user['id'],$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row)localJson(['success'=>false,'message'=>'Job not found'],404);
$grossPerStaff=(int)($row['openings_count']??0)>0?(float)$row['payout_amount']/(int)$row['openings_count']:(float)$row['payout_amount'];
$row['staff_payout_amount']=localStaffNetAmount($grossPerStaff);
$row['staff_payout_period']=(string)($row['role_amount_period']??$row['payout_period']??'per_day');
localJson(['success'=>true,'data'=>$row]);
}catch(Throwable $e){error_log('MWH Local job detail: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load job'],500);}
