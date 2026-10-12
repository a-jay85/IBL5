<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Season\Season;
use TeamOffDefStats\TeamOffDefStatsRepository;

#[Group('database')]
class BoxScoreGeneratedColumnsTest extends DatabaseTestCase
{
    private const TEST_PID = 200000188;

    protected function setUp(): void
    {
        parent::setUp();
        $this->insertTestPlayer(self::TEST_PID, 'TestPlayer');
    }

    public function testSeptemberPlayerRowGetsPreseasonGameTypeAndEndingSeasonYear(): void
    {
        $id = $this->insertPlayerBoxscoreRow('8887-09-15', self::TEST_PID, 'TestPlayer', 'PG', 2, 1, 1);

        $row = $this->db->query("SELECT game_type, season_year FROM ibl_box_scores WHERE id = $id")->fetch_assoc();

        $this->assertSame(Season::IBL_PRESEASON_GAME_TYPE, (int) $row['game_type']);
        $this->assertSame(8888, (int) $row['season_year']);
    }

    public function testSeptemberTeamRowGetsPreseasonGameTypeAndEndingSeasonYear(): void
    {
        $id = $this->insertTeamBoxscoreRow('8887-09-15', 'Metros', 1, 2, 1);

        $row = $this->db->query("SELECT game_type, season_year FROM ibl_box_scores_teams WHERE id = $id")->fetch_assoc();

        $this->assertSame(Season::IBL_PRESEASON_GAME_TYPE, (int) $row['game_type']);
        $this->assertSame(8888, (int) $row['season_year']);
    }

    /** @return array<string, array{string, int, int}> */
    public static function nonSeptemberDateProvider(): array
    {
        return [
            'June playoffs'    => ['8888-06-10', 2, 8888],
            'October HEAT'     => ['8887-10-15', 3, 8888],
            'November regular' => ['8887-11-15', 1, 8888],
            'August boundary'  => ['8887-08-15', 1, 8887],
        ];
    }

    #[DataProvider('nonSeptemberDateProvider')]
    public function testNonSeptemberTeamRowTagsAreUnchanged(string $date, int $expectedType, int $expectedSeasonYear): void
    {
        $id = $this->insertTeamBoxscoreRow($date, 'Metros', 1, 2, 1);

        $row = $this->db->query("SELECT game_type, season_year FROM ibl_box_scores_teams WHERE id = $id")->fetch_assoc();

        $this->assertSame($expectedType, (int) $row['game_type']);
        $this->assertSame($expectedSeasonYear, (int) $row['season_year']);
    }

    public function testSeptemberGameIsExcludedFromTeamWinLoss(): void
    {
        $this->insertTeamBoxscoreRow('8887-09-15', 'Metros', 1, 2, 1);

        $count = $this->db->query("SELECT COUNT(*) AS cnt FROM ibl_team_win_loss WHERE year IN (8887, 8888)")->fetch_assoc();
        $this->assertSame(0, (int) $count['cnt']);

        // Positive control: a November row must appear in the view.
        $this->insertTeamBoxscoreRow('8887-11-15', 'Metros', 1, 2, 1);
        $count2 = $this->db->query("SELECT COUNT(*) AS cnt FROM ibl_team_win_loss WHERE year = 8888")->fetch_assoc();
        $this->assertGreaterThanOrEqual(1, (int) $count2['cnt']);
    }

    public function testPreseasonPhaseSurfacesSeptemberRowsInTeamOffDefStats(): void
    {
        $this->insertFranchiseSeasonRow(1, 8888, 'Metros');
        $this->insertTeamBoxscoreRow('8887-09-15', 'Metros', 1, 2, 1);

        $repo = new TeamOffDefStatsRepository($this->db);

        $preseasonRows = $repo->getAllTeamStats(8888, TeamOffDefStatsRepository::gameTypesForPhase('Preseason'));
        $preseasonRow = array_filter($preseasonRows, static fn (array $r): bool => (int) $r['teamid'] === 1);
        $preseasonRow = array_values($preseasonRow)[0] ?? null;
        $this->assertNotNull($preseasonRow, 'Team 1 row missing from Preseason stats');
        $this->assertSame(1, (int) ($preseasonRow['offense_games'] ?? 0));

        $regularRows = $repo->getAllTeamStats(8888, [1]);
        $regularRow = array_filter($regularRows, static fn (array $r): bool => (int) $r['teamid'] === 1);
        $regularRow = array_values($regularRow)[0] ?? null;
        $this->assertTrue(
            $regularRow === null || (int) ($regularRow['offense_games'] ?? 0) === 0,
            'September game must not appear in regular-season stats'
        );
    }
}
