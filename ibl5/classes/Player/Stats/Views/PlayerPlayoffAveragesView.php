<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerPlayoffAveragesViewInterface;
use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;

class PlayerPlayoffAveragesView implements PlayerPlayoffAveragesViewInterface
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
     * @see PlayerPlayoffAveragesViewInterface::renderAverages()
     */
    public function renderAverages(string $playerName): string
    {
        $playoffStats = $this->repository->getPlayoffStats($playerName);
        $careerAverages = $this->repository->getPlayoffCareerAverages($playerName);

        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::AVERAGES,
            title: 'Playoff Averages',
            careerLabel: 'Playoff Career',
        );

        return $this->renderer->render($config, $playoffStats, $careerAverages);
    }
}
