<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use League\LeagueContext;

/**
 * Facade that delegates to per-entity collaborator repositories.
 *
 * Preserves the JsbImportRepositoryInterface public contract while routing
 * each method to the appropriate single-responsibility repository.
 */
class JsbImportRepository extends \BaseMysqliRepository implements JsbImportRepositoryInterface
{
    private Repositories\TrnRepository $trn;
    private Repositories\HisRepository $his;
    private Repositories\AswRepository $asw;
    private Repositories\AwaRepository $awa;
    private Repositories\RcbRepository $rcb;
    private Repositories\PlbRepository $plb;
    private Repositories\DraRepository $dra;
    private Repositories\RetRepository $ret;
    private Repositories\HofRepository $hof;
    private Repositories\JsbLookupRepository $lookup;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->trn    = new Repositories\TrnRepository($db, $leagueContext);
        $this->his    = new Repositories\HisRepository($db, $leagueContext);
        $this->asw    = new Repositories\AswRepository($db, $leagueContext);
        $this->awa    = new Repositories\AwaRepository($db, $leagueContext);
        $this->rcb    = new Repositories\RcbRepository($db, $leagueContext);
        $this->plb    = new Repositories\PlbRepository($db, $leagueContext);
        $this->dra    = new Repositories\DraRepository($db, $leagueContext);
        $this->ret    = new Repositories\RetRepository($db, $leagueContext);
        $this->hof    = new Repositories\HofRepository($db, $leagueContext);
        $this->lookup = new Repositories\JsbLookupRepository($db, $leagueContext);
    }

    public function upsertTransaction(array $record): int
    {
        return $this->trn->upsertTransaction($record);
    }

    public function upsertHistoryRecord(array $record): int
    {
        return $this->his->upsertHistoryRecord($record);
    }

    public function upsertAllStarRoster(array $record): int
    {
        return $this->asw->upsertAllStarRoster($record);
    }

    public function upsertAllStarScore(array $record): int
    {
        return $this->asw->upsertAllStarScore($record);
    }

    public function upsertAward(int $year, string $award, string $name): int
    {
        return $this->awa->upsertAward($year, $award, $name);
    }

    public function resolveTeamIdByName(string $teamName): ?int
    {
        return $this->lookup->resolveTeamIdByName($teamName);
    }

    public function replaceRcbAlltimeRecords(array $records): int
    {
        return $this->rcb->replaceRcbAlltimeRecords($records);
    }

    public function replaceRcbSeasonRecords(int $seasonYear, array $records): int
    {
        return $this->rcb->replaceRcbSeasonRecords($seasonYear, $records);
    }

    public function fetchMaxTradeGroupId(): int
    {
        return $this->trn->fetchMaxTradeGroupId();
    }

    public function getPlayerName(int $pid): ?string
    {
        return $this->lookup->getPlayerName($pid);
    }

    public function upsertPlbSnapshot(array $record): int
    {
        return $this->plb->upsertPlbSnapshot($record);
    }

    public function upsertDraftResult(array $record): int
    {
        return $this->dra->upsertDraftResult($record);
    }

    public function upsertRetiredPlayer(array $record): int
    {
        return $this->ret->upsertRetiredPlayer($record);
    }

    public function markPlayerRetired(int $pid): int
    {
        return $this->ret->markPlayerRetired($pid);
    }

    public function upsertHofInductee(array $record): int
    {
        return $this->hof->upsertHofInductee($record);
    }

    public function hasChampionForSeason(int $seasonYear): bool
    {
        return $this->his->hasChampionForSeason($seasonYear);
    }
}
