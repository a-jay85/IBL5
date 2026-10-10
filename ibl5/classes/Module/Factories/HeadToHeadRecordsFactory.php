<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/HeadToHeadRecords/index.php.
 */
final class HeadToHeadRecordsFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    /**
     * Builds the controller with no highlighted team. index.php passes the GM's
     * team to the controller itself, so this factory never sees actor identity.
     *
     * @param string $imageRoot Filesystem directory the logo resolver checks for team logos
     */
    public function controller(string $imageRoot): \HeadToHeadRecords\HeadToHeadRecordsController
    {
        $db = $this->services->db();
        $season = new \Season\Season($db, $this->services->leagueContext());
        $logoResolver = new \HeadToHeadRecords\LogoResolver();

        $innerRepo = new \HeadToHeadRecords\HeadToHeadRecordsRepository(
            $db,
            $season->endingYear,
            fn (int $id, string $name): string => $logoResolver->resolve($id, $name, $imageRoot),
        );

        $repo = new \HeadToHeadRecords\CachedHeadToHeadRecordsRepository(
            $innerRepo,
            new \Cache\DatabaseCache($db)
        );

        return new \HeadToHeadRecords\HeadToHeadRecordsController(
            $repo,
            new \HeadToHeadRecords\HeadToHeadRecordsView(),
            $season,
            new \stdClass(),
            $this->services->teamIdentity()
        );
    }
}
