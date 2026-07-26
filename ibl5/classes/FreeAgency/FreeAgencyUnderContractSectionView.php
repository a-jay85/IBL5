<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyTableRendererInterface;
use FreeAgency\Contracts\FreeAgencyUnderContractSectionViewInterface;
use Player\Player;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 */
final class FreeAgencyUnderContractSectionView implements FreeAgencyUnderContractSectionViewInterface
{
    private FreeAgencyTableRendererInterface $tableRenderer;

    public function __construct(FreeAgencyTableRendererInterface $tableRenderer)
    {
        $this->tableRenderer = $tableRenderer;
    }

    /**
     * Render players under contract table
     *
     * @param Team $team Team object
     * @param Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @param list<array{player: Player, contractAction: 'rookie_option'|'extension'|null}> $players Pre-built contracted player entries with their resolved contract action
     * @param list<array{player: Player, label: string}> $cashPlayers Pre-built cash consideration players
     * @return string HTML table
     */
    public function render(Team $team, Season $season, array $capMetrics, array $players, array $cashPlayers): string
    {
        ob_start();
        ?>
<div class="table-scroll-wrapper">
<div class="table-scroll-container" tabindex="0" role="region" aria-label="Players under contract">
<table class="ibl-data-table team-table fa-table sortable" style="<?= \UI\TableStyles::inlineTeamVars($team->color1, $team->color2) ?>">
    <?= HtmlSanitizer::trusted($this->tableRenderer->renderColgroups(false, false)) ?>
    <?= HtmlSanitizer::trusted($this->tableRenderer->renderTableHeader('Players Under Contract', false, $team, false, false, $season)) ?>
    <tbody>
        <?php foreach ($players as $entry): ?>
            <?php
            $player = $entry['player'];
            if (!$player->isPlayerFreeAgent($season) || $player->isSalaryPlaceholder()):
                $futureSalaries = $player->getFutureSalaries();
                $playerName = $player->getName() ?? '';
                if (($player->getOrdinal() ?? 0) > \JSB::WAIVERS_ORDINAL) {
                    $playerName .= "*";
                }
            ?>
        <?php
        // The contract-management action ('rookie_option' | 'extension' | null)
        // is decided in FreeAgencyService; the View only maps it to a hint URL
        // and label below. 'extension' is unreachable for a contracted player
        // (an extension-eligible player is a free agent and never enters this
        // table) but is preserved to keep behavior identical.
        $contractAction = $entry['contractAction'];
        ?>
        <tr>
            <td><?= HtmlSanitizer::e($player->getPosition() ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->getPlayerID() ?? 0, $playerName) ?>
            <td><?= HtmlSanitizer::e($player->getAge() ?? 0) ?></td>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerRatings($player)) ?>
            <td class="col-salary"><?= HtmlSanitizer::e($futureSalaries[0]) ?></td>
            <?php if ($contractAction !== null): ?>
                <?php
                $actionUrl = match ($contractAction) {
                    'rookie_option' => 'modules.php?name=Player&amp;pa=rookieoption&amp;pid=' . ($player->getPlayerID() ?? 0) . '&amp;from=fa',
                    'extension' => 'modules.php?name=Player&amp;pa=negotiate&amp;pid=' . ($player->getPlayerID() ?? 0),
                };
                $actionLabel = match ($contractAction) {
                    'rookie_option' => 'Rookie Option',
                    'extension' => 'Contract Extension',
                };
                ?>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[1]) ?><a href="<?= HtmlSanitizer::trusted($actionUrl) ?>" class="contract-hint-link" data-no-abbreviate><?= HtmlSanitizer::e($actionLabel) ?></a></td>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[2]) ?></td>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[3]) ?></td>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[4]) ?></td>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[5]) ?></td>
            <?php else: ?>
                <td class="col-salary"><?= HtmlSanitizer::e($futureSalaries[1]) ?></td>
                <td class="col-salary"><?= HtmlSanitizer::e($futureSalaries[2]) ?></td>
                <td class="col-salary"><?= HtmlSanitizer::e($futureSalaries[3]) ?></td>
                <td class="col-salary"><?= HtmlSanitizer::e($futureSalaries[4]) ?></td>
                <td class="col-salary"><?= HtmlSanitizer::e($futureSalaries[5]) ?></td>
            <?php endif; ?>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerPreferences($player)) ?>
        </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php foreach ($cashPlayers as $cashEntry):
            $cashPlayer = $cashEntry['player'];
            $cashLabel = $cashEntry['label'];
            $cashFutureSalaries = $cashPlayer->getFutureSalaries();
        ?>
        <tr>
            <td></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell(0, '| ' . $cashLabel) ?>
            <td>0</td>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerRatings($cashPlayer)) ?>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[0]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[1]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[2]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[3]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[4]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[5]) ?></td>
            <?= HtmlSanitizer::trusted($this->tableRenderer->renderPlayerPreferences($cashPlayer)) ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="17" class="cap-footer-spacer"></td>
            <td colspan="10" class="text-right"><strong><?= HtmlSanitizer::e($team->name) ?> Total Salary</strong></td>
            <?php foreach ($capMetrics['totalSalaries'] as $salary): ?>
                <td class="col-salary"><strong><?= HtmlSanitizer::e($salary) ?></strong></td>
            <?php endforeach; ?>
            <td colspan="5" class="cap-footer-spacer"></td>
        </tr>
    </tfoot>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
