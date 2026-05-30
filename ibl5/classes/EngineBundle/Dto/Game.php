<?php

declare(strict_types=1);

namespace EngineBundle\Dto;

/**
 * One scheduled game in the engine input bundle.
 *
 * Property names use PHP convention; the JSON key remapping to the Go contract
 * (`home_team_id`, `visitor_team_id`, `date`, `game_type`) lives in
 * {@see \EngineBundle\BundleSerializer}. The `ibl_schedule` source columns are
 * `home_teamid` / `visitor_teamid` / `game_date` — neither matches the Go tag,
 * so the mapping is explicit.
 */
final class Game
{
    public function __construct(
        public readonly int $homeTeamId,
        public readonly int $visitorTeamId,
        public readonly string $date,
        public readonly int $gameType,
    ) {
    }
}
