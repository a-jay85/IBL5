<?php

declare(strict_types=1);

namespace Services;

use Services\Contracts\PlayerLookupRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from PlayerLookupRepositoryInterface
 */
class PlayerLookupRepository extends \BaseMysqliRepository implements PlayerLookupRepositoryInterface
{
    /**
     * @return PlayerRow|null
     */
    public function getPlayerByID(int $playerID): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM `ibl_plr` p
            LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid
            WHERE p.pid = ?",
            "i",
            $playerID
        );
    }

    public function getPlayerIDFromPlayerName(string $playerName): ?int
    {
        /** @var array{pid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT pid FROM `ibl_plr` WHERE name = ? LIMIT 1",
            "s",
            $playerName
        );

        return $result !== null ? ($result['pid'] ?? null) : null;
    }

    /**
     * @return PlayerRow|null
     */
    public function getPlayerByName(string $playerName): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM `ibl_plr` p
            LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid
            WHERE p.name = ?",
            "s",
            $playerName
        );
    }
}
