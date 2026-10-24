-- Add 'started' to notified_state: prod posts "Sim N recap started on <host>."
-- once, on the first claim of a sim (CAS NULL -> 'started').
-- Additive: the seven existing values keep their order and ordinals;
-- 'started' is appended last, so stored rows are untouched.
-- rollback-incompatible: only the old SimSummariesSelfHealingSchemaTest pin of the seven-value enum breaks; old code treats notified_state as an opaque CAS string
ALTER TABLE `ibl_sim_summaries`
  MODIFY COLUMN `notified_state`
    enum('retrying','waiting','handed_mac','handed_actions','posted','recovered','unavailable','started')
    DEFAULT NULL
    COMMENT 'Last #admin-chat status posted for this sim; compare-and-set de-dup key.';
