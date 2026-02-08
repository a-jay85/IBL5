<?php

declare(strict_types=1);

namespace PlayerDatabase;

use Player\PlayerImageHelper;
use PlayerDatabase\Contracts\PlayerDatabaseViewInterface;
use UI\TeamCellHelper;

/**
 * @see PlayerDatabaseViewInterface
 */
class PlayerDatabaseView implements PlayerDatabaseViewInterface
{
    /** @phpstan-ignore-next-line constructor.unusedParameter */
    public function __construct(PlayerDatabaseService $service)
    {
    }

    /**
     * @see PlayerDatabaseViewInterface::renderSearchForm()
     *
     * @param array<string, mixed> $params
     */
    public function renderSearchForm(array $params): string
    {
        $positions = \JSB::PLAYER_POSITIONS;

        /**
         * Helper to extract a form field value as string for HTML display
         *
         * @param mixed $value
         * @return string
         */
        $str = static function (mixed $value): string {
            if ($value === null) {
                return '';
            }
            if (is_int($value) || is_string($value)) {
                return (string) $value;
            }
            return '';
        };

        // Extract form parameters with defaults
        $pos = $str($params['pos'] ?? null);
        $age = $str($params['age'] ?? null);
        $talent = $str($params['talent'] ?? null);
        $skill = $str($params['skill'] ?? null);
        $intangibles = $str($params['intangibles'] ?? null);
        $Clutch = $str($params['Clutch'] ?? null);
        $Consistency = $str($params['Consistency'] ?? null);
        $college = $str($params['college'] ?? null);
        $active = $params['active'] ?? null;
        $exp = $str($params['exp'] ?? null);
        $exp_max = $str($params['exp_max'] ?? null);
        $bird = $str($params['bird'] ?? null);
        $bird_max = $str($params['bird_max'] ?? null);
        $search_name = $str($params['search_name'] ?? null);

        // Rating values
        $r_fga = $str($params['r_fga'] ?? null);
        $r_fgp = $str($params['r_fgp'] ?? null);
        $r_fta = $str($params['r_fta'] ?? null);
        $r_ftp = $str($params['r_ftp'] ?? null);
        $r_tga = $str($params['r_tga'] ?? null);
        $r_tgp = $str($params['r_tgp'] ?? null);
        $r_orb = $str($params['r_orb'] ?? null);
        $r_drb = $str($params['r_drb'] ?? null);
        $r_ast = $str($params['r_ast'] ?? null);
        $r_stl = $str($params['r_stl'] ?? null);
        $r_blk = $str($params['r_blk'] ?? null);
        $r_to = $str($params['r_to'] ?? null);
        $r_foul = $str($params['r_foul'] ?? null);

        // Skill values
        $oo = $str($params['oo'] ?? null);
        $do = $str($params['do'] ?? null);
        $po = $str($params['po'] ?? null);
        $to = $str($params['to'] ?? null);
        $od = $str($params['od'] ?? null);
        $dd = $str($params['dd'] ?? null);
        $pd = $str($params['pd'] ?? null);
        $td = $str($params['td'] ?? null);

        ob_start();
        ?>
<p style="text-align: center;">Age is less than or equal to the age entered. All other fields are greater than or equal to the amount entered.</p>
<p style="text-align: center;">Partial matches on a name or college are okay and are <strong>not</strong> case sensitive<br>
(e.g., entering "Dard" will match with "Darden" and "Bedard").</p>

<form name="Search" method="post" action="modules.php?name=PlayerDatabase" class="ibl-filter-form" style="max-width: 48rem; margin: 0 auto;">
    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Basics</legend>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="search_name">Name:</label>
                <input id="search_name" type="text" name="search_name" style="width: 10rem;" value="<?= htmlspecialchars($search_name) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="pos">Position:</label>
                <select id="pos" name="pos">
                    <option value="">-</option>
                    <?php foreach ($positions as $position): ?>
                        <option value="<?= htmlspecialchars($position) ?>"<?= ($pos === $position) ? ' selected' : '' ?>><?= htmlspecialchars($position) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="active">Retirees?</label>
                <select id="active" name="active">
                    <option value="1"<?= ($active === 1) ? ' selected' : '' ?>>Yes</option>
                    <option value="0"<?= ($active === 0 || $active === null) ? ' selected' : '' ?>>No</option>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Years</legend>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="age">Max Age:</label>
                <input id="age" type="text" name="age" style="width: 3.5rem;" value="<?= htmlspecialchars($age) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="exp">Min Exp:</label>
                <input id="exp" type="text" name="exp" style="width: 3.5rem;" value="<?= htmlspecialchars($exp) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="exp_max">Max Exp:</label>
                <input id="exp_max" type="text" name="exp_max" style="width: 3.5rem;" value="<?= htmlspecialchars($exp_max) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="bird">Min Bird:</label>
                <input id="bird" type="text" name="bird" style="width: 3.5rem;" value="<?= htmlspecialchars($bird) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="bird_max">Max Bird:</label>
                <input id="bird_max" type="text" name="bird_max" style="width: 3.5rem;" value="<?= htmlspecialchars($bird_max) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Statistical Ratings</legend>
        <div class="ibl-filter-form__row" style="margin-bottom: 0.5rem;">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_fga">2ga:</label>
                <input id="r_fga" type="text" name="r_fga" style="width: 3.5rem;" value="<?= htmlspecialchars($r_fga) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_fgp">2gp:</label>
                <input id="r_fgp" type="text" name="r_fgp" style="width: 3.5rem;" value="<?= htmlspecialchars($r_fgp) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_fta">fta:</label>
                <input id="r_fta" type="text" name="r_fta" style="width: 3.5rem;" value="<?= htmlspecialchars($r_fta) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_ftp">ftp:</label>
                <input id="r_ftp" type="text" name="r_ftp" style="width: 3.5rem;" value="<?= htmlspecialchars($r_ftp) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_tga">3ga:</label>
                <input id="r_tga" type="text" name="r_tga" style="width: 3.5rem;" value="<?= htmlspecialchars($r_tga) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_tgp">3gp:</label>
                <input id="r_tgp" type="text" name="r_tgp" style="width: 3.5rem;" value="<?= htmlspecialchars($r_tgp) ?>">
            </div>
        </div>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_orb">orb:</label>
                <input id="r_orb" type="text" name="r_orb" style="width: 3.5rem;" value="<?= htmlspecialchars($r_orb) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_drb">drb:</label>
                <input id="r_drb" type="text" name="r_drb" style="width: 3.5rem;" value="<?= htmlspecialchars($r_drb) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_ast">ast:</label>
                <input id="r_ast" type="text" name="r_ast" style="width: 3.5rem;" value="<?= htmlspecialchars($r_ast) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_stl">stl:</label>
                <input id="r_stl" type="text" name="r_stl" style="width: 3.5rem;" value="<?= htmlspecialchars($r_stl) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_blk">blk:</label>
                <input id="r_blk" type="text" name="r_blk" style="width: 3.5rem;" value="<?= htmlspecialchars($r_blk) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_to">tvr:</label>
                <input id="r_to" type="text" name="r_to" style="width: 3.5rem;" value="<?= htmlspecialchars($r_to) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_foul">foul:</label>
                <input id="r_foul" type="text" name="r_foul" style="width: 3.5rem;" value="<?= htmlspecialchars($r_foul) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Offensive/Defensive Ratings</legend>
        <div class="ibl-filter-form__row" style="margin-bottom: 0.5rem;">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="oo">oo:</label>
                <input id="oo" type="text" name="oo" style="width: 3rem;" value="<?= htmlspecialchars($oo) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="do">do:</label>
                <input id="do" type="text" name="do" style="width: 3rem;" value="<?= htmlspecialchars($do) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="po">po:</label>
                <input id="po" type="text" name="po" style="width: 3rem;" value="<?= htmlspecialchars($po) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="to">to:</label>
                <input id="to" type="text" name="to" style="width: 3rem;" value="<?= htmlspecialchars($to) ?>">
            </div>
        </div>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="od">od:</label>
                <input id="od" type="text" name="od" style="width: 3rem;" value="<?= htmlspecialchars($od) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="dd">dd:</label>
                <input id="dd" type="text" name="dd" style="width: 3rem;" value="<?= htmlspecialchars($dd) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="pd">pd:</label>
                <input id="pd" type="text" name="pd" style="width: 3rem;" value="<?= htmlspecialchars($pd) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="td">td:</label>
                <input id="td" type="text" name="td" style="width: 3rem;" value="<?= htmlspecialchars($td) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Misc. Attributes</legend>
        <div class="ibl-filter-form__row" style="margin-bottom: 0.5rem;">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="talent">Talent:</label>
                <input id="talent" type="text" name="talent" style="width: 3rem;" value="<?= htmlspecialchars($talent) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="skill">Skill:</label>
                <input id="skill" type="text" name="skill" style="width: 3rem;" value="<?= htmlspecialchars($skill) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="intangibles">Intangibles:</label>
                <input id="intangibles" type="text" name="intangibles" style="width: 3rem;" value="<?= htmlspecialchars($intangibles) ?>">
            </div>
        </div>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="Clutch">Clutch:</label>
                <input id="Clutch" type="text" name="Clutch" style="width: 3rem;" value="<?= htmlspecialchars($Clutch) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="Consistency">Consistency:</label>
                <input id="Consistency" type="text" name="Consistency" style="width: 3rem;" value="<?= htmlspecialchars($Consistency) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="college">College:</label>
                <input id="college" type="text" name="college" style="width: 10rem;" value="<?= htmlspecialchars($college) ?>">
            </div>
        </div>
    </fieldset>

    <div class="ibl-filter-form__row" style="gap: 0.75rem;">
        <button type="button" class="ibl-btn ibl-btn--ghost" onclick="resetPlayerDatabase();">Reset</button>
        <button type="submit" class="ibl-filter-form__submit">Search for Player</button>
    </div>
</form>

<script type="text/javascript">
function resetPlayerDatabase() {
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
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see PlayerDatabaseViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<table class="sortable ibl-data-table" data-no-responsive>
    <tr>
        <th>Pos</th>
        <th>Player</th>
        <th>Age</th>
        <th class="ibl-team-cell--colored">Team</th>
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
        <th>TAL</th>
        <th>SKL</th>
        <th>INT</th>
        <th>CLU</th>
        <th>CON</th>
        <th>College</th>
    </tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see PlayerDatabaseViewInterface::renderPlayerRow()
     */
    public function renderPlayerRow(\Player\PlayerData $player, int $rowIndex): string
    {
        $retired = $player->isRetired ?? 0;
        $pid = (int) $player->playerID;
        $playerName = $player->name ?? '';
        $position = $player->position ?? '';
        $college = $player->collegeName ?? '';

        ob_start();

        if ($retired === 1) {
            ?>
<tr>
    <td><?= htmlspecialchars($position) ?></td>
    <?= PlayerImageHelper::renderFlexiblePlayerCell($pid, $playerName) ?>
    <td colspan="30"> --- Retired --- </td>
    <td><?= htmlspecialchars($college) ?></td>
</tr>
            <?php
        } else {
            $teamID = (int) $player->teamID;
            ?>
<tr>
    <td><?= htmlspecialchars($position) ?></td>
    <?= PlayerImageHelper::renderFlexiblePlayerCell($pid, $playerName) ?>
    <td><?= $player->age !== null ? (string) $player->age : '' ?></td>
    <?= TeamCellHelper::renderTeamCellOrFreeAgent($teamID, $player->teamName ?? '', $player->teamColor1 ?? 'FFFFFF', $player->teamColor2 ?? '000000') ?>
    <td><?= (int) $player->yearsOfExperience ?></td>
    <td><?= (int) $player->birdYears ?></td>
    <td><?= (int) $player->ratingFieldGoalAttempts ?></td>
    <td><?= (int) $player->ratingFieldGoalPercentage ?></td>
    <td><?= (int) $player->ratingFreeThrowAttempts ?></td>
    <td><?= (int) $player->ratingFreeThrowPercentage ?></td>
    <td><?= (int) $player->ratingThreePointAttempts ?></td>
    <td><?= (int) $player->ratingThreePointPercentage ?></td>
    <td><?= (int) $player->ratingOffensiveRebounds ?></td>
    <td><?= (int) $player->ratingDefensiveRebounds ?></td>
    <td><?= (int) $player->ratingAssists ?></td>
    <td><?= (int) $player->ratingSteals ?></td>
    <td><?= (int) $player->ratingTurnovers ?></td>
    <td><?= (int) $player->ratingBlocks ?></td>
    <td><?= (int) $player->ratingFouls ?></td>
    <td><?= (int) $player->ratingOutsideOffense ?></td>
    <td><?= (int) $player->ratingOutsideDefense ?></td>
    <td><?= (int) $player->ratingDriveOffense ?></td>
    <td><?= (int) $player->ratingDriveDefense ?></td>
    <td><?= (int) $player->ratingPostOffense ?></td>
    <td><?= (int) $player->ratingPostDefense ?></td>
    <td><?= (int) $player->ratingTransitionOffense ?></td>
    <td><?= (int) $player->ratingTransitionDefense ?></td>
    <td><?= (int) $player->ratingTalent ?></td>
    <td><?= (int) $player->ratingSkill ?></td>
    <td><?= (int) $player->ratingIntangibles ?></td>
    <td><?= (int) $player->ratingClutch ?></td>
    <td><?= (int) $player->ratingConsistency ?></td>
    <td><?= htmlspecialchars($college) ?></td>
</tr>
            <?php
        }

        return (string) ob_get_clean();
    }

    /**
     * @see PlayerDatabaseViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return '</table>';
    }
}
