<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerHeatTotalsViewInterface;
use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;

class PlayerHeatTotalsView implements PlayerHeatTotalsViewInterface
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
     * @see PlayerHeatTotalsViewInterface::renderTotals()
     */
    public function renderTotals(string $playerName): string
    {
        $heatStats = $this->repository->getHeatStats($playerName);

        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'H.E.A.T. Totals',
            careerLabel: 'H.E.A.T. Totals',
        );

        return $this->renderer->render($config, $heatStats);
    }
}
