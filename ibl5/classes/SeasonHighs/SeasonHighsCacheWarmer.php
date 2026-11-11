<?php

declare(strict_types=1);

namespace SeasonHighs;

use Season\Season;

/**
 * Owns the Records "This Season" Season Highs keys: enumerates, deletes, and rebuilds them.
 * Keys depend only on the date range, and SeasonHighsService::getDateRangeForPhase has four outcomes.
 */
final class SeasonHighsCacheWarmer
{
    /** One phase string per date range. Every phase string not listed resolves to the 'Regular Season' range. */
    public const WARM_PHASES = ['Regular Season', 'Playoffs', 'Preseason', 'HEAT'];

    private SeasonHighsService $service;

    public function __construct(private readonly CachedSeasonHighsRepository $repository, Season $season)
    {
        $this->service = new SeasonHighsService($repository, $season);
    }

    /**
     * @return list<string> Every key the Records This Season tab requests, without touching the database
     */
    public function expectedKeys(): array
    {
        return $this->repository->planKeys(fn () => $this->requestEveryPhase());
    }

    /**
     * @return int Number of keys deleted
     */
    public function invalidate(): int
    {
        return $this->repository->deleteKeys($this->expectedKeys());
    }

    /**
     * @return int Number of keys written
     */
    public function rebuild(): int
    {
        return count($this->repository->warmKeys(fn () => $this->requestEveryPhase()));
    }

    private function requestEveryPhase(): void
    {
        foreach (self::WARM_PHASES as $phase) {
            $this->service->getHomeAwayHighs($phase);
            $this->service->getSeasonHighsData($phase);
        }
    }
}
