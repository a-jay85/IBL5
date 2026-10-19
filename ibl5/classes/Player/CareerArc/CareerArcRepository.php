<?php

declare(strict_types=1);

namespace Player\CareerArc;

use Database\BaseMysqliRepository;
use Player\CareerArc\Contracts\CareerArcRepositoryInterface;

/**
 * CareerArcRepository - pid-keyed reads for the player career arc timeline.
 *
 * @see CareerArcRepositoryInterface For method contracts
 */
class CareerArcRepository extends BaseMysqliRepository implements CareerArcRepositoryInterface
{
    private const SEASONS_SQL = 'SELECT year, teamid, team, games, pts, age,
       r_oo, r_drive_off, r_po, r_trans_off, r_od, r_dd, r_pd, r_td
FROM `ibl_hist` WHERE pid = ? ORDER BY year ASC, pid ASC';

    private const MOVES_SQL = 'SELECT t.season_year, t.transaction_month, t.transaction_day, t.transaction_type,
       t.from_teamid, t.to_teamid,
       ft.team_name AS from_team_name, tt.team_name AS to_team_name
FROM `ibl_jsb_transactions` t
LEFT JOIN `ibl_team_info` ft ON ft.teamid = t.from_teamid
LEFT JOIN `ibl_team_info` tt ON tt.teamid = t.to_teamid
WHERE t.pid = ? AND t.transaction_type IN (2, 3)
ORDER BY t.season_year, t.transaction_month, t.transaction_day, t.id';

    private const PLAYER_FACTS_SQL = 'SELECT name, draftyear, draftround, draftpickno, draftedby FROM `ibl_plr` WHERE pid = ? LIMIT 1';

    private const NAME_COUNT_SQL = 'SELECT COUNT(*) AS n FROM `ibl_plr` WHERE name = ?';

    private const AWARDS_SQL = 'SELECT year, award FROM `ibl_awards` WHERE name = ? ORDER BY year ASC, award ASC'; // @phpstan-ignore ibl.orderByMissingTiebreaker (name is fixed and UNIQUE KEY uk_year_award_name makes year, award a total order)

    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see CareerArcRepositoryInterface::getSeasonRows()
     */
    public function getSeasonRows(int $pid): array
    {
        if ($pid <= 0) {
            return [];
        }

        $rows = $this->fetchAll(self::SEASONS_SQL, 'i', $pid);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'year' => self::scalarToInt($row['year']),
                'teamid' => self::scalarToInt($row['teamid']),
                'team' => self::scalarToString($row['team']),
                'games' => self::scalarToInt($row['games']),
                'pts' => self::scalarToInt($row['pts']),
                'age' => self::scalarToInt($row['age']),
                'r_oo' => self::scalarToInt($row['r_oo']),
                'r_drive_off' => self::scalarToInt($row['r_drive_off']),
                'r_po' => self::scalarToInt($row['r_po']),
                'r_trans_off' => self::scalarToInt($row['r_trans_off']),
                'r_od' => self::scalarToInt($row['r_od']),
                'r_dd' => self::scalarToInt($row['r_dd']),
                'r_pd' => self::scalarToInt($row['r_pd']),
                'r_td' => self::scalarToInt($row['r_td']),
            ];
        }

        return $result;
    }

    /**
     * @see CareerArcRepositoryInterface::getMoves()
     */
    public function getMoves(int $pid): array
    {
        if ($pid <= 0) {
            return [];
        }

        $rows = $this->fetchAll(self::MOVES_SQL, 'i', $pid);

        $result = [];
        foreach ($rows as $row) {
            $fromName = $row['from_team_name'] ?? null;
            $toName = $row['to_team_name'] ?? null;
            $result[] = [
                'season_year' => self::scalarToInt($row['season_year']),
                'transaction_month' => self::scalarToInt($row['transaction_month']),
                'transaction_day' => self::scalarToInt($row['transaction_day']),
                'transaction_type' => self::scalarToInt($row['transaction_type']),
                'from_teamid' => self::scalarToInt($row['from_teamid']),
                'to_teamid' => self::scalarToInt($row['to_teamid']),
                'from_team_name' => $fromName === null ? null : self::scalarToString($fromName),
                'to_team_name' => $toName === null ? null : self::scalarToString($toName),
            ];
        }

        return $result;
    }

    /**
     * @see CareerArcRepositoryInterface::getPlayerFacts()
     */
    public function getPlayerFacts(int $pid): ?array
    {
        if ($pid <= 0) {
            return null;
        }

        $row = $this->fetchOne(self::PLAYER_FACTS_SQL, 'i', $pid);
        if ($row === null) {
            return null;
        }

        return [
            'name' => self::scalarToString($row['name']),
            'draftyear' => self::scalarToInt($row['draftyear']),
            'draftround' => self::scalarToInt($row['draftround']),
            'draftpickno' => self::scalarToInt($row['draftpickno']),
            'draftedby' => self::scalarToString($row['draftedby']),
        ];
    }

    /**
     * @see CareerArcRepositoryInterface::getAwardsForUniqueName()
     */
    public function getAwardsForUniqueName(string $name): ?array
    {
        if (trim($name) === '') {
            return null;
        }

        $countRow = $this->fetchOne(self::NAME_COUNT_SQL, 's', $name);
        $count = $countRow === null ? 0 : self::scalarToInt($countRow['n']);
        if ($count !== 1) {
            return null;
        }

        $rows = $this->fetchAll(self::AWARDS_SQL, 's', $name);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'year' => self::scalarToInt($row['year']),
                'award' => self::scalarToString($row['award']),
            ];
        }

        return $result;
    }
}
