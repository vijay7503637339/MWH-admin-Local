<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    localJson(['success'=>false,'message'=>'GET request required'],405);
}

try {
    $user = localRequireUser($pdo);
    if ((string)$user['role'] !== 'contractor') {
        localJson(['success'=>false,'message'=>'Contractor access is required'],403);
    }

    $stmt = $pdo->prepare(
        'SELECT
            u.id,u.name,u.mobile,u.email,u.aadhaar_number,u.role,u.account_status,u.created_at,
            p.business_name,p.business_type,p.whatsapp_mobile,p.full_address,p.city,p.state,p.postcode,p.gstin
         FROM local_users u
         LEFT JOIN local_contractor_profiles p ON p.user_id=u.id
         WHERE u.id=? AND u.role="contractor" AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([(int)$user['id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        localJson(['success'=>false,'message'=>'Contractor profile not found'],404);
    }

    unset($profile['aadhaar_number']);
    $profile['id'] = (int)$profile['id'];

    localJson([
        'success'=>true,
        'data'=>[
            'profile'=>$profile,
        ],
    ]);
} catch (Throwable $e) {
    error_log('MWH Local contractor profile: ' . $e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to load contractor profile'],500);
}
