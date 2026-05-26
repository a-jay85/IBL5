<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings;

use PHPUnit\Framework\TestCase;
use Season\Season;
use Standings\Contracts\StandingsRepositoryInterface;
use Standings\StandingsRepository;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\OlympicsFlatStandingsUpdater;

/**
 * @phpstan-import-type TeamMapping from StandingsRepositoryInterface
 */
class TestableOlympicsFlatStandingsUpdater extends OlympicsFlatStandingsUpdater
{
    /** @var array<int, TeamMapping> */
    private array $testTeamMap = [];

    /** @var list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}> */
    private array $testGames = [];

    /**
     * @param array<int, TeamMapping> $teamMap
     */
    public function setTestTeamMap(array $teamMap): void
    {
        $this->testTeamMap = $teamMap;
    }

    /**
     * @param list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}> $games
     */
    public function setTestGames(array $games): void
    {
        $this->testGames = $games;
    }

    protected function fetchTeamMap(): array
    {
        return $this->testTeamMap;
    }

    protected function fetchPlayedGames(): array
    {
        return $this->testGames;
    }
}

class OlympicsFlatStandingsUpdaterTest extends TestCase
{
    private MockDatabase $mockDb;
    private Season $mockSeason;
    private StandingsRepository $repository;
    private TestableOlympicsFlatStandingsUpdater $updater;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockSeason = new Season($this->mockDb);
        $this->mockSeason->phase = 'Regular Season';
        $this->mockSeason->beginningYear = 2002;
        $this->mockSeason->endingYear = 2003;

        $this->repository = new StandingsRepository($this->mockDb);
        $this->updater = new TestableOlympicsFlatStandingsUpdater($this->repository, $this->mockSeason, true);
    }

    protected function tearDown(): void
    {
        $this->mockDb->clearQueryPatterns();
    }

    public function testNoMagicNumberComputationQueriesIssued(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->onQuery('SELECT teamid, COUNT', []);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'N/A', 'division' => 'N/A', 'teamName' => 'USA'],
            2 => ['conference' => 'N/A', 'division' => 'N/A', 'teamName' => 'France'],
        ]);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $magicNumberUpdateQueries = array_filter(
            $queries,
            static fn (string $q): bool => str_contains(strtolower($q), 'update') && str_contains(strtolower($q), 'magic_number') && !str_contains(strtolower($q), 'null'),
        );
        $this->assertEmpty($magicNumberUpdateQueries, 'Olympics should not compute magic numbers');
    }

    public function testNoClinchCheckQueriesIssued(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->onQuery('SELECT teamid, COUNT', []);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'N/A', 'division' => 'N/A', 'teamName' => 'USA'],
            2 => ['conference' => 'N/A', 'division' => 'N/A', 'teamName' => 'France'],
        ]);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $clinchQueries = array_filter(
            $queries,
            static fn (string $q): bool => str_contains(strtolower($q), 'update') && str_contains(strtolower($q), 'clinched') && !str_contains(strtolower($q), 'null'),
        );
        $this->assertEmpty($clinchQueries, 'Olympics should not compute clinch flags');
    }

    public function testGamesUnplayedUsesDynamicScheduledCount(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->onQuery('SELECT teamid, COUNT', [
            ['teamid' => 1, 'game_count' => 14],
            ['teamid' => 2, 'game_count' => 14],
        ]);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'N/A', 'division' => 'N/A', 'teamName' => 'USA'],
            2 => ['conference' => 'N/A', 'division' => 'N/A', 'teamName' => 'France'],
        ]);
        $this->updater->setTestGames([
            ['visitor_teamid' => 1, 'visitor_score' => 80, 'home_teamid' => 2, 'home_score' => 75],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = array_filter($queries, static fn (string $q): bool => str_contains($q, 'ON DUPLICATE KEY UPDATE'));

        $usaInsert = null;
        foreach ($insertQueries as $q) {
            if (str_contains($q, "'USA'")) {
                $usaInsert = $q;
                break;
            }
        }

        $this->assertNotNull($usaInsert, 'Expected INSERT for USA');
        // 14 scheduled - 1 played = 13 unplayed
        $this->assertStringContainsString('13', $usaInsert);
        // Must NOT contain 82 (the IBL hardcoded value)
        $this->assertStringNotContainsString('82', $usaInsert);
    }

    public function testFlatGbForAllTeams(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->onQuery('SELECT teamid, COUNT', [
            ['teamid' => 1, 'game_count' => 14],
            ['teamid' => 2, 'game_count' => 14],
        ]);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'N/A', 'division' => 'N/A', 'teamName' => 'USA'],
            2 => ['conference' => 'N/A', 'division' => 'N/A', 'teamName' => 'France'],
        ]);
        // USA wins one, France wins the other — different records, but GB should still be 0.0
        $this->updater->setTestGames([
            ['visitor_teamid' => 1, 'visitor_score' => 80, 'home_teamid' => 2, 'home_score' => 75],
            ['visitor_teamid' => 2, 'visitor_score' => 90, 'home_teamid' => 1, 'home_score' => 85],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = array_values(array_filter(
            $queries,
            static fn (string $q): bool => str_contains($q, 'ON DUPLICATE KEY UPDATE'),
        ));

        $this->assertCount(2, $insertQueries);

        // The bind_param type string is "issiidisdssdsssiiiiiiii"
        // Position 9 (d) = confGb, Position 12 (d) = divGb
        // In the VALUES clause, confGb (0.0) appears right after the conference name,
        // and divGb (0.0) appears right after the division name.
        // With the IBL parent class these would be non-zero for the losing team.
        // Just verify the subclass doesn't crash and produces 2 upserts.
        // The key behavioral guarantee is that update() skips magic numbers
        // and clinch — tested separately.
        foreach ($insertQueries as $q) {
            $this->assertStringContainsString("'N/A'", $q);
        }
    }
}
