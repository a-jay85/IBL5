-- Migration 177 — extend bug_reports status enum with 'filed'.
-- Forward-only, purely additive: MODIFY COLUMN appends one value. Re-runnable; a second
-- run is a no-op because MODIFY COLUMN restates the full definition.

ALTER TABLE `ibl_bug_reports`
    MODIFY COLUMN `status` ENUM(
        'queued','awaiting_info','hunting','blocked','pr_open','fixed','needs_human',
        'parked_idle','gathering','awaiting_ajay','planned','dropped','filed'
    ) NOT NULL DEFAULT 'queued';

-- Arm A: rows that already have an issue are terminal (GM already holds the link).
-- Idempotent: after the first run no row matches.
UPDATE `ibl_bug_reports`
   SET `status` = 'filed',
       `lease_owner` = NULL,
       `lease_expires` = NULL,
       `blocked_until` = NULL
 WHERE `status` IN ('planned','hunting','gathering','awaiting_ajay','blocked')
   AND `issue_number` IS NOT NULL;

-- Arm B: rows with no issue go back to the front of the queue so the flag-on
-- classify_row path files them and replies. Nulling blocked_until is load-bearing:
-- the enumerator requires blocked_until IS NULL OR blocked_until <= NOW().
-- parked_idle rows are deliberately excluded (already terminal-ish, GM already notified).
-- Idempotent: after the first run no row matches.
UPDATE `ibl_bug_reports`
   SET `status` = 'queued',
       `class` = NULL,
       `lease_owner` = NULL,
       `lease_expires` = NULL,
       `approval_message_id` = NULL,
       `blocked_until` = NULL,
       `reminder_sent_at` = NULL
 WHERE `status` IN ('planned','hunting','gathering','awaiting_ajay','blocked','awaiting_info')
   AND `issue_number` IS NULL;

-- Close the invisible-row hole: queued+class-set+no-issue is invisible to the enumerator.
UPDATE `ibl_bug_reports`
   SET `class` = NULL
 WHERE `status` = 'queued'
   AND `class` IS NOT NULL
   AND `issue_number` IS NULL;
