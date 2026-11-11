-- Manual undo for migration 210. Never runs automatically (undo/ is not globbed).
INSERT IGNORE INTO ibl_jsb_allstar_rosters SELECT * FROM ibl_jsb_allstar_rosters_backup_210;
INSERT IGNORE INTO ibl_jsb_allstar_scores SELECT * FROM ibl_jsb_allstar_scores_backup_210;
DELETE FROM ibl_awards
 WHERE year = 2008
   AND award IN ('Eastern Conference All-Star', 'Rookie-Sophomore Challenge',
                 'Three-Point Contest', 'Slam Dunk Competition');
DELETE FROM ibl_awards
 WHERE year = 2008 AND award = 'Western Conference All-Star'
   AND name NOT IN ('Chris Bosh', 'Myles Turner', 'Tyrese Maxey');
