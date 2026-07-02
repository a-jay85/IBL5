<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Season\Season;
use BasketballStats\TeamStatsCalculator;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\PowerRankingsUpdater;

class PowerRankingsUpdaterTest extends TestCase
{
    private MockDatabase $db;

    /**
     * @param array{wins:int,losses:int,homeWins:int,homeLosses:int,awayWins:int,awayLosses:int,winPoints:int,lossPoints:int,winsInLast10Games:int,lossesInLast10Games:int,streak:int,streakType:string} $teamStats
     */
    private function buildUpdater(array $teamStats): PowerRankingsUpdater
    {
        $this->db = new MockDatabase();
        $this->db->onQuery('streak_type, streak', [
            ['teamid' => 5, 'streak_type' => $teamStats['streakType'], 'streak' => $teamStats['streak']],
        ]);
        $this->db->onQuery('team_name FROM ibl_team_info', [
            ['teamid' => 5, 'team_name' => 'Alpha'],
        ]);

        $season = self::createStub(Season::class);
        $season->phase = 'Regular Season';
        $season->beginningYear = 2024;
        $season->endingYear = 2025;

        $calc = self::createStub(TeamStatsCalculator::class);
        $calc->method('calculate')->willReturn($teamStats);

        return new PowerRankingsUpdater($this->db, $season, $calc, null);
    }

    private function runUpdate(PowerRankingsUpdater $u): void
    {
        ob_start();
        $u->update();
        ob_end_clean();
    }

    private function assertExecutedQueryContains(string $needle): void
    {
        $hit = array_filter(
            $this->db->getExecutedQueries(),
            static fn (string $q): bool => str_contains($q, $needle),
        );
        $this->assertNotEmpty($hit, "No executed query contained: {$needle}");
    }

    public function testUpdateWritesComputedRankingToPowerTable(): void
    {
        $stats = [
            'wins' => 10, 'losses' => 5,
            'homeWins' => 5, 'homeLosses' => 2,
            'awayWins' => 5, 'awayLosses' => 3,
            'winPoints' => 70, 'lossPoints' => 15,
            'winsInLast10Games' => 7, 'lossesInLast10Games' => 3,
            'streak' => 4, 'streakType' => 'W',
        ];

        $u = $this->buildUpdater($stats);
        $this->runUpdate($u);

        $this->assertExecutedQueryContains('UPDATE ibl_power');
        $this->assertExecutedQueryContains('ranking = 80');
    }

    public function testRankingIsZeroWhenNoPointsAccumulated(): void
    {
        $stats = [
            'wins' => 0, 'losses' => 0,
            'homeWins' => 0, 'homeLosses' => 0,
            'awayWins' => 0, 'awayLosses' => 0,
            'winPoints' => 0, 'lossPoints' => 0,
            'winsInLast10Games' => 0, 'lossesInLast10Games' => 0,
            'streak' => 0, 'streakType' => '-',
        ];

        $u = $this->buildUpdater($stats);
        $this->runUpdate($u);

        $this->assertExecutedQueryContains('ranking = 0');
    }
}
