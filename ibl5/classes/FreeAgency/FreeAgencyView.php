<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyTableRendererInterface;
use Player\Player;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 */
class FreeAgencyView
{
    private FreeAgencyTableRendererInterface $tableRenderer;

    public function __construct(
        TeamIdentityRepositoryInterface $commonRepo,
        ?FreeAgencyTableRendererInterface $tableRenderer = null
    ) {
        $this->tableRenderer = $tableRenderer ?? new FreeAgencyTableRendererView($commonRepo);
    }

    /**
     * @param array{team: Team, season: Season, capMetrics: CapMetrics, allOtherPlayers: list<Player>, teamColorsByTeamId: array<int, array{color1: string, color2: string}>, playersUnderContract: list<array{player: Player, contractAction: 'rookie_option'|'extension'|null}>, unsignedFreeAgents: list<Player>, offerPlayers: list<array{player: Player, offer: array<string, int>}>, cashPlayers: list<array{player: Player, label: string}>} $mainPageData
     */
    public function render(array $mainPageData, ?string $result = null): string
    {
        $team = $mainPageData['team'];
        $season = $mainPageData['season'];
        $capMetrics = $mainPageData['capMetrics'];
        $allOtherPlayers = $mainPageData['allOtherPlayers'];
        $teamColorsByTeamId = $mainPageData['teamColorsByTeamId'];
        $playersUnderContract = $mainPageData['playersUnderContract'];
        $unsignedFreeAgents = $mainPageData['unsignedFreeAgents'];
        $offerPlayers = $mainPageData['offerPlayers'];
        $cashPlayers = $mainPageData['cashPlayers'];

        ob_start();
        echo \UI\AlertRenderer::fromCode($result, [
            'offer_success' => ['class' => 'ibl-alert--success', 'message' => 'Your offer is legal and has been saved.'],
            'deleted' => ['class' => 'ibl-alert--info', 'message' => 'Your offer has been deleted.'],
            'already_signed' => ['class' => 'ibl-alert--warning', 'message' => 'This player was previously signed to a team this Free Agency period.'],
            'rookie_option_success' => ['class' => 'ibl-alert--success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['class' => 'ibl-alert--warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
            'csrf_error' => ['class' => 'ibl-alert--error', 'message' => 'Your session expired or the form submission was invalid. Please try again.'],
            'error' => ['class' => 'ibl-alert--error', 'message' => 'An unexpected error occurred. Please try again.'],
        ]);
        ?>
<h1 class="ibl-title">Free Agency</h1>
<img src="images/logo/<?= HtmlSanitizer::e($team->teamid) ?>.jpg" alt="Team Logo" class="team-logo-banner">
<div class="mt-6"></div>
<?= HtmlSanitizer::trusted($this->renderPlayersUnderContract($team, $season, $capMetrics, $playersUnderContract, $cashPlayers)) ?>
<div class="mt-6"></div>
<?= HtmlSanitizer::trusted($this->renderContractOffers($team, $season, $capMetrics, $offerPlayers)) ?>
<div class="mt-6"></div>
<?= HtmlSanitizer::trusted($this->renderTeamFreeAgents($team, $season, $capMetrics, $unsignedFreeAgents)) ?>
<?= HtmlSanitizer::trusted($this->renderOtherFreeAgents($team, $season, $allOtherPlayers, $teamColorsByTeamId)) ?>
        <?php
        return (string) ob_get_clean();
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
    private function renderPlayersUnderContract(Team $team, Season $season, array $capMetrics, array $players, array $cashPlayers): string
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

    /**
     * Render contract offers table
     *
     * @param Team $team Team object
     * @param Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @param list<array{player: Player, offer: array<string, int>}> $offerPlayers Pre-built offer data
     * @return string HTML table
     */
    private function renderContractOffers(Team $team, Season $season, array $capMetrics, array $offerPlayers): string
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

    /**
     * Render team free agents table
     *
     * @param Team $team Team object
     * @param Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @param list<Player> $unsignedPlayers Pre-built unsigned free agent players
     * @return string HTML table
     */
    private function renderTeamFreeAgents(Team $team, Season $season, array $capMetrics, array $unsignedPlayers): string
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

    /**
     * @param list<Player> $allOtherPlayers Pre-built Player objects from service
     * @param array<int, array{color1: string, color2: string}> $teamColorsByTeamId
     */
    private function renderOtherFreeAgents(Team $team, Season $season, array $allOtherPlayers, array $teamColorsByTeamId): string
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
