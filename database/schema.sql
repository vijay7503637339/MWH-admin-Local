-- MWH Local standalone database schema
-- Intended for the dedicated sywwzxsv_mwh_local database.
-- No Hospitality tables are referenced.

CREATE TABLE local_admin_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_local_admin_role_status (role_id, status),
    CONSTRAINT fk_local_admin_role FOREIGN KEY (role_id) REFERENCES local_admin_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(190) NULL UNIQUE,
    aadhaar_number VARCHAR(20) NULL,
    role ENUM('staff','contractor') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    account_status ENUM('pending_verification','verified','rejected','blocked') NOT NULL DEFAULT 'pending_verification',
    rejection_reason VARCHAR(500) NULL,
    verified_at TIMESTAMP NULL,
    verified_by_admin_id BIGINT UNSIGNED NULL,
    blocked_at TIMESTAMP NULL,
    blocked_by_admin_id BIGINT UNSIGNED NULL,
    block_reason VARCHAR(500) NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_local_users_role_status (role, account_status),
    INDEX idx_local_users_created (created_at),
    CONSTRAINT fk_local_verified_admin FOREIGN KEY (verified_by_admin_id) REFERENCES local_admin_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_local_blocked_admin FOREIGN KEY (blocked_by_admin_id) REFERENCES local_admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_staff_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    date_of_birth DATE NULL,
    gender VARCHAR(30) NULL,
    city VARCHAR(100) NULL,
    full_address VARCHAR(500) NULL,
    experience_years DECIMAL(4,1) NOT NULL DEFAULT 0,
    skills TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_local_staff_user FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_contractor_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    business_name VARCHAR(190) NULL,
    business_type VARCHAR(100) NULL,
    whatsapp_mobile VARCHAR(20) NULL,
    full_address VARCHAR(500) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    postcode VARCHAR(20) NULL,
    gstin VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_local_contractor_user FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    INDEX idx_local_contractor_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_job_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uq_local_job_role_category_name (category_id, name),
    INDEX idx_local_job_roles_category_active (category_id, is_active),
    CONSTRAINT fk_local_job_role_category FOREIGN KEY (category_id) REFERENCES local_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contractor_user_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    job_role_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    openings_count INT UNSIGNED NOT NULL DEFAULT 1,
    work_location VARCHAR(255) NOT NULL,
    work_address VARCHAR(500) NULL,
    shift_date DATE NOT NULL,
    shift_start TIME NULL,
    shift_end TIME NULL,
    minimum_experience VARCHAR(50) NULL,
    payout_amount DECIMAL(12,2) NOT NULL,
    payout_period VARCHAR(30) NOT NULL DEFAULT 'per_shift',
    status ENUM('open','active','completed','cancelled','closed') NOT NULL DEFAULT 'open',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_local_req_contractor_status (contractor_user_id, status, created_at),
    INDEX idx_local_req_shift_date_status (shift_date, status),
    INDEX idx_local_req_role_status (job_role_id, status),
    CONSTRAINT fk_local_req_contractor FOREIGN KEY (contractor_user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_req_category FOREIGN KEY (category_id) REFERENCES local_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_local_req_job_role FOREIGN KEY (job_role_id) REFERENCES local_job_roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requirement_id BIGINT UNSIGNED NOT NULL,
    staff_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('applied','shortlisted','selected','rejected','withdrawn') NOT NULL DEFAULT 'applied',
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes TEXT NULL,
    UNIQUE KEY uq_local_application_requirement_staff (requirement_id, staff_user_id),
    INDEX idx_local_applications_staff_status (staff_user_id, status),
    INDEX idx_local_applications_requirement_status (requirement_id, status),
    CONSTRAINT fk_local_application_requirement FOREIGN KEY (requirement_id) REFERENCES local_requirements(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_application_staff FOREIGN KEY (staff_user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requirement_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    staff_user_id BIGINT UNSIGNED NOT NULL,
    contractor_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('assigned','arrived','active','completed','cancelled') NOT NULL DEFAULT 'assigned',
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    payout_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_local_assign_staff_status (staff_user_id, status),
    INDEX idx_local_assign_contractor_status (contractor_user_id, status),
    CONSTRAINT fk_local_assign_requirement FOREIGN KEY (requirement_id) REFERENCES local_requirements(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_assign_application FOREIGN KEY (application_id) REFERENCES local_applications(id) ON DELETE SET NULL,
    CONSTRAINT fk_local_assign_staff FOREIGN KEY (staff_user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_assign_contractor FOREIGN KEY (contractor_user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    staff_user_id BIGINT UNSIGNED NOT NULL,
    check_in_at DATETIME NULL,
    check_in_photo_path VARCHAR(500) NULL,
    check_in_latitude DECIMAL(10,7) NULL,
    check_in_longitude DECIMAL(10,7) NULL,
    check_out_at DATETIME NULL,
    check_in_notes VARCHAR(500) NULL,
    check_out_notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_local_attendance_staff_date (staff_user_id, check_in_at),
    CONSTRAINT fk_local_attendance_assignment FOREIGN KEY (assignment_id) REFERENCES local_assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_attendance_staff FOREIGN KEY (staff_user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    staff_user_id BIGINT UNSIGNED NOT NULL,
    contractor_user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    transaction_reference VARCHAR(190) NULL,
    status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    notes VARCHAR(500) NULL,
    entered_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_local_payments_staff_date (staff_user_id, payment_date),
    INDEX idx_local_payments_assignment (assignment_id),
    UNIQUE KEY uq_local_payment_reference (transaction_reference),
    CONSTRAINT fk_local_payment_assignment FOREIGN KEY (assignment_id) REFERENCES local_assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_payment_staff FOREIGN KEY (staff_user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_payment_contractor FOREIGN KEY (contractor_user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_payment_admin FOREIGN KEY (entered_by_admin_id) REFERENCES local_admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    admin_user_id BIGINT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    data_json JSON NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_local_notifications_user_read (user_id, read_at, created_at),
    INDEX idx_local_notifications_admin_read (admin_user_id, read_at, created_at),
    CONSTRAINT fk_local_notification_user FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_notification_admin FOREIGN KEY (admin_user_id) REFERENCES local_admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_api_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_local_api_tokens_user_active (user_id, revoked_at, expires_at),
    CONSTRAINT fk_local_api_token_user FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE local_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type VARCHAR(20) NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_local_audit_entity (entity_type, entity_id, created_at),
    INDEX idx_local_audit_actor (actor_type, actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO local_admin_roles (name, description) VALUES
('super_admin', 'Full MWH Local access'),
('operations_admin', 'Manage Local contractors, staff, requirements, applications and assignments'),
('finance_admin', 'Manage Local payments and attendance')
ON DUPLICATE KEY UPDATE description = VALUES(description);
