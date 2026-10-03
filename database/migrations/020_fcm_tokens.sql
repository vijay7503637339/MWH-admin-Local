-- MWH Local FCM device token registry
CREATE TABLE IF NOT EXISTS local_fcm_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(512) NOT NULL,
    platform VARCHAR(20) NOT NULL DEFAULT 'android',
    app_version VARCHAR(40) NULL,
    device_id VARCHAR(190) NULL,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    disabled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_local_fcm_token (token),
    INDEX idx_local_fcm_user_active (user_id, disabled_at),
    INDEX idx_local_fcm_last_seen (last_seen_at),
    CONSTRAINT fk_local_fcm_user FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
