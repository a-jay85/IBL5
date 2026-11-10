<?php

declare(strict_types=1);

namespace Player\Stats\Views;

use Player\Stats\PlayerStatsRepository;
use Player\Stats\Contracts\PlayerRegularSeasonTotalsViewInterface;
use Player\Stats\Views\Contracts\PlayerSeasonTableRendererInterface;

/**
 * PlayerRegularSeasonTotalsView - Renders regular season totals table
 * 
 * Shows season-by-season totals with career totals row.
 * Uses PlayerStatsRepository for all database access.
 * 
 * @see PlayerRegularSeasonTotalsViewInterface
 *
 * @phpstan-import-type StatsRow from \Player\Stats\Contracts\PlayerStatsRepositoryInterface
 */
class PlayerRegularSeasonTotalsView implements PlayerRegularSeasonTotalsViewInterface
{
    public function __construct(
        private readonly PlayerStatsRepository $repository,
        private readonly PlayerSeasonTableRendererInterface $renderer,
    ) {
    }

    /**
     * @see PlayerViewInterface::render()
     */
    public function render(): string
    {
        return '';
    }

    /**
     * @see PlayerRegularSeasonTotalsViewInterface::renderTotals()
     */
    public function renderTotals(int $playerID): string
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Regular Season Totals',
            careerLabel: 'Career',
            linkTeamToSeason: true,
            careerPointsFromMadeShots: true,
        );

        return $this->renderer->render(
            $config,
            self::withMadeShotPointsFallback($this->repository->getHistoricalStats($playerID)),
        );
    }

    /**
     * Replace a stored pts of 0 (e.g. 2006 season) with 2*fgm + ftm + tgm.
     *
     * @param list<StatsRow> $rows
     * @return list<StatsRow>
     */
    private static function withMadeShotPointsFallback(array $rows): array
    {
        foreach ($rows as $i => $row) {
            if ($row['pts'] === 0) {
                $rows[$i]['pts'] = (2 * $row['fgm']) + $row['ftm'] + $row['tgm'];
            }
        }

        return $rows;
    }
}
