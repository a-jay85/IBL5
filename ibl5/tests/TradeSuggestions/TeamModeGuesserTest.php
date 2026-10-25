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

    public function testStrongWinningTeamIsWinNow(): void
    {
        $guess = $this->guesser->guess(self::inputs(wins: 30, losses: 10, priorWins: 55, priorLosses: 27, rank: 3));

        self::assertSame(TeamMode::WinNow, $guess->mode);
        self::assertGreaterThan(0.0, $guess->score);
    }

    public function testWeakLosingTeamIsRebuild(): void
    {
        $guess = $this->guesser->guess(self::inputs(wins: 10, losses: 30, priorWins: 20, priorLosses: 62, rank: 26));

        self::assertSame(TeamMode::Rebuild, $guess->mode);
        self::assertLessThan(0.0, $guess->score);
    }

    /**
     * A few games in, last season still carries the record: a 4-0 start does not
     * turn a 10-72 team into a contender, and a young 68-14 team is not a rebuild.
     */
    public function testEarlySeasonLeansOnLastSeason(): void
    {
        $lastPlace = $this->guesser->guess(self::inputs(wins: 4, losses: 0, priorWins: 10, priorLosses: 72, rank: 24, age: 30.0));
        $champion = $this->guesser->guess(self::inputs(wins: 0, losses: 4, priorWins: 68, priorLosses: 14, rank: 5, age: 23.0));

        self::assertSame(TeamMode::Rebuild, $lastPlace->mode);
        self::assertSame(TeamMode::WinNow, $champion->mode);
        // 4 + 20 * 10/82 wins over 24 games.
        self::assertEqualsWithDelta((4 + 20 * 10 / 82) / 24, $lastPlace->recordPct, 1e-9);
    }

    public function testRosterStrengthBreaksAnEvenRecord(): void
    {
        $best = $this->guesser->guess(self::inputs(wins: 20, losses: 20, priorWins: 41, priorLosses: 41, rank: 1));
        $worst = $this->guesser->guess(self::inputs(wins: 20, losses: 20, priorWins: 41, priorLosses: 41, rank: 28));

        self::assertSame(TeamMode::WinNow, $best->mode);
        self::assertSame(TeamMode::Rebuild, $worst->mode);
    }

    /**
     * Same mid-table roster, opposite records: the record alone flips the mode.
     */
    public function testRecordDecidesForAMidTableRoster(): void
    {
        $winning = $this->guesser->guess(self::inputs(wins: 30, losses: 10, priorWins: 50, priorLosses: 32, rank: 14));
        $losing = $this->guesser->guess(self::inputs(wins: 10, losses: 30, priorWins: 32, priorLosses: 50, rank: 14));

        self::assertSame(TeamMode::WinNow, $winning->mode);
        self::assertSame(TeamMode::Rebuild, $losing->mode);
    }

    public function testGuessKeepsItsInputs(): void
    {
        $inputs = self::inputs(wins: 1, losses: 2, priorWins: 3, priorLosses: 4, rank: 5);

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
                $players[] = self::player($tid * 100 + $i, 50 + $tid, 26, 2);
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
            self::player(1, 70, 30, 3),
            self::player(2, 60, 20, 1),
        ];
        for ($i = 3; $i <= 8; $i++) {
            $players[] = self::player($i, 50, 25, 2);
        }
        // Ninth man: outside the rotation, so his age and ovr must not count.
        $players[] = self::player(9, 40, 40, 5);
        $assets = [
            1 => new TeamAssets(1, $players, []),
            2 => new TeamAssets(2, [self::player(20, 80, 25, 1)], []),
        ];

        $inputs = TeamModeGuesser::inputsForLeague($assets, [1 => ['wins' => 3, 'losses' => 1]], [1 => ['wins' => 50, 'losses' => 32]])[1];

        self::assertSame(3, $inputs->wins);
        self::assertSame(1, $inputs->losses);
        self::assertSame(50, $inputs->priorWins);
        self::assertSame(32, $inputs->priorLosses);
        self::assertEqualsWithDelta((70 + 60 + 6 * 50) / 8, $inputs->rotationOvr, 1e-9);
        self::assertEqualsWithDelta((30 + 20 + 6 * 25) / 8, $inputs->rotationAge, 1e-9);
        // Everyone in the top eight but the 60 is signed 2+ years.
        self::assertEqualsWithDelta(370 / 430, $inputs->lockedShare, 1e-9);
        self::assertSame(2, $inputs->strengthRank);
        self::assertSame(2, $inputs->teamCount);
    }

    public function testRotationSkipsInjuredPlayers(): void
    {
        $healthy = self::player(1, 60, 25, 2);
        $hurtStar = new SuggestionPlayer(2, 'Player 2', 'SF', 25, 80, true, 1000, 2, 1000);

        $rotation = TeamModeGuesser::rotation([$hurtStar, $healthy]);

        self::assertSame([1], array_map(static fn (SuggestionPlayer $p): int => $p->pid, $rotation));
    }

    private static function inputs(int $wins, int $losses, int $priorWins, int $priorLosses, int $rank, float $age = 26.5): ModeInputs
    {
        return new ModeInputs($wins, $losses, $priorWins, $priorLosses, 55.0, $rank, 28, $age, 0.5);
    }

    private static function player(int $pid, int $ovr, int $age, int $years): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, 'Player ' . $pid, 'SF', $age, $ovr, false, 1000, $years, 1000 * ($years - 1));
    }
}
