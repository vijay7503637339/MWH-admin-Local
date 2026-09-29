-- MWH Local staff payout ledger
CREATE TABLE IF NOT EXISTS local_staff_payouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    staff_user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    payment_method VARCHAR(30) NULL,
    transaction_reference VARCHAR(190) NULL,
    notes VARCHAR(500) NULL,
    entered_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_local_staff_payout_assignment (assignment_id),
    INDEX idx_local_staff_payout_staff_status (staff_user_id,status),
    CONSTRAINT fk_local_staff_payout_assignment FOREIGN KEY (assignment_id) REFERENCES local_assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_staff_payout_staff FOREIGN KEY (staff_user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_staff_payout_admin FOREIGN KEY (entered_by_admin_id) REFERENCES local_admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
