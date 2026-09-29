<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    localJson(['success'=>false,'message'=>'POST request required'],405);
}

try {
    $user = localRequireUser($pdo);
    if ((string)$user['role'] !== 'contractor') {
        localJson(['success'=>false,'message'=>'Contractor access is required'],403);
    }

    $data = localInput();
    $requirementId = (int)($data['requirement_id'] ?? 0);
    $applicationIds = $data['application_ids'] ?? $data['selected_application_ids'] ?? [];

    if ($requirementId <= 0) {
        localJson(['success'=>false,'message'=>'requirement_id is required'],422);
    }
    if (!is_array($applicationIds)) {
        localJson(['success'=>false,'message'=>'application_ids must be an array'],422);
    }

    $applicationIds = array_values(array_unique(array_filter(array_map(
        static fn($id) => (int)$id, $applicationIds
    ), static fn($id) => $id > 0)));

    if (!$applicationIds) {
        localJson(['success'=>false,'message'=>'Select at least one applicant'],422);
    }

    $reqStmt = $pdo->prepare(
        'SELECT id,title,openings_count,shift_date,shift_start,shift_end,payout_amount,status
         FROM local_requirements
         WHERE id=? AND contractor_user_id=? AND deleted_at IS NULL
         LIMIT 1'
    );
    $reqStmt->execute([$requirementId,(int)$user['id']]);
    $requirement = $reqStmt->fetch(PDO::FETCH_ASSOC);

    if (!$requirement) {
        localJson(['success'=>false,'message'=>'Requirement not found'],404);
    }
    if (!in_array((string)$requirement['status'], ['open','active'], true)) {
        localJson(['success'=>false,'message'=>'This requirement is not available for selection'],409);
    }
    if (count($applicationIds) > (int)$requirement['openings_count']) {
        localJson([
            'success'=>false,
            'message'=>'You can select a maximum of ' . (int)$requirement['openings_count'] . ' staff for this requirement',
        ],422);
    }

    $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
    $params = array_merge([$requirementId], $applicationIds);

    $verify = $pdo->prepare(
        'SELECT la.id,la.staff_user_id,la.status,u.name,u.account_status
         FROM local_applications la
         INNER JOIN local_users u ON u.id=la.staff_user_id
         WHERE la.requirement_id=? AND la.id IN (' . $placeholders . ')
         FOR UPDATE'
    );

    $pdo->beginTransaction();
    $verify->execute($params);
    $rows = $verify->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== count($applicationIds)) {
        throw new InvalidArgumentException('One or more selected applicants do not belong to this requirement.');
    }

    $updateSelected = $pdo->prepare(
        'UPDATE local_applications
         SET status=?, updated_at=UTC_TIMESTAMP()
         WHERE id=? AND requirement_id=?'
    );

    $updateOthers = $pdo->prepare(
        'UPDATE local_applications
         SET status=?, updated_at=UTC_TIMESTAMP()
         WHERE requirement_id=? AND id<>? AND status NOT IN ("withdrawn")'
    );

    $assignmentInsert = $pdo->prepare(
        'INSERT INTO local_assignments
         (requirement_id,application_id,staff_user_id,contractor_user_id,status,start_at,payout_amount,notes)
         VALUES(?,?,?,?,?,?,?,?)'
    );

    $notificationInsert = $pdo->prepare(
        'INSERT INTO local_notifications
         (user_id,admin_user_id,type,title,message,data_json)
         VALUES(?,?,?,?,?,?)'
    );

    $selectedIds = [];
    foreach ($rows as $row) {
        if ((string)$row['account_status'] !== 'verified') {
            throw new InvalidArgumentException(
                'Selected staff account is not verified: ' . (string)$row['name']
            );
        }

        $applicationId = (int)$row['id'];
        $staffUserId = (int)$row['staff_user_id'];
        $selectedIds[] = $applicationId;

        $updateSelected->execute(['selected',$applicationId,$requirementId]);

        $startAt = null;
        if (!empty($requirement['shift_date'])) {
            $date = (string)$requirement['shift_date'];
            $time = trim((string)($requirement['shift_start'] ?? ''));
            $startAt = $date . ' ' . ($time !== '' ? $time : '00:00:00');
        }

        try {
            $assignmentInsert->execute([
                $requirementId,
                $applicationId,
                $staffUserId,
                (int)$user['id'],
                'assigned',
                $startAt,
                (float)$requirement['payout_amount'],
                null,
            ]);
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] !== 1062) {
                throw $e;
            }
        }

        $shift = (string)$requirement['shift_date'];
        $timing = trim((string)($requirement['shift_start'] ?? '') . ' - ' . (string)($requirement['shift_end'] ?? ''), ' -');
        $message = 'You have been selected for "' . (string)$requirement['title'] . '". Shift: ' . $shift;
        if ($timing !== '') {
            $message .= ' ' . $timing;
        }
        if (!empty($requirement['work_location'])) {
            $message .= '. Location: ' . (string)$requirement['work_location'];
        }

        $payload = json_encode([
            'type'=>'selection',
            'requirement_id'=>$requirementId,
            'application_id'=>$applicationId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $notificationInsert->execute([
            $staffUserId,
            null,
            'selection',
            'You have been selected',
            $message,
            $payload,
        ]);
    }

    foreach ($selectedIds as $selectedId) {
        $updateOthers->execute(['rejected',$requirementId,$selectedId]);
    }

    $newStatus = count($selectedIds) >= (int)$requirement['openings_count'] ? 'active' : 'active';
    $pdo->prepare(
        'UPDATE local_requirements
         SET status=?,updated_at=UTC_TIMESTAMP()
         WHERE id=?'
    )->execute([$newStatus,$requirementId]);

    $pdo->commit();

    localJson([
        'success'=>true,
        'message'=>'Staff selected successfully',
        'data'=>[
            'requirement_id'=>$requirementId,
            'selected_count'=>count($selectedIds),
            'status'=>$newStatus,
        ],
    ]);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    localJson(['success'=>false,'message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('MWH Local application selection: ' . $e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to select staff'],500);
}
