<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

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
    $staffRole = trim((string)($data['staff_role'] ?? $data['role_name'] ?? ''));
    $categoryId = (int)($data['category_id'] ?? 0);
    $jobRoleId = (int)($data['job_role_id'] ?? 0);
    $headcount = (int)($data['headcount'] ?? $data['staff_count'] ?? $data['count'] ?? 0);
    $location = trim((string)($data['location'] ?? $data['work_location'] ?? ''));
    $shiftDate = trim((string)($data['shift_date'] ?? $data['date'] ?? ''));
    $shiftStart = trim((string)($data['shift_start'] ?? $data['start_time'] ?? ''));
    $shiftEnd = trim((string)($data['shift_end'] ?? $data['end_time'] ?? ''));
    $experience = trim((string)($data['minimum_experience'] ?? $data['experience'] ?? ''));
    $payout = (float)($data['payout_amount'] ?? $data['pay'] ?? 0);
    $payoutPeriod = trim((string)($data['payout_period'] ?? 'per_shift'));
    $address = trim((string)($data['address'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));

    if ($staffRole === '' && $jobRoleId <= 0) {
        throw new InvalidArgumentException('Staff role is required');
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
    if ($payout <= 0) {
        throw new InvalidArgumentException('Payout amount must be greater than zero');
    }

    $roleName = $staffRole;
    if ($jobRoleId > 0) {
        $roleStmt = $pdo->prepare(
            'SELECT jr.name,jr.category_id
             FROM local_job_roles jr
             WHERE jr.id=? AND jr.is_active=1 AND jr.deleted_at IS NULL
             LIMIT 1'
        );
        $roleStmt->execute([$jobRoleId]);
        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
        if (!$roleRow) {
            throw new InvalidArgumentException('Selected job role is invalid');
        }
        if ($categoryId > 0 && (int)$roleRow['category_id'] !== $categoryId) {
            throw new InvalidArgumentException('Selected job role does not match category');
        }
        $categoryId = (int)$roleRow['category_id'];
        $roleName = $roleName !== '' ? $roleName : (string)$roleRow['name'];
    }

    if ($title === '') {
        $title = $headcount . ' × ' . ($roleName !== '' ? $roleName : 'Kitchen Staff');
    }

    $parts=[];
    if ($description !== '') $parts[]=$description;
    if ($roleName !== '') $parts[]='Staff role: ' . $roleName;
    if ($shiftStart !== '' || $shiftEnd !== '') $parts[]='Shift: ' . trim($shiftStart . ' - ' . $shiftEnd, ' -');
    if ($experience !== '') $parts[]='Minimum experience: ' . $experience;
    if ($address !== '') $parts[]='Address: ' . $address;

    $stmt=$pdo->prepare(
        'INSERT INTO local_requirements
         (contractor_user_id,category_id,job_role_id,title,description,openings_count,
          work_location,work_address,shift_date,shift_start,shift_end,minimum_experience,
          payout_amount,payout_period,status,notes)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        (int)$user['id'],
        $categoryId > 0 ? $categoryId : null,
        $jobRoleId > 0 ? $jobRoleId : null,
        $title,
        $parts ? implode("

", $parts) : null,
        $headcount,
        $location,
        $address !== '' ? $address : null,
        $shiftDate,
        $shiftStart !== '' ? $shiftStart : null,
        $shiftEnd !== '' ? $shiftEnd : null,
        $experience !== '' ? $experience : null,
        $payout,
        $payoutPeriod !== '' ? $payoutPeriod : 'per_shift',
        'open',
        $notes !== '' ? $notes : null,
    ]);
    $id=(int)$pdo->lastInsertId();

    localJson([
        'success'=>true,
        'message'=>'Requirement created successfully',
        'data'=>[
            'requirement_id'=>$id,
            'status'=>'open',
            'title'=>$title,
        ],
    ],201);
} catch (InvalidArgumentException $e) {
    localJson(['success'=>false,'message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    error_log('MWH Local requirement create: '.$e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to create requirement'],500);
}
