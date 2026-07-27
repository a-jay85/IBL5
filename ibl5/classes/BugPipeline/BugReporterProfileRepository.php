<?php

declare(strict_types=1);

namespace BugPipeline;

use BugPipeline\Contracts\BugReporterProfileRepositoryInterface;

/**
 * Reporter tech-level profile store for the Discord bug pipeline
 * (`ibl_bug_reporter_profile`). Split out of {@see BugReportRepository}
 * (backlog 1.26); the facade delegates to it.
 */
class BugReporterProfileRepository extends \BaseMysqliRepository implements BugReporterProfileRepositoryInterface
{
    public function upsertReporterProfile(string $discordId, string $techLevel): void
    {
        // ON DUPLICATE KEY UPDATE => affected-rows is 0|1|2; success is "no exception", not "=== 1".
        $this->execute(
            'INSERT INTO `ibl_bug_reporter_profile` (discord_author_id, tech_level, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE tech_level = VALUES(tech_level), updated_at = NOW()',
            'ss',
            $discordId,
            $techLevel
        );
    }

    public function getReporterTechLevel(string $discordId): ?string
    {
        $row = $this->fetchOne(
            'SELECT tech_level FROM `ibl_bug_reporter_profile` WHERE discord_author_id = ? LIMIT 1',
            's',
            $discordId
        );
        if ($row === null) {
            return null;
        }
        /** @var string $techLevel */
        $techLevel = $row['tech_level'];
        return $techLevel;
    }
}
