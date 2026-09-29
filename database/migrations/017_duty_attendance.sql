-- MWH Local duty attendance timestamps
-- Records the moment a selected staff member marks arrival.
SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'local_assignments'
      AND COLUMN_NAME = 'arrival_at'
);
SET @column_sql := IF(
    @column_exists = 0,
    'ALTER TABLE local_assignments ADD COLUMN arrival_at DATETIME NULL AFTER assigned_at',
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
      AND INDEX_NAME = 'idx_local_assign_arrival'
);
SET @index_sql := IF(
    @index_exists = 0,
    'ALTER TABLE local_assignments ADD INDEX idx_local_assign_arrival (arrival_at)',
    'SELECT 1'
);
PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;
