-- Manual undo for migration 212. Never runs automatically (scripts/undo/ is outside migrations/).
INSERT INTO ibl_awards (year, award, name)
SELECT v.year, v.award, v.name FROM (
            SELECT 2008 AS year, 'Rookie-Sophomore Challenge' AS award, 'Modestas Paulauskas' AS name
  UNION ALL SELECT 2008, 'Three-Point Contest', 'Karl-Anthony Towns'
  UNION ALL SELECT 2008, 'Slam Dunk Competition', 'Pete Maravich'
) AS v
WHERE NOT EXISTS (
  SELECT 1 FROM ibl_awards a WHERE a.year = v.year AND a.award = v.award AND a.name = v.name
);
DELETE FROM ibl_awards
 WHERE year = 2008
   AND award IN ('All-Star Game MVP', 'Rookie-Sophomore Challenge - MVP',
                 'Three-Point Contest - Winner', 'Slam Dunk Competition - Winner');
