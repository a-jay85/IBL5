<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerPlayoffTotalsViewInterface;
use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;

class PlayerPlayoffTotalsView implements PlayerPlayoffTotalsViewInterface
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
     * @see PlayerPlayoffTotalsViewInterface::renderTotals()
     */
    public function renderTotals(string $playerName): string
    {
        $playoffStats = $this->repository->getPlayoffStats($playerName);

        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Playoff Totals',
            careerLabel: 'Playoff Totals',
        );

        return $this->renderer->render($config, $playoffStats);
    }
}
