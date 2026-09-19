<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET','POST'], true)) {
    localJson(['success'=>false,'message'=>'GET or POST request required'],405);
}

try {
    $user=localRequireUser($pdo);
    if ((string)$user['role'] !== 'contractor') {
        localJson(['success'=>false,'message'=>'Contractor access is required'],403);
    }

    $input=localInput();
    $status=trim((string)($input['status'] ?? $_GET['status'] ?? ''));
    $allowed=['open','active','completed','cancelled','closed'];
    if ($status!=='' && !in_array($status,$allowed,true)) {
        localJson(['success'=>false,'message'=>'Invalid status'],422);
    }

    $limit=(int)($input['limit'] ?? $_GET['limit'] ?? 20);
    $limit=max(1,min(100,$limit));

    $sql='SELECT lr.id,lr.category_id,lr.job_role_id,lr.title,lr.description,
                 lr.openings_count,lr.work_location,lr.work_address,lr.shift_date,
                 lr.shift_start,lr.shift_end,lr.minimum_experience,lr.payout_amount,
                 lr.payout_period,lr.status,lr.notes,lr.created_at,lr.updated_at,
                 c.name AS category_name,jr.name AS job_role_name
          FROM local_requirements lr
          LEFT JOIN local_categories c ON c.id=lr.category_id
          LEFT JOIN local_job_roles jr ON jr.id=lr.job_role_id
          WHERE lr.contractor_user_id=? AND lr.deleted_at IS NULL';
    $params=[(int)$user['id']];
    if($status!==''){ $sql.=' AND lr.status=?'; $params[]=$status; }
    $sql.=' ORDER BY lr.created_at DESC LIMIT '.$limit;

    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    $items=$stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary=$pdo->prepare(
        'SELECT COUNT(*) AS total,
                SUM(status="open") AS open_count,
                SUM(status="active") AS active_count,
                SUM(status="completed") AS completed_count
         FROM local_requirements
         WHERE contractor_user_id=? AND deleted_at IS NULL'
    );
    $summary->execute([(int)$user['id']]);
    $s=$summary->fetch(PDO::FETCH_ASSOC) ?: [];

    localJson([
        'success'=>true,
        'data'=>[
            'items'=>$items,
            'summary'=>[
                'total'=>(int)($s['total'] ?? 0),
                'open'=>(int)($s['open_count'] ?? 0),
                'active'=>(int)($s['active_count'] ?? 0),
                'completed'=>(int)($s['completed_count'] ?? 0),
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log('MWH Local requirements list: '.$e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to load requirements'],500);
}
