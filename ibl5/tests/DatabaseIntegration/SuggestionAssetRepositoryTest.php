<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\SuggestionAssetRepository;
use TradeSuggestions\SuggestionPlayer;

/**
 * Tests SuggestionAssetRepository against real MariaDB — batched roster and pick reads.
 */
#[Group('database')]
class SuggestionAssetRepositoryTest extends DatabaseTestCase
{
    private SuggestionAssetRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SuggestionAssetRepository($this->db);
    }

    public function testRostersForTeamsGroupsByTeamAndExcludesRetired(): void
    {
        $this->insertTestPlayer(200000001, 'Suggest One', [
            'teamid' => 1, 'pos' => 'C', 'talent' => 5, 'skill' => 4, 'intangibles' => 3,
            'cy' => 2, 'cyt' => 4, 'salary_yr1' => 900, 'salary_yr2' => 1000, 'salary_yr3' => 1100, 'salary_yr4' => 1200,
        ]);
        $this->insertTestPlayer(200000002, 'Suggest Two', ['teamid' => 2]);
        $this->insertTestPlayer(200000003, 'Suggest Retired', ['teamid' => 1, 'retired' => 1]);

        $rosters = $this->repo->getRostersForTeams([1, 2]);

        $team1Pids = array_map(static fn (SuggestionPlayer $p): int => $p->pid, $rosters[1] ?? []);
        $team2Pids = array_map(static fn (SuggestionPlayer $p): int => $p->pid, $rosters[2] ?? []);

        self::assertContains(200000001, $team1Pids);
        self::assertNotContains(200000002, $team1Pids);
        self::assertNotContains(200000003, $team1Pids);
        self::assertContains(200000002, $team2Pids);
        self::assertNotContains(200000001, $team2Pids);

        $one = null;
        foreach ($rosters[1] ?? [] as $player) {
            if ($player->pid === 200000001) {
                $one = $player;
            }
        }
        self::assertNotNull($one);
        self::assertSame(5 * 2 + 4 * 2 + 3, $one->quality);
        self::assertSame(1000, $one->currentSalary);
        self::assertSame(3, $one->yearsRemaining);
        self::assertSame(1100 + 1200, $one->futureSalary);
        self::assertSame('C', $one->pos);
    }

    public function testRostersForTeamsEmptyListReturnsEmpty(): void
    {
        self::assertSame([], $this->repo->getRostersForTeams([]));
    }

    public function testPicksForTeamsFiltersYearAndRound(): void
    {
        $current = $this->insertDraftPickRow(1, 3, 2090, 1);
        $secondRound = $this->insertDraftPickRow(1, 4, 2091, 2);
        $past = $this->insertDraftPickRow(1, 5, 2088, 1);
        $thirdRound = $this->insertDraftPickRow(1, 6, 2090, 3);

        $picks = $this->repo->getPicksForTeams([1], 2090);

        $ids = array_map(static fn (array $row): int => $row['pickid'], $picks[1] ?? []);
        self::assertContains($current, $ids);
        self::assertContains($secondRound, $ids);
        self::assertNotContains($past, $ids);
        self::assertNotContains($thirdRound, $ids);

        foreach ($picks[1] ?? [] as $row) {
            if ($row['pickid'] === $current) {
                self::assertSame(['pickid' => $current, 'owner_teamid' => 1, 'teampick_teamid' => 3, 'year' => 2090, 'round' => 1], $row);
            }
        }
    }
}
