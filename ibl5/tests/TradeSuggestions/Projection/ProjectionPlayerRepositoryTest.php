<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Projection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use TradeSuggestions\ProductionSource;
use TradeSuggestions\Projection\ProjectionPlayerRepository;
use TradeSuggestions\Projection\Rating;

final class ProjectionPlayerRepositoryTest extends TestCase
{
    private MockDatabase $db;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
    }

    public function testGroupsPlayersByTeamId(): void
    {
        $this->db->onQuery('FROM ibl_plr', [
            self::row(['pid' => 11, 'teamid' => 1]),
            self::row(['pid' => 12, 'teamid' => 1]),
            self::row(['pid' => 21, 'teamid' => 2]),
        ]);
        $this->db->onQuery('FROM ibl_hist', []);

        $players = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 2026);

        self::assertSame([1, 2], array_keys($players));
        self::assertSame([11, 12], array_map(static fn ($p): int => $p->pid, $players[1]));
        self::assertSame([21], array_map(static fn ($p): int => $p->pid, $players[2]));
    }

    public function testEffectiveCyAdvancesInOffseason(): void
    {
        $this->db->onQuery('FROM ibl_plr', [self::row(['cy' => 2, 'cyt' => 4])]);
        $this->db->onQuery('FROM ibl_hist', []);
        $repo = new ProjectionPlayerRepository($this->db);

        self::assertSame(3, $repo->getLeaguePlayers(true, 2026)[1][0]->effectiveCy);
        self::assertSame(2, $repo->getLeaguePlayers(false, 2026)[1][0]->effectiveCy);
    }

    public function testUsesLastSeasonLineWhenCurrentMinutesUnderFourHundred(): void
    {
        $this->db->onQuery('FROM ibl_plr', [self::row(['pid' => 7, 'stats_min' => 300, 'stats_pf' => 9])]);
        $this->db->onQuery('FROM ibl_hist', [[
            'pid' => 7, 'games' => 70, 'minutes' => 2000, 'fgm' => 400, 'fga' => 850, 'ftm' => 150,
            'fta' => 190, 'tgm' => 60, 'tga' => 170, 'reb' => 300, 'ast' => 250, 'stl' => 70,
            'blk' => 30, 'tvr' => 120, 'pf' => 140, 'pts' => 1010,
        ]]);

        $player = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 2026)[1][0];

        self::assertNotNull($player->production);
        self::assertSame(ProductionSource::LastSeason, $player->production->source);
        self::assertSame(2000, $player->production->minutes);
        self::assertSame(140, $player->productionFouls);
    }

    public function testRatingSetCarriesAllTwentyOneKeysFromRow(): void
    {
        $overrides = [];
        foreach (Rating::ALL as $i => $key) {
            $overrides[$key] = 30 + $i;
        }
        $this->db->onQuery('FROM ibl_plr', [self::row($overrides)]);
        $this->db->onQuery('FROM ibl_hist', []);

        $player = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 2026)[1][0];

        self::assertCount(21, $player->ratings->toArray());
        foreach (Rating::ALL as $i => $key) {
            self::assertSame((float) (30 + $i), $player->ratings->get($key), $key);
            self::assertMatchesRegularExpression('/\b' . $key . '\b/', $this->plrSelect());
        }
    }

    public function testSelectNeverNamesPeakOrStamina(): void
    {
        $this->db->onQuery('FROM ibl_plr', [self::row()]);
        $this->db->onQuery('FROM ibl_hist', []);

        (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 2026);

        self::assertDoesNotMatchRegularExpression('/\b(peak|stamina)\b/i', $this->plrSelect());
    }

    public function testSelectUsesPreparedFetchAllWithNoInterpolatedInput(): void
    {
        $this->db->onQuery('FROM ibl_plr', [self::row()]);
        $this->db->onQuery('FROM ibl_hist', []);

        (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(true, 2025);

        $prepared = $this->db->getPreparedQueries();
        $histQueries = array_values(array_filter($prepared, static fn (string $q): bool => str_contains($q, 'ibl_hist')));
        self::assertCount(1, $histQueries);
        self::assertStringContainsString('year = ?', $histQueries[0]);
        self::assertStringNotContainsString('?', $this->plrSelect());
        self::assertStringNotContainsString('2025', $this->plrSelect());
    }

    public function testRatingScaleReadsLeagueMaxPerKey(): void
    {
        $max = [];
        foreach (Rating::ALL as $key) {
            $max[$key] = 80;
        }
        $max['r_fga'] = 95;
        $this->db->onQuery('MAX\(', [$max]);

        $scale = (new ProjectionPlayerRepository($this->db))->getRatingScale();

        self::assertSame(95.0, $scale->max('r_fga'));
        self::assertSame(80.0, $scale->max('oo'));
        $sql = implode("\n", $this->db->getPreparedQueries());
        foreach (Rating::ALL as $key) {
            self::assertMatchesRegularExpression('/MAX\(`?' . $key . '`?\)/', $sql, $key);
        }
    }

    public function testSalaryByYearCarriesAllSixYearsAndDepthKeepsEverySlot(): void
    {
        $this->db->onQuery('FROM ibl_plr', [self::row([
            'salary_yr1' => 101, 'salary_yr2' => 102, 'salary_yr3' => 103, 'salary_yr4' => 104,
            'salary_yr5' => 105, 'salary_yr6' => 106,
            'pg_depth' => 1, 'dc_pg_depth' => 2,
        ])]);
        $this->db->onQuery('FROM ibl_hist', []);

        $player = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 2026)[1][0];

        self::assertSame([1 => 101, 2 => 102, 3 => 103, 4 => 104, 5 => 105, 6 => 106], $player->salaryByYear);
        self::assertSame(['PG' => 1, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0], $player->depth);
        self::assertSame(['PG' => 2, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0], $player->dcDepth);
    }

    /**
     * @return array<string, array{array<string, mixed>, bool, bool}>
     */
    public static function tradeableProvider(): array
    {
        return [
            'ordinary contract' => [[], false, true],
            'zero salary this year' => [['salary_yr1' => 0], false, false],
            'unsigned (cy 0)' => [['cy' => 0], false, false],
            'unsigned (cy 0) in offseason' => [['cy' => 0], true, false],
            'cyt already behind cy still has a year' => [['cy' => 3, 'cyt' => 2, 'salary_yr3' => 500], false, true],
            'offseason past year six' => [['cy' => 6, 'cyt' => 6, 'salary_yr6' => 500], true, false],
            'on the waivers boundary' => [['ordinal' => 960], false, true],
            'on waivers' => [['ordinal' => 961], false, false],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('tradeableProvider')]
    public function testTradeableMirrorsTheValidator(array $overrides, bool $advances, bool $expected): void
    {
        $this->db->onQuery('FROM ibl_plr', [self::row($overrides)]);
        $this->db->onQuery('FROM ibl_hist', []);

        $player = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers($advances, 2026)[1][0];

        self::assertSame($expected, $player->tradeable);
    }

    public function testEveryPlayerGetsTheirOwnLastSeasonLine(): void
    {
        $this->db->onQuery('FROM ibl_plr', [
            self::row(['pid' => 7, 'stats_min' => 100]),
            self::row(['pid' => 8, 'stats_min' => 100]),
        ]);
        $this->db->onQuery('FROM ibl_hist', [
            self::histRow(['pid' => 7, 'minutes' => 1500]),
            self::histRow(['pid' => 8, 'minutes' => 1600]),
        ]);

        $players = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 2026)[1];

        self::assertSame(1500, $players[0]->production?->minutes);
        self::assertSame(1600, $players[1]->production?->minutes);
    }

    public function testNoProductionMeansZeroFoulsEvenWithAThinLastSeasonLine(): void
    {
        $this->db->onQuery('FROM ibl_plr', [self::row(['pid' => 7, 'stats_min' => 100, 'stats_pf' => 9])]);
        $this->db->onQuery('FROM ibl_hist', [self::histRow(['pid' => 7, 'minutes' => 200, 'pf' => 33])]);

        $player = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 2026)[1][0];

        self::assertNull($player->production);
        self::assertSame(0, $player->productionFouls);
    }

    public function testNoProductionAndNoHistoryMeansZeroFouls(): void
    {
        $this->db->onQuery('FROM ibl_plr', [self::row(['stats_min' => 100, 'stats_pf' => 9])]);
        $this->db->onQuery('FROM ibl_hist', []);

        $player = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 2026)[1][0];

        self::assertNull($player->production);
        self::assertSame(0, $player->productionFouls);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function histRow(array $overrides = []): array
    {
        return array_merge([
            'pid' => 7, 'games' => 70, 'minutes' => 2000, 'fgm' => 400, 'fga' => 850, 'ftm' => 150,
            'fta' => 190, 'tgm' => 60, 'tga' => 170, 'reb' => 300, 'ast' => 250, 'stl' => 70,
            'blk' => 30, 'tvr' => 120, 'pf' => 140, 'pts' => 1010,
        ], $overrides);
    }

    private function plrSelect(): string
    {
        foreach ($this->db->getPreparedQueries() as $query) {
            if (str_contains($query, 'ibl_plr')) {
                return $query;
            }
        }
        self::fail('No ibl_plr query was prepared.');
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function row(array $overrides = []): array
    {
        $row = [
            'pid' => 1, 'name' => 'Test Player', 'pos' => 'PG', 'age' => 26, 'teamid' => 1, 'injured' => 0,
            'cy' => 1, 'cyt' => 3, 'ordinal' => 5, 'exp' => 4, 'bird' => 1, 'talent' => 50,
            'loyalty' => 1, 'winner' => 1, 'tradition' => 1, 'security' => 1, 'playing_time' => 1,
            'draftyear' => 2020, 'draftround' => 1, 'draftpickno' => 10,
            'pg_depth' => 1, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0,
            'dc_pg_depth' => 1, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0, 'dc_pf_depth' => 0, 'dc_c_depth' => 0,
            'dc_minutes' => 30,
            'stats_gm' => 40, 'stats_min' => 1200, 'stats_fgm' => 200, 'stats_fga' => 420, 'stats_ftm' => 80,
            'stats_fta' => 100, 'stats_3gm' => 40, 'stats_3ga' => 110, 'stats_orb' => 40, 'stats_drb' => 160,
            'stats_ast' => 120, 'stats_stl' => 40, 'stats_blk' => 20, 'stats_tvr' => 60, 'stats_pf' => 70,
            'salary_yr1' => 500, 'salary_yr2' => 500, 'salary_yr3' => 500, 'salary_yr4' => 0,
            'salary_yr5' => 0, 'salary_yr6' => 0,
        ];
        foreach (Rating::ALL as $key) {
            $row[$key] = 50;
        }

        return array_merge($row, $overrides);
    }
}
