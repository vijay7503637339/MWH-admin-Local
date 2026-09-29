-- MWH Local job role pricing master
-- Stores the default amount and billing period for each job role.
SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'local_job_roles'
      AND COLUMN_NAME = 'job_amount'
);
SET @column_sql := IF(
    @column_exists = 0,
    'ALTER TABLE local_job_roles ADD COLUMN job_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER description',
    'SELECT 1'
);
PREPARE column_stmt FROM @column_sql;
EXECUTE column_stmt;
DEALLOCATE PREPARE column_stmt;

SET @period_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'local_job_roles'
      AND COLUMN_NAME = 'amount_period'
);
SET @period_sql := IF(
    @period_exists = 0,
    'ALTER TABLE local_job_roles ADD COLUMN amount_period ENUM("per_day","per_month") NOT NULL DEFAULT "per_day" AFTER job_amount',
    'SELECT 1'
);
PREPARE period_stmt FROM @period_sql;
EXECUTE period_stmt;
DEALLOCATE PREPARE period_stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'local_job_roles'
      AND INDEX_NAME = 'idx_local_job_roles_pricing'
);
SET @index_sql := IF(
    @index_exists = 0,
    'ALTER TABLE local_job_roles ADD INDEX idx_local_job_roles_pricing (amount_period, job_amount)',
    'SELECT 1'
);
PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;
