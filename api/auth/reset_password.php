<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/password_reset.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

$data = localInput();

try {
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $otp = trim((string)($data['otp'] ?? ''));
    $newPassword = $data['new_password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        throw new InvalidArgumentException('Enter a valid email address');
    }
    if (!preg_match('/^\d{6}$/', $otp)) {
        throw new InvalidArgumentException('Enter the 6-digit OTP');
    }
    $newPassword = localPassword($newPassword);

    $stmt = $pdo->prepare(
        'SELECT id
         FROM local_users
         WHERE email=? AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        localJson([
            'success' => false,
            'code' => 'EMAIL_NOT_FOUND',
            'message' => 'No account was found with this email address',
        ], 404);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id,otp_hash,expires_at,attempts,used_at
         FROM local_password_reset_otps
         WHERE user_id=? AND email=? AND used_at IS NULL
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([(int)$user['id'], $email]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record || !empty($record['used_at']) || strtotime((string)$record['expires_at']) <= time()) {
        $pdo->rollBack();
        localJson([
            'success' => false,
            'code' => 'OTP_INVALID',
            'message' => 'OTP is invalid or expired. Please request a new OTP.',
        ], 422);
    }

    $attempts = (int)$record['attempts'];
    if ($attempts >= LOCAL_PASSWORD_RESET_MAX_ATTEMPTS) {
        $pdo->rollBack();
        localJson([
            'success' => false,
            'code' => 'OTP_ATTEMPTS_EXCEEDED',
            'message' => 'Too many incorrect OTP attempts. Please request a new OTP.',
        ], 422);
    }

    $expectedHash = (string)$record['otp_hash'];
    $providedHash = hash('sha256', $otp);

    if (!hash_equals($expectedHash, $providedHash)) {
        $newAttempts = $attempts + 1;
        $usedSql = $newAttempts >= LOCAL_PASSWORD_RESET_MAX_ATTEMPTS ? ', used_at=UTC_TIMESTAMP()' : '';
        $pdo->prepare(
            'UPDATE local_password_reset_otps
             SET attempts=?' . $usedSql . '
             WHERE id=?'
        )->execute([$newAttempts, (int)$record['id']]);
        $pdo->commit();

        if ($newAttempts >= LOCAL_PASSWORD_RESET_MAX_ATTEMPTS) {
            localJson([
                'success' => false,
                'code' => 'OTP_ATTEMPTS_EXCEEDED',
                'message' => 'Too many incorrect OTP attempts. Please request a new OTP.',
            ], 422);
        }

        localJson([
            'success' => false,
            'code' => 'OTP_INVALID',
            'message' => 'Incorrect OTP. Please try again.',
        ], 422);
    }

    $pdo->prepare(
        'UPDATE local_users
         SET password_hash=?, updated_at=UTC_TIMESTAMP()
         WHERE id=?'
    )->execute([
        password_hash($newPassword, PASSWORD_DEFAULT),
        (int)$user['id'],
    ]);

    // Any existing API sessions are invalidated after a successful password reset.
    $pdo->prepare(
        'UPDATE local_api_tokens
         SET revoked_at=UTC_TIMESTAMP()
         WHERE user_id=? AND revoked_at IS NULL'
    )->execute([(int)$user['id']]);

    $pdo->prepare(
        'UPDATE local_password_reset_otps
         SET used_at=UTC_TIMESTAMP()
         WHERE id=?'
    )->execute([(int)$record['id']]);

    $pdo->commit();

    localJson([
        'success' => true,
        'message' => 'Password changed successfully. Please log in with your new password.',
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
    error_log('MWH Local password reset: ' . $e->getMessage());
    localJson(['success' => false, 'message' => 'Unable to reset password'], 500);
}
