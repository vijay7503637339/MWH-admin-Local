-- MWH Local assignment payout split
-- payout_amount remains the gross contractor obligation per selected staff assignment.
-- staff_payout_amount is the net amount payable to that staff member after commission.
SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'local_assignments'
      AND COLUMN_NAME = 'staff_payout_amount'
);
SET @column_sql := IF(
    @column_exists = 0,
    'ALTER TABLE local_assignments ADD COLUMN staff_payout_amount DECIMAL(12,2) NULL AFTER payout_amount',
    'SELECT 1'
);
PREPARE column_stmt FROM @column_sql;
EXECUTE column_stmt;
DEALLOCATE PREPARE column_stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'local_assignments'
      AND INDEX_NAME = 'idx_local_assign_staff_payout'
);
SET @index_sql := IF(
    @index_exists = 0,
    'ALTER TABLE local_assignments ADD INDEX idx_local_assign_staff_payout (staff_payout_amount)',
    'SELECT 1'
);
PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;

UPDATE local_assignments a
INNER JOIN local_requirements r ON r.id=a.requirement_id
INNER JOIN local_job_roles jr ON jr.id=r.job_role_id
SET a.staff_payout_amount=ROUND(jr.job_amount * 0.80, 2)
WHERE a.staff_payout_amount IS NULL AND jr.job_amount > 0;
