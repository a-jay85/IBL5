-- Migration: Fix player names in ibl_awards
-- Purpose: Update archive player names to match canonical names in ibl_plr.
-- Handles missing apostrophes (e.g., "Aja Wilson" -> "A'Ja Wilson")
-- and diacritical marks (e.g., "Darius" -> "Dariuš") via utf8mb4_unicode_ci collation.

-- Apply changes
UPDATE ibl_awards a
JOIN ibl_plr p ON REPLACE(p.name, '''', '') = REPLACE(a.name, '''', '')
SET a.name = p.name
WHERE a.name COLLATE utf8mb4_bin != p.name COLLATE utf8mb4_bin AND a.name != '';
