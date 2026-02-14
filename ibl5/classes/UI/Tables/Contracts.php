<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;

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
     * @param \Team $team Team object
     * @param \Shared\Contracts\SharedRepositoryInterface $sharedFunctions Shared repository
     * @param list<int> $starterPids Starter player IDs
     * @return string HTML table
     */
    public static function render($db, $result, $team, $sharedFunctions, array $starterPids = []): string
    {
        $season = new \Season($db);

        if ($sharedFunctions->isFreeAgencyModuleActive() === 1) {
            $season->endingYear++;
        }

        $cap1 = $cap2 = $cap3 = $cap4 = $cap5 = $cap6 = 0;
        /** @var list<array{player: Player, con1: int, con2: int, con3: int, con4: int, con5: int, con6: int}> $playerRows */
        $playerRows = [];

        foreach ($result as $plrRow) {
            /** @var PlayerRow $plrRow */
            $player = Player::withPlrRow($db, $plrRow);

            // Calculate contract year offset based on free agency status
            $yearOffset = ($sharedFunctions->isFreeAgencyModuleActive() === 0) ? 0 : 1;

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
            ];

            $cap1 += $contracts[1];
            $cap2 += $contracts[2];
            $cap3 += $contracts[3];
            $cap4 += $contracts[4];
            $cap5 += $contracts[5];
            $cap6 += $contracts[6];
        }

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>Age</th>
            <th>Exp</th>
            <th>Bird</th>
            <th class="sep-team"></th>
            <th class="salary"><?= substr((string) ($season->endingYear - 1), -2) ?>-<?= substr((string) $season->endingYear, -2) ?></th>
            <th class="salary"><?= substr((string) $season->endingYear, -2) ?>-<?= substr((string) ($season->endingYear + 1), -2) ?></th>
            <th class="salary"><?= substr((string) ($season->endingYear + 1), -2) ?>-<?= substr((string) ($season->endingYear + 2), -2) ?></th>
            <th class="salary"><?= substr((string) ($season->endingYear + 2), -2) ?>-<?= substr((string) ($season->endingYear + 3), -2) ?></th>
            <th class="salary"><?= substr((string) ($season->endingYear + 3), -2) ?>-<?= substr((string) ($season->endingYear + 4), -2) ?></th>
            <th class="salary"><?= substr((string) ($season->endingYear + 4), -2) ?>-<?= substr((string) ($season->endingYear + 5), -2) ?></th>
            <th class="sep-team"></th>
            <th>Tal</th>
            <th>Skl</th>
            <th>Int</th>
            <th class="sep-team"></th>
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
?>
        <tr>
            <td style="text-align: center;"><?= htmlspecialchars($player->position ?? '') ?></td>
            <?= PlayerImageHelper::renderPlayerCell((int)$player->playerID, $player->decoratedName ?? '', $starterPids) ?>
            <td style="text-align: center;"><?= (int)$player->age ?></td>
            <td style="text-align: center;"><?= (int)$player->yearsOfExperience ?></td>
            <td style="text-align: center;"><?= (int)$player->birdYears ?></td>
            <td class="sep-team"></td>
            <td class="salary"><?= $row['con1'] ?></td>
            <td class="salary"><?= $row['con2'] ?></td>
            <td class="salary"><?= $row['con3'] ?></td>
            <td class="salary"><?= $row['con4'] ?></td>
            <td class="salary"><?= $row['con5'] ?></td>
            <td class="salary"><?= $row['con6'] ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingTalent ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingSkill ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingIntangibles ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->freeAgencyLoyalty ?></td>
            <td style="text-align: center;"><?= (int)$player->freeAgencyPlayForWinner ?></td>
            <td style="text-align: center;"><?= (int)$player->freeAgencyPlayingTime ?></td>
            <td style="text-align: center;"><?= (int)$player->freeAgencySecurity ?></td>
            <td style="text-align: center;"><?= (int)$player->freeAgencyTradition ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td></td>
            <td>Cap Totals</td>
            <td></td>
            <td></td>
            <td></td>
            <td class="sep-team"></td>
            <td class="salary"><?= $cap1 ?></td>
            <td class="salary"><?= $cap2 ?></td>
            <td class="salary"><?= $cap3 ?></td>
            <td class="salary"><?= $cap4 ?></td>
            <td class="salary"><?= $cap5 ?></td>
            <td class="salary"><?= $cap6 ?></td>
            <td class="sep-team"></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="sep-team"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr class="tfoot-legend">
            <td colspan="22" style="text-align: left;">
                Key: &nbsp; <i>(Waived)*</i> &nbsp; Becomes Free Agent^
            </td>
        </tr>
    </tfoot>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
