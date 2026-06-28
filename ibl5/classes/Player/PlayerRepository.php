<?php

declare(strict_types=1);

namespace Player;

use BaseMysqliRepository;
use Player\Contracts\PlayerRepositoryInterface;

/**
 * PlayerRepository - Database operations for player data
 *
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type HistoricalPlayerRow from PlayerRepositoryInterface
 * @phpstan-import-type AwardRow from PlayerRepositoryInterface
 * @phpstan-import-type PlayerNewsRow from PlayerRepositoryInterface
 * @phpstan-import-type OneOnOneWinRow from PlayerRepositoryInterface
 * @phpstan-import-type OneOnOneLossRow from PlayerRepositoryInterface
 *
 * @see PlayerRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 */
class PlayerRepository extends BaseMysqliRepository implements PlayerRepositoryInterface
{
    private PlayerDataMapper $mapper;

    /** @var array{allStar: int, threePoint: int, dunkContest: int, rookieSoph: int}|null */
    private ?array $cachedAllStarWeekendCounts = null;
    private ?string $cachedAllStarWeekendPlayerName = null;

    /**
     * Constructor - inherits from BaseMysqliRepository
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
        $this->mapper = new PlayerDataMapper();
    }

    /**
     * Load a player by their ID from the current player table
     * 
     * Uses fetchOne from BaseMysqliRepository with prepared statement.
     */
    public function loadByID(int $playerID): PlayerData
    {
        /** @var PlayerRow|null $plrRow */
        $plrRow = $this->fetchOne(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
             FROM `ibl_plr` p
             LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid
             WHERE p.pid = ? LIMIT 1",
            "i",
            $playerID
        );

        if ($plrRow === null) {
            throw new \RuntimeException("Player with ID $playerID not found");
        }

        return $this->fillFromCurrentRow($plrRow);
    }

    /**
     * Fill a PlayerData object from a current player row
     *
     * @see PlayerDataMapper::fillFromCurrentRow()
     *
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    public function fillFromCurrentRow(array $plrRow): PlayerData
    {
        return $this->mapper->fillFromCurrentRow($plrRow);
    }

    /**
     * Fill a PlayerData object from a historical player row
     *
     * @see PlayerDataMapper::fillFromHistoricalRow()
     *
     * @param HistoricalPlayerRow $plrRow
     */
    public function fillFromHistoricalRow(array $plrRow): PlayerData
    {
        return $this->mapper->fillFromHistoricalRow($plrRow);
    }

    /**
     * @see PlayerRepositoryInterface::getFreeAgencyDemands()
     * 
     * Uses fetchOne from BaseMysqliRepository with prepared statement.
     */
    public function getFreeAgencyDemands(int $playerID): array
    {
        $row = $this->fetchOne(
            "SELECT * FROM `ibl_demands` WHERE pid = ?",
            "i",
            $playerID
        );

        // Return demand array or empty array with all keys set to 0
        if ($row !== null) {
            /** @var array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int, ...} $row */
            return [
                'dem1' => $row['dem1'] ?? 0,
                'dem2' => $row['dem2'] ?? 0,
                'dem3' => $row['dem3'] ?? 0,
                'dem4' => $row['dem4'] ?? 0,
                'dem5' => $row['dem5'] ?? 0,
                'dem6' => $row['dem6'] ?? 0,
            ];
        }
        
        return [
            'dem1' => 0,
            'dem2' => 0,
            'dem3' => 0,
            'dem4' => 0,
            'dem5' => 0,
            'dem6' => 0,
        ];
    }

    /**
     * @see PlayerRepositoryInterface::getAllStarGameCount()
     */
    public function getAllStarGameCount(string $playerName): int
    {
        return $this->getAllStarWeekendCounts($playerName)['allStar'];
    }

    /**
     * @see PlayerRepositoryInterface::getThreePointContestCount()
     */
    public function getThreePointContestCount(string $playerName): int
    {
        return $this->getAllStarWeekendCounts($playerName)['threePoint'];
    }

    /**
     * @see PlayerRepositoryInterface::getDunkContestCount()
     */
    public function getDunkContestCount(string $playerName): int
    {
        return $this->getAllStarWeekendCounts($playerName)['dunkContest'];
    }

    /**
     * @see PlayerRepositoryInterface::getRookieSophChallengeCount()
     */
    public function getRookieSophChallengeCount(string $playerName): int
    {
        return $this->getAllStarWeekendCounts($playerName)['rookieSoph'];
    }

