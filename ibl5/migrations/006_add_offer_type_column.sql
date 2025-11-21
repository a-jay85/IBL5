-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 6: Add offer_type Column
-- ============================================================================
-- This migration adds an offer_type column to ibl_fa_offers table to preserve
-- the original offer type (Custom, MLE 1-6 years, LLE, Veteran's Minimum)
-- for improved code clarity and debugging.
--
-- PREREQUISITES:
-- - Phase 1-5 migrations must be completed
-- - ibl_fa_offers table must exist and be using InnoDB engine
--
-- IMPORTANT: Run this during a maintenance window
-- Estimated time: < 1 minute
-- 
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

USE IBL;

-- Add offer_type column to track the type of contract offer
-- Values: 0=Custom, 1-6=MLE (years), 7=Lower-Level Exception, 8=Veteran's Minimum
ALTER TABLE ibl_fa_offers
ADD COLUMN offer_type INT NOT NULL DEFAULT 0 COMMENT 'Offer type: 0=Custom, 1-6=MLE years, 7=LLE, 8=Vet Min'
AFTER lle;

-- Add index for offer_type to enable efficient queries by offer type
ALTER TABLE ibl_fa_offers
ADD INDEX idx_offer_type (offer_type);

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these queries after migration to verify success:
--
-- 1. Verify column was added:
--    DESCRIBE ibl_fa_offers;
--
-- 2. Verify index was created:
--    SHOW INDEX FROM ibl_fa_offers WHERE Key_name = 'idx_offer_type';
--
-- 3. Check existing data (should all be 0 - default):
--    SELECT offer_type, COUNT(*) FROM ibl_fa_offers GROUP BY offer_type;
--
-- ============================================================================
-- ROLLBACK (if needed)
-- ============================================================================
-- To rollback this migration, run:
--
-- ALTER TABLE ibl_fa_offers DROP INDEX idx_offer_type;
-- ALTER TABLE ibl_fa_offers DROP COLUMN offer_type;
--
-- ============================================================================
