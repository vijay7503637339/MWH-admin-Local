<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

try {
    $user = localRequireUser($pdo);
    if ((string)$user['role'] !== 'staff') localJson(['success'=>false,'message'=>'Staff access is required'],403);

    $q=trim((string)($_GET['q']??''));
    $city=trim((string)($_GET['city']??''));
    $minPay=(float)($_GET['min_pay']??0);
    $where=['lr.status="open"','lr.deleted_at IS NULL','lr.shift_date>=UTC_DATE()'];
    $params=[];
    $term='%'.$q.'%';
    if($q!==''){ $where[]='(lr.title LIKE ? OR lr.description LIKE ? OR lr.work_location LIKE ? OR jr.name LIKE ? OR c.name LIKE ?)'; array_push($params,$term,$term,$term,$term,$term);}
    if($city!==''){ $where[]='lr.work_location LIKE ?'; $params[]='%'.$city.'%';}
    if($minPay>0){$where[]='lr.payout_amount>=?';$params[]=$minPay;}

    $sql='SELECT lr.id,lr.title,lr.description,lr.notes,lr.openings_count,lr.work_location,lr.work_address,lr.shift_date,lr.shift_start,lr.shift_end,lr.minimum_experience,lr.payout_amount,lr.payout_period,lr.status,u.name AS contractor_name,c.name AS category_name,jr.name AS job_role_name,
          COALESCE(la.status,"") AS application_status,COALESCE(a.status,"") AS assignment_status
          FROM local_requirements lr
          INNER JOIN local_users u ON u.id=lr.contractor_user_id AND u.role="contractor" AND u.deleted_at IS NULL
          LEFT JOIN local_categories c ON c.id=lr.category_id
          LEFT JOIN local_job_roles jr ON jr.id=lr.job_role_id
          LEFT JOIN local_applications la ON la.requirement_id=lr.id AND la.staff_user_id=?
          LEFT JOIN local_assignments a ON a.requirement_id=lr.id AND a.staff_user_id=?
          WHERE '.implode(' AND ',$where).'
          ORDER BY lr.shift_date ASC,lr.id DESC LIMIT 100';
    $params=array_merge([(int)$user['id'],(int)$user['id']],$params);
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    localJson(['success'=>true,'data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]]);
}catch(Throwable $e){error_log('MWH Local jobs list: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load jobs'],500);}
