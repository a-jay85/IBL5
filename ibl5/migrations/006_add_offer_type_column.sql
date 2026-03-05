-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 6: Add offer_type Column
-- ============================================================================

-- Database selected via CLI argument; no USE statement needed

-- Add offer_type column to track the type of contract offer
-- Values: 0=Custom, 1-6=MLE (years), 7=Lower-Level Exception, 8=Veteran's Minimum
ALTER TABLE ibl_fa_offers
ADD COLUMN IF NOT EXISTS offer_type INT NOT NULL DEFAULT 0 COMMENT 'Offer type: 0=Custom, 1-6=MLE years, 7=LLE, 8=Vet Min'
AFTER lle;

-- Add index for offer_type to enable efficient queries by offer type
ALTER TABLE ibl_fa_offers
ADD INDEX IF NOT EXISTS idx_offer_type (offer_type);
