<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Fixtures;

use League\League;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\LeagueSnapshotInputs;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\Rating;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\TeamFactors;

/**
 * A deterministic synthetic league for engine tests. Seeded with
 * `mt_srand(7)`. Ratings are spread so slot pools order strictly, except one
 * planted tie: the first player of teams 1 and 2 share every rating, age and talent.
 */
final class SnapshotFactory
{
    public const int SEASON_YEAR = 2026;
    /** A roster row on a non-franchise team that `teamIds` must leave out. */
    public const int NON_FRANCHISE_TID = 40;
    public const int NON_FRANCHISE_PID = 9001;
    /** Planted pool tie (identical ratings). */
    public const int TIE_PID_A = 101;
    public const int TIE_PID_B = 201;

    private const array POSITIONS = ['PG', 'SG', 'SF', 'PF', 'C'];

    public static function league(int $teams = 28, int $perTeam = 13): LeagueSnapshot
    {
        return LeagueSnapshotBuilder::build(self::inputs($teams, $perTeam));
    }

    /**
     * @param array<int, list<ProjectionPlayer>> $overrideRosters tid => roster, replacing the generated one
     */
    public static function inputs(int $teams = 28, int $perTeam = 13, array $overrideRosters = []): LeagueSnapshotInputs
    {
        mt_srand(7);
        $playersByTid = [];
        $standings = [];
        $streaks = [];
        $factors = [];
        $cash = [];
        $pickRows = [];
        $modes = [];
        $names = [];
        $round1 = [];
        $round2 = [];
        $tieRatings = null;
        for ($tid = 1; $tid <= $teams; $tid++) {
            $roster = [];
            for ($i = 1; $i <= $perTeam; $i++) {
                $pid = $tid * 100 + $i;
                $ratings = [];
                foreach (Rating::ALL as $key) {
                    $ratings[$key] = mt_rand(20, 90);
                }
                $age = mt_rand(20, 34);
                $talent = mt_rand(30, 80);
                if ($pid === self::TIE_PID_A) {
                    $tieRatings = [$ratings, $age, $talent];
                }
                if ($pid === self::TIE_PID_B && $tieRatings !== null) {
                    [$ratings, $age, $talent] = $tieRatings;
                }
                $years = mt_rand(1, 4);
                $salary = [];
                for ($y = 1; $y <= 6; $y++) {
                    $salary[$y] = $y <= $years ? mt_rand(100, 2500) : 0;
                }
                $roster[] = ProjectionFactory::player(
                    pid: $pid,
                    tid: $tid,
                    pos: self::POSITIONS[($i - 1) % 5],
                    age: $age,
                    talent: $talent,
                    salaryByYear: $salary,
                    ratings: ProjectionFactory::ratingSet($ratings),
                    dcDepth: [self::POSITIONS[($i - 1) % 5] => intdiv($i - 1, 5) + 1],
                    dcMinutes: $i <= 5 ? 32 : ($i <= 10 ? 16 : 0),
                );
            }
            $playersByTid[$tid] = $overrideRosters[$tid] ?? $roster;

            $wins = mt_rand(15, 45);
            $losses = 60 - $wins;
            $standings[$tid] = [
                'wins' => $wins,
                'losses' => $losses,
                'pct' => round($wins / 60, 3),
                'games_unplayed' => League::REGULAR_SEASON_GAMES - 60,
                'conference' => $tid <= 14 ? 'Eastern' : 'Western',
            ];
            $streaks[$tid] = ['last_win' => mt_rand(0, 10), 'last_loss' => mt_rand(0, 10)];
            $factors[$tid] = new TeamFactors($tid, $wins, $losses, mt_rand(500, 1500), mt_rand(500, 1500));
            $cash[$tid] = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
            $modes[$tid] = match ($tid % 3) {
                0 => TeamMode::Rebuild,
                1 => TeamMode::WinNow,
                default => TeamMode::Middle,
            };
            $names[$tid] = 'Team ' . $tid;
            $pickRows[$tid] = [
                ['pickid' => $tid * 10 + 1, 'owner_teamid' => $tid, 'teampick_teamid' => $tid, 'year' => self::SEASON_YEAR, 'round' => 1],
                ['pickid' => $tid * 10 + 2, 'owner_teamid' => $tid, 'teampick_teamid' => $tid, 'year' => self::SEASON_YEAR + 1, 'round' => 2],
            ];
            $slot = self::draftSlot($tid, $wins, $losses);
            $round1[] = $slot;
            $round2[] = $slot;
        }
        $playersByTid[self::NON_FRANCHISE_TID] = [
            ProjectionFactory::player(pid: self::NON_FRANCHISE_PID, tid: self::NON_FRANCHISE_TID),
        ];

        return new LeagueSnapshotInputs(
            seasonYear: self::SEASON_YEAR,
            advancesContractYears: false,
            regularSeason: true,
            playersByTid: $playersByTid,
            scale: ProjectionFactory::scale(),
            standings: $standings,
            streaks: $streaks,
            teamFactors: $factors,
            cashByTeamAndOffset: $cash,
            pickRows: $pickRows,
            draftOrder: ['round1' => $round1, 'round2' => $round2],
            modes: $modes,
            teamNames: $names,
        );
    }

    /**
     * @return array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int|null, player: string}
     */
    private static function draftSlot(int $tid, int $wins, int $losses): array
    {
        return [
            'pick' => $tid,
            'teamId' => $tid,
            'teamName' => 'Team ' . $tid,
            'wins' => $wins,
            'losses' => $losses,
            'color1' => '000000',
            'color2' => 'FFFFFF',
            'ownerId' => $tid,
            'ownerName' => 'Team ' . $tid,
            'ownerColor1' => '000000',
            'ownerColor2' => 'FFFFFF',
            'isTraded' => false,
            'notes' => '',
            'movement' => null,
            'player' => '',
        ];
    }
}
