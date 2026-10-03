<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/fcm.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

$data = localInput();

try {
    $user = localRequireUser($pdo);
    if ((string)$user['role'] !== 'contractor') {
        localJson(['success' => false, 'message' => 'Contractor access is required'], 403);
    }
    if ((string)$user['account_status'] !== 'verified') {
        localJson(['success' => false, 'message' => 'Account is not verified'], 403);
    }

    $title = trim((string)($data['title'] ?? ''));
    $categoryId = (int)($data['category_id'] ?? 0);
    $jobRoleId = (int)($data['job_role_id'] ?? 0);
    $headcount = (int)($data['headcount'] ?? $data['staff_count'] ?? $data['count'] ?? 0);
    $location = trim((string)($data['location'] ?? $data['work_location'] ?? ''));
    $shiftDate = trim((string)($data['shift_date'] ?? $data['date'] ?? ''));
    $shiftStart = trim((string)($data['shift_start'] ?? $data['start_time'] ?? ''));
    $shiftEnd = trim((string)($data['shift_end'] ?? $data['end_time'] ?? ''));
    $address = trim((string)($data['address'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));

    if ($categoryId <= 0) {
        throw new InvalidArgumentException('Category is required');
    }
    if ($jobRoleId <= 0) {
        throw new InvalidArgumentException('Job role is required');
    }
    if ($headcount < 1 || $headcount > 500) {
        throw new InvalidArgumentException('Headcount must be between 1 and 500');
    }
    if ($location === '') {
        throw new InvalidArgumentException('Work location is required');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
        throw new InvalidArgumentException('shift_date must use YYYY-MM-DD format');
    }

    $roleStmt = $pdo->prepare(
        'SELECT jr.id,jr.name,jr.category_id,jr.job_amount,jr.amount_period,lc.name AS category_name
         FROM local_job_roles jr
         INNER JOIN local_categories lc ON lc.id=jr.category_id
         WHERE jr.id=?
           AND jr.is_active=1
           AND jr.deleted_at IS NULL
           AND lc.id=?
           AND lc.is_active=1
           AND lc.deleted_at IS NULL
         LIMIT 1'
    );
    $roleStmt->execute([$jobRoleId, $categoryId]);
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$roleRow) {
        throw new InvalidArgumentException('Selected category and job role are invalid');
    }

    $baseAmount = (float)$roleRow['job_amount'];
    $amountPeriod = (string)$roleRow['amount_period'];

    if ($baseAmount <= 0) {
        throw new InvalidArgumentException('Selected job role does not have a valid payout amount');
    }

    $totalPayout = round($baseAmount * $headcount, 2);
    $roleName = (string)$roleRow['name'];

    if ($title === '') {
        $title = $headcount . ' × ' . $roleName;
    }

    $parts = [];
    if ($description !== '') $parts[] = $description;
    $parts[] = 'Staff role: ' . $roleName;
    if ($shiftStart !== '' || $shiftEnd !== '') {
        $parts[] = 'Shift: ' . trim($shiftStart . ' - ' . $shiftEnd, ' -');
    }
    if ($address !== '') $parts[] = 'Address: ' . $address;

    $stmt = $pdo->prepare(
        'INSERT INTO local_requirements
         (contractor_user_id,category_id,job_role_id,title,description,openings_count,
          work_location,work_address,shift_date,shift_start,shift_end,minimum_experience,
          payout_amount,payout_period,status,notes)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        (int)$user['id'],
        $categoryId,
        $jobRoleId,
        $title,
        $parts ? implode("\n\n", $parts) : null,
        $headcount,
        $location,
        $address !== '' ? $address : null,
        $shiftDate,
        $shiftStart !== '' ? $shiftStart : null,
        $shiftEnd !== '' ? $shiftEnd : null,
        null,
        $totalPayout,
        $amountPeriod,
        'open',
        $notes !== '' ? $notes : null,
    ]);

    $id = (int)$pdo->lastInsertId();

    $pushReport = [
        'ok' => true,
        'requested' => 0,
        'sent' => 0,
        'failed' => 0,
        'message' => 'No push attempted.',
    ];

    try {
        $staffRows = $pdo->query(
            'SELECT id
             FROM local_users
             WHERE role="staff"
               AND account_status="verified"
               AND deleted_at IS NULL'
        )->fetchAll(PDO::FETCH_COLUMN);
        $staffUserIds = array_map('intval', $staffRows);

        if ($staffUserIds) {
            $pushMessage = 'New job available: ' . $roleName
                . ' • ' . $location
                . ' • ' . $shiftDate;

            $pushReport = localNotifyUsers(
                $pdo,
                $staffUserIds,
                'new_job',
                'New local job available',
                $pushMessage,
                [
                    'screen' => 'jobs',
                    'requirement_id' => $id,
                    'job_role_id' => $jobRoleId,
                ]
            )['push'];
        }
    } catch (Throwable $notificationError) {
        error_log('MWH Local new-job notification: ' . $notificationError->getMessage());
        $pushReport = [
            'ok' => false,
            'requested' => 0,
            'sent' => 0,
            'failed' => 0,
            'message' => 'Job created, but push notification could not be processed.',
        ];
    }

    localJson([
        'success' => true,
        'message' => 'Requirement created successfully',
        'data' => [
            'requirement_id' => $id,
            'status' => 'open',
            'title' => $title,
            'category_id' => $categoryId,
            'job_role_id' => $jobRoleId,
            'base_amount' => $baseAmount,
            'amount_period' => $amountPeriod,
            'headcount' => $headcount,
            'total_payout' => $totalPayout,
            'push_notification' => $pushReport,
        ],
    ], 201);
} catch (InvalidArgumentException $e) {
    localJson(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('MWH Local requirement create: ' . $e->getMessage());
    localJson(['success' => false, 'message' => 'Unable to create requirement'], 500);
}
