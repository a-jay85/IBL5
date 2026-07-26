<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyTableRendererInterface;
use FreeAgency\Contracts\FreeAgencyTeamFreeAgentsSectionViewInterface;
use Player\Player;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 */
final class FreeAgencyTeamFreeAgentsSectionView implements FreeAgencyTeamFreeAgentsSectionViewInterface
{
    private FreeAgencyTableRendererInterface $tableRenderer;

    public function __construct(FreeAgencyTableRendererInterface $tableRenderer)
    {
        $this->tableRenderer = $tableRenderer;
    }

    /**
     * Render team free agents table
     *
     * @param Team $team Team object
     * @param Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @param list<Player> $unsignedPlayers Pre-built unsigned free agent players
     * @return string HTML table
     */
    public function render(Team $team, Season $season, array $capMetrics, array $unsignedPlayers): string
    {
        ob_start();
        ?>
<div class="table-scroll-wrapper">
<div class="table-scroll-container" tabindex="0" role="region" aria-label="Unsigned free agents">
<table class="ibl-data-table team-table fa-table sortable" style="<?= \UI\TableStyles::inlineTeamVars($team->color1, $team->color2) ?>">
    <?= HtmlSanitizer::trusted($this->tableRenderer->renderColgroups(false)) ?>
    <?= HtmlSanitizer::trusted($this->tableRenderer->renderTableHeader('Unsigned Free Agents', true, $team, false, true, $season)) ?>
    <tbody>
        <?php foreach ($unsignedPlayers as $player):
            $demands = $player->getFreeAgencyDemands();
        ?>
        <tr>
            <td>
                <?php if ($capMetrics['rosterSpots'][0] > 0): ?>
                    <a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= HtmlSanitizer::e($player->getPlayerID() ?? 0) ?>">Offer</a>
                <?php endif; ?>
            </td>
            <td><?= HtmlSanitizer::e($player->getPosition() ?? '') ?></td>
            <?php $resolved = PlayerImageHelper::resolvePlayerDisplay($player->getPlayerID() ?? 0, $player->getName() ?? ''); ?>
            <td class="ibl-player-cell"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= HtmlSanitizer::e($player->getPlayerID() ?? 0) ?>">
                <?= HtmlSanitizer::trusted($resolved['thumbnail']) ?>
                <?php if (($player->getBirdYears() ?? 0) >= 3): ?>
                    *<em><?= HtmlSanitizer::e($resolved['name']) ?></em>*
                <?php else: ?>
                    <?= HtmlSanitizer::e($resolved['name']) ?>
                <?php endif; ?>
            </a></td>
            <td><?= HtmlSanitizer::e($player->getAge() ?? 0) ?></td>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerRatings($player)) ?>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerDemands($demands)) ?>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerPreferences($player)) ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
