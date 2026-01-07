<?php

declare(strict_types=1);

namespace Player;

use BaseMysqliRepository;
use Player\Contracts\PlayerAwardsRepositoryInterface;

/**
 * PlayerAwardsRepository - Database operations for player awards
 * 
 * @see PlayerAwardsRepositoryInterface
 */
class PlayerAwardsRepository extends BaseMysqliRepository implements PlayerAwardsRepositoryInterface
{
    /**
     * @see PlayerAwardsRepositoryInterface::getPlayerAwards
     */
    public function getPlayerAwards(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_awards WHERE name = ? ORDER BY year DESC",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerAwardsRepositoryInterface::countAllStarSelections
     */
    public function countAllStarSelections(string $playerName): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM ibl_awards WHERE name = ? AND Award LIKE '%Conference All-Star'",
            "s",
            $playerName
        );
        return (int) ($result['count'] ?? 0);
    }

    /**
     * @see PlayerAwardsRepositoryInterface::countThreePointContests
     */
    public function countThreePointContests(string $playerName): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM ibl_awards WHERE name = ? AND Award LIKE 'Three-Point Contest%'",
            "s",
            $playerName
        );
        return (int) ($result['count'] ?? 0);
    }

    /**
     * @see PlayerAwardsRepositoryInterface::countDunkContests
     */
    public function countDunkContests(string $playerName): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM ibl_awards WHERE name = ? AND Award LIKE 'Slam Dunk Competition%'",
            "s",
            $playerName
        );
        return (int) ($result['count'] ?? 0);
    }

    /**
     * @see PlayerAwardsRepositoryInterface::countRookieSophomoreChallenges
     */
    public function countRookieSophomoreChallenges(string $playerName): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM ibl_awards WHERE name = ? AND Award LIKE 'Rookie-Sophomore Challenge'",
            "s",
            $playerName
        );
        return (int) ($result['count'] ?? 0);
    }

    /**
     * @see PlayerAwardsRepositoryInterface::getAllStarActivity
     */
    public function getAllStarActivity(string $playerName): array
    {
        return [
            'allStarGames' => $this->countAllStarSelections($playerName),
            'threePointContests' => $this->countThreePointContests($playerName),
            'dunkContests' => $this->countDunkContests($playerName),
            'rookieSophomoreChallenges' => $this->countRookieSophomoreChallenges($playerName)
        ];
    }

    /**
     * @see PlayerAwardsRepositoryInterface::getPlayerAwardsByPattern
     */
    public function getPlayerAwardsByPattern(string $playerName, string $awardPattern): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE ? ORDER BY year DESC",
            "ss",
            $playerName,
            $awardPattern
        );
    }

    /**
     * @see PlayerAwardsRepositoryInterface::getPlayerNews
     */
    public function getPlayerNews(string $playerName, int $limit = 10): array
    {
        return $this->fetchAll(
            "SELECT * FROM nuke_stories WHERE bodytext LIKE ? ORDER BY time DESC LIMIT ?",
            "si",
            '%' . $playerName . '%',
            $limit
        );
    }
}
