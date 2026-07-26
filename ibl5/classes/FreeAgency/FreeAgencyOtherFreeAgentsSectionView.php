<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyTableRendererInterface;
use FreeAgency\Contracts\FreeAgencyOtherFreeAgentsSectionViewInterface;
use Player\Player;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;

final class FreeAgencyOtherFreeAgentsSectionView implements FreeAgencyOtherFreeAgentsSectionViewInterface
{
    private FreeAgencyTableRendererInterface $tableRenderer;

    public function __construct(FreeAgencyTableRendererInterface $tableRenderer)
    {
        $this->tableRenderer = $tableRenderer;
    }

    /**
     * @param list<Player> $allOtherPlayers Pre-built Player objects from service
     * @param array<int, array{color1: string, color2: string}> $teamColorsByTeamId
     */
    public function render(Team $team, Season $season, array $allOtherPlayers, array $teamColorsByTeamId): string
    {
        ob_start();
        ?>
<div class="sticky-scroll-wrapper page-sticky">
<div class="sticky-scroll-container">
<table class="ibl-data-table team-table fa-table sticky-table sortable" style="<?= \UI\TableStyles::inlineTeamVars('666666', 'ffffff') ?>">
    <?= HtmlSanitizer::trusted($this->tableRenderer->renderColgroups()) ?>
    <?= HtmlSanitizer::trusted($this->tableRenderer->renderTableHeader('All Other Free Agents', false, $team, true, true, $season)) ?>
    <tbody>
        <?php
        foreach ($allOtherPlayers as $player):

            if ($player->isPlayerFreeAgent($season) && !$player->isSalaryPlaceholder()):
                $demands = $player->getFreeAgencyDemands();
                $teamColors = $teamColorsByTeamId[$player->getTeamid() ?? 0] ?? null;
        ?>
        <tr>
            <td><a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= HtmlSanitizer::e($player->getPlayerID() ?? 0) ?>">Offer</a></td>
            <td><?= HtmlSanitizer::e($player->getPosition() ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->getPlayerID() ?? 0, $player->getName() ?? '') ?>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderTeamCell($player, $teamColors)) ?>
            <td><?= HtmlSanitizer::e($player->getAge() ?? 0) ?></td>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerRatings($player)) ?>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerDemands($demands)) ?>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerPreferences($player)) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
