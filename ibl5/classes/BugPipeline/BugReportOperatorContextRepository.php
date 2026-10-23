<?php

declare(strict_types=1);

namespace BugPipeline;

/**
 * Operator-context log for the Discord bug pipeline (`ibl_bug_report_operator_context`).
 *
 * One row per operator @mention in a report's thread. The wake path in
 * {@see BugReportRepository::wakeByOperator()} appends here; the enumerator and
 * claim-next scripts read it back so the classify/hunt prompts carry the operator's words.
 * Split out of the facade to keep that file small.
 */
class BugReportOperatorContextRepository extends \Database\BaseMysqliRepository
{
    /** Stored content cap; mirrors the migration's "truncated to 4000 chars in PHP". */
    private const MAX_CONTENT_CHARS = 4000;

    /**
     * Append one operator message. INSERT IGNORE against UNIQUE (discord_message_id): a replayed
     * wake call inserts nothing. Returns true only when a new row was written.
     *
     * Snowflakes bind 's' (never int-cast). bug_report_id is the local AUTO_INCREMENT id.
     */
    public function append(int $reportId, string $messageId, string $authorId, string $content): bool
    {
        $content = mb_substr($content, 0, self::MAX_CONTENT_CHARS);
        return $this->execute(
            'INSERT IGNORE INTO `ibl_bug_report_operator_context`
                (bug_report_id, discord_message_id, discord_author_id, content, created_at)
             VALUES (?, ?, ?, ?, NOW())',
            'isss',
            $reportId,
            $messageId,
            $authorId,
            $content
        ) === 1;
    }

    /**
     * Operator context for many reports in ONE batched query, keyed by report id, oldest-first.
     * An empty $ids short-circuits inside fetchAllInList without touching the database.
     *
     * @param list<int> $ids
     * @return array<int, list<array{message_id: string, created_at: string, content: string}>>
     */
    public function listForReportIds(array $ids): array
    {
        $rows = $this->fetchAllInList(
            'SELECT bug_report_id, discord_message_id, created_at, content
               FROM `ibl_bug_report_operator_context`
              WHERE bug_report_id IN ({IN})
              ORDER BY created_at ASC, id ASC',
            'i',
            $ids
        );

        $grouped = [];
        foreach ($rows as $row) {
            $reportId = self::scalarToInt($row['bug_report_id']);
            $grouped[$reportId][] = [
                'message_id' => self::scalarToString($row['discord_message_id']),
                'created_at' => self::scalarToString($row['created_at']),
                'content'    => self::scalarToString($row['content']),
            ];
        }
        return $grouped;
    }
}
