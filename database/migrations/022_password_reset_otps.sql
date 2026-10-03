-- Store short-lived password reset OTPs for Local staff and contractor accounts.
CREATE TABLE IF NOT EXISTS local_password_reset_otps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    otp_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_local_password_reset_user_created (user_id, created_at),
    INDEX idx_local_password_reset_email_created (email, created_at),
    INDEX idx_local_password_reset_expires (expires_at),
    CONSTRAINT fk_local_password_reset_user
        FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
