<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

function localCompanyPaymentSettings(PDO $pdo): array {
    try {
        $stmt=$pdo->query("SELECT id,support_phone,support_email,whatsapp_number,instructions FROM local_payment_settings WHERE is_active=1 ORDER BY id DESC LIMIT 1");
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    } catch(Throwable $e) {
        return [];
    }
}
