<?php

declare(strict_types=1);

namespace FreeAgency;

use Player\Player;
use Player\PlayerImageHelper;
use UI\TeamCellHelper;
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
    private TeamIdentityRepositoryInterface $commonRepo;

    public function __construct(TeamIdentityRepositoryInterface $commonRepo)
    {
        $this->commonRepo = $commonRepo;
    }

    /**
     * @param array{team: Team, season: Season, capMetrics: CapMetrics, allOtherPlayers: list<Player>, teamColorsByTeamId: array<int, array{color1: string, color2: string}>, playersUnderContract: list<Player>, unsignedFreeAgents: list<Player>, offerPlayers: list<array{player: Player, offer: array<string, int>}>, cashPlayers: list<array{player: Player, label: string}>} $mainPageData
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
<h2 class="ibl-title">Free Agency</h2>
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
     * @param list<Player> $players Pre-built contracted player objects
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
    <?= HtmlSanitizer::trusted($this->renderColgroups(false, false)) ?>
    <?= HtmlSanitizer::trusted($this->renderTableHeader('Players Under Contract', false, $team, false, false, $season)) ?>
    <tbody>
        <?php foreach ($players as $player): ?>
            <?php
            if (!$player->isPlayerFreeAgent($season) || $player->isSalaryPlaceholder()):
                $futureSalaries = $player->getFutureSalaries();
                $playerName = $player->name ?? '';
                if (($player->ordinal ?? 0) > \JSB::WAIVERS_ORDINAL) {
                    $playerName .= "*";
                }
            ?>
        <?php
        $hasRookieOption = $player->canRookieOption($season->phase);
        $isExtensionPhase = in_array($season->phase, ['Preseason', 'Regular Season', 'Playoffs'], true);
        $hasExtension = !$hasRookieOption && $isExtensionPhase && $player->canRenegotiateContract();
        ?>
        <tr>
            <td><?= HtmlSanitizer::e($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->playerID ?? 0, $playerName) ?>
            <td><?= HtmlSanitizer::e($player->age ?? 0) ?></td>
            <?= HtmlSanitizer::trusted($this->renderPlayerRatings($player)) ?>
            <td class="col-salary"><?= HtmlSanitizer::e($futureSalaries[0]) ?></td>
            <?php if ($hasRookieOption): ?>
                <?php $actionUrl = 'modules.php?name=Player&amp;pa=rookieoption&amp;pid=' . ($player->playerID ?? 0) . '&amp;from=fa'; $actionLabel = 'Rookie Option'; ?>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[1]) ?><a href="<?= HtmlSanitizer::trusted($actionUrl) ?>" class="contract-hint-link" data-no-abbreviate><?= HtmlSanitizer::e($actionLabel) ?></a></td>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[2]) ?></td>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[3]) ?></td>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[4]) ?></td>
                <td class="col-salary contract-hint-cell" tabindex="0"><?= HtmlSanitizer::e($futureSalaries[5]) ?></td>
            <?php elseif ($hasExtension): ?>
                <?php $actionUrl = 'modules.php?name=Player&amp;pa=negotiate&amp;pid=' . ($player->playerID ?? 0); $actionLabel = 'Contract Extension'; ?>
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
            <?= HtmlSanitizer::trusted($this->renderPlayerPreferences($player)) ?>
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
            <?= HtmlSanitizer::trusted($this->renderPlayerRatings($cashPlayer)) ?>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[0]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[1]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[2]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[3]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[4]) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($cashFutureSalaries[5]) ?></td>
            <?= HtmlSanitizer::trusted($this->renderPlayerPreferences($cashPlayer)) ?>
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
    <?= HtmlSanitizer::trusted($this->renderColgroups(false)) ?>
    <?= HtmlSanitizer::trusted($this->renderTableHeader('Contract Offers', false, $team, false, true, $season)) ?>
    <tbody>
        <?php foreach ($offerPlayers as $offerEntry):
            $player = $offerEntry['player'];
            $offer = $offerEntry['offer'];
        ?>
        <tr>
            <td><a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= HtmlSanitizer::e($player->playerID ?? 0) ?>">Offer</a></td>
            <td><?= HtmlSanitizer::e($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->playerID ?? 0, $player->name ?? '') ?>
            <td><?= HtmlSanitizer::e($player->age ?? 0) ?></td>
            <?= HtmlSanitizer::trusted($this->renderPlayerRatings($player)) ?>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer1']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer2']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer3']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer4']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer5']) ?></td>
            <td class="col-salary"><?= HtmlSanitizer::e($offer['offer6']) ?></td>
            <?= HtmlSanitizer::trusted($this->renderPlayerPreferences($player)) ?>
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
        <?= HtmlSanitizer::trusted($this->renderCapSpaceFooter($team, $capMetrics)) ?>
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
    <?= HtmlSanitizer::trusted($this->renderColgroups(false)) ?>
    <?= HtmlSanitizer::trusted($this->renderTableHeader('Unsigned Free Agents', true, $team, false, true, $season)) ?>
    <tbody>
        <?php foreach ($unsignedPlayers as $player):
            $demands = $player->getFreeAgencyDemands();
        ?>
        <tr>
            <td>
                <?php if ($capMetrics['rosterSpots'][0] > 0): ?>
                    <a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= HtmlSanitizer::e($player->playerID ?? 0) ?>">Offer</a>
                <?php endif; ?>
            </td>
            <td><?= HtmlSanitizer::e($player->position ?? '') ?></td>
            <?php $resolved = PlayerImageHelper::resolvePlayerDisplay($player->playerID ?? 0, $player->name ?? ''); ?>
            <td class="ibl-player-cell"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= HtmlSanitizer::e($player->playerID ?? 0) ?>">
                <?= HtmlSanitizer::trusted($resolved['thumbnail']) ?>
                <?php if (($player->birdYears ?? 0) >= 3): ?>
                    *<em><?= HtmlSanitizer::e($resolved['name']) ?></em>*
                <?php else: ?>
                    <?= HtmlSanitizer::e($resolved['name']) ?>
                <?php endif; ?>
            </a></td>
            <td><?= HtmlSanitizer::e($player->age ?? 0) ?></td>
            <?= HtmlSanitizer::trusted($this->renderPlayerRatings($player)) ?>
            <?= HtmlSanitizer::trusted($this->renderPlayerDemands($demands)) ?>
            <?= HtmlSanitizer::trusted($this->renderPlayerPreferences($player)) ?>
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
    <?= HtmlSanitizer::trusted($this->renderColgroups()) ?>
    <?= HtmlSanitizer::trusted($this->renderTableHeader('All Other Free Agents', false, $team, true, true, $season)) ?>
    <tbody>
        <?php
        foreach ($allOtherPlayers as $player):

            if ($player->isPlayerFreeAgent($season) && !$player->isSalaryPlaceholder()):
                $demands = $player->getFreeAgencyDemands();
                $teamColors = $teamColorsByTeamId[$player->teamid ?? 0] ?? null;
        ?>
        <tr>
            <td><a href="modules.php?name=FreeAgency&amp;pa=negotiate&amp;pid=<?= HtmlSanitizer::e($player->playerID ?? 0) ?>">Offer</a></td>
            <td><?= HtmlSanitizer::e($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderFlexiblePlayerCell($player->playerID ?? 0, $player->name ?? '') ?>
            <?= HtmlSanitizer::trusted($this->renderTeamCell($player, $teamColors)) ?>
            <td><?= HtmlSanitizer::e($player->age ?? 0) ?></td>
            <?= HtmlSanitizer::trusted($this->renderPlayerRatings($player)) ?>
            <?= HtmlSanitizer::trusted($this->renderPlayerDemands($demands)) ?>
            <?= HtmlSanitizer::trusted($this->renderPlayerPreferences($player)) ?>
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

    /**
     * Render colgroups for table column organization
     *
     * @return string HTML colgroup elements
     */
    private function renderColgroups(bool $showTeamColumn = true, bool $showOptionsColumn = true): string
    {
        ob_start();
        if ($showTeamColumn && $showOptionsColumn) {
            ?><colgroup span="4"></colgroup><?php // Options, Pos, Player, Team
        } elseif ($showTeamColumn) {
            ?><colgroup span="3"></colgroup><?php // Pos, Player, Team
        } elseif ($showOptionsColumn) {
            ?><colgroup span="3"></colgroup><?php // Options, Pos, Player
        } else {
            ?><colgroup span="2"></colgroup><?php // Pos, Player
        }
        ?><colgroup span="7"></colgroup><colgroup span="7"></colgroup><colgroup span="8"></colgroup><colgroup span="3"></colgroup><colgroup span="6"></colgroup><colgroup span="5"></colgroup>
        <?php // Age,2ga,2g%,fta,ft%,3ga,3g% | orb,drb,ast,stl,tvr,blk,foul | oo,do,po,to,od,dd,pd,td | T,S,I | Yr1-6 | Loy,PFW,PT,Sec,Trd
        return (string) ob_get_clean();
    }

    /**
     * Render table header
     *
     * @param string $title Table title to display in header
     * @param bool $showBirdRightsNote Whether to show the Bird Rights note
     * @param Team $team Team object for name display
     * @return string HTML table header
     */
    private function renderTableHeader(string $title, bool $showBirdRightsNote, Team $team, bool $showTeamColumn = true, bool $showOptionsColumn = true, ?Season $season = null): string
    {
        $fullTitle = $title;

        $colspan = 38 + ($showTeamColumn ? 1 : 0) + ($showOptionsColumn ? 1 : 0);

        // Season year headers (same format as Contracts table)
        $yearHeaders = [];
        if ($season !== null) {
            $baseYear = $season->endingYear;
            if ($season->isOffseasonPhase()) {
                $baseYear++;
            }
            for ($i = 0; $i < 6; $i++) {
                $yearHeaders[] = substr((string) ($baseYear - 1 + $i), -2) . '-' . substr((string) ($baseYear + $i), -2);
            }
        } else {
            $yearHeaders = ['Yr1', 'Yr2', 'Yr3', 'Yr4', 'Yr5', 'Yr6'];
        }

        ob_start();
        ?>
    <thead>
        <tr>
            <th colspan="<?= HtmlSanitizer::e($colspan) ?>">
                <?= HtmlSanitizer::e($fullTitle) ?>
                <?php if ($showBirdRightsNote): ?>
                    <br><small>(Note: * and <em>italicized</em> indicates player has Bird Rights)</small>
                <?php endif; ?>
            </th>
        </tr>
        <tr>
            <?php if ($showOptionsColumn): ?>
            <th></th>
            <?php endif; ?>
            <th>Pos</th>
            <th>Player</th>
            <?php if ($showTeamColumn): ?>
            <th class="sep-r-team">Team</th>
            <?php endif; ?>
            <th>Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>T</th>
            <th>S</th>
            <th class="sep-r-team">I</th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[0]) ?></th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[1]) ?></th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[2]) ?></th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[3]) ?></th>
            <th class="col-salary"><?= HtmlSanitizer::e($yearHeaders[4]) ?></th>
            <th class="col-salary sep-r-team"><?= HtmlSanitizer::e($yearHeaders[5]) ?></th>
            <th>Loy</th>
            <th>PFW</th>
            <th>PT</th>
            <th>Sec</th>
            <th>Trd</th>
        </tr>
    </thead>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{color1: string, color2: string}|null $teamColors
     */
    private function renderTeamCell(Player $player, ?array $teamColors = null): string
    {
        $teamId = $player->teamid ?? 0;

        if ($teamId === 0) {
            return '<td>FA</td>';
        }

        $teamName = $player->teamName ?? '';
        if ($teamName === '') {
            $teamName = $this->commonRepo->getTeamnameFromTeamID($teamId) ?? '';
        }

        return TeamCellHelper::renderTeamCellOrFreeAgent(
            $teamId,
            $teamName,
            $teamColors['color1'] ?? 'D4AF37',
            $teamColors['color2'] ?? '1e3a5f',
        );
    }

    /**
     * Render player ratings cells
     *
     * @param Player $player
     * @return string HTML table cells
     */
    private function renderPlayerRatings(Player $player): string
    {
        ob_start();
        ?>
<td><?= HtmlSanitizer::e($player->ratingFieldGoalAttempts ?? 0) ?></td>
<td class="sep-r-weak"><?= HtmlSanitizer::e($player->ratingFieldGoalPercentage ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingFreeThrowAttempts ?? 0) ?></td>
<td class="sep-r-weak"><?= HtmlSanitizer::e($player->ratingFreeThrowPercentage ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingThreePointAttempts ?? 0) ?></td>
<td class="sep-r-team"><?= HtmlSanitizer::e($player->ratingThreePointPercentage ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingOffensiveRebounds ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingDefensiveRebounds ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingAssists ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingSteals ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingTurnovers ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingBlocks ?? 0) ?></td>
<td class="sep-r-team"><?= HtmlSanitizer::e($player->ratingFouls ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingOutsideOffense ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingDriveOffense ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingPostOffense ?? 0) ?></td>
<td class="sep-r-weak"><?= HtmlSanitizer::e($player->ratingTransitionOffense ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingOutsideDefense ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingDriveDefense ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingPostDefense ?? 0) ?></td>
<td class="sep-r-team"><?= HtmlSanitizer::e($player->ratingTransitionDefense ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingTalent ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->ratingSkill ?? 0) ?></td>
<td class="sep-r-team"><?= HtmlSanitizer::e($player->ratingIntangibles ?? 0) ?></td>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render player preferences cells
     *
     * @param Player $player
     * @return string HTML table cells
     */
    private function renderPlayerPreferences(Player $player): string
    {
        ob_start();
        ?>
<td><?= HtmlSanitizer::e($player->freeAgencyLoyalty ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->freeAgencyPlayForWinner ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->freeAgencyPlayingTime ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->freeAgencySecurity ?? 0) ?></td>
<td><?= HtmlSanitizer::e($player->freeAgencyTradition ?? 0) ?></td>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render player demands cells
     *
     * @param array<string, int> $demands
     * @return string HTML table cells
     */
    private function renderPlayerDemands(array $demands): string
    {
        $dem1 = $demands['dem1'] ?? 0;
        $dem2 = $demands['dem2'] ?? 0;
        $dem3 = $demands['dem3'] ?? 0;
        $dem4 = $demands['dem4'] ?? 0;
        $dem5 = $demands['dem5'] ?? 0;
        $dem6 = $demands['dem6'] ?? 0;

        ob_start();
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem1 !== 0 ? $dem1 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem2 !== 0 ? $dem2 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem3 !== 0 ? $dem3 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem4 !== 0 ? $dem4 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem5 !== 0 ? $dem5 : '') . '</td>';
        echo '<td class="col-salary">' . HtmlSanitizer::e($dem6 !== 0 ? $dem6 : '') . '</td>';
        return (string) ob_get_clean();
    }

    /**
     * Render cap space footer rows
     *
     * @param Team $team Team object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @return string HTML table rows
     */
    private function renderCapSpaceFooter(Team $team, array $capMetrics): string
    {
        $MLEicon = ($team->has_mle === 1) ? "\u{2705}" : "\u{274C}";
        $LLEicon = ($team->has_lle === 1) ? "\u{2705}" : "\u{274C}";

        ob_start();
        ?>
<tr class="cap-footer-row">
    <td colspan="18" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label">Soft Cap Space</td>
    <?php foreach ($capMetrics['softCapSpace'] as $capSpace): ?>
        <td class="col-salary"><?= HtmlSanitizer::e($capSpace) ?></td>
    <?php endforeach; ?>
    <td class="cap-footer-spacer"></td>
    <td colspan="2" class="cap-footer-label"><strong>MLE:</strong></td>
    <td><?= HtmlSanitizer::e($MLEicon) ?></td>
    <td class="cap-footer-spacer"></td>
</tr>
<tr class="cap-footer-row">
    <td colspan="18" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label">Hard Cap Space</td>
    <?php foreach ($capMetrics['hardCapSpace'] as $capSpace): ?>
        <td class="col-salary"><?= HtmlSanitizer::e($capSpace) ?></td>
    <?php endforeach; ?>
    <td class="cap-footer-spacer"></td>
    <td colspan="2" class="cap-footer-label"><strong>LLE:</strong></td>
    <td><?= HtmlSanitizer::e($LLEicon) ?></td>
    <td class="cap-footer-spacer"></td>
</tr>
<tr class="cap-footer-row">
    <td colspan="18" class="cap-footer-spacer"></td>
    <td colspan="10" class="cap-footer-label">Empty Roster Slots</td>
    <?php foreach ($capMetrics['rosterSpots'] as $spots): ?>
        <td class="col-salary"><?= HtmlSanitizer::e($spots) ?></td>
    <?php endforeach; ?>
    <td colspan="5" class="cap-footer-spacer"></td>
</tr>
        <?php
        return (string) ob_get_clean();
    }
}
