-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 5: UUID Auto-Generation Trigger
-- ============================================================================
-- This migration adds a BEFORE INSERT trigger to auto-generate UUIDs for 
-- ibl_plr table records when uuid column is not provided
--
-- PREREQUISITES:
-- - Phase 1-4 migrations must be completed
-- - uuid column must exist in ibl_plr table (char(36) or varchar(36))
--
-- IMPORTANT: Run this during a maintenance window
-- Estimated time: 1 minute
-- 
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

-- Drop existing trigger if it exists (to allow re-running this migration)
DROP TRIGGER IF EXISTS ibl_plr_before_insert_uuid;

-- Create trigger to auto-generate UUID for ibl_plr inserts
DELIMITER $$
CREATE TRIGGER ibl_plr_before_insert_uuid
BEFORE INSERT ON ibl_plr
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$
DELIMITER ;

-- Verify trigger creation
SELECT 'Trigger ibl_plr_before_insert_uuid created successfully' AS status;
