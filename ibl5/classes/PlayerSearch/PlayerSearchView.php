<?php

declare(strict_types=1);

namespace PlayerSearch;

use PlayerSearch\Contracts\PlayerSearchViewInterface;

/**
 * @see PlayerSearchViewInterface
 */
class PlayerSearchView implements PlayerSearchViewInterface
{
    private PlayerSearchService $service;

    public function __construct(PlayerSearchService $service)
    {
        $this->service = $service;
    }

    /**
     * @see PlayerSearchViewInterface::renderSearchForm()
     */
    public function renderSearchForm(array $params): string
    {
        $positions = \JSB::PLAYER_POSITIONS;

        // Extract form parameters with defaults
        $pos = $params['pos'] ?? '';
        $age = $params['age'] ?? '';
        $talent = $params['talent'] ?? '';
        $skill = $params['skill'] ?? '';
        $intangibles = $params['intangibles'] ?? '';
        $Clutch = $params['Clutch'] ?? '';
        $Consistency = $params['Consistency'] ?? '';
        $college = $params['college'] ?? '';
        $active = $params['active'] ?? '';
        $exp = $params['exp'] ?? '';
        $exp_max = $params['exp_max'] ?? '';
        $bird = $params['bird'] ?? '';
        $bird_max = $params['bird_max'] ?? '';
        $search_name = $params['search_name'] ?? '';

        // Rating values
        $r_fga = $params['r_fga'] ?? '';
        $r_fgp = $params['r_fgp'] ?? '';
        $r_fta = $params['r_fta'] ?? '';
        $r_ftp = $params['r_ftp'] ?? '';
        $r_tga = $params['r_tga'] ?? '';
        $r_tgp = $params['r_tgp'] ?? '';
        $r_orb = $params['r_orb'] ?? '';
        $r_drb = $params['r_drb'] ?? '';
        $r_ast = $params['r_ast'] ?? '';
        $r_stl = $params['r_stl'] ?? '';
        $r_blk = $params['r_blk'] ?? '';
        $r_to = $params['r_to'] ?? '';
        $r_foul = $params['r_foul'] ?? '';

        // Skill values
        $oo = $params['oo'] ?? '';
        $do = $params['do'] ?? '';
        $po = $params['po'] ?? '';
        $to = $params['to'] ?? '';
        $od = $params['od'] ?? '';
        $dd = $params['dd'] ?? '';
        $pd = $params['pd'] ?? '';
        $td = $params['td'] ?? '';

        ob_start();
        ?>
<p>Age is less than or equal to the age entered. All other fields are greater than or equal to the amount entered.</p>
<p>Partial matches on a name or college are okay and are <strong>not</strong> case sensitive<br>
(e.g., entering "Dard" will match with "Darden" and "Bedard").</p>

<style>
    form table td:nth-child(even) {
        padding-right: 1rem;
    }
</style>

<form name="Search" method="post" action="modules.php?name=Player_Search">
    <fieldset style="margin-bottom: 15px;">
        <legend>Basics</legend>
        <table border="0" cellpadding="2" cellspacing="0">
            <tr>
                <td><label for="search_name">Name:</label></td>
                <td><input id="search_name" type="text" name="search_name" size="25" value="<?= htmlspecialchars((string)$search_name) ?>"></td>
                <td><label for="pos">Position:</label></td>
                <td>
                    <select id="pos" name="pos">
                        <option value="">-</option>
                        <?php foreach ($positions as $position): ?>
                            <option value="<?= htmlspecialchars($position) ?>"<?= ($pos === $position) ? ' selected' : '' ?>><?= htmlspecialchars($position) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><label for="active">Include Retirees?</label></td>
                <td>
                    <select id="active" name="active">
                        <option value="1"<?= ($active === 1) ? ' selected' : '' ?>>Yes</option>
                        <option value="0"<?= ($active === 0 || $active === null) ? ' selected' : '' ?>>No</option>
                    </select>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset style="margin-bottom: 15px;">
        <legend>Years</legend>
        <table border="0" cellpadding="2" cellspacing="0">
            <tr>
                <td><label for="age">Max Age:</label></td>
                <td><input id="age" type="text" name="age" size="2" value="<?= htmlspecialchars((string)$age) ?>"></td>
                <td><label for="exp">Min Exp:</label></td>
                <td><input id="exp" type="text" name="exp" size="2" value="<?= htmlspecialchars((string)$exp) ?>"></td>
                <td><label for="exp_max">Max Exp:</label></td>
                <td><input id="exp_max" type="text" name="exp_max" size="2" value="<?= htmlspecialchars((string)$exp_max) ?>"></td>
                <td><label for="bird">Min Bird:</label></td>
                <td><input id="bird" type="text" name="bird" size="2" value="<?= htmlspecialchars((string)$bird) ?>"></td>
                <td><label for="bird_max">Max Bird:</label></td>
                <td><input id="bird_max" type="text" name="bird_max" size="2" value="<?= htmlspecialchars((string)$bird_max) ?>"></td>
            </tr>
        </table>
    </fieldset>

    <fieldset style="margin-bottom: 15px;">
        <legend>Statistical Ratings</legend>
        <table border="0" cellpadding="2" cellspacing="0">
            <tr>
                <td><label for="r_fga">2ga:</label></td>
                <td><input id="r_fga" type="text" name="r_fga" size="2" value="<?= htmlspecialchars((string)$r_fga) ?>"></td>
                <td><label for="r_fgp">2gp:</label></td>
                <td><input id="r_fgp" type="text" name="r_fgp" size="2" value="<?= htmlspecialchars((string)$r_fgp) ?>"></td>
                <td><label for="r_fta">fta:</label></td>
                <td><input id="r_fta" type="text" name="r_fta" size="2" value="<?= htmlspecialchars((string)$r_fta) ?>"></td>
                <td><label for="r_ftp">ftp:</label></td>
                <td><input id="r_ftp" type="text" name="r_ftp" size="2" value="<?= htmlspecialchars((string)$r_ftp) ?>"></td>
                <td><label for="r_tga">3ga:</label></td>
                <td><input id="r_tga" type="text" name="r_tga" size="2" value="<?= htmlspecialchars((string)$r_tga) ?>"></td>
                <td><label for="r_tgp">3gp:</label></td>
                <td><input id="r_tgp" type="text" name="r_tgp" size="2" value="<?= htmlspecialchars((string)$r_tgp) ?>"></td>
            </tr>
            <tr>
                <td><label for="r_orb">orb:</label></td>
                <td><input id="r_orb" type="text" name="r_orb" size="2" value="<?= htmlspecialchars((string)$r_orb) ?>"></td>
                <td><label for="r_drb">drb:</label></td>
                <td><input id="r_drb" type="text" name="r_drb" size="2" value="<?= htmlspecialchars((string)$r_drb) ?>"></td>
                <td><label for="r_ast">ast:</label></td>
                <td><input id="r_ast" type="text" name="r_ast" size="2" value="<?= htmlspecialchars((string)$r_ast) ?>"></td>
                <td><label for="r_stl">stl:</label></td>
                <td><input id="r_stl" type="text" name="r_stl" size="2" value="<?= htmlspecialchars((string)$r_stl) ?>"></td>
                <td><label for="r_blk">blk:</label></td>
                <td><input id="r_blk" type="text" name="r_blk" size="2" value="<?= htmlspecialchars((string)$r_blk) ?>"></td>
                <td><label for="r_to">tvr:</label></td>
                <td><input id="r_to" type="text" name="r_to" size="2" value="<?= htmlspecialchars((string)$r_to) ?>"></td>
                <td><label for="r_foul">foul:</label></td>
                <td><input id="r_foul" type="text" name="r_foul" size="2" value="<?= htmlspecialchars((string)$r_foul) ?>"></td>
            </tr>
        </table>
    </fieldset>

    <fieldset style="margin-bottom: 15px;">
        <legend>Offensive/Defensive Ratings</legend>
        <table border="0" cellpadding="2" cellspacing="0">
            <tr>
                <td><label for="oo">oo:</label></td>
                <td><input id="oo" type="text" name="oo" size="1" value="<?= htmlspecialchars((string)$oo) ?>"></td>
                <td><label for="do">do:</label></td>
                <td><input id="do" type="text" name="do" size="1" value="<?= htmlspecialchars((string)$do) ?>"></td>
                <td><label for="po">po:</label></td>
                <td><input id="po" type="text" name="po" size="1" value="<?= htmlspecialchars((string)$po) ?>"></td>
                <td><label for="to">to:</label></td>
                <td><input id="to" type="text" name="to" size="1" value="<?= htmlspecialchars((string)$to) ?>"></td>
            </tr>
            <tr>
                <td><label for="od">od:</label></td>
                <td><input id="od" type="text" name="od" size="1" value="<?= htmlspecialchars((string)$od) ?>"></td>
                <td><label for="dd">dd:</label></td>
                <td><input id="dd" type="text" name="dd" size="1" value="<?= htmlspecialchars((string)$dd) ?>"></td>
                <td><label for="pd">pd:</label></td>
                <td><input id="pd" type="text" name="pd" size="1" value="<?= htmlspecialchars((string)$pd) ?>"></td>
                <td><label for="td">td:</label></td>
                <td><input id="td" type="text" name="td" size="1" value="<?= htmlspecialchars((string)$td) ?>"></td>
            </tr>
        </table>
    </fieldset>

    <fieldset style="margin-bottom: 15px;">
        <legend>Misc. Attributes</legend>
        <table border="0" cellpadding="2" cellspacing="0">
            <tr>
                <td><label for="talent">Talent:</label></td>
                <td><input id="talent" type="text" name="talent" size="1" value="<?= htmlspecialchars((string)$talent) ?>"></td>
                <td><label for="skill">Skill:</label></td>
                <td><input id="skill" type="text" name="skill" size="1" value="<?= htmlspecialchars((string)$skill) ?>"></td>
                <td><label for="intangibles">Intangibles:</label></td>
                <td><input id="intangibles" type="text" name="intangibles" size="1" value="<?= htmlspecialchars((string)$intangibles) ?>"></td>
            </tr>
            <tr>
                <td><label for="Clutch">Clutch:</label></td>
                <td><input id="Clutch" type="text" name="Clutch" size="1" value="<?= htmlspecialchars((string)$Clutch) ?>"></td>
                <td><label for="Consistency">Consistency:</label></td>
                <td><input id="Consistency" type="text" name="Consistency" size="1" value="<?= htmlspecialchars((string)$Consistency) ?>"></td>
                <td><label for="college">College:</label></td>
                <td><input id="college" type="text" name="college" size="20" value="<?= htmlspecialchars((string)$college) ?>"></td>
            </tr>
        </table>
    </fieldset>

    <input type="button" value="Reset" onclick="resetPlayerSearch();" style="margin-right: 20px; background-color: #f0f0f0; color: #666; border: 1px solid #999; padding: 6px 12px; cursor: pointer;">
    <input type="submit" value="Search for Player!" style="background-color: #28a745; color: white; border: 2px solid #1e7e34; padding: 8px 20px; cursor: pointer; font-weight: bold;">
</form>

<script type="text/javascript">
function resetPlayerSearch() {
    var form = document.forms['Search'];
    if (!form) {
        return;
    }

    var inputs = form.getElementsByTagName('input');
    var selects = form.getElementsByTagName('select');

    // Clear all text input fields
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].type === 'text') {
            inputs[i].value = '';
        }
    }

    // Clear all select fields except 'active'
    for (var i = 0; i < selects.length; i++) {
        if (selects[i].name !== 'active') {
            selects[i].value = '';
        }
    }

    return false;
}
</script>
<br>
        <?php
        return ob_get_clean();
    }

    /**
     * @see PlayerSearchViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<table class="sortable" border="1" cellpadding="0" cellspacing="0">
    <tr>
        <th>Pos</th>
        <th>Player</th>
        <th>Age</th>
        <th>Team</th>
        <th>Exp</th>
        <th>Bird</th>
        <th>2ga</th>
        <th>2gp</th>
        <th>fta</th>
        <th>ftp</th>
        <th>3ga</th>
        <th>3gp</th>
        <th>orb</th>
        <th>drb</th>
        <th>ast</th>
        <th>stl</th>
        <th>tvr</th>
        <th>blk</th>
        <th>foul</th>
        <th>oo</th>
        <th>do</th>
        <th>po</th>
        <th>to</th>
        <th>od</th>
        <th>dd</th>
        <th>pd</th>
        <th>td</th>
        <th>Talent</th>
        <th>Skill</th>
        <th>Intangibles</th>
        <th>Clutch</th>
        <th>Consistency</th>
        <th>College</th>
    </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @see PlayerSearchViewInterface::renderPlayerRow()
     */
    public function renderPlayerRow(\Player\PlayerData $player, int $rowIndex): string
    {
        $bgColor = ($rowIndex % 2) ? '#ffffff' : '#e6e7e2';
        $retired = (int)$player->isRetired;

        ob_start();
        
        if ($retired === 1) {
            ?>
<tr style="background-color: <?= $bgColor ?>;">
    <td style="text-align: center;"><?= htmlspecialchars($player->position) ?></td>
    <td style="text-align: center;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $player->playerID ?>"><?= htmlspecialchars($player->name) ?></a></td>
    <td colspan="30" style="text-align: center;"> --- Retired --- </td>
    <td><?= htmlspecialchars((string)($player->collegeName ?? '')) ?></td>
</tr>
            <?php
        } else {
            ?>
<tr style="background-color: <?= $bgColor ?>;">
    <td style="text-align: center;"><?= htmlspecialchars($player->position) ?></td>
    <td style="text-align: center;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $player->playerID ?>"><?= htmlspecialchars($player->name) ?></a></td>
    <td style="text-align: center;"><?= $player->age ?></td>
    <td style="text-align: center;"><a href="team.php?tid=<?= $player->teamID ?>"><?= htmlspecialchars($player->teamName) ?></a></td>
    <td style="text-align: center;"><?= $player->yearsOfExperience ?></td>
    <td style="text-align: center;"><?= $player->birdYears ?></td>
    <td style="text-align: center;"><?= $player->ratingFieldGoalAttempts ?></td>
    <td style="text-align: center;"><?= $player->ratingFieldGoalPercentage ?></td>
    <td style="text-align: center;"><?= $player->ratingFreeThrowAttempts ?></td>
    <td style="text-align: center;"><?= $player->ratingFreeThrowPercentage ?></td>
    <td style="text-align: center;"><?= $player->ratingThreePointAttempts ?></td>
    <td style="text-align: center;"><?= $player->ratingThreePointPercentage ?></td>
    <td style="text-align: center;"><?= $player->ratingOffensiveRebounds ?></td>
    <td style="text-align: center;"><?= $player->ratingDefensiveRebounds ?></td>
    <td style="text-align: center;"><?= $player->ratingAssists ?></td>
    <td style="text-align: center;"><?= $player->ratingSteals ?></td>
    <td style="text-align: center;"><?= $player->ratingTurnovers ?></td>
    <td style="text-align: center;"><?= $player->ratingBlocks ?></td>
    <td style="text-align: center;"><?= $player->ratingFouls ?></td>
    <td style="text-align: center;"><?= $player->ratingOutsideOffense ?></td>
    <td style="text-align: center;"><?= $player->ratingOutsideDefense ?></td>
    <td style="text-align: center;"><?= $player->ratingDriveOffense ?></td>
    <td style="text-align: center;"><?= $player->ratingDriveDefense ?></td>
    <td style="text-align: center;"><?= $player->ratingPostOffense ?></td>
    <td style="text-align: center;"><?= $player->ratingPostDefense ?></td>
    <td style="text-align: center;"><?= $player->ratingTransitionOffense ?></td>
    <td style="text-align: center;"><?= $player->ratingTransitionDefense ?></td>
    <td style="text-align: center;"><?= $player->ratingTalent ?></td>
    <td style="text-align: center;"><?= $player->ratingSkill ?></td>
    <td style="text-align: center;"><?= $player->ratingIntangibles ?></td>
    <td style="text-align: center;"><?= $player->ratingClutch ?></td>
    <td style="text-align: center;"><?= $player->ratingConsistency ?></td>
    <td><?= htmlspecialchars((string)($player->collegeName ?? '')) ?></td>
</tr>
            <?php
        }
        
        return ob_get_clean();
    }

    /**
     * @see PlayerSearchViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</table>';
    }
}
