-- Manual undo for migration 216. Never runs automatically (scripts/undo/ is outside migrations/).
UPDATE ibl_awards SET name = 'A"Ja Wilson' WHERE table_id = 13597 AND name = 'A''Ja Wilson';
UPDATE ibl_awards SET name = 'Shaquille O"Neal' WHERE table_id = 13602 AND name = 'Shaquille O''Neal';
UPDATE ibl_awards SET name = 'Jermaine O"Neal' WHERE table_id = 13603 AND name = 'Jermaine O''Neal';
UPDATE ibl_awards SET name = 'A"Ja Wilson' WHERE table_id = 13620 AND name = 'A''Ja Wilson';
UPDATE ibl_awards SET name = 'Jermaine O"Neal' WHERE table_id = 13622 AND name = 'Jermaine O''Neal';
UPDATE ibl_awards SET name = 'Shaquille O"Neal' WHERE table_id = 13637 AND name = 'Shaquille O''Neal';
UPDATE ibl_awards SET name = 'Jermaine O"Neal' WHERE table_id = 13638 AND name = 'Jermaine O''Neal';
UPDATE ibl_awards SET name = 'A"Ja Wilson' WHERE table_id = 13643 AND name = 'A''Ja Wilson';
UPDATE ibl_awards SET name = 'Shaquille O"Neal' WHERE table_id = 13665 AND name = 'Shaquille O''Neal';
UPDATE ibl_awards SET name = 'Jermaine O"Neal' WHERE table_id = 13670 AND name = 'Jermaine O''Neal';
