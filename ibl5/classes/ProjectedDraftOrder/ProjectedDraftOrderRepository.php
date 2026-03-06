<?php

declare(strict_types=1);

namespace ProjectedDraftOrder;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;

/**
 * @see ProjectedDraftOrderRepositoryInterface
 * @see \BaseMysqliRepository
 */
class ProjectedDraftOrderRepository extends \BaseMysqliRepository implements ProjectedDraftOrderRepositoryInterface
{
    /** @return list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}> */
    public function getAllTeamsWithStandings(): array
    {
        /** @var list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}> */
        return $this->fetchAll(
            "SELECT s.tid, s.team_name, s.wins, s.losses, s.pct, s.conference, s.division,
                    s.confWins, s.confLosses, s.divWins, s.divLosses, s.clinchedDivision,
                    t.color1, t.color2
             FROM ibl_standings s
             JOIN ibl_team_info t ON s.tid = t.teamid
             WHERE s.tid BETWEEN 1 AND ?",
            "i",
            \League::MAX_REAL_TEAMID
        );
    }

    /** @return list<array{Visitor: int, VScore: int, Home: int, HScore: int}> */
    public function getPlayedGames(int $seasonYear): array
    {
        /** @var list<array{Visitor: int, VScore: int, Home: int, HScore: int}> */
        return $this->fetchAll(
            "SELECT Visitor, VScore, Home, HScore
             FROM ibl_schedule
             WHERE Year = ? AND VScore > 0 AND HScore > 0",
            "i",
            $seasonYear
        );
    }

    /** @return list<array{ownerofpick: string, teampick: string, round: int, notes: string|null}> */
    public function getPickOwnership(int $draftYear): array
    {
        /** @var list<array{ownerofpick: string, teampick: string, round: int, notes: string|null}> */
        return $this->fetchAll(
            "SELECT ownerofpick, teampick, round, notes
             FROM ibl_draft_picks
             WHERE year = ? AND round IN (1, 2)",
            "i",
            $draftYear
        );
    }

    /** @return list<array{tid: int, pointsFor: float, pointsAgainst: float}> */
    public function getPointDifferentials(int $seasonYear): array
    {
        /** @var list<array{tid: int, pointsFor: float, pointsAgainst: float}> */
        return $this->fetchAll(
            "SELECT tid, SUM(pf) AS pointsFor, SUM(pa) AS pointsAgainst FROM (
                SELECT Visitor AS tid, VScore AS pf, HScore AS pa
                FROM ibl_schedule WHERE Year = ? AND VScore > 0 AND HScore > 0
                UNION ALL
                SELECT Home AS tid, HScore AS pf, VScore AS pa
                FROM ibl_schedule WHERE Year = ? AND VScore > 0 AND HScore > 0
             ) AS g GROUP BY tid",
            "ii",
            $seasonYear,
            $seasonYear
        );
    }

    public function isDraftOrderFinalized(): bool
    {
        $row = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = 'Draft Order Finalized'",
        );

        return $row !== null && $row['value'] === 'Yes';
    }

    /**
     * @param list<array{round: int, pick: int, team: string, tid: int}> $picks
     */
    public function saveFinalDraftOrder(int $year, array $picks): void
    {
        $this->db->begin_transaction();
        try {
            // Delete all draft rows from previous years (out of date)
            $this->execute(
                "DELETE FROM ibl_draft WHERE year < ?",
                "i",
                $year,
            );

            // Delete unfilled slots for this year (both rounds) before inserting new ones
            $this->execute(
                "DELETE FROM ibl_draft WHERE year = ? AND player = ''",
                "i",
                $year,
            );

            foreach ($picks as $pick) {
                $this->execute(
                    "INSERT INTO ibl_draft (year, round, pick, team, tid, player, uuid)
                     VALUES (?, ?, ?, ?, ?, '', UUID())",
                    "iiisi",
                    $year,
                    $pick['round'],
                    $pick['pick'],
                    $pick['team'],
                    $pick['tid'],
                );
            }

            $this->execute(
                "UPDATE ibl_settings SET value = 'Yes' WHERE name = 'Draft Order Finalized'",
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /** @return list<array{pick: int, team: string, tid: int, player: string}> */
    public function getFinalDraftOrder(int $year): array
    {
        /** @var list<array{pick: int, team: string, tid: int, player: string}> */
        return $this->fetchAll(
            "SELECT pick, team, tid, player FROM ibl_draft
             WHERE year = ? AND round = 1
             ORDER BY pick",
            "i",
            $year,
        );
    }

    /** @see ProjectedDraftOrderRepositoryInterface::isDraftStarted() */
    public function isDraftStarted(int $year): bool
    {
        $row = $this->fetchOne(
            "SELECT 1 FROM ibl_draft WHERE year = ? AND player != '' LIMIT 1",
            "i",
            $year,
        );

        return $row !== null;
    }
}
