-- Migration: Fix player names in ibl_awards
-- Purpose: Update archive player names to match canonical names in ibl_plr.
-- Handles missing apostrophes (e.g., "Aja Wilson" -> "A'Ja Wilson")
-- and diacritical marks (e.g., "Darius" -> "Dariuš") via utf8mb4_unicode_ci collation.

-- Step 1: Preview changes (run this first to review)
-- JOIN uses unicode_ci (default) so accent-insensitive matching finds the pairs.
-- WHERE uses utf8mb4_bin so even accent-only differences are detected and updated.
SELECT DISTINCT a.name AS archive_name, p.name AS canonical_name
FROM ibl_awards a
JOIN ibl_plr p ON REPLACE(p.name, '''', '') = REPLACE(a.name, '''', '')
WHERE a.name COLLATE utf8mb4_bin != p.name COLLATE utf8mb4_bin AND a.name != '';

-- Step 2: Apply changes
UPDATE ibl_awards a
JOIN ibl_plr p ON REPLACE(p.name, '''', '') = REPLACE(a.name, '''', '')
SET a.name = p.name
WHERE a.name COLLATE utf8mb4_bin != p.name COLLATE utf8mb4_bin AND a.name != '';
