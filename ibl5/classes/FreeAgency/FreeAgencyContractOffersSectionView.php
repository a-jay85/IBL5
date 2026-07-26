<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyTableRendererInterface;
use FreeAgency\Contracts\FreeAgencyContractOffersSectionViewInterface;
use Player\Player;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 */
final class FreeAgencyContractOffersSectionView implements FreeAgencyContractOffersSectionViewInterface
{
    private FreeAgencyTableRendererInterface $tableRenderer;

    public function __construct(FreeAgencyTableRendererInterface $tableRenderer)
    {
        $this->tableRenderer = $tableRenderer;
    }

    /**
     * Render contract offers table
     *
     * @param Team $team Team object
     * @param Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @param list<array{player: Player, offer: array<string, int>}> $offerPlayers Pre-built offer data
     * @return string HTML table
     */
    public function render(Team $team, Season $season, array $capMetrics, array $offerPlayers): string
    {
        ob_start();
        ?>
<div class="table-scroll-wrapper">
<div class="table-scroll-container" tabindex="0" role="region" aria-label="Contract offers">
<table class="ibl-data-table team-table fa-table sortable" style="<?= \UI\TableStyles::inlineTeamVars($team->color1, $team->color2) ?>">
    <?= HtmlSanitizer::trusted($this->tableRenderer->renderColgroups(false)) ?>
    <?= HtmlSanitizer::trusted($this->tableRenderer->renderTableHeader('Contract Offers', false, $team, false, true, $season)) ?>
    <tbody>
        <?php foreach ($offerPlayers as $offerEntry):
            $player = $offerEntry['player'];
            $offer = $offerEntry['offer'];
        ?>
        <tr>
            <td><a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= HtmlSanitizer::e($player->getPlayerID() ?? 0) ?>">Offer</a></td>
            <td><?= HtmlSanitizer::e($player->getPosition() ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->getPlayerID() ?? 0, $player->getName() ?? '') ?>
            <td><?= HtmlSanitizer::e($player->getAge() ?? 0) ?></td>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerRatings($player)) ?>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer1']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer2']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer3']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer4']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer5']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer6']) ?></td>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerPreferences($player)) ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="18" class="cap-footer-spacer"></td>
            <td colspan="10" class="text-right"><strong><?= HtmlSanitizer::e($team->name) ?> Total Salary Plus Contract Offers</strong></td>
            <?php foreach ($capMetrics['totalSalaries'] as $salary): ?>
                <td class="col-salary"><strong><?= HtmlSanitizer::e($salary) ?></strong></td>
            <?php endforeach; ?>
            <td colspan="5" class="cap-footer-spacer"></td>
        </tr>
        <?= HtmlSanitizer::trusted($this->tableRenderer->renderCapSpaceFooter($team, $capMetrics)) ?>
    </tfoot>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
