<?php

declare(strict_types=1);

namespace Repositories;

use Repositories\Contracts\PlayerLookupRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from PlayerLookupRepositoryInterface
 */
class PlayerLookupRepository extends \BaseMysqliRepository implements PlayerLookupRepositoryInterface
{
    use PlayerTeamJoinQuery;

    /**
     * @return PlayerRow|null
     */
    public function getPlayerByID(int $playerID): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            $this->playerWithTeamSelect() . "
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

        return $result !== null ? $result['pid'] : null;
    }

    /**
     * @return PlayerRow|null
     */
    public function getPlayerByName(string $playerName): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            $this->playerWithTeamSelect() . "
            WHERE p.name = ?",
            "s",
            $playerName
        );
    }
}
