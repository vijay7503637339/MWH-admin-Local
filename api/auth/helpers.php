<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit;
}

function localJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function localInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $_POST;
}

function localMobile(mixed $value): string
{
    $mobile = preg_replace('/\D+/', '', (string)$value) ?? '';
    if (strlen($mobile) === 12 && str_starts_with($mobile, '91')) {
        $mobile = substr($mobile, 2);
    }
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        throw new InvalidArgumentException('A valid 10-digit Indian mobile number is required');
    }
    return $mobile;
}

function localPassword(mixed $value): string
{
    $password = (string)$value;
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Password must be at least 8 characters');
    }
    if (strlen($password) > 255) {
        throw new InvalidArgumentException('Password is too long');
    }
    return $password;
}

function localRole(mixed $value): string
{
    $role = strtolower(trim((string)$value));
    $aliases = [
        'chef' => 'staff',
        'cooking_staff' => 'staff',
        'cooking staff' => 'staff',
        'caterer' => 'contractor',
        'agency' => 'contractor',
    ];
    $role = $aliases[$role] ?? $role;
    if (!in_array($role, ['staff', 'contractor'], true)) {
        throw new InvalidArgumentException('role must be staff or contractor');
    }
    return $role;
}

function localIssueToken(PDO $pdo, int $userId): array
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

    $pdo->prepare(
        'UPDATE local_api_tokens SET revoked_at = UTC_TIMESTAMP()
         WHERE user_id = ? AND revoked_at IS NULL'
    )->execute([$userId]);

    $pdo->prepare(
        'INSERT INTO local_api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $tokenHash, $expiresAt]);

    return [
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_at' => $expiresAt,
    ];
}

function localRequireUser(PDO $pdo): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        localJson(['success' => false, 'message' => 'Authorization token required'], 401);
    }

    $tokenHash = hash('sha256', trim($matches[1]));
    $stmt = $pdo->prepare(
        'SELECT u.id,u.name,u.mobile,u.email,u.role,u.account_status
         FROM local_api_tokens t
         INNER JOIN local_users u ON u.id=t.user_id
         WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>UTC_TIMESTAMP()
           AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        localJson(['success' => false, 'message' => 'Invalid or expired authorization token'], 401);
    }
    return $user;
}
