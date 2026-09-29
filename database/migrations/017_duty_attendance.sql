-- MWH Local duty attendance timestamps
-- Records the moment a selected staff member marks arrival.
ALTER TABLE local_assignments
    ADD COLUMN IF NOT EXISTS arrival_at DATETIME NULL AFTER assigned_at;

ALTER TABLE local_assignments
    ADD INDEX IF NOT EXISTS idx_local_assign_arrival (arrival_at);
