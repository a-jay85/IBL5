<?php

declare(strict_types=1);

namespace Standings;

use Security\HtmlSanitizer;
use Standings\Contracts\OlympicsStandingsViewInterface;
use Standings\Contracts\StandingsRepositoryInterface;

class OlympicsStandingsView implements OlympicsStandingsViewInterface
{
    private StandingsRepositoryInterface $repository;
    private int $seasonYear;

    /** @var list<int> */
    private array $realTeamIds;

    /**
     * @param list<int> $realTeamIds
     */
    public function __construct(
        StandingsRepositoryInterface $repository,
        int $seasonYear,
        array $realTeamIds,
    ) {
        $this->repository = $repository;
        $this->seasonYear = $seasonYear;
        $this->realTeamIds = $realTeamIds;
    }

    /**
     * @see OlympicsStandingsViewInterface::render()
     */
    public function render(): string
    {
        $allStandings = $this->repository->getAllStandings();
        $realTeamIdMap = array_flip($this->realTeamIds);

        $filtered = array_filter(
            $allStandings,
            static fn (array $row): bool => isset($realTeamIdMap[$row['teamid']]),
        );

        usort($filtered, static function (array $a, array $b): int {
            $pctCmp = (float) $b['pct'] <=> (float) $a['pct'];
            if ($pctCmp !== 0) {
                return $pctCmp;
            }
            return $b['wins'] <=> $a['wins'];
        });

        ob_start();
        ?>
        <h2 class="ibl-title"><?= HtmlSanitizer::e($this->seasonYear . ' Olympics Standings') ?></h2>
        <table class="ibl-data-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Team</th>
                    <th>W-L</th>
                    <th>Win%</th>
                    <th>Home</th>
                    <th>Away</th>
                    <th>Games Left</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rank = 0;
        foreach ($filtered as $team) {
            $rank++;
            ?>
                    <tr>
                        <td><?= HtmlSanitizer::e($rank) ?></td>
                        <td><?= HtmlSanitizer::e($team['team_name']) ?></td>
                        <td><?= HtmlSanitizer::e($team['league_record']) ?></td>
                        <td><?= HtmlSanitizer::e($team['pct']) ?></td>
                        <td><?= HtmlSanitizer::e($team['home_record']) ?></td>
                        <td><?= HtmlSanitizer::e($team['away_record']) ?></td>
                        <td><?= (int) $team['games_unplayed'] ?></td>
                    </tr>
                    <?php
        }
        ?>
            </tbody>
        </table>
        <?php
        return (string) ob_get_clean();
    }
}