    /**
     * Get all All-Star Weekend event counts in a single query
     *
     * @return array{allStar: int, threePoint: int, dunkContest: int, rookieSoph: int}
     */
    private function getAllStarWeekendCounts(string $playerName): array
    {
        if ($this->cachedAllStarWeekendCounts !== null && $this->cachedAllStarWeekendPlayerName === $playerName) {
            return $this->cachedAllStarWeekendCounts;
        }

        /** @var array{allStar: int, threePoint: int, dunkContest: int, rookieSoph: int}|null $result */
        $result = $this->fetchOne(
            "SELECT
                SUM(CASE WHEN award LIKE '%Conference All-Star' THEN 1 ELSE 0 END) AS allStar,
                SUM(CASE WHEN award LIKE 'Three-Point Contest%' THEN 1 ELSE 0 END) AS threePoint,
                SUM(CASE WHEN award LIKE 'Slam Dunk Competition%' THEN 1 ELSE 0 END) AS dunkContest,
                SUM(CASE WHEN award LIKE 'Rookie-Sophomore Challenge' THEN 1 ELSE 0 END) AS rookieSoph
            FROM `ibl_awards`
            WHERE name = ?",
            "s",
            $playerName
        );

        $this->cachedAllStarWeekendPlayerName = $playerName;
        $this->cachedAllStarWeekendCounts = [
            'allStar' => (int) ($result['allStar'] ?? 0),
            'threePoint' => (int) ($result['threePoint'] ?? 0),
            'dunkContest' => (int) ($result['dunkContest'] ?? 0),
            'rookieSoph' => (int) ($result['rookieSoph'] ?? 0),
        ];

        return $this->cachedAllStarWeekendCounts;
    }

    /**
     * @see PlayerRepositoryInterface::getAwards()
     *
     * @return list<AwardRow>
     */
    public function getAwards(string $playerName): array
    {
        /** @var list<AwardRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_awards` WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * Get news articles mentioning a player
     *
     * @see PlayerRepositoryInterface::getPlayerNews()
     *
     * @return list<PlayerNewsRow>
     */
    public function getPlayerNews(string $playerName): array
    {
        $searchPattern = '%' . $playerName . '%';
        $searchPatternII = '%' . $playerName . ' II%';

        /** @var list<PlayerNewsRow> */
        return $this->fetchAll(
            "SELECT sid, title, time FROM nuke_stories 
             WHERE (hometext LIKE ? OR bodytext LIKE ?) 
             AND (hometext NOT LIKE ? OR bodytext NOT LIKE ?) 
             ORDER BY time DESC",
            "ssss",
            $searchPattern,
            $searchPattern,
            $searchPatternII,
            $searchPatternII
        );
    }

    /**
     * Get one-on-one game wins for a player
     *
     * @see PlayerRepositoryInterface::getOneOnOneWins()
     *
     * @return list<OneOnOneWinRow>
     */
    public function getOneOnOneWins(string $playerName): array
    {
        /** @var list<OneOnOneWinRow> */
        return $this->fetchAll(
            "SELECT o.gameid, o.winner, o.loser, o.winscore, o.lossscore, p.pid as loser_pid 
             FROM `ibl_one_on_one` o 
             LEFT JOIN `ibl_plr` p ON o.loser = p.name 
             WHERE o.winner = ? 
             ORDER BY o.gameid ASC",
            "s",
            $playerName
        );
    }

    /**
     * Get one-on-one game losses for a player
     *
     * @see PlayerRepositoryInterface::getOneOnOneLosses()
     *
     * @return list<OneOnOneLossRow>
     */
    public function getOneOnOneLosses(string $playerName): array
    {
        /** @var list<OneOnOneLossRow> */
        return $this->fetchAll(
            "SELECT o.gameid, o.winner, o.loser, o.winscore, o.lossscore, p.pid as winner_pid
             FROM `ibl_one_on_one` o
             LEFT JOIN `ibl_plr` p ON o.winner = p.name
             WHERE o.loser = ?
             ORDER BY o.gameid ASC",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerRepositoryInterface::getPlayerIdByUuid()
     */
    public function getPlayerIdByUuid(string $uuid): ?int
    {
        /** @var array{pid: int}|null $row */
        $row = $this->fetchOne(
            "SELECT pid FROM `ibl_plr` WHERE uuid = ?",
            "s",
            $uuid
        );

        return $row !== null ? $row['pid'] : null;
    }
}
