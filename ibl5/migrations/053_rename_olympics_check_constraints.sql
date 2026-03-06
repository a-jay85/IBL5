-- Migration 053: Rename duplicate CHECK constraint names on ibl_olympics_plr
-- MariaDB allows duplicate constraint names across tables, but MySQL 8.0
-- requires globally unique names. The ibl_olympics_plr table reuses the same
-- constraint names as ibl_plr, causing import failures on MySQL 8.0 (MAMP).
-- Prefix the olympics constraints with 'olym_' to make them unique.

ALTER TABLE ibl_olympics_plr
  DROP CONSTRAINT IF EXISTS chk_plr_cy,
  DROP CONSTRAINT IF EXISTS chk_plr_cyt,
  DROP CONSTRAINT IF EXISTS chk_plr_cy1,
  DROP CONSTRAINT IF EXISTS chk_plr_cy2,
  DROP CONSTRAINT IF EXISTS chk_plr_cy3,
  DROP CONSTRAINT IF EXISTS chk_plr_cy4,
  DROP CONSTRAINT IF EXISTS chk_plr_cy5,
  DROP CONSTRAINT IF EXISTS chk_plr_cy6,
  DROP CONSTRAINT IF EXISTS chk_olym_plr_cy,
  DROP CONSTRAINT IF EXISTS chk_olym_plr_cyt,
  DROP CONSTRAINT IF EXISTS chk_olym_plr_cy1,
  DROP CONSTRAINT IF EXISTS chk_olym_plr_cy2,
  DROP CONSTRAINT IF EXISTS chk_olym_plr_cy3,
  DROP CONSTRAINT IF EXISTS chk_olym_plr_cy4,
  DROP CONSTRAINT IF EXISTS chk_olym_plr_cy5,
  DROP CONSTRAINT IF EXISTS chk_olym_plr_cy6;

ALTER TABLE ibl_olympics_plr
  ADD CONSTRAINT chk_olym_plr_cy  CHECK (cy  >= 0 AND cy  <= 6),
  ADD CONSTRAINT chk_olym_plr_cyt CHECK (cyt >= 0 AND cyt <= 6),
  ADD CONSTRAINT chk_olym_plr_cy1 CHECK (cy1 >= -7000 AND cy1 <= 7000),
  ADD CONSTRAINT chk_olym_plr_cy2 CHECK (cy2 >= -7000 AND cy2 <= 7000),
  ADD CONSTRAINT chk_olym_plr_cy3 CHECK (cy3 >= -7000 AND cy3 <= 7000),
  ADD CONSTRAINT chk_olym_plr_cy4 CHECK (cy4 >= -7000 AND cy4 <= 7000),
  ADD CONSTRAINT chk_olym_plr_cy5 CHECK (cy5 >= -7000 AND cy5 <= 7000),
  ADD CONSTRAINT chk_olym_plr_cy6 CHECK (cy6 >= -7000 AND cy6 <= 7000);
