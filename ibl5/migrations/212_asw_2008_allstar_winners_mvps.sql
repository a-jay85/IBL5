-- 2008 All-Star Weekend winners and MVPs (follow-up to migration 210).
-- Sources: the 2008-all-star-weekend thread in the league Discord's "all-star history"
-- category. The contest winners also match the final-round top score in
-- ibl_jsb_allstar_scores (Towns 24 vs Curry 22; Maravich 972 vs Marbury 950).
-- Stored the way 2004-2007 store them: the game MVP is an extra row beside the
-- player's conference row; a contest winner or Rookie-Sophomore MVP row replaces
-- that player's plain entrant row.
-- Runs only where the 2008 weekend rosters exist, so the CI seed gets no rows.
-- literal-check: 2008 -> ibl_jsb_allstar_rosters.season_year smallint(5) unsigned (per ibl5/docs/schema/current-schema.sql)
-- literal-check: 2008 -> ibl_awards.year int(11) (per ibl5/docs/schema/current-schema.sql)
-- literal-check: 'Rookie-Sophomore Challenge - MVP' (32 chars, longest award) -> ibl_awards.award varchar(128) (per ibl5/docs/schema/current-schema.sql)
-- literal-check: player names (19 chars max) -> ibl_awards.name varchar(32) (per ibl5/docs/schema/current-schema.sql)
INSERT INTO ibl_awards (year, award, name)
SELECT v.year, v.award, v.name FROM (
            SELECT 2008 AS year, 'All-Star Game MVP' AS award, 'Kobe Bryant' AS name
  UNION ALL SELECT 2008, 'Rookie-Sophomore Challenge - MVP', 'Modestas Paulauskas'
  UNION ALL SELECT 2008, 'Three-Point Contest - Winner', 'Karl-Anthony Towns'
  UNION ALL SELECT 2008, 'Slam Dunk Competition - Winner', 'Pete Maravich'
) AS v
WHERE NOT EXISTS (
  SELECT 1 FROM ibl_awards a WHERE a.year = v.year AND a.award = v.award
)
AND EXISTS (SELECT 1 FROM ibl_jsb_allstar_rosters r WHERE r.season_year = 2008);

-- Drop the plain entrant row each new winner row replaces.
-- destructive-migration[rewrite-no-backup]: each deleted row is a fixed (year, award, name) triple that the undo script ibl5/scripts/undo/212_asw_2008_allstar_winners_mvps.sql re-inserts verbatim, and a row goes only when its winner row exists
DELETE p FROM ibl_awards p
JOIN ibl_awards w ON w.year = p.year AND w.name = p.name
 AND w.award = CONCAT(p.award, IF(p.award = 'Rookie-Sophomore Challenge', ' - MVP', ' - Winner'))
WHERE p.year = 2008
  AND (p.award, p.name) IN (('Rookie-Sophomore Challenge', 'Modestas Paulauskas'),
                            ('Three-Point Contest', 'Karl-Anthony Towns'),
                            ('Slam Dunk Competition', 'Pete Maravich'));
