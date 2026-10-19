<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * A single notable historical event that happened on a calendar day.
 *
 * Documented fact keys per kind:
 * - FiftyPointGame, TripleDouble: points, rebounds, assists, steals, blocks
 * - RecordGame: value (plus $statLabel)
 * - SeriesClincher: round, wins, losses
 * - Trade: players_moved
 */
final readonly class Moment
{
    /**
     * @param string $key Stable identity, format `<kind>:<Y-m-d>:<pid-or-tid-pair>`
     * @param string $date Y-m-d (box-score Date)
     * @param int $pid 0 for Trade / SeriesClincher
     * @param array<string, int> $facts
     */
    public function __construct(
        public MomentKind $kind,
        public string $key,
        public string $date,
        public int $seasonYear,
        public int $pid,
        public string $playerName,
        public int $teamId,
        public string $teamName,
        public int $opponentTeamId,
        public string $opponentName,
        public array $facts,
        public ?string $statLabel = null,
    ) {
    }
}
