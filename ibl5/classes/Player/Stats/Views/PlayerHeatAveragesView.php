<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerHeatAveragesViewInterface;
use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;

class PlayerHeatAveragesView implements PlayerHeatAveragesViewInterface
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
     * @see PlayerHeatAveragesViewInterface::renderAverages()
     */
    public function renderAverages(string $playerName): string
    {
        $heatStats = $this->repository->getHeatStats($playerName);
        $careerAverages = $this->repository->getHeatCareerAverages($playerName);

        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::AVERAGES,
            title: 'H.E.A.T. Averages',
            careerLabel: 'H.E.A.T. Career',
        );

        return $this->renderer->render($config, $heatStats, $careerAverages);
    }
}
