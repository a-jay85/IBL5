<?php

declare(strict_types=1);

namespace BugPipeline;

/**
 * Shared snowflake-casting for the Discord bug pipeline repositories.
 *
 * All snowflake columns are (string)-cast on read (see castRow()) because
 * db/db.php:100 sets MYSQLI_OPT_INT_AND_FLOAT_NATIVE — BIGINT reads back as
 * PHP int, and json_encode of a bare int loses precision above 2^53.
 *
 * The `BugReportRow` type is defined here (not on any one repository) so every
 * sub-repository and the facade can share one row shape via
 * `@phpstan-import-type BugReportRow from \BugPipeline\BugReportRowCasting`.
 *
 * @phpstan-type BugReportRow array{
 *   id: int,
 *   discord_author_id: string,
 *   channel_id: string,
 *   original_message_id: string,
 *   original_text: string,
 *   thread_id: ?string,
 *   class: ?string,
 *   status: string,
 *   lease_owner: ?string,
 *   lease_expires: ?string,
 *   hunt_attempts: int,
 *   pr_number: ?int,
 *   issue_number: ?int,
 *   approval_message_id: ?string,
 *   blocked_until: ?string,
 *   last_gm_reply_at: ?string,
 *   last_processed_at: ?string,
 *   reminder_sent_at: ?string,
 *   created_at: string,
 *   updated_at: string
 * }
 */
trait BugReportRowCasting
{
    /** Snowflake columns of ibl_bug_reports that must serialize as JSON strings (see db.php:100). */
    private const SNOWFLAKE_COLUMNS = [
        'discord_author_id',
        'channel_id',
        'original_message_id',
        'thread_id',
        'approval_message_id',
    ];

    /**
     * @param array<string, mixed> $row
     * @phpstan-return BugReportRow
     */
    private function castRow(array $row): array
    {
        foreach (self::SNOWFLAKE_COLUMNS as $col) {
            if (isset($row[$col]) && is_scalar($row[$col])) {
                $row[$col] = (string) $row[$col];
            }
        }
        /** @var BugReportRow $row */
        return $row;
    }
}
