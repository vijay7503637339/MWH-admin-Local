<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

try {
    $user = localRequireUser($pdo);
    if ((string)$user['role'] !== 'staff') {
        localJson(['success' => false, 'message' => 'Staff access is required'], 403);
    }

    $data = localInput();
    $requirementId = (int)($data['requirement_id'] ?? $data['id'] ?? 0);

    if ($requirementId <= 0) {
        localJson(['success' => false, 'message' => 'requirement_id is required'], 422);
    }

    $reqStmt = $pdo->prepare(
        'SELECT id, contractor_user_id, title, status, shift_date
         FROM local_requirements
         WHERE id=? AND deleted_at IS NULL
         LIMIT 1'
    );
    $reqStmt->execute([$requirementId]);
    $requirement = $reqStmt->fetch(PDO::FETCH_ASSOC);

    if (!$requirement) {
        localJson(['success' => false, 'message' => 'Requirement not found'], 404);
    }

    if ((string)$requirement['status'] !== 'open') {
        localJson(['success' => false, 'message' => 'This requirement is no longer accepting applications'], 409);
    }

    if ((string)$requirement['shift_date'] < gmdate('Y-m-d')) {
        localJson(['success' => false, 'message' => 'This shift has already passed'], 409);
    }

    $dup = $pdo->prepare(
        'SELECT id,status FROM local_applications
         WHERE requirement_id=? AND staff_user_id=?
         LIMIT 1'
    );
    $dup->execute([$requirementId, (int)$user['id']]);
    $existing = $dup->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        localJson([
            'success' => false,
            'code' => 'ALREADY_APPLIED',
            'message' => 'You have already applied for this requirement',
            'data' => ['application_id' => (int)$existing['id'], 'status' => $existing['status']],
        ], 409);
    }

    $notes = trim((string)($data['notes'] ?? $data['note'] ?? ''));

    $stmt = $pdo->prepare(
        'INSERT INTO local_applications
         (requirement_id, staff_user_id, status, notes)
         VALUES(?,?,? ,?)'
    );
    $stmt->execute([
        $requirementId,
        (int)$user['id'],
        'applied',
        $notes !== '' ? $notes : null,
    ]);

    $applicationId = (int)$pdo->lastInsertId();

    localJson([
        'success' => true,
        'message' => 'Application submitted successfully',
        'data' => [
            'application_id' => $applicationId,
            'requirement_id' => $requirementId,
            'status' => 'applied',
            'title' => (string)$requirement['title'],
        ],
    ], 201);
} catch (Throwable $e) {
    error_log('MWH Local application create: ' . $e->getMessage());
    localJson(['success' => false, 'message' => 'Unable to submit application'], 500);
}
