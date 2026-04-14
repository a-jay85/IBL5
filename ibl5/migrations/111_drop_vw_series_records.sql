-- Drop the vw_series_records view.
-- HeadToHeadRecords reads ibl_box_scores_teams directly; Standings now
-- inlines an equivalent query. The view is no longer referenced.
DROP VIEW IF EXISTS vw_series_records;
