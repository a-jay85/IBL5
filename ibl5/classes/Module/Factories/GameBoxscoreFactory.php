<?php

declare(strict_types=1);

namespace Module\Factories;

use GameBoxscore\GameBoxscoreRepository;
use GameBoxscore\GameBoxscoreService;
use GameBoxscore\GameBoxscoreView;

/**
 * Composition for the GameBoxscore module entry point.
 */
final class GameBoxscoreFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function service(): GameBoxscoreService
    {
        $repository = new GameBoxscoreRepository($this->services->db(), $this->services->leagueContext());

        return new GameBoxscoreService($repository);
    }

    public function view(): GameBoxscoreView
    {
        return new GameBoxscoreView();
    }
}
