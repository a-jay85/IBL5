<?php

declare(strict_types=1);

namespace BugPipeline;

use BugPipeline\Contracts\BugPipelineStateRepositoryInterface;

/**
 * Per-channel backfill watermark store for the Discord bug pipeline
 * (`ibl_bug_pipeline_state`). Split out of {@see BugReportRepository}
 * (backlog 1.26); the facade delegates to it.
 */
class BugPipelineStateRepository extends \BaseMysqliRepository implements BugPipelineStateRepositoryInterface
{
    public function upsertPipelineState(string $channelId, string $messageId): void
    {
        // Monotonic: GREATEST() keeps the highest snowflake seen — the cursor only moves forward.
        $this->execute(
            'INSERT INTO `ibl_bug_pipeline_state` (channel_id, last_processed_message_id, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                 last_processed_message_id = GREATEST(last_processed_message_id, VALUES(last_processed_message_id)),
                 updated_at = NOW()',
            'ss',
            $channelId,
            $messageId
        );
    }

    /**
     * PR #4 backfill cursor. Returns the watermark snowflake as a STRING, or null on first boot.
     */
    public function findPipelineState(string $channelId): ?string
    {
        $row = $this->fetchOne(
            'SELECT last_processed_message_id FROM `ibl_bug_pipeline_state` WHERE channel_id = ? LIMIT 1',
            's',
            $channelId
        );
        if ($row === null) {
            return null;
        }
        return isset($row['last_processed_message_id']) && is_scalar($row['last_processed_message_id'])
            ? (string) $row['last_processed_message_id']
            : null;
    }
}
