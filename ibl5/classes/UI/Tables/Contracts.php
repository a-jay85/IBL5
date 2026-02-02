<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerImageHelper;

/**
 * Contracts - Displays team contracts table
 */
class Contracts
{
    /**
     * Render the contracts table
     *
     * @param object $db Database connection
     * @param iterable $result Player result set
     * @param object $team Team object
     * @param object $sharedFunctions Shared functions object
     * @return string HTML table
     */
    public static function render($db, $result, $team, $sharedFunctions, array $starterPids = []): string
    {
        $season = new \Season($db);

        if ($sharedFunctions->isFreeAgencyModuleActive() === 1) {
            $season->endingYear++;
        }

        $cap1 = $cap2 = $cap3 = $cap4 = $cap5 = $cap6 = 0;
        $playerRows = [];

        foreach ($result as $plrRow) {
            $player = Player::withPlrRow($db, $plrRow);

            // Calculate contract year offset based on free agency status
            $yearOffset = ($sharedFunctions->isFreeAgencyModuleActive() === 0) ? 0 : 1;

            // Calculate contract values for each year
            $contracts = [];
            for ($y = 1; $y <= 6; $y++) {
                $yearNum = $player->contractCurrentYear + ($y - 1) + $yearOffset;
                if ($yearNum < 7) {
                    if ($player->contractCurrentYear === 0) {
                        $contracts[$y] = $player->{'contractYear' . $y . 'Salary'};
                    } else {
                        $contracts[$y] = $player->{'contractYear' . $yearNum . 'Salary'};
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
            <th>Trad</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($playerRows as $row):
    $player = $row['player'];
?>
        <tr>
            <td style="text-align: center;"><?= htmlspecialchars($player->position) ?></td>
            <?= PlayerImageHelper::renderPlayerCell((int)$player->playerID, $player->decoratedName, $starterPids) ?>
            <td style="text-align: center;"><?= (int)$player->age ?></td>
            <td style="text-align: center;"><?= (int)$player->yearsOfExperience ?></td>
            <td style="text-align: center;"><?= (int)$player->birdYears ?></td>
            <td class="sep-team"></td>
            <td class="salary"><?= (int)$row['con1'] ?></td>
            <td class="salary"><?= (int)$row['con2'] ?></td>
            <td class="salary"><?= (int)$row['con3'] ?></td>
            <td class="salary"><?= (int)$row['con4'] ?></td>
            <td class="salary"><?= (int)$row['con5'] ?></td>
            <td class="salary"><?= (int)$row['con6'] ?></td>
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
            <td class="salary"><?= (int)$cap1 ?></td>
            <td class="salary"><?= (int)$cap2 ?></td>
            <td class="salary"><?= (int)$cap3 ?></td>
            <td class="salary"><?= (int)$cap4 ?></td>
            <td class="salary"><?= (int)$cap5 ?></td>
            <td class="salary"><?= (int)$cap6 ?></td>
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
        return ob_get_clean();
    }
}
