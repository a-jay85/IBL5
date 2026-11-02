<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectionPlayer;

/**
 * A team's standings and roster situation (F13). Pure: the caller fills the
 * standings and streak arrays from `StandingsRepository`.
 */
final readonly class TeamSituation
{
    public const int PLAYOFF_SEEDS = 8;
    public const float BUBBLE_GAMES = 3.0;
    public const int ROSTER_MAX = 15;
    public const int CORE_SIZE = 8;

    public function __construct(
        public int $tid,
        public int $wins,
        public int $losses,
        public float $pct,
        public int $lastTenWins,
        public int $conferenceRank,
        public bool $playoffBubble,
        public int $gamesLeft,
        public int $openRosterSpots,
        public float $coreAge,
        public bool $regularSeason,
    ) {
    }

    /**
     * `losses` is not a `getAllStandings()` column; the caller derives it.
     *
     * @param array<int, array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string}> $standings keyed by tid
     * @param array<int, array{last_win: int, last_loss: int}> $streaks keyed by tid
     */
    public static function build(int $tid, array $standings, array $streaks, SeasonLineup $offsetZero, bool $regularSeason): self
    {
        $row = $standings[$tid] ?? throw new \InvalidArgumentException("No standings row for team {$tid}");

        $conference = array_keys(array_filter(
            $standings,
            static fn (array $r): bool => $r['conference'] === $row['conference']
        ));
        usort($conference, static fn (int $a, int $b): int
            => [$standings[$b]['pct'], $standings[$b]['wins'], $a] <=> [$standings[$a]['pct'], $standings[$a]['wins'], $b]);
        $rank = (int) array_search($tid, $conference, true) + 1;

        return new self(
            tid: $tid,
            wins: $row['wins'],
            losses: $row['losses'],
            pct: $row['pct'],
            lastTenWins: $streaks[$tid]['last_win'] ?? 0,
            conferenceRank: $rank,
            playoffBubble: self::onBubble($rank, $row, $conference, $standings),
            gamesLeft: $row['games_unplayed'],
            openRosterSpots: max(0, self::ROSTER_MAX - count($offsetZero->players)),
            coreAge: self::coreAge($offsetZero),
            regularSeason: $regularSeason,
        );
    }

    /**
     * The F12 seam: the player's injury status against this team's games left.
     */
    public function injuryStatusFor(ProjectionPlayer $p): InjuryStatus
    {
        return InjuryStatus::fromDays($p->injuredDays, $this->gamesLeft, $this->regularSeason);
    }

    /**
     * Outside the seeds: within BUBBLE_GAMES of the last seed. Inside the
     * seeds: the first team outside is within BUBBLE_GAMES of it.
     *
     * @param array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string} $row
     * @param list<int> $conference tids in conference order
     * @param array<int, array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string}> $standings
     */
    private static function onBubble(int $rank, array $row, array $conference, array $standings): bool
    {
        $otherTid = $rank > self::PLAYOFF_SEEDS
            ? ($conference[self::PLAYOFF_SEEDS - 1] ?? null)
            : ($conference[self::PLAYOFF_SEEDS] ?? null);
        if ($otherTid === null) {
            return false;
        }
        $other = $standings[$otherTid];
        $gap = abs((($row['wins'] - $other['wins']) + ($other['losses'] - $row['losses'])) / 2);

        return $gap <= self::BUBBLE_GAMES;
    }

    /**
     * Minutes-weighted mean age of the CORE_SIZE players with the most minutes.
     */
    private static function coreAge(SeasonLineup $lineup): float
    {
        $pids = array_keys($lineup->players);
        usort($pids, static fn (int $a, int $b): int
            => [$lineup->minutesWeight[$b] ?? 0.0, $a] <=> [$lineup->minutesWeight[$a] ?? 0.0, $b]);
        $core = array_slice($pids, 0, self::CORE_SIZE);
        if ($core === []) {
            return 0.0;
        }

        $sum = 0.0;
        $weight = 0.0;
        foreach ($core as $pid) {
            $w = $lineup->minutesWeight[$pid] ?? 0.0;
            $sum += $w * $lineup->players[$pid]->age;
            $weight += $w;
        }
        if ($weight === 0.0) {
            $ages = array_map(static fn (int $pid): int => $lineup->players[$pid]->age, $core);

            return array_sum($ages) / count($ages);
        }

        return $sum / $weight;
    }
}
