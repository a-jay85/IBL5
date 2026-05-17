<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerOlympicAveragesViewInterface;
use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;

class PlayerOlympicAveragesView implements PlayerOlympicAveragesViewInterface
{
    private PlayerStatsRepository $repository;
    private PlayerSeasonTableRendererInterface $renderer;

    public function __construct(PlayerStatsRepository $repository, PlayerSeasonTableRendererInterface $renderer)
    {
        $this->repository = $repository;
        $this->renderer = $renderer;
    }

    /**
     * @see PlayerViewInterface::render()
     */
    public function render(): string
    {
        return '';
    }

    /**
     * @see PlayerOlympicAveragesViewInterface::renderAverages()
     */
    public function renderAverages(int $playerID): string
    {
        $olympicsStats = $this->repository->getOlympicsStats($playerID);
        $careerAverages = $this->repository->getOlympicsCareerAverages($playerID);

        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::AVERAGES,
            title: 'Olympics Averages',
            careerLabel: 'Olympics Career',
        );

        return $this->renderer->render($config, $olympicsStats, $careerAverages);
    }
}
