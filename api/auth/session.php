<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    localJson(['success'=>false,'message'=>'GET request required'],405);
}

try {
    $user = localRequireUser($pdo);
    localJson([
        'success'=>true,
        'data'=>[
            'user'=>$user,
            'active_role'=>(string)$user['role'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('MWH Local session: '.$e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to validate session'],500);
}
