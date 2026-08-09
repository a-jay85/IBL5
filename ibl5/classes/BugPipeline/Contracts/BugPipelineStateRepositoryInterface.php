<?php

declare(strict_types=1);

namespace BugPipeline\Contracts;

/**
 * Per-channel backfill watermark contract for the Discord bug pipeline
 * (`ibl_bug_pipeline_state`). Split out of {@see \BugPipeline\BugReportRepository}
 * (backlog 1.26); the facade delegates to it.
 */
interface BugPipelineStateRepositoryInterface
{
    /**
     * @see \BugPipeline\BugPipelineStateRepository::upsertPipelineState()
     */
    public function upsertPipelineState(string $channelId, string $messageId): void;

    /**
     * @see \BugPipeline\BugPipelineStateRepository::findPipelineState()
     */
    public function findPipelineState(string $channelId): ?string;
}
