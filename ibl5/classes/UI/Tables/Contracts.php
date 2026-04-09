<?php

declare(strict_types=1);

namespace UI\Tables;

use League\League;
use Player\Player;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * Contracts - Displays team contracts table
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class Contracts
{
    /**
     * Render the contracts table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, array<string, mixed>> $result Player result set
     * @param Team $team Team object
     * @param Season $season Season object
     * @param list<int> $starterPids Starter player IDs
     * @param list<int> $excludeFromCapPids PIDs to exclude from cap total sums (e.g. outgoing trade players)
     * @param bool $showActionLinks When false, Rookie Option / Contract Extension eligibility markers still render but are not clickable links (used when previewing another GM's roster in the Trading module)
     * @return string HTML table
     */
    public static function render(\mysqli $db, iterable $result, Team $team, Season $season, array $starterPids = [], array $excludeFromCapPids = [], bool $showActionLinks = true): string
    {
        $isFreeAgency = $season->isOffseasonPhase();
        // Contract extensions are only actionable during in-season phases. During
        // Draft and Free Agency, the eligibility marker still renders (so GMs can
        // see who will be eligible next season) but it renders as a non-clickable
        // span rather than a hyperlink to the negotiation form.
        $isExtensionActionablePhase = in_array($season->phase, ['Preseason', 'HEAT', 'Regular Season', 'Playoffs'], true);

        if ($isFreeAgency) {
            $season->endingYear++;
        }

        $cap1 = $cap2 = $cap3 = $cap4 = $cap5 = $cap6 = 0;
        /** @var list<array{player: Player, con1: int, con2: int, con3: int, con4: int, con5: int, con6: int, isCashRow: bool}> $playerRows */
        $playerRows = [];

        foreach ($result as $plrRow) {
            /** @var PlayerRow $plrRow */
            $player = Player::withPlrRow($db, $plrRow);

            // Calculate contract year offset based on free agency phase
            $yearOffset = $isFreeAgency ? 1 : 0;

            // Build salary lookup from explicit properties
            $salaryByYear = [
                1 => $player->contractYear1Salary ?? 0,
                2 => $player->contractYear2Salary ?? 0,
                3 => $player->contractYear3Salary ?? 0,
                4 => $player->contractYear4Salary ?? 0,
                5 => $player->contractYear5Salary ?? 0,
                6 => $player->contractYear6Salary ?? 0,
            ];

            // Calculate contract values for each year
            /** @var array<int, int> $contracts */
            $contracts = [];
            for ($y = 1; $y <= 6; $y++) {
                $contractCurrentYear = $player->contractCurrentYear ?? 0;
                $yearNum = $contractCurrentYear + ($y - 1) + $yearOffset;
                if ($yearNum < 7) {
                    if ($contractCurrentYear === 0) {
                        $contracts[$y] = $salaryByYear[$y];
                    } else {
                        $contracts[$y] = $salaryByYear[$yearNum] ?? 0;
                    }
                } else {
                    $contracts[$y] = 0;
                }
            }

            $playerRows[] = [
                'player' => $player,
                'con1' => $contracts[1],
                'con2' => $contracts[2],
                'con3' => $contracts[3],
                'con4' => $contracts[4],
                'con5' => $contracts[5],
                'con6' => $contracts[6],
                'isCashRow' => (bool) ($plrRow['isCashRow'] ?? false),
            ];

            $pid = (int) ($plrRow['pid'] ?? 0);
            if (!in_array($pid, $excludeFromCapPids, true)) {
                $cap1 += $contracts[1];
                $cap2 += $contracts[2];
                $cap3 += $contracts[3];
                $cap4 += $contracts[4];
                $cap5 += $contracts[5];
                $cap6 += $contracts[6];
            }
        }

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table contracts-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>Age</th>
            <th>Exp</th>
            <th class="sep-r-team">Bird</th>
            <th class="col-salary"><?= substr((string) ($season->endingYear - 1), -2) ?>-<?= substr((string) $season->endingYear, -2) ?></th>
            <th class="col-salary"><?= substr((string) $season->endingYear, -2) ?>-<?= substr((string) ($season->endingYear + 1), -2) ?></th>
            <th class="col-salary"><?= substr((string) ($season->endingYear + 1), -2) ?>-<?= substr((string) ($season->endingYear + 2), -2) ?></th>
            <th class="col-salary"><?= substr((string) ($season->endingYear + 2), -2) ?>-<?= substr((string) ($season->endingYear + 3), -2) ?></th>
            <th class="col-salary"><?= substr((string) ($season->endingYear + 3), -2) ?>-<?= substr((string) ($season->endingYear + 4), -2) ?></th>
            <th class="col-salary sep-r-team"><?= substr((string) ($season->endingYear + 4), -2) ?>-<?= substr((string) ($season->endingYear + 5), -2) ?></th>
            <th>Tal</th>
            <th>Skl</th>
            <th class="sep-r-team">Int</th>
            <th>Loy</th>
            <th>PFW</th>
            <th>PT</th>
            <th>Sec</th>
            <th>Trd</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($playerRows as $row):
    $player = $row['player'];
    $isCashPlayer = $row['isCashRow'] || str_contains($player->name ?? '', '|');
    $hasRookieOption = !$isCashPlayer && $player->canRookieOption($season->phase);
    // Pass $season so eligibility reflects the incoming season's state during Draft / Free Agency.
    $hasExtension = !$isCashPlayer && !$hasRookieOption && $player->canRenegotiateContract($season);

    $hintActionUrl = '';
    $hintActionLabel = '';
    $renderHintAsLink = false;
    if ($hasRookieOption) {
        $hintActionUrl = 'modules.php?name=Player&amp;pa=rookieoption&amp;pid=' . (int)$player->playerID . '&amp;from=team';
        $hintActionLabel = 'Rookie Option';
        $renderHintAsLink = $showActionLinks;
    } elseif ($hasExtension) {
        $hintActionUrl = 'modules.php?name=Player&amp;pa=negotiate&amp;pid=' . (int)$player->playerID;
        $hintActionLabel = 'Contract Extension';
        $renderHintAsLink = $showActionLinks && $isExtensionActionablePhase;
    }
?>
        <tr<?= $row['isCashRow'] ? ' data-cash-row' : '' ?>>
            <td><?= HtmlSanitizer::e($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderPlayerCell((int)$player->playerID, $player->decoratedName ?? '', $starterPids, $player->nameStatusClass) ?>
            <td><?= (int)$player->age ?></td>
            <td><?= (int)$player->yearsOfExperience ?></td>
            <td class="sep-r-team"><?= (int)$player->birdYears ?></td>
            <td class="col-salary"><?= $row['con1'] ?></td>
            <?php if ($hasRookieOption || $hasExtension): ?>
            <td class="col-salary contract-hint-cell" tabindex="0"><?= $row['con2'] === 0 ? '0*' : $row['con2'] ?><?php if ($renderHintAsLink): ?><a href="<?= $hintActionUrl ?>" class="contract-hint-link" data-no-abbreviate><?= $hintActionLabel ?></a><?php else: ?><span class="contract-hint-link" data-no-abbreviate><?= $hintActionLabel ?></span><?php endif; ?></td>
            <td class="col-salary contract-hint-cell" tabindex="0"><?= $row['con3'] ?></td>
            <td class="col-salary contract-hint-cell" tabindex="0"><?= $row['con4'] ?></td>
            <td class="col-salary contract-hint-cell" tabindex="0"><?= $row['con5'] ?></td>
            <td class="col-salary contract-hint-cell sep-r-team" tabindex="0"><?= $row['con6'] ?></td>
            <?php else: ?>
            <td class="col-salary"><?= $row['con2'] ?></td>
            <td class="col-salary"><?= $row['con3'] ?></td>
            <td class="col-salary"><?= $row['con4'] ?></td>
            <td class="col-salary"><?= $row['con5'] ?></td>
            <td class="col-salary sep-r-team"><?= $row['con6'] ?></td>
            <?php endif; ?>
            <td><?= (int)$player->ratingTalent ?></td>
            <td><?= (int)$player->ratingSkill ?></td>
            <td class="sep-r-team"><?= (int)$player->ratingIntangibles ?></td>
            <td><?= (int)$player->freeAgencyLoyalty ?></td>
            <td><?= (int)$player->freeAgencyPlayForWinner ?></td>
            <td><?= (int)$player->freeAgencyPlayingTime ?></td>
            <td><?= (int)$player->freeAgencySecurity ?></td>
            <td><?= (int)$player->freeAgencyTradition ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
<?php $hardCapMax = League::HARD_CAP_MAX; ?>
        <tr>
            <td></td>
            <td class="sticky-col">Cap Totals</td>
            <td></td>
            <td></td>
            <td class="sep-r-team"></td>
            <td class="col-salary<?= $cap1 > $hardCapMax ? ' cap-exceeded' : '' ?>"><?= $cap1 ?></td>
            <td class="col-salary<?= $cap2 > $hardCapMax ? ' cap-exceeded' : '' ?>"><?= $cap2 ?></td>
            <td class="col-salary<?= $cap3 > $hardCapMax ? ' cap-exceeded' : '' ?>"><?= $cap3 ?></td>
            <td class="col-salary<?= $cap4 > $hardCapMax ? ' cap-exceeded' : '' ?>"><?= $cap4 ?></td>
            <td class="col-salary<?= $cap5 > $hardCapMax ? ' cap-exceeded' : '' ?>"><?= $cap5 ?></td>
            <td class="col-salary sep-r-team<?= $cap6 > $hardCapMax ? ' cap-exceeded' : '' ?>"><?= $cap6 ?></td>
            <td></td>
            <td></td>
            <td class="sep-r-team"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr class="tfoot-legend">
            <td colspan="19" style="text-align: left;">
                Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
            </td>
        </tr>
    </tfoot>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
