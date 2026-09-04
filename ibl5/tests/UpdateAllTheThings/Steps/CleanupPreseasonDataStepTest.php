<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use Boxscore\BoxscoreRepository;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\Steps\CleanupPreseasonDataStep;

class CleanupPreseasonDataStepTest extends TestCase
{
    private BoxscoreRepository $stubBoxscoreRepo;
    private Season $stubSeason;

    protected function setUp(): void
    {
        $this->stubBoxscoreRepo = $this->createStub(BoxscoreRepository::class);
        $this->stubSeason = $this->createStub(Season::class);
        $this->stubSeason->phase = 'HEAT';
        $this->stubSeason->beginningYear = 2026;
        $this->stubSeason->endingYear = 2027;
    }

    private function buildStep(\mysqli $db, ?BoxscoreRepository $repo = null, ?Season $season = null): CleanupPreseasonDataStep
    {
        return new CleanupPreseasonDataStep(
            $repo ?? $this->stubBoxscoreRepo,
            $season ?? $this->stubSeason,
            $db,
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = $this->createStub(\mysqli::class);
        $this->assertSame('Preseason data cleaned', $this->buildStep($stub)->getLabel());
    }

    public function testSkipsWhenPhaseIsNotHeat(): void
    {
        $seasonStub = $this->createStub(Season::class);
        $seasonStub->phase = 'REGULAR_SEASON';
        $seasonStub->beginningYear = 2026;
        $seasonStub->endingYear = 2027;

        $result = $this->buildStep($this->createStub(\mysqli::class), season: $seasonStub)->execute();

        $this->assertSame('Not HEAT phase', $result->detail);
    }

    public function testSkipsWhenNoPreseasonBoxScoresExist(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('SELECT COUNT', [['cnt' => 0]]);

        $result = $this->buildStep($mockDb)->execute();

        $this->assertSame('No preseason data to clean', $result->detail);
    }

    public function testCleansPreseasonDataWhenHeatPhaseWithData(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('SELECT COUNT', [['cnt' => 1]]);

        $mockBoxscoreRepo = $this->createMock(BoxscoreRepository::class);
        $mockBoxscoreRepo->expects($this->once())
            ->method('deletePreseasonBoxScores')
            ->with(2026);

        $result = $this->buildStep($mockDb, $mockBoxscoreRepo)->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('box scores', $result->detail);

        $log = $mockDb->getOperationLog();
        $this->assertNotEmpty(array_filter($log, fn($entry) => str_contains((string) $entry, 'ibl_sim_dates')));
        $this->assertNotEmpty(array_filter($log, fn($entry) => str_contains((string) $entry, 'ibl_team_awards')));
    }
}
