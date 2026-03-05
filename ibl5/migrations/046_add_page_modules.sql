-- Migration 046: Add converted page modules
-- Use INSERT ... SELECT ... WHERE NOT EXISTS to guard against duplicates
-- (nuke_modules has no UNIQUE on title)

INSERT INTO nuke_modules (title, custom_title, active, view, inmenu, mod_group, admins)
SELECT 'All_Star_Appearances', 'All-Star Appearances', 1, 0, 1, 0, ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM nuke_modules WHERE title = 'All_Star_Appearances');

INSERT INTO nuke_modules (title, custom_title, active, view, inmenu, mod_group, admins)
SELECT 'GMContactList', 'GM Contact List', 1, 0, 1, 0, ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM nuke_modules WHERE title = 'GMContactList');

INSERT INTO nuke_modules (title, custom_title, active, view, inmenu, mod_group, admins)
SELECT 'Contract_List', 'Contract List', 1, 0, 1, 0, ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM nuke_modules WHERE title = 'Contract_List');

INSERT INTO nuke_modules (title, custom_title, active, view, inmenu, mod_group, admins)
SELECT 'Draft_History', 'Draft History', 1, 0, 1, 0, ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM nuke_modules WHERE title = 'Draft_History');

INSERT INTO nuke_modules (title, custom_title, active, view, inmenu, mod_group, admins)
SELECT 'Free_Agency_Preview', 'Free Agency Preview', 1, 0, 1, 0, ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM nuke_modules WHERE title = 'Free_Agency_Preview');

INSERT INTO nuke_modules (title, custom_title, active, view, inmenu, mod_group, admins)
SELECT 'Season_Highs', 'Season Highs', 1, 0, 1, 0, ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM nuke_modules WHERE title = 'Season_Highs');
