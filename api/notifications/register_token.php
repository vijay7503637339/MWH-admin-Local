<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

try {
    $user = localRequireUser($pdo);
    $data = localInput();

    $token = trim((string)($data['token'] ?? ''));
    $platform = strtolower(trim((string)($data['platform'] ?? 'android')));
    $appVersion = trim((string)($data['app_version'] ?? ''));
    $deviceId = trim((string)($data['device_id'] ?? ''));

    if ($token === '' || strlen($token) > 512) {
        localJson(['success' => false, 'message' => 'A valid FCM token is required'], 422);
    }

    if (!in_array($platform, ['android', 'ios'], true)) {
        $platform = 'android';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO local_fcm_tokens
         (user_id,token,platform,app_version,device_id,last_seen_at,disabled_at)
         VALUES(?,?,?,?,?,UTC_TIMESTAMP(),NULL)
         ON DUPLICATE KEY UPDATE
            user_id=VALUES(user_id),
            platform=VALUES(platform),
            app_version=VALUES(app_version),
            device_id=VALUES(device_id),
            last_seen_at=UTC_TIMESTAMP(),
            disabled_at=NULL,
            updated_at=UTC_TIMESTAMP()'
    );
    $stmt->execute([
        (int)$user['id'],
        $token,
        $platform,
        $appVersion !== '' ? $appVersion : null,
        $deviceId !== '' ? $deviceId : null,
    ]);

    localJson([
        'success' => true,
        'message' => 'FCM token registered',
        'data' => ['registered' => true],
    ]);
} catch (Throwable $e) {
    error_log('MWH Local FCM token registration: ' . $e->getMessage());
    localJson(['success' => false, 'message' => 'Unable to register notification token'], 500);
}
