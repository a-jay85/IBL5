-- Fix double-encoded UTF-8 player names in ibl_plr.
-- CP1252→UTF-8 conversion was applied correctly by PlrLineParser,
-- but the DB connection lacked utf8mb4 charset at import time,
-- so MySQL re-encoded the already-UTF-8 bytes as Latin-1 → UTF-8.
-- Pattern: C385C2xx or C383C2xx hex sequences (two-layer UTF-8).

UPDATE ibl_plr
SET name = CONVERT(CAST(CONVERT(name USING latin1) AS BINARY) USING utf8mb4)
WHERE HEX(name) LIKE '%C385C2%'
   OR HEX(name) LIKE '%C383C2%';
