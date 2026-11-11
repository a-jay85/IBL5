-- Part 1: ibl_jsb_allstar_* season 2009 is a byte copy of 2007 written by a stale
-- IBL5.asw import (a-jay85/ibl5-bugs#29). Back up, then delete. The 2009 weekend
-- has not been played.
-- destructive-migration[rewrite-no-backup]: both DELETEs below first copy their rows into a _backup_210 table in this file, and the undo script lives at ibl5/scripts/undo/210_asw_stale_rows_allstar_awards_backfill.sql, kept outside ibl5/migrations/ so the migration runner never applies it.
-- rollback-incompatible: only production's SchemaInvariantTest FK-gap inventory breaks, since it pins the exact list of pid columns without an FK and the two FK-free _backup_210 tables add two; production PHP never reads the backup tables
-- literal-check: 2009 -> ibl_jsb_allstar_rosters.season_year smallint(5) unsigned (per ibl5/docs/schema/current-schema.sql)
-- literal-check: 2009 -> ibl_jsb_allstar_scores.season_year smallint(5) unsigned (per ibl5/docs/schema/current-schema.sql)
CREATE TABLE IF NOT EXISTS ibl_jsb_allstar_rosters_backup_210 LIKE ibl_jsb_allstar_rosters;
INSERT INTO ibl_jsb_allstar_rosters_backup_210
  SELECT * FROM ibl_jsb_allstar_rosters WHERE season_year = 2009;
DELETE FROM ibl_jsb_allstar_rosters WHERE season_year = 2009;

CREATE TABLE IF NOT EXISTS ibl_jsb_allstar_scores_backup_210 LIKE ibl_jsb_allstar_scores;
INSERT INTO ibl_jsb_allstar_scores_backup_210
  SELECT * FROM ibl_jsb_allstar_scores WHERE season_year = 2009;
DELETE FROM ibl_jsb_allstar_scores WHERE season_year = 2009;

-- Part 2: 2008 All-Star Weekend participants. Idempotent; never relabels.
-- Runs only where the 2008 weekend rosters exist, so a DB without league history
-- (the CI seed) gets no rows.
-- literal-check: 2008 -> ibl_jsb_allstar_rosters.season_year smallint(5) unsigned (per ibl5/docs/schema/current-schema.sql)
-- literal-check: 2008 -> ibl_awards.year int(11) (per ibl5/docs/schema/current-schema.sql)
-- literal-check: 'Rookie-Sophomore Challenge - MVP' (32 chars, longest award) -> ibl_awards.award varchar(128) (per ibl5/docs/schema/current-schema.sql)
-- literal-check: player names (19 chars max) -> ibl_awards.name varchar(32) (per ibl5/docs/schema/current-schema.sql)
INSERT INTO ibl_awards (year, award, name)
SELECT v.year, v.award, v.name FROM (
            SELECT 2008 AS year, 'Eastern Conference All-Star' AS award, 'Chris Paul' AS name
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Shareef Abdur-Rahim'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Karl-Anthony Towns'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'James Harden'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Devin Booker'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'LeBron James'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Tyrese Haliburton'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Jermaine O''Neal'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Anthony Edwards'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Nate Thurmond'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Tyreke Evans'
  UNION ALL SELECT 2008, 'Eastern Conference All-Star', 'Immanuel Quickley'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'A''Ja Wilson'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Dwyane Wade'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Blake Griffin'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Nancy Lieberman'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'George Mikan'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Takenori Akagi'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Dwight Howard'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Stephen Curry'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Anfernee Hardaway'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Kobe Bryant'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Shaquille O''Neal'
  UNION ALL SELECT 2008, 'Western Conference All-Star', 'Kristaps Porzingis'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Darius Garland'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Mike Kent'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Coby White'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Vassillis Spanoulis'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Elgin Baylor'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'DeAndre Hunter'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Zion Williamson'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Jaren Jackson Jr'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Kareem Abdul-Jabbar'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Bol Bol'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Jason Williams'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Mike Bibby'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Vince Carter'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Cuttino Mobley'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Modestas Paulauskas'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Kiki Vandeweghe'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Dirk Nowitzki'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Danny Manning'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Jean-Paul Beugnot'
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge', 'Michael Olowokandi'
  UNION ALL SELECT 2008, 'Three-Point Contest', 'Karl-Anthony Towns'
  UNION ALL SELECT 2008, 'Three-Point Contest', 'Stephen Curry'
  UNION ALL SELECT 2008, 'Three-Point Contest', 'Immanuel Quickley'
  UNION ALL SELECT 2008, 'Three-Point Contest', 'Anthony Edwards'
  UNION ALL SELECT 2008, 'Three-Point Contest', 'LaMelo Ball'
  UNION ALL SELECT 2008, 'Three-Point Contest', 'Jamal Mashburn'
  UNION ALL SELECT 2008, 'Three-Point Contest', 'J.R. Smith'
  UNION ALL SELECT 2008, 'Three-Point Contest', 'Jrue Holiday'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'Pete Maravich'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'Stephon Marbury'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'Kobe Bryant'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'Mike Kent'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'Issac Okoro'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'J.R. Smith'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'Allen Iverson'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'Ruben Patterson'
) AS v
WHERE NOT EXISTS (
  SELECT 1 FROM ibl_awards a
   WHERE a.year = v.year AND a.name = v.name
     AND (a.award = v.award
       OR (v.award IN ('Eastern Conference All-Star', 'Western Conference All-Star')
           AND a.award IN ('Eastern Conference All-Star', 'Western Conference All-Star'))
       OR (v.award = 'Rookie-Sophomore Challenge' AND a.award = 'Rookie-Sophomore Challenge - MVP')
       OR (v.award = 'Three-Point Contest' AND a.award = 'Three-Point Contest - Winner')
       OR (v.award = 'Slam Dunk Competition' AND a.award = 'Slam Dunk Competition - Winner'))
)
AND EXISTS (SELECT 1 FROM ibl_jsb_allstar_rosters r WHERE r.season_year = 2008);
