<?php

namespace UI\Tables;

use Player\Player;

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
    public static function render($db, $result, $team, $sharedFunctions): string
    {
        $season = new \Season($db);

        if ($sharedFunctions->isFreeAgencyModuleActive() == 1) {
            $season->endingYear++;
        }

        $cap1 = $cap2 = $cap3 = $cap4 = $cap5 = $cap6 = 0;
        $playerRows = [];

        $i = 0;
        foreach ($result as $plrRow) {
            $player = Player::withPlrRow($db, $plrRow);

            // Calculate contract year offset based on free agency status
            $yearOffset = ($sharedFunctions->isFreeAgencyModuleActive() == 0) ? 0 : 1;
            
            // Calculate contract values for each year
            $contracts = [];
            for ($y = 1; $y <= 6; $y++) {
                $yearNum = $player->contractCurrentYear + ($y - 1) + $yearOffset;
                if ($yearNum < 7) {
                    if ($player->contractCurrentYear == 0) {
                        $contracts[$y] = $player->{'contractYear' . $y . 'Salary'};
                    } else {
                        $contracts[$y] = $player->{'contractYear' . $yearNum . 'Salary'};
                    }
                } else {
                    $contracts[$y] = 0;
                }
            }

            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";

            $playerRows[] = [
                'player' => $player,
                'bgcolor' => $bgcolor,
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
            $i++;
        }

        ob_start();
        echo \UI\TableStyles::render('contracts', $team->color1, $team->color2);
        ?>
<table style="margin: 0 auto;" class="sortable contracts">
    <thead>
        <tr style="background-color: #<?= htmlspecialchars($team->color1) ?>;">
            <th>Pos</th>
            <th colspan="2">Player</th>
            <th>Age</th>
            <th>Exp</th>
            <th>Bird</th>
            <th class="sep-team"></th>
            <th><?= substr(($season->endingYear + -1), -2) ?>-<?= substr(($season->endingYear + 0), -2) ?></th>
            <th><?= substr(($season->endingYear + 0), -2) ?>-<?= substr(($season->endingYear + 1), -2) ?></th>
            <th><?= substr(($season->endingYear + 1), -2) ?>-<?= substr(($season->endingYear + 2), -2) ?></th>
            <th><?= substr(($season->endingYear + 2), -2) ?>-<?= substr(($season->endingYear + 3), -2) ?></th>
            <th><?= substr(($season->endingYear + 3), -2) ?>-<?= substr(($season->endingYear + 4), -2) ?></th>
            <th class="sep-team"><?= substr(($season->endingYear + 4), -2) ?>-<?= substr(($season->endingYear + 5), -2) ?></th>
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
    $bgcolor = $row['bgcolor'];
?>
        <tr style="background-color: #<?= $bgcolor ?>;">
            <td style="text-align: center;"><?= htmlspecialchars($player->position) ?></td>
            <td colspan="2"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int)$player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td style="text-align: center;"><?= (int)$player->age ?></td>
            <td style="text-align: center;"><?= (int)$player->yearsOfExperience ?></td>
            <td style="text-align: center;"><?= (int)$player->birdYears ?></td>
            <td class="sep-team"></td>
            <td><?= (int)$row['con1'] ?></td>
            <td><?= (int)$row['con2'] ?></td>
            <td><?= (int)$row['con3'] ?></td>
            <td><?= (int)$row['con4'] ?></td>
            <td><?= (int)$row['con5'] ?></td>
            <td><?= (int)$row['con6'] ?></td>
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
            <td colspan="2"><b>Cap Totals</b></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="sep-team"></td>
            <td><b><?= (int)$cap1 ?></b></td>
            <td><b><?= (int)$cap2 ?></b></td>
            <td><b><?= (int)$cap3 ?></b></td>
            <td><b><?= (int)$cap4 ?></b></td>
            <td><b><?= (int)$cap5 ?></b></td>
            <td><b><?= (int)$cap6 ?></b></td>
            <td class="sep-team"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="19"><i>Note:</i> Players whose names appear in parenthesis and with a trailing asterisk are waived players that still count against the salary cap.</td>
        </tr>
    </tfoot>
</table>
        <?php
        return ob_get_clean();
    }
}
