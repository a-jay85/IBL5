<?php

declare(strict_types=1);

namespace BugPipeline\Contracts;

/**
 * Reporter tech-level profile contract for the Discord bug pipeline
 * (`ibl_bug_reporter_profile`). Split out of {@see \BugPipeline\BugReportRepository}
 * (backlog 1.26); the facade delegates to it.
 */
interface BugReporterProfileRepositoryInterface
{
    /**
     * @see \BugPipeline\BugReporterProfileRepository::upsertReporterProfile()
     */
    public function upsertReporterProfile(string $discordId, string $techLevel): void;

    /**
     * @see \BugPipeline\BugReporterProfileRepository::getReporterTechLevel()
     */
    public function getReporterTechLevel(string $discordId): ?string;
}
