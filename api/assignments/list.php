<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/pricing.php';

try {
    $user = localRequireUser($pdo);
    $role = (string)$user['role'];

    if ($role === 'staff') {
        $status = trim((string)($_GET['status'] ?? ''));
        $allowedStatuses = ['assigned','arrived','active','completed'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            localJson(['success'=>false,'message'=>'Invalid assignment status'],422);
        }
        $statusWhere = $status === '' ? 'a.status<>"cancelled"' : 'a.status=?';
        $stmt = $pdo->prepare(
            'SELECT a.id assignment_id,a.requirement_id,a.contractor_user_id,a.status,a.assigned_at,a.arrival_at,a.start_at,a.end_at,a.payout_amount,a.notes,
                    r.title,r.work_location,r.work_address,r.shift_date,r.shift_start,r.shift_end,
                    u.name AS contractor_name,u.mobile AS contractor_mobile,
                    la.status AS application_status,
                    att.check_in_at,att.check_out_at,
                    COALESCE((SELECT SUM(p.amount) FROM local_payments p WHERE p.assignment_id=a.id AND p.status="paid"),0) AS paid_amount,
                    COALESCE((SELECT SUM(p.amount) FROM local_payments p WHERE p.assignment_id=a.id AND p.status="pending"),0) AS pending_payment_amount,
                    COALESCE(sp.amount,a.staff_payout_amount,(a.payout_amount * ((100.0-' . LOCAL_STAFF_COMMISSION_PERCENT . ')/100.0))) AS staff_payout_amount,';
                    COALESCE(sp.status,CASE WHEN a.status="completed" THEN "pending" ELSE "not_ready" END) AS staff_payout_status,
                    sp.paid_at AS staff_payout_paid_at
             FROM local_assignments a
             INNER JOIN local_requirements r ON r.id=a.requirement_id
             INNER JOIN local_users u ON u.id=a.contractor_user_id
             LEFT JOIN local_applications la ON la.id=a.application_id
             LEFT JOIN local_attendance att ON att.assignment_id=a.id
             LEFT JOIN local_staff_payouts sp ON sp.assignment_id=a.id AND sp.staff_user_id=a.staff_user_id
             WHERE a.staff_user_id=? AND '.$statusWhere.'
             ORDER BY
                CASE a.status WHEN "active" THEN 0 WHEN "arrived" THEN 1 WHEN "assigned" THEN 2 WHEN "completed" THEN 3 ELSE 4 END,
                r.shift_date ASC,a.id DESC'
        );
        $params = [(int)$user['id']];
        if ($status !== '') $params[] = $status;
        $stmt->execute($params);
        localJson(['success'=>true,'data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    if ($role === 'contractor') {
        $stmt = $pdo->prepare(
            'SELECT a.id assignment_id,a.requirement_id,a.staff_user_id,a.status,a.assigned_at,a.start_at,a.end_at,a.payout_amount,a.notes,
                    r.title,r.work_location,r.shift_date,r.shift_start,r.shift_end,
                    s.name AS staff_name,s.mobile AS staff_mobile,
                    att.check_in_at,att.check_out_at,
                    COALESCE((SELECT SUM(p.amount) FROM local_payments p WHERE p.assignment_id=a.id AND p.status="paid"),0) AS paid_amount,
                    COALESCE((SELECT SUM(p.amount) FROM local_payments p WHERE p.assignment_id=a.id AND p.status="pending"),0) AS pending_payment_amount
             FROM local_assignments a
             INNER JOIN local_requirements r ON r.id=a.requirement_id
             INNER JOIN local_users s ON s.id=a.staff_user_id
             LEFT JOIN local_attendance att ON att.assignment_id=a.id
             WHERE a.contractor_user_id=? AND a.status<>"cancelled"
             ORDER BY r.shift_date ASC,a.id DESC'
        );
        $stmt->execute([(int)$user['id']]);
        localJson(['success'=>true,'data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    localJson(['success'=>false,'message'=>'Unsupported account role'],403);
} catch (Throwable $e) {
    error_log('MWH Local assignments list: ' . $e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to load assignments'],500);
}
