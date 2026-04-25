<?php

declare(strict_types=1);

namespace Tests\Updater;

use Boxscore\BoxscoreRepository;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Tests\Integration\Mocks\MockDatabase;
use Updater\Steps\CleanupPreseasonDataStep;

/**
 * @covers \Updater\Steps\CleanupPreseasonDataStep
 */
class CleanupPreseasonDataStepTest extends TestCase
{
    private \MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
    }

    public function testSkipsWhenPhaseIsPreseason(): void
    {
        $season = $this->buildSeason('Preseason');
        $step = $this->buildStep($season);

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Not Regular Season', $result->detail);
    }

    public function testSkipsWhenPhaseIsPlayoffs(): void
    {
        $season = $this->buildSeason('Playoffs');
        $step = $this->buildStep($season);

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Not Regular Season', $result->detail);
    }

    public function testSkipsWhenSimDatesExist(): void
    {
        $season = $this->buildSeason('Regular Season');

        $this->mockDb->onQuery('SELECT COUNT.*ibl_sim_dates', [['cnt' => 3]]);

        $step = $this->buildStep($season);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Not first Regular Season sim', $result->detail);
    }

    public function testSkipsWhenNoPreseasonBoxScores(): void
    {
        $season = $this->buildSeason('Regular Season');

        $this->mockDb->onQuery('SELECT COUNT.*ibl_sim_dates', [['cnt' => 0]]);
        $this->mockDb->onQuery('SELECT COUNT.*ibl_box_scores_teams', [['cnt' => 0]]);

        $step = $this->buildStep($season);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No preseason data to clean', $result->detail);
    }

    public function testCleansPreseasonDataOnFirstRegularSeasonSim(): void
    {
        $season = $this->buildSeason('Regular Season');

        $this->mockDb->onQuery('SELECT COUNT.*ibl_sim_dates', [['cnt' => 0]]);
        $this->mockDb->onQuery('SELECT COUNT.*ibl_box_scores_teams', [['cnt' => 42]]);
        $this->mockDb->setReturnTrue(true);

        $step = $this->buildStep($season);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Cleaned:', $result->detail);
        $this->assertStringContainsString('box scores', $result->detail);
        $this->assertStringContainsString('team awards', $result->detail);
    }

    public function testGetLabelReturnsExpectedString(): void
    {
        $season = $this->buildSeason('Regular Season');
        $step = $this->buildStep($season);

        $this->assertSame('Preseason data cleaned', $step->getLabel());
    }

    /**
     * @return \Tests\Integration\Mocks\Season
     */
    private function buildSeason(string $phase): \Tests\Integration\Mocks\Season
    {
        $season = new \Tests\Integration\Mocks\Season($this->mockDb);
        $season->phase = $phase;
        $season->beginningYear = 2024;
        $season->endingYear = 2025;
        $season->lastSimNumber = 0;
        $season->lastSimStartDate = '';
        $season->lastSimEndDate = '';

        return $season;
    }

    private function buildStep(\Tests\Integration\Mocks\Season $season): CleanupPreseasonDataStep
    {
        /** @var Season $season */
        $repo = new BoxscoreRepository($this->mockDb);

        return new CleanupPreseasonDataStep($repo, $season, $this->mockDb);
    }
}
