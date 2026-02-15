<?php

declare(strict_types=1);

namespace ComparePlayers;

use ComparePlayers\Contracts\ComparePlayersRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @see ComparePlayersRepositoryInterface
 */
class ComparePlayersRepository extends \BaseMysqliRepository implements ComparePlayersRepositoryInterface
{
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see ComparePlayersRepositoryInterface::getAllPlayerNames()
     */
    public function getAllPlayerNames(): array
    {
        $rows = $this->fetchAll(
            "SELECT name FROM ibl_plr WHERE ordinal != 0 AND name NOT LIKE '|%' AND name != '(no starter)' ORDER BY name ASC"
        );

        $names = [];
        foreach ($rows as $row) {
            /** @var array{name: string} $row */
            $names[] = $row['name'];
        }

        return $names;
    }

    /**
     * @see ComparePlayersRepositoryInterface::getPlayerByName()
     *
     * @return PlayerRow|null
     */
    public function getPlayerByName(string $playerName): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT p.*, t.team_city, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.name = ? LIMIT 1",
            "s",
            $playerName
        );
    }
}
