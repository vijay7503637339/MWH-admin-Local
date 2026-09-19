<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

$data = localInput();

try {
    $mobile = localMobile($data['mobile'] ?? $data['whatsapp_mobile'] ?? '');
    $password = (string)($data['password'] ?? '');
    if ($password === '') {
        throw new InvalidArgumentException('Password is required');
    }

    $requestedRole = trim((string)($data['role'] ?? ''));
    $requestedRole = $requestedRole !== '' ? localRole($requestedRole) : '';

    $sql = 'SELECT id,name,mobile,email,password_hash,role,account_status
            FROM local_users
            WHERE mobile=? AND deleted_at IS NULL';
    $params = [$mobile];

    if ($requestedRole !== '') {
        $sql .= ' AND role=?';
        $params[] = $requestedRole;
    }

    $sql .= ' ORDER BY id DESC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
        localJson(['success' => false, 'message' => 'Invalid mobile number or password'], 401);
    }

    $status = strtolower((string)$user['account_status']);
    if ($status === 'pending_verification') {
        localJson([
            'success' => false,
            'code' => 'PENDING_VERIFICATION',
            'message' => 'Your account is pending admin verification.',
            'data' => ['user_id' => (int)$user['id']],
        ], 403);
    }
    if (in_array($status, ['blocked','rejected'], true)) {
        localJson([
            'success' => false,
            'code' => strtoupper($status),
            'message' => $status === 'blocked' ? 'Account is blocked' : 'Account is rejected',
        ], 403);
    }
    if ($status !== 'verified') {
        localJson([
            'success' => false,
            'code' => 'ACCOUNT_NOT_ACTIVE',
            'message' => 'This account is not active. Please contact support.',
        ], 403);
    }

    $pdo->beginTransaction();
    $tokenData = localIssueToken($pdo, (int)$user['id']);
    $pdo->prepare('UPDATE local_users SET last_login_at=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP() WHERE id=?')
        ->execute([(int)$user['id']]);
    $pdo->commit();

    unset($user['password_hash']);
    $user['id'] = (int)$user['id'];

    localJson([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            ...$tokenData,
            'user' => $user,
            'roles' => [(string)$user['role']],
            'active_role' => (string)$user['role'],
        ],
    ]);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    localJson(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('MWH Local login: ' . $e->getMessage());
    localJson(['success' => false, 'message' => 'Login failed'], 500);
}
