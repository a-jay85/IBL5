<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\AutoSeedOlympicsTeamInfoStep;

class AutoSeedOlympicsTeamInfoStepTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    protected function tearDown(): void
    {
        $this->mockDb->clearQueryPatterns();
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new AutoSeedOlympicsTeamInfoStep($this->mockDb, 2003, null);
        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testFirstUploadWithoutParamFails(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['cnt' => 0]]);

        $step = new AutoSeedOlympicsTeamInfoStep($this->mockDb, 2003, null);
        $result = $step->execute();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('real_team_count', $result->errorMessage);
    }

    public function testFirstUploadWithParamSeedsAllSlots(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['cnt' => 0]]);
        $this->mockDb->onQuery('SELECT team_slot', [
            ['team_slot' => 1, 'team_name' => 'USA'],
            ['team_slot' => 2, 'team_name' => 'France'],
            ['team_slot' => 3, 'team_name' => 'Placeholder3'],
        ]);
        $this->mockDb->setReturnTrue(true);

        $step = new AutoSeedOlympicsTeamInfoStep($this->mockDb, 2003, 2);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('2 real', $result->detail);
        $this->assertStringContainsString('1 placeholder', $result->detail);
    }

    public function testReUploadPreservesExistingRealCount(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['cnt' => 8]]);
        $this->mockDb->onQuery('SELECT team_slot', [
            ['team_slot' => 1, 'team_name' => 'USA'],
            ['team_slot' => 2, 'team_name' => 'France'],
        ]);
        $this->mockDb->setReturnTrue(true);

        $step = new AutoSeedOlympicsTeamInfoStep($this->mockDb, 2003, null);
        $result = $step->execute();

        $this->assertTrue($result->success);
    }

    public function testNoLeagueConfigRowsReturnsFailure(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['cnt' => 0]]);
        $this->mockDb->onQuery('SELECT team_slot', []);

        $step = new AutoSeedOlympicsTeamInfoStep($this->mockDb, 2003, 8);
        $result = $step->execute();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No league config', $result->errorMessage);
    }
}
