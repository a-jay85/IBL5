-- Migration: Add converted page modules
-- Run this to register the new modules in the database

INSERT INTO nuke_modules (title, custom_title, active, view, inmenu, mod_group, admins) VALUES
('All_Star_Appearances', 'All-Star Appearances', 1, 0, 1, 0, ''),
('GMContactList', 'GM Contact List', 1, 0, 1, 0, ''),
('Contract_List', 'Contract List', 1, 0, 1, 0, ''),
('Draft_History', 'Draft History', 1, 0, 1, 0, ''),
('Free_Agency_Preview', 'Free Agency Preview', 1, 0, 1, 0, ''),
('Season_Highs', 'Season Highs', 1, 0, 1, 0, '');
