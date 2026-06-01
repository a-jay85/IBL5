<?php

declare(strict_types=1);

namespace EngineBundle;

use EngineBundle\Contracts\BundleSerializerInterface;
use EngineBundle\Contracts\EngineBundleRepositoryInterface;
use EngineBundle\Dto\Bundle;

/**
 * Builds the engine input bundle JSON from the live database for a sim window.
 *
 * This is the production path: a real sim run calls buildBundleJson() to feed
 * the Go engine. The caller decides which games and what game_type; the service
 * does NOT infer playoff/ASG (ibl_schedule has no game-type column — that
 * classification is a later PR's concern). Fails fast with a typed exception
 * when the window has no games or no roster, rather than emitting a bundle the
 * engine would reject.
 */
final class EngineBundleService
{
    /** IBL main league. */
    public const DEFAULT_LEAGUE_ID = 1;

    /** JSB regular-season game type (see engine GameType enum: 2/3 regular). */
    public const DEFAULT_GAME_TYPE = 2;

    public function __construct(
        private readonly EngineBundleRepositoryInterface $repository,
        private readonly BundleSerializerInterface $serializer,
    ) {
    }

    /**
     * Build the bundle JSON for a season's unplayed games.
     *
     * @param int         $seasonYear ibl_schedule.season_year
     * @param string|null $startDate  inclusive game_date lower bound (Y-m-d) or null
     * @param string|null $endDate    inclusive game_date upper bound (Y-m-d) or null
     * @param int         $gameType   JSB game-type flag stamped on every game (default regular)
     * @param int|null    $seed       explicit seed, or null to generate one in [0, PHP_INT_MAX]
     * @param int         $leagueId   league id recorded in the bundle
     * @param int|null    $maxGames   cap the unplayed-game window to the earliest N games, or null for all
     *
     * @throws EmptyScheduleException when the window yields no games to simulate
     * @throws EmptyRosterException   when no rosterable players exist
     */
    public function buildBundleJson(
        int $seasonYear,
        ?string $startDate = null,
        ?string $endDate = null,
        int $gameType = self::DEFAULT_GAME_TYPE,
        ?int $seed = null,
        int $leagueId = self::DEFAULT_LEAGUE_ID,
        ?int $maxGames = null,
    ): string {
        $schedule = $this->repository->getUnplayedGames($seasonYear, $startDate, $endDate, $gameType, $maxGames);
        if ($schedule === []) {
            throw new EmptyScheduleException(
                "No unplayed games to simulate for season $seasonYear in the given window.",
            );
        }

        $players = $this->repository->getPlayers();
        if ($players === []) {
            throw new EmptyRosterException('No rosterable players found (ordinal <= 1440, pid <> 0).');
        }

        $teams = $this->repository->getTeams();

        // Seed in [0, PHP_INT_MAX] so it round-trips as a JSON number into Go's
        // uint64 without overflowing PHP's signed int. The seed is recorded in
        // the bundle (and echoed by the engine) so any run can be replayed.
        $resolvedSeed = $seed ?? random_int(0, PHP_INT_MAX);

        $bundle = new Bundle($leagueId, $resolvedSeed, $teams, $players, $schedule);

        return $this->serializer->serialize($bundle);
    }
}
