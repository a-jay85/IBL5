<?php

declare(strict_types=1);

namespace Rivalries;

/**
 * RivalriesServiceFactory - Shared wiring for the Rivalries and Team composition roots.
 */
final class RivalriesServiceFactory
{
    public static function create(\mysqli $db, \Season\Season $season, string $imageRoot): RivalriesService
    {
        $logoResolver = new \HeadToHeadRecords\LogoResolver();
        $h2h = new \HeadToHeadRecords\CachedHeadToHeadRecordsRepository(
            new \HeadToHeadRecords\HeadToHeadRecordsRepository(
                $db,
                $season->endingYear,
                fn (int $id, string $name): string => $logoResolver->resolve($id, $name, $imageRoot),
            ),
            new \Cache\DatabaseCache($db),
        );

        return new RivalriesService(self::createRepository($db), $h2h, new RivalryScorer(), $season->endingYear);
    }

    public static function createRepository(\mysqli $db): CachedRivalriesRepository
    {
        return new CachedRivalriesRepository(new RivalriesRepository($db), new \Cache\DatabaseCache($db));
    }
}
