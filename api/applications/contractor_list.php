<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

try {
    $user = localRequireUser($pdo);

    if ((string)$user['role'] !== 'contractor') {
        localJson(['success'=>false,'message'=>'Contractor access is required'],403);
    }

    $requirementId = (int)($_GET['requirement_id'] ?? $_POST['requirement_id'] ?? 0);
    if ($requirementId <= 0) {
        localJson(['success'=>false,'message'=>'requirement_id is required'],422);
    }

    $req = $pdo->prepare(
        'SELECT lr.id,lr.title,lr.description,lr.openings_count,lr.work_location,lr.work_address,
                lr.shift_date,lr.shift_start,lr.shift_end,lr.minimum_experience,lr.payout_amount,
                lr.payout_period,lr.status,
                COUNT(la.id) AS applicant_count
         FROM local_requirements lr
         LEFT JOIN local_applications la ON la.requirement_id=lr.id
         WHERE lr.id=? AND lr.contractor_user_id=? AND lr.deleted_at IS NULL
         GROUP BY lr.id
         LIMIT 1'
    );
    $req->execute([$requirementId,(int)$user['id']]);
    $requirement = $req->fetch(PDO::FETCH_ASSOC);

    if (!$requirement) {
        localJson(['success'=>false,'message'=>'Requirement not found'],404);
    }

    $apps = $pdo->prepare(
        'SELECT la.id,la.status,la.applied_at,la.updated_at,la.notes,
                u.id AS staff_user_id,u.name,u.mobile,u.email,u.account_status,
                sp.gender,sp.city,sp.experience_years,sp.skills
         FROM local_applications la
         INNER JOIN local_users u ON u.id=la.staff_user_id
         LEFT JOIN local_staff_profiles sp ON sp.user_id=u.id
         WHERE la.requirement_id=?
         ORDER BY CASE la.status
             WHEN "selected" THEN 0
             WHEN "shortlisted" THEN 1
             WHEN "applied" THEN 2
             WHEN "rejected" THEN 3
             ELSE 4
         END, la.applied_at ASC, la.id ASC'
    );
    $apps->execute([$requirementId]);
    $applications = $apps->fetchAll(PDO::FETCH_ASSOC);

    localJson([
        'success'=>true,
        'data'=>[
            'requirement'=>$requirement,
            'applications'=>$applications,
        ],
    ]);
} catch (Throwable $e) {
    error_log('MWH Local contractor applicants: '.$e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to load applicants'],500);
}
