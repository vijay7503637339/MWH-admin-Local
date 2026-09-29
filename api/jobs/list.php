<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/pricing.php';

try {
    $user = localRequireUser($pdo);
    if ((string)$user['role'] !== 'staff') localJson(['success'=>false,'message'=>'Staff access is required'],403);

    $q=trim((string)($_GET['q']??''));
    $city=trim((string)($_GET['city']??''));
    $minPay=(float)($_GET['min_pay']??0);
    $where=[
        'lr.status IN ("open","active")',
        'lr.deleted_at IS NULL',
        'lr.shift_date>=DATE(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 330 MINUTE))',
        '(SELECT COUNT(*) FROM local_applications sa WHERE sa.requirement_id=lr.id AND sa.status="selected") < lr.openings_count'
    ];
    $params=[];
    $term='%'.$q.'%';
    if($q!==''){ $where[]='(lr.title LIKE ? OR lr.description LIKE ? OR lr.work_location LIKE ? OR jr.name LIKE ? OR c.name LIKE ?)'; array_push($params,$term,$term,$term,$term,$term);}
    if($city!==''){ $where[]='lr.work_location LIKE ?'; $params[]='%'.$city.'%';}
    if($minPay>0){$where[]='(COALESCE(jr.job_amount,lr.payout_amount/NULLIF(lr.openings_count,0))*((100.0-LOCAL_STAFF_COMMISSION_PERCENT)/100.0))>=?';$params[]=$minPay;}

    $sql='SELECT lr.id,lr.title,lr.description,lr.notes,lr.openings_count,
                 (lr.openings_count - (SELECT COUNT(*) FROM local_applications sa WHERE sa.requirement_id=lr.id AND sa.status="selected")) AS available_openings,
                 lr.work_location,lr.work_address,lr.shift_date,lr.shift_start,lr.shift_end,lr.minimum_experience,lr.payout_amount,lr.payout_period,lr.status,u.name AS contractor_name,c.name AS category_name,jr.name AS job_role_name,jr.job_amount AS role_master_amount,jr.amount_period AS role_amount_period,
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
    $items=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($items as &$item){
        $grossPerStaff=(int)($item['openings_count']??0)>0
            ? (float)$item['payout_amount']/(int)$item['openings_count']
            : (float)$item['payout_amount'];
        $item['staff_payout_amount']=localStaffNetAmount($grossPerStaff);
        $item['staff_payout_period']=(string)($item['role_amount_period']??$item['payout_period']??'per_day');
    }
    unset($item);
    localJson(['success'=>true,'data'=>['items'=>$items]]);
}catch(Throwable $e){error_log('MWH Local jobs list: '.$e->getMessage());localJson(['success'=>false,'message'=>'Unable to load jobs'],500);}
