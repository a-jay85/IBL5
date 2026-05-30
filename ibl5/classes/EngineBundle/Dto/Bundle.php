<?php

declare(strict_types=1);

namespace EngineBundle\Dto;

/**
 * The complete engine input bundle: league id, seed, teams, players, and the
 * games to simulate. Serialized to JSON by {@see \EngineBundle\BundleSerializer}
 * for the Go engine to decode (engine/internal/bundle/bundle.go).
 */
final class Bundle
{
    /**
     * @param list<Team>   $teams
     * @param list<Player> $players
     * @param list<Game>   $schedule
     */
    public function __construct(
        public readonly int $leagueId,
        public readonly int $seed,
        public readonly array $teams,
        public readonly array $players,
        public readonly array $schedule,
    ) {
    }
}
