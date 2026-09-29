-- MWH Local payment settings
-- Apply once to the existing sywwzxsv_mwh_local database.

CREATE TABLE IF NOT EXISTS local_payment_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(190) NOT NULL DEFAULT 'Maan World Local',
    upi_id VARCHAR(190) NULL,
    account_number VARCHAR(40) NULL,
    account_name VARCHAR(190) NULL,
    ifsc_code VARCHAR(30) NULL,
    qr_code_path VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_local_payment_settings_active (is_active, updated_at),
    CONSTRAINT fk_local_payment_settings_admin
        FOREIGN KEY (updated_by_admin_id) REFERENCES local_admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO local_payment_settings (company_name, is_active)
SELECT 'Maan World Local', 1
WHERE NOT EXISTS (SELECT 1 FROM local_payment_settings);
