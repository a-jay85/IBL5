<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\BugPipeline;

use BugPipeline\BugReporterProfileRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class BugReporterProfileRepositoryTest extends DatabaseTestCase
{
    private BugReporterProfileRepository $repo;

    // Representative snowflake fixture — real Discord IDs are 17–19 digits
    private const AUTHOR = '100000000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BugReporterProfileRepository($this->db);
    }

    // ── upsertReporterProfile / getReporterTechLevel ───────────────────────────

    public function testGetReporterTechLevelReturnsNullWhenMissing(): void
    {
        self::assertNull($this->repo->getReporterTechLevel('999999999999999999'));
    }

    public function testUpsertReporterProfileInsertsAndUpdates(): void
    {
        $this->repo->upsertReporterProfile(self::AUTHOR, 'technical');
        self::assertSame('technical', $this->repo->getReporterTechLevel(self::AUTHOR));

        // Idempotent update
        $this->repo->upsertReporterProfile(self::AUTHOR, 'nontechnical');
        self::assertSame('nontechnical', $this->repo->getReporterTechLevel(self::AUTHOR));
    }
}
