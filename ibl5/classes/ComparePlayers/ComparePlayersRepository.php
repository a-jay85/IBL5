<?php

declare(strict_types=1);

namespace ComparePlayers;

use ComparePlayers\Contracts\ComparePlayersRepositoryInterface;

/**
 * @see ComparePlayersRepositoryInterface
 * @extends \BaseMysqliRepository
 */
class ComparePlayersRepository extends \BaseMysqliRepository implements ComparePlayersRepositoryInterface
{
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see ComparePlayersRepositoryInterface::getAllPlayerNames()
     */
    public function getAllPlayerNames(): array
    {
        $rows = $this->fetchAll(
            "SELECT name FROM ibl_plr WHERE ordinal != 0 ORDER BY name ASC"
        );
        
        $names = [];
        foreach ($rows as $row) {
            $names[] = $row['name'];
        }
        
        return $names;
    }

    /**
     * @see ComparePlayersRepositoryInterface::getPlayerByName()
     */
    public function getPlayerByName(string $playerName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE name = ? LIMIT 1",
            "s",
            $playerName
        );
    }
}
