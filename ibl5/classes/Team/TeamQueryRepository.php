<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamQueryRepositoryInterface;
use Season\Season;
use Trading\BuyoutLedgerRepository;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
use Repositories\PlayerTeamJoinQuery;

/**
 * TeamQueryRepository - Query methods for team-related data
 *
 * Extracted from the Team entity class to separate query concerns from entity state.
 * Extends BaseMysqliRepository for standardized database access via fetchAll/fetchOne.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type CashConsiderationRow from \Trading\Contracts\BuyoutLedgerRepositoryInterface
 * @phpstan-import-type DraftPickRow from TeamQueryRepositoryInterface
 * @phpstan-import-type FreeAgencyOfferRow from TeamQueryRepositoryInterface
 *
 * @see TeamQueryRepositoryInterface
 * @see \Database\BaseMysqliRepository For base class documentation and error codes
 */
class TeamQueryRepository extends \Database\BaseMysqliRepository implements TeamQueryRepositoryInterface
{
    use PlayerTeamJoinQuery;

    /** Last-sim starter column per JSB position (closed set; identifier literals only). */
    private const LAST_SIM_DEPTH_COLUMNS = [
        'PG' => 'pg_depth',
        'SG' => 'sg_depth',
        'SF' => 'sf_depth',
        'PF' => 'pf_depth',
        'C' => 'c_depth',
    ];

    /** Depth-chart starter column per JSB position (closed set; identifier literals only). */
    private const DEPTH_CHART_DEPTH_COLUMNS = [
        'PG' => 'dc_pg_depth',
        'SG' => 'dc_sg_depth',
        'SF' => 'dc_sf_depth',
        'PF' => 'dc_pf_depth',
        'C' => 'dc_c_depth',
    ];

    private BuyoutLedgerRepositoryInterface $cashConsiderationRepo;

    public function __construct(\mysqli $db, ?\League\LeagueContext $leagueContext = null, ?BuyoutLedgerRepositoryInterface $cashConsiderationRepo = null)
    {
        parent::__construct($db, $leagueContext);
        $this->cashConsiderationRepo = $cashConsiderationRepo ?? new BuyoutLedgerRepository($db);
    }

    /**
     * @return value-of<self::LAST_SIM_DEPTH_COLUMNS>
     * @throws \InvalidArgumentException when $position is not a JSB position
     */
    private function lastSimDepthColumn(string $position): string
    {
        return self::LAST_SIM_DEPTH_COLUMNS[strtoupper($position)]
            ?? throw new \InvalidArgumentException("Invalid position: {$position}");
    }

    /**
     * @return value-of<self::DEPTH_CHART_DEPTH_COLUMNS>
     * @throws \InvalidArgumentException when $position is not a JSB position
     */
    private function depthChartDepthColumn(string $position): string
    {
        return self::DEPTH_CHART_DEPTH_COLUMNS[strtoupper($position)]
            ?? throw new \InvalidArgumentException("Invalid position: {$position}");
    }

    /**
     * @see TeamQueryRepositoryInterface::getBuyouts()
     *
     * @return list<CashConsiderationRow>
     */
    public function getBuyouts(int $teamId): array
    {
        return $this->cashConsiderationRepo->getTeamBuyouts($teamId);
    }

