<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    localJson(['success' => false, 'message' => 'POST request required'], 405);
}

try {
    $user = localRequireUser($pdo);
    if ((string)$user['role'] !== 'contractor') {
        localJson(['success' => false, 'message' => 'Contractor access is required'], 403);
    }

    $d = localInput();
    $assignmentId = (int)($d['assignment_id'] ?? 0);
    $amount = (float)($d['amount'] ?? 0);
    $method = trim((string)($d['payment_method'] ?? 'upi'));

    if ($assignmentId <= 0 || $amount <= 0) {
        localJson(['success' => false, 'message' => 'Valid assignment and amount are required'], 422);
    }
    if (!in_array($method, ['upi', 'bank_transfer', 'cash', 'other'], true)) {
        localJson(['success' => false, 'message' => 'Invalid payment method'], 422);
    }

    if (!isset($_FILES['payment_screenshot']) ||
        (int)$_FILES['payment_screenshot']['error'] === UPLOAD_ERR_NO_FILE) {
        localJson(['success' => false, 'message' => 'Payment screenshot is required'], 422);
    }

    $file = $_FILES['payment_screenshot'];
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        localJson(['success' => false, 'message' => 'Payment screenshot upload failed'], 422);
    }
    if ((int)$file['size'] > 5 * 1024 * 1024) {
        localJson(['success' => false, 'message' => 'Payment screenshot must be 5 MB or smaller'], 422);
    }

    $tmp = (string)$file['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        localJson(['success' => false, 'message' => 'Payment screenshot must be JPG, PNG or WEBP'], 422);
    }

    $q = $pdo->prepare(
        'SELECT id,staff_user_id,contractor_user_id,payout_amount,status
         FROM local_assignments
         WHERE id=? AND contractor_user_id=? LIMIT 1'
    );
    $q->execute([$assignmentId, (int)$user['id']]);
    $a = $q->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        localJson(['success' => false, 'message' => 'Assignment not found'], 404);
    }
    if (!in_array((string)$a['status'], ['assigned','arrived','active','completed'], true)) {
        localJson(['success' => false, 'message' => 'Payment is not available for this assignment'], 409);
    }

    $paid = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0)
         FROM local_payments
         WHERE assignment_id=? AND status IN ("pending","paid")'
    );
    $paid->execute([$assignmentId]);
    $already = (float)$paid->fetchColumn();

    $remaining = round((float)$a['payout_amount'] - $already, 2);
    if ($remaining <= 0) {
        localJson(['success' => false, 'message' => 'This assignment is already fully covered'], 409);
    }
    if ($amount > $remaining) {
        localJson(['success' => false, 'message' => 'Payment exceeds the remaining amount'], 422);
    }

    $dir = dirname(__DIR__, 2) . '/uploads/payment_proofs';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        localJson(['success' => false, 'message' => 'Unable to create payment upload directory'], 500);
    }

    $fileName = 'payment_' . (int)$user['id'] . '_' . $assignmentId . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $targetPath = $dir . '/' . $fileName;

    if (!move_uploaded_file($tmp, $targetPath)) {
        localJson(['success' => false, 'message' => 'Unable to save payment screenshot'], 500);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO local_payments
             (assignment_id,staff_user_id,contractor_user_id,amount,payment_date,payment_method,transaction_reference,payment_screenshot_path,status)
             VALUES(?,?,?,?,UTC_DATE(),?,?,NULL,? )'
        );
        $stmt->execute([
            $assignmentId,
            (int)$a['staff_user_id'],
            (int)$user['id'],
            $amount,
            $method,
            'pending'
        ]);

        // The statement above intentionally omits the screenshot path; update it
        // immediately so the payment row keeps the same generated file reference.
        $paymentId = (int)$pdo->lastInsertId();
        $update = $pdo->prepare(
            'UPDATE local_payments SET payment_screenshot_path=? WHERE id=?'
        );
        $update->execute([
            'uploads/payment_proofs/' . $fileName,
            $paymentId
        ]);
    } catch (Throwable $dbError) {
        @unlink($targetPath);
        throw $dbError;
    }

    localJson([
        'success' => true,
        'message' => 'Payment submitted for admin approval',
        'data' => [
            'payment_id' => $paymentId,
            'status' => 'pending',
            'amount' => $amount,
            'screenshot_uploaded' => true,
        ],
    ]);
} catch (Throwable $e) {
    error_log('MWH Local payment prework: ' . $e->getMessage());
    localJson(['success' => false, 'message' => 'Unable to submit payment'], 500);
}
