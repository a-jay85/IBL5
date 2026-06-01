<?php

declare(strict_types=1);

namespace EngineBundle;

use EngineBundle\Contracts\EngineBundleRepositoryInterface;
use EngineBundle\Dto\Game;
use EngineBundle\Dto\Player;
use EngineBundle\Dto\Team;
use League\League;

/**
 * Reads players, teams, and unplayed games for the engine input bundle.
 *
 * Mirrors the JsbExportRepository read pattern (static SELECT + per-field
 * is_int/is_string narrowing). All reads are prepared statements via
 * BaseMysqliRepository; the only user-supplied parameters are the season year
 * and optional date range on getUnplayedGames(), which are bound, never
 * interpolated.
 */
final class EngineBundleRepository extends \BaseMysqliRepository implements EngineBundleRepositoryInterface
{
    /**
     * @see EngineBundleRepositoryInterface::getPlayers()
     */
    public function getPlayers(): array
    {
        // Column list is built from the hardcoded contract field list (no user
        // input) — column name == JSON tag, so no aliasing is needed.
        $columns = implode(', ', Player::FIELDS);

        $rows = $this->fetchAll(
            "SELECT $columns
             FROM `ibl_plr`
             WHERE ordinal <= 1440 AND pid <> 0
             ORDER BY pid",
        );

        $players = [];
        foreach ($rows as $row) {
            $players[] = Player::fromRow($row);
        }

        return $players;
    }

    /**
     * @see EngineBundleRepositoryInterface::getTeams()
     */
    public function getTeams(): array
    {
        $rows = $this->fetchAll(
            "SELECT teamid, CONCAT(team_city, ' ', team_name) AS name
             FROM `ibl_team_info`
             WHERE teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
             ORDER BY teamid",
        );

        $teams = [];
        foreach ($rows as $row) {
            $teams[] = new Team(
                is_int($row['teamid']) ? $row['teamid'] : 0,
                is_string($row['name']) ? $row['name'] : '',
            );
        }

        return $teams;
    }

    /**
     * @see EngineBundleRepositoryInterface::getUnplayedGames()
     */
    public function getUnplayedGames(
        int $seasonYear,
        ?string $startDate = null,
        ?string $endDate = null,
        int $gameType = 2,
        ?int $limit = null,
    ): array {
        // Canonical "unplayed" convention: both scores 0 (see PowerRankingsUpdater
        // line 129, ScheduleHighlighter). box_id is NOT a played flag.
        $sql = "SELECT game_date, visitor_teamid, home_teamid
                FROM `ibl_schedule`
                WHERE visitor_score = 0 AND home_score = 0 AND season_year = ?";
        $types = 'i';
        $params = [$seasonYear];

        // Bind the date bounds conditionally (mysqli has no NULL bind type).
        if ($startDate !== null) {
            $sql .= " AND game_date >= ?";
            $types .= 's';
            $params[] = $startDate;
        }
        if ($endDate !== null) {
            $sql .= " AND game_date <= ?";
            $types .= 's';
            $params[] = $endDate;
        }
        $sql .= " ORDER BY game_date, id";

        // Cap per-run work to the earliest $limit games (bound, never interpolated)
        // so callers can keep a single sim run within memory bounds.
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $types .= 'i';
            $params[] = $limit;
        }

        $rows = $this->fetchAll($sql, $types, ...$params);

        $games = [];
        foreach ($rows as $row) {
            $games[] = new Game(
                is_int($row['home_teamid']) ? $row['home_teamid'] : 0,
                is_int($row['visitor_teamid']) ? $row['visitor_teamid'] : 0,
                is_string($row['game_date']) ? $row['game_date'] : '',
                $gameType,
            );
        }

        return $games;
    }
}
