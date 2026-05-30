<?php

declare(strict_types=1);

namespace EngineBundle\Contracts;

use EngineBundle\Dto\Game;
use EngineBundle\Dto\Player;
use EngineBundle\Dto\Team;

/**
 * Reads the live database for the three inputs the engine bundle needs:
 * rosterable players, real teams, and the games still to simulate.
 */
interface EngineBundleRepositoryInterface
{
    /**
     * All rosterable players (ordinal <= 1440 AND pid <> 0), each narrowed to
     * the engine contract fields (see {@see Player::FIELDS}).
     *
     * @return list<Player>
     */
    public function getPlayers(): array;

    /**
     * All real franchises (teamid 1..{@see \League\League::MAX_REAL_TEAMID}),
     * `name` = team_city + ' ' + team_name.
     *
     * @return list<Team>
     */
    public function getTeams(): array;

    /**
     * Games still to simulate for a season: unplayed games, identified by
     * {@code visitor_score = 0 AND home_score = 0} (the league's canonical
     * unplayed convention — see PowerRankingsUpdater / ScheduleHighlighter),
     * optionally bounded by an inclusive game_date range.
     *
     * @param int         $seasonYear ibl_schedule.season_year
     * @param string|null $startDate  inclusive lower bound (Y-m-d) or null
     * @param string|null $endDate    inclusive upper bound (Y-m-d) or null
     * @param int         $gameType   JSB game-type flag stamped on each Game (caller decides; default regular)
     * @return list<Game>
     */
    public function getUnplayedGames(
        int $seasonYear,
        ?string $startDate = null,
        ?string $endDate = null,
        int $gameType = 2,
    ): array;
}
