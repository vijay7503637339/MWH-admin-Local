<?php
declare(strict_types=1);

/*
 * Firebase Cloud Messaging HTTP v1 helper.
 *
 * Expected server credential:
 *   Set MWH_FCM_SERVICE_ACCOUNT to the absolute path of the Firebase
 *   service-account JSON file, or place it at:
 *   <project-root>/private/firebase-service-account.json
 *
 * Never commit the service-account JSON to Git.
 */

function localFcmServiceAccountPath(): string
{
    $env = trim((string)(getenv('MWH_FCM_SERVICE_ACCOUNT') ?: ''));
    if ($env !== '') {
        return $env;
    }

    $projectPrivate = dirname(__DIR__, 2) . '/private/firebase-service-account.json';
    if (is_file($projectPrivate)) {
        return $projectPrivate;
    }

    // Common cPanel layout: project is under public_html and credential is
    // stored one level above public_html.
    return dirname(__DIR__, 4) . '/private/firebase-service-account.json';
}

function localFcmBase64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function localFcmAccessToken(): array
{
    static $cached = null;

    if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60) {
        return $cached;
    }

    $path = localFcmServiceAccountPath();
    if (!is_file($path)) {
        return [
            'ok' => false,
            'message' => 'FCM service-account JSON is not configured on the server.',
        ];
    }

    $raw = file_get_contents($path);
    $service = json_decode((string)$raw, true);

    if (!is_array($service)) {
        return [
            'ok' => false,
            'message' => 'FCM service-account JSON is invalid.',
        ];
    }

    $clientEmail = trim((string)($service['client_email'] ?? ''));
    $privateKey = (string)($service['private_key'] ?? '');
    $tokenUri = trim((string)($service['token_uri'] ?? 'https://oauth2.googleapis.com/token'));

    if ($clientEmail === '' || $privateKey === '') {
        return [
            'ok' => false,
            'message' => 'FCM service-account JSON is missing client_email or private_key.',
        ];
    }

    $now = time();
    $header = localFcmBase64Url(json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT',
    ], JSON_UNESCAPED_SLASHES));

    $claims = localFcmBase64Url(json_encode([
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $tokenUri,
        'iat' => $now,
        'exp' => $now + 3600,
    ], JSON_UNESCAPED_SLASHES));

    $unsigned = $header . '.' . $claims;
    $signature = '';

    if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        return [
            'ok' => false,
            'message' => 'Unable to sign Firebase OAuth request.',
        ];
    }

    $jwt = $unsigned . '.' . localFcmBase64Url($signature);

    $ch = curl_init($tokenUri);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '' || $status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'message' => 'Unable to obtain Firebase access token.',
        ];
    }

    $json = json_decode((string)$response, true);
    $accessToken = trim((string)($json['access_token'] ?? ''));

    if ($accessToken === '') {
        return [
            'ok' => false,
            'message' => 'Firebase OAuth response did not contain an access token.',
        ];
    }

    $cached = [
        'ok' => true,
        'access_token' => $accessToken,
        'project_id' => trim((string)($service['project_id'] ?? 'maan-world-hospitality')),
        'expires_at' => $now + (int)($json['expires_in'] ?? 3600),
    ];

    return $cached;
}

function localFcmSendTokens(
    PDO $pdo,
    array $tokens,
    string $title,
    string $body,
    array $data = []
): array {
    $tokens = array_values(array_unique(array_filter(
        array_map(static fn($token) => trim((string)$token), $tokens),
        static fn($token) => $token !== ''
    )));

    if (!$tokens) {
        return [
            'ok' => true,
            'requested' => 0,
            'sent' => 0,
            'failed' => 0,
            'message' => 'No active FCM tokens found.',
        ];
    }

    $auth = localFcmAccessToken();
    if (($auth['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'requested' => count($tokens),
            'sent' => 0,
            'failed' => count($tokens),
            'message' => (string)($auth['message'] ?? 'FCM is not configured.'),
        ];
    }

    $projectId = trim((string)$auth['project_id']);
    $endpoint = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';
    $sent = 0;
    $failed = 0;

    foreach ($tokens as $token) {
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map(static fn($v) => (string)$v, $data),
                'android' => [
                    'priority' => 'HIGH',
                ],
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $auth['access_token'],
                'Content-Type: application/json; charset=utf-8',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $error === '' && $status >= 200 && $status < 300) {
            $sent++;
            continue;
        }

        $failed++;

        $errorJson = json_decode((string)$response, true);
        $errorStatus = (string)($errorJson['error']['status'] ?? '');

        if ($errorStatus === 'UNREGISTERED' || $status === 404) {
            $pdo->prepare(
                'UPDATE local_fcm_tokens
                 SET disabled_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()
                 WHERE token=?'
            )->execute([$token]);
        }
    }

    return [
        'ok' => $failed === 0,
        'requested' => count($tokens),
        'sent' => $sent,
        'failed' => $failed,
        'message' => $failed === 0
            ? 'Push sent successfully.'
            : $sent . ' sent, ' . $failed . ' failed.',
    ];
}

function localFcmTokensForUsers(PDO $pdo, array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(
        array_map(static fn($id) => (int)$id, $userIds),
        static fn($id) => $id > 0
    )));

    if (!$userIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT token
         FROM local_fcm_tokens
         WHERE user_id IN (' . $placeholders . ')
           AND disabled_at IS NULL
         ORDER BY last_seen_at DESC'
    );
    $stmt->execute($userIds);

    return array_values(array_unique(array_map(
        static fn($row) => (string)$row['token'],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    )));
}

function localNotifyUsers(
    PDO $pdo,
    array $userIds,
    string $type,
    string $title,
    string $message,
    array $data = []
): array {
    $userIds = array_values(array_unique(array_filter(
        array_map(static fn($id) => (int)$id, $userIds),
        static fn($id) => $id > 0
    )));

    if (!$userIds) {
        return [
            'notifications' => 0,
            'push' => [
                'ok' => true,
                'requested' => 0,
                'sent' => 0,
                'failed' => 0,
                'message' => 'No recipients.',
            ],
        ];
    }

    $insert = $pdo->prepare(
        'INSERT INTO local_notifications(user_id,type,title,message,data_json)
         VALUES(?,?,?,?,?)'
    );
    $jsonData = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    foreach ($userIds as $userId) {
        $insert->execute([
            $userId,
            $type,
            $title,
            $message,
            $jsonData,
        ]);
    }

    $tokens = localFcmTokensForUsers($pdo, $userIds);
    $push = localFcmSendTokens($pdo, $tokens, $title, $message, $data);

    return [
        'notifications' => count($userIds),
        'push' => $push,
    ];
}