    /**
     * @see TeamQueryRepositoryInterface::getDraftHistory()
     *
     * @return list<PlayerRow>
     */
    public function getDraftHistory(string $teamName): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            $this->playerWithTeamSelect() . "
            WHERE p.draftedby LIKE ?
            ORDER BY p.draftyear DESC,
                     p.draftround,
                     p.draftpickno ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getDraftPicks()
     *
     * @return list<DraftPickRow>
     */
    public function getDraftPicks(int $teamId): array
    {
        /** @var list<DraftPickRow> */
        return $this->fetchAll(
            "SELECT *
            FROM `ibl_draft_picks`
            WHERE owner_teamid = ?
            ORDER BY year, round, teampick ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getFreeAgencyOffers()
     *
     * @return list<FreeAgencyOfferRow>
     */
    public function getFreeAgencyOffers(int $teamId): array
    {
        /** @var list<FreeAgencyOfferRow> */
        return $this->fetchAll(
            "SELECT *
            FROM `ibl_fa_offers`
            WHERE teamid = ?
            ORDER BY name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getFreeAgencyRosterOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getFreeAgencyRosterOrderedByName(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            $this->playerWithTeamSelect() . "
            WHERE p.teamid = ?
              AND p.retired = 0
              AND p.cyt != p.cy
            ORDER BY p.name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getHealthyAndInjuredPlayersOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getHealthyAndInjuredPlayersOrderedByName(int $teamId, ?Season $season = null): array
    {
        $freeAgencyCondition = '';
        if ($season !== null && $season->isOffseasonPhase()) {
            // During Free Agency, only count players who have a salary for next year
            $freeAgencyCondition = " AND (
                (p.cy = 0 AND p.salary_yr1 > 0) OR
                (p.cy = 0 AND p.salary_yr2 > 0) OR
                (p.cy = 1 AND p.salary_yr2 > 0) OR
                (p.cy = 2 AND p.salary_yr3 > 0) OR
                (p.cy = 3 AND p.salary_yr4 > 0) OR
                (p.cy = 4 AND p.salary_yr5 > 0) OR
                (p.cy = 5 AND p.salary_yr6 > 0)
            )";
        }

        /** @var list<PlayerRow> */
        return $this->fetchAll(
            $this->playerWithTeamSelect() . "
            WHERE p.teamid = ?
              AND p.retired = 0
              AND p.ordinal <= '" . \League\JsbConstants::WAIVERS_ORDINAL . "'" . $freeAgencyCondition . "
            ORDER BY p.name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getHealthyPlayersOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getHealthyPlayersOrderedByName(int $teamId, ?Season $season = null): array
    {
        $freeAgencyCondition = '';
        if ($season !== null && $season->isOffseasonPhase()) {
            // During Free Agency, only count players who have a salary for next year
            $freeAgencyCondition = " AND (
                (p.cy = 0 AND p.salary_yr1 > 0) OR
                (p.cy = 0 AND p.salary_yr2 > 0) OR
                (p.cy = 1 AND p.salary_yr2 > 0) OR
                (p.cy = 2 AND p.salary_yr3 > 0) OR
                (p.cy = 3 AND p.salary_yr4 > 0) OR
                (p.cy = 4 AND p.salary_yr5 > 0) OR
                (p.cy = 5 AND p.salary_yr6 > 0)
            )";
        }

        /** @var list<PlayerRow> */
        return $this->fetchAll(
            $this->playerWithTeamSelect() . "
            WHERE p.teamid = ?
              AND p.retired = 0
              AND p.ordinal <= '" . \League\JsbConstants::WAIVERS_ORDINAL . "'" . $freeAgencyCondition . "
              AND p.injured = '0'
            ORDER BY p.name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getLastSimStarterPlayerIDForPosition()
     */
    public function getLastSimStarterPlayerIDForPosition(int $teamId, string $position): int
    {
        /** @var array{pid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT pid
            FROM `ibl_plr`
            WHERE teamid = ?
              AND retired = 0
              AND " . $this->lastSimDepthColumn($position) . " = 1",
            "i",
            $teamId
        );
        return $result !== null ? $result['pid'] : 0;
    }

    /**
     * @see TeamQueryRepositoryInterface::getCurrentlySetStarterPlayerIDForPosition()
     */
    public function getCurrentlySetStarterPlayerIDForPosition(int $teamId, string $position): int
    {
        /** @var array{pid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT pid
            FROM `ibl_plr`
            WHERE teamid = ?
              AND retired = 0
              AND " . $this->depthChartDepthColumn($position) . " = 1",
            "i",
            $teamId
        );
        return $result !== null ? $result['pid'] : 0;
    }

    /**
     * @see TeamQueryRepositoryInterface::getAllPlayersUnderContract()
     *
     * @return list<PlayerRow>
     */
    public function getAllPlayersUnderContract(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            $this->playerWithTeamSelect() . "
            WHERE p.teamid = ?
              AND p.salary_yr1 != 0
              AND p.retired = 0",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getPlayersUnderContractByPosition()
     *
     * @return list<PlayerRow>
     */
    public function getPlayersUnderContractByPosition(int $teamId, string $position): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            $this->playerWithTeamSelect() . "
            WHERE p.teamid = ?
              AND p.pos = ?
              AND p.salary_yr1 != 0
              AND p.retired = 0",
            "is",
            $teamId,
            $position
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getRosterUnderContractOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getRosterUnderContractOrderedByName(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            $this->playerWithTeamSelect() . "
            WHERE p.teamid = ?
              AND p.retired = 0
            ORDER BY p.name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getRosterUnderContractOrderedByOrdinal()
     *
     * @return list<PlayerRow>
     */
    public function getRosterUnderContractOrderedByOrdinal(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            $this->playerWithTeamSelect() . "
            WHERE p.teamid = ?
              AND p.retired = 0
            ORDER BY p.ordinal ASC",
            "i",
            $teamId
        );
    }
}
