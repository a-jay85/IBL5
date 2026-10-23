<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\ModeInputs;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeGuesser;

class TeamModeGuesserTest extends TestCase
{
    private TeamModeGuesser $guesser;

    protected function setUp(): void
    {
        $this->guesser = new TeamModeGuesser();
    }

    public function testWinningOlderLockedTeamGuessesWinNow(): void
    {
        $guess = $this->guesser->guess(self::inputs(wins: 30, losses: 10, priorWins: 55, priorLosses: 27, age: 30.0, locked: 0.8));

        self::assertSame(TeamMode::WinNow, $guess->mode);
        self::assertGreaterThan(0.0, $guess->score);
    }

    public function testLosingYoungExpiringTeamGuessesRebuild(): void
    {
        $guess = $this->guesser->guess(self::inputs(wins: 10, losses: 30, priorWins: 20, priorLosses: 62, age: 23.0, locked: 0.1));

        self::assertSame(TeamMode::Rebuild, $guess->mode);
        self::assertLessThan(0.0, $guess->score);
    }

    /**
     * A few games in, last season still carries the record: a 4-0 start does not
     * turn a 10-72 team into a contender, and a young 68-14 team is not a rebuild.
     */
    public function testEarlySeasonLeansOnLastSeason(): void
    {
        $lastPlace = $this->guesser->guess(self::inputs(wins: 4, losses: 0, priorWins: 10, priorLosses: 72, age: 30.0));
        $champion = $this->guesser->guess(self::inputs(wins: 0, losses: 4, priorWins: 68, priorLosses: 14, age: 23.0));

        self::assertSame(TeamMode::Rebuild, $lastPlace->mode);
        self::assertSame(TeamMode::WinNow, $champion->mode);
        // 4 + 20 * 10/82 wins over 24 games.
        self::assertEqualsWithDelta((4 + 20 * 10 / 82) / 24, $lastPlace->recordPct, 1e-9);
    }

    /**
     * Same roster, opposite records: the record alone flips the mode.
     */
    public function testRecordDecidesForAMidTableRoster(): void
    {
        $winning = $this->guesser->guess(self::inputs(wins: 30, losses: 10, priorWins: 50, priorLosses: 32));
        $losing = $this->guesser->guess(self::inputs(wins: 10, losses: 30, priorWins: 32, priorLosses: 50));

        self::assertSame(TeamMode::WinNow, $winning->mode);
        self::assertSame(TeamMode::Rebuild, $losing->mode);
    }

    public function testGuessKeepsItsInputs(): void
    {
        $inputs = self::inputs(wins: 1, losses: 2, priorWins: 3, priorLosses: 4);

        self::assertSame($inputs, $this->guesser->guess($inputs)->inputs);
    }

    /**
     * The v1 guess called 26 of 28 prod teams Rebuild. A league with a normal
     * spread of records and rosters must come out split.
     */
    public function testLeagueOfMixedTeamsGetsBothModes(): void
    {
        $assets = [];
        $current = [];
        $prior = [];
        for ($tid = 1; $tid <= 10; $tid++) {
            $players = [];
            for ($i = 0; $i < 10; $i++) {
                $players[] = self::player($tid * 100 + $i, 26, 2);
            }
            $assets[$tid] = new TeamAssets($tid, $players, []);
            $current[$tid] = ['wins' => 2, 'losses' => 2];
            $prior[$tid] = ['wins' => 20 + 4 * $tid, 'losses' => 62 - 4 * $tid];
        }

        $modes = [];
        foreach (TeamModeGuesser::inputsForLeague($assets, $current, $prior) as $tid => $inputs) {
            $modes[$tid] = $this->guesser->guess($inputs)->mode;
        }

        $counts = array_count_values(array_map(static fn (TeamMode $m): string => $m->name, $modes));
        self::assertGreaterThanOrEqual(3, $counts[TeamMode::WinNow->name] ?? 0);
        self::assertGreaterThanOrEqual(3, $counts[TeamMode::Rebuild->name] ?? 0);
        self::assertSame(TeamMode::WinNow, $modes[10]);
        self::assertSame(TeamMode::Rebuild, $modes[1]);
    }

    public function testInputsForLeagueReadsTopEightOnly(): void
    {
        $players = [
            self::player(1, 30, 3, dcMinutes: 40),
            self::player(2, 20, 1, dcMinutes: 30),
        ];
        for ($i = 3; $i <= 8; $i++) {
            $players[] = self::player($i, 25, 2, dcMinutes: 20);
        }
        // Ninth man: outside the rotation, so his age and minutes must not count.
        $players[] = self::player(9, 40, 5, dcMinutes: 10);
        $assets = [1 => new TeamAssets(1, $players, [])];

        $inputs = TeamModeGuesser::inputsForLeague($assets, [1 => ['wins' => 3, 'losses' => 1]], [1 => ['wins' => 50, 'losses' => 32]])[1];

        self::assertSame(3, $inputs->wins);
        self::assertSame(1, $inputs->losses);
        self::assertSame(50, $inputs->priorWins);
        self::assertSame(32, $inputs->priorLosses);
        self::assertEqualsWithDelta((30 + 20 + 6 * 25) / 8, $inputs->rotationAge, 1e-9);
        // Everyone in the top eight but the 30-minute man is signed 2+ years.
        self::assertEqualsWithDelta(160 / 190, $inputs->lockedShare, 1e-9);
    }

    public function testRotationOrdersByDepthChartMinutesThenSalary(): void
    {
        $rotation = TeamModeGuesser::rotation([
            self::player(1, 25, 2, dcMinutes: 30, salary: 500),
            self::player(2, 25, 2, dcMinutes: 30, salary: 900),
            self::player(3, 25, 2, dcMinutes: 40, salary: 100),
            self::player(4, 25, 2, dcMinutes: 30, salary: 900),
        ]);

        self::assertSame([3, 2, 4, 1], array_map(static fn (SuggestionPlayer $p): int => $p->pid, $rotation));
    }

    public function testLockedShareIsMinutesWeighted(): void
    {
        $assets = [1 => new TeamAssets(1, [
            self::player(1, 25, 3, dcMinutes: 30),
            self::player(2, 25, 1, dcMinutes: 10),
        ], [])];

        $inputs = TeamModeGuesser::inputsForLeague($assets, [], [])[1];

        self::assertEqualsWithDelta(0.75, $inputs->lockedShare, 1e-9);
    }

    public function testZeroMinuteRotationGivesZeroLockedShare(): void
    {
        $assets = [1 => new TeamAssets(1, [self::player(1, 25, 3), self::player(2, 25, 3)], [])];

        $inputs = TeamModeGuesser::inputsForLeague($assets, [], [])[1];

        self::assertSame(0.0, $inputs->lockedShare);
    }

    private static function inputs(int $wins, int $losses, int $priorWins, int $priorLosses, float $age = 26.5, float $locked = 0.5): ModeInputs
    {
        return new ModeInputs($wins, $losses, $priorWins, $priorLosses, $age, $locked);
    }

    private static function player(int $pid, int $age, int $years, int $dcMinutes = 0, int $salary = 1000): SuggestionPlayer
    {
        return new SuggestionPlayer(
            pid: $pid,
            name: 'Player ' . $pid,
            pos: 'SF',
            age: $age,
            ovr: 0,
            injured: false,
            currentSalary: $salary,
            yearsRemaining: $years,
            futureSalary: $salary * ($years - 1),
            dcMinutes: $dcMinutes,
        );
    }
}
