<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerOlympicTotalsViewInterface;
use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;

class PlayerOlympicTotalsView implements PlayerOlympicTotalsViewInterface
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
     * @see PlayerOlympicTotalsViewInterface::renderTotals()
     */
    public function renderTotals(int $playerID): string
    {
        $olympicsStats = $this->repository->getOlympicsStats($playerID);

        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Olympics Totals',
            careerLabel: 'Olympics Totals',
            recalculatePoints: true,
        );

        return $this->renderer->render($config, $olympicsStats);
    }
}
