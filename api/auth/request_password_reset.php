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
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        throw new InvalidArgumentException('Enter a valid email address');
    }

    $stmt = $pdo->prepare(
        'SELECT id,name,email
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

    $stmt = $pdo->prepare(
        'SELECT id,created_at
         FROM local_password_reset_otps
         WHERE user_id=? AND used_at IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([(int)$user['id']]);
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($latest && (time() - strtotime((string)$latest['created_at'])) < LOCAL_PASSWORD_RESET_RESEND_SECONDS) {
        $remaining = max(
            1,
            LOCAL_PASSWORD_RESET_RESEND_SECONDS - (time() - strtotime((string)$latest['created_at']))
        );
        localJson([
            'success' => false,
            'code' => 'OTP_COOLDOWN',
            'message' => "Please wait {$remaining} seconds before requesting another OTP",
        ], 429);
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + LOCAL_PASSWORD_RESET_OTP_TTL_MINUTES * 60);

    $pdo->beginTransaction();
    $pdo->prepare(
        'UPDATE local_password_reset_otps
         SET used_at=UTC_TIMESTAMP()
         WHERE user_id=? AND used_at IS NULL'
    )->execute([(int)$user['id']]);

    $pdo->prepare(
        'INSERT INTO local_password_reset_otps
         (user_id,email,otp_hash,expires_at,attempts,created_at)
         VALUES(?,?,?,?,0,UTC_TIMESTAMP())'
    )->execute([
        (int)$user['id'],
        $email,
        hash('sha256', $otp),
        $expiresAt,
    ]);
    $pdo->commit();

    if (!localSendPasswordResetOtp($email, (string)$user['name'], $otp)) {
        $pdo->prepare(
            'UPDATE local_password_reset_otps
             SET used_at=UTC_TIMESTAMP()
             WHERE user_id=? AND used_at IS NULL'
        )->execute([(int)$user['id']]);

        error_log('MWH Local password reset: mail() failed for ' . $email);
        localJson([
            'success' => false,
            'code' => 'EMAIL_SEND_FAILED',
            'message' => 'Unable to send the OTP email right now. Please try again later.',
        ], 500);
    }

    localJson([
        'success' => true,
        'message' => 'OTP sent to your registered email address',
        'data' => [
            'expires_in_seconds' => LOCAL_PASSWORD_RESET_OTP_TTL_MINUTES * 60,
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
    error_log('MWH Local password reset request: ' . $e->getMessage());
    localJson(['success' => false, 'message' => 'Unable to send password reset OTP'], 500);
}
