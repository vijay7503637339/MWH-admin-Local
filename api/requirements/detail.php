<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET','POST'], true)) {
    localJson(['success'=>false,'message'=>'GET or POST request required'],405);
}

try {
    $user=localRequireUser($pdo);
    $input=localInput();
    $requirementId=(int)($input['requirement_id'] ?? $_GET['requirement_id'] ?? $_GET['id'] ?? 0);
    if($requirementId<=0){
        localJson(['success'=>false,'message'=>'requirement_id is required'],422);
    }

    $stmt=$pdo->prepare(
        'SELECT lr.id,lr.category_id,lr.job_role_id,lr.title,lr.description,
                lr.openings_count,lr.work_location,lr.work_address,lr.shift_date,
                lr.shift_start,lr.shift_end,lr.minimum_experience,lr.payout_amount,
                lr.payout_period,lr.status,lr.notes,lr.created_at,lr.updated_at,
                c.name AS category_name,jr.name AS job_role_name
         FROM local_requirements lr
         LEFT JOIN local_categories c ON c.id=lr.category_id
         LEFT JOIN local_job_roles jr ON jr.id=lr.job_role_id
         WHERE lr.id=? AND lr.contractor_user_id=? AND lr.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$requirementId,(int)$user['id']]);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$item){
        localJson(['success'=>false,'message'=>'Requirement not found'],404);
    }
    localJson(['success'=>true,'data'=>$item]);
} catch(Throwable $e){
    error_log('MWH Local requirement detail: '.$e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to load requirement'],500);
}
