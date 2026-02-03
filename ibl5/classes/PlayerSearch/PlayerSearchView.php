<?php

declare(strict_types=1);

namespace PlayerSearch;

use Player\PlayerImageHelper;
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
<p style="text-align: center;">Age is less than or equal to the age entered. All other fields are greater than or equal to the amount entered.</p>
<p style="text-align: center;">Partial matches on a name or college are okay and are <strong>not</strong> case sensitive<br>
(e.g., entering "Dard" will match with "Darden" and "Bedard").</p>

<form name="Search" method="post" action="modules.php?name=Player_Search" class="ibl-filter-form" style="max-width: 48rem; margin: 0 auto;">
    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Basics</legend>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="search_name">Name:</label>
                <input id="search_name" type="text" name="search_name" style="width: 10rem;" value="<?= htmlspecialchars((string)$search_name) ?>">
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
                <input id="age" type="text" name="age" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$age) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="exp">Min Exp:</label>
                <input id="exp" type="text" name="exp" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$exp) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="exp_max">Max Exp:</label>
                <input id="exp_max" type="text" name="exp_max" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$exp_max) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="bird">Min Bird:</label>
                <input id="bird" type="text" name="bird" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$bird) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="bird_max">Max Bird:</label>
                <input id="bird_max" type="text" name="bird_max" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$bird_max) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Statistical Ratings</legend>
        <div class="ibl-filter-form__row" style="margin-bottom: 0.5rem;">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_fga">2ga:</label>
                <input id="r_fga" type="text" name="r_fga" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_fga) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_fgp">2gp:</label>
                <input id="r_fgp" type="text" name="r_fgp" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_fgp) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_fta">fta:</label>
                <input id="r_fta" type="text" name="r_fta" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_fta) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_ftp">ftp:</label>
                <input id="r_ftp" type="text" name="r_ftp" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_ftp) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_tga">3ga:</label>
                <input id="r_tga" type="text" name="r_tga" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_tga) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_tgp">3gp:</label>
                <input id="r_tgp" type="text" name="r_tgp" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_tgp) ?>">
            </div>
        </div>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_orb">orb:</label>
                <input id="r_orb" type="text" name="r_orb" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_orb) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_drb">drb:</label>
                <input id="r_drb" type="text" name="r_drb" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_drb) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_ast">ast:</label>
                <input id="r_ast" type="text" name="r_ast" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_ast) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_stl">stl:</label>
                <input id="r_stl" type="text" name="r_stl" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_stl) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_blk">blk:</label>
                <input id="r_blk" type="text" name="r_blk" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_blk) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_to">tvr:</label>
                <input id="r_to" type="text" name="r_to" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_to) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="r_foul">foul:</label>
                <input id="r_foul" type="text" name="r_foul" style="width: 3.5rem;" value="<?= htmlspecialchars((string)$r_foul) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Offensive/Defensive Ratings</legend>
        <div class="ibl-filter-form__row" style="margin-bottom: 0.5rem;">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="oo">oo:</label>
                <input id="oo" type="text" name="oo" style="width: 3rem;" value="<?= htmlspecialchars((string)$oo) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="do">do:</label>
                <input id="do" type="text" name="do" style="width: 3rem;" value="<?= htmlspecialchars((string)$do) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="po">po:</label>
                <input id="po" type="text" name="po" style="width: 3rem;" value="<?= htmlspecialchars((string)$po) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="to">to:</label>
                <input id="to" type="text" name="to" style="width: 3rem;" value="<?= htmlspecialchars((string)$to) ?>">
            </div>
        </div>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="od">od:</label>
                <input id="od" type="text" name="od" style="width: 3rem;" value="<?= htmlspecialchars((string)$od) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="dd">dd:</label>
                <input id="dd" type="text" name="dd" style="width: 3rem;" value="<?= htmlspecialchars((string)$dd) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="pd">pd:</label>
                <input id="pd" type="text" name="pd" style="width: 3rem;" value="<?= htmlspecialchars((string)$pd) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="td">td:</label>
                <input id="td" type="text" name="td" style="width: 3rem;" value="<?= htmlspecialchars((string)$td) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset style="margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 0.375rem); padding: 0.75rem 1rem;">
        <legend>Misc. Attributes</legend>
        <div class="ibl-filter-form__row" style="margin-bottom: 0.5rem;">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="talent">Talent:</label>
                <input id="talent" type="text" name="talent" style="width: 3rem;" value="<?= htmlspecialchars((string)$talent) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="skill">Skill:</label>
                <input id="skill" type="text" name="skill" style="width: 3rem;" value="<?= htmlspecialchars((string)$skill) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="intangibles">Intangibles:</label>
                <input id="intangibles" type="text" name="intangibles" style="width: 3rem;" value="<?= htmlspecialchars((string)$intangibles) ?>">
            </div>
        </div>
        <div class="ibl-filter-form__row">
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="Clutch">Clutch:</label>
                <input id="Clutch" type="text" name="Clutch" style="width: 3rem;" value="<?= htmlspecialchars((string)$Clutch) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="Consistency">Consistency:</label>
                <input id="Consistency" type="text" name="Consistency" style="width: 3rem;" value="<?= htmlspecialchars((string)$Consistency) ?>">
            </div>
            <div class="ibl-filter-form__group">
                <label class="ibl-filter-form__label" for="college">College:</label>
                <input id="college" type="text" name="college" style="width: 10rem;" value="<?= htmlspecialchars((string)$college) ?>">
            </div>
        </div>
    </fieldset>

    <div class="ibl-filter-form__row" style="gap: 0.75rem;">
        <button type="button" class="ibl-btn ibl-btn--ghost" onclick="resetPlayerSearch();">Reset</button>
        <button type="submit" class="ibl-filter-form__submit">Search for Player</button>
    </div>
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
        return ob_get_clean();
    }

    /**
     * @see PlayerSearchViewInterface::renderPlayerRow()
     */
    public function renderPlayerRow(\Player\PlayerData $player, int $rowIndex): string
    {
        $retired = (int)$player->isRetired;

        ob_start();

        if ($retired === 1) {
            ?>
<tr>
    <td><?= htmlspecialchars($player->position) ?></td>
    <?php $resolved = PlayerImageHelper::resolvePlayerDisplay((int)$player->playerID, $player->name); ?>
    <td class="ibl-player-cell"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $player->playerID ?>"><?= $resolved['thumbnail'] ?><?= htmlspecialchars($resolved['name']) ?></a></td>
    <td colspan="30"> --- Retired --- </td>
    <td><?= htmlspecialchars((string)($player->collegeName ?? '')) ?></td>
</tr>
            <?php
        } else {
            ?>
<tr>
    <td><?= htmlspecialchars($player->position) ?></td>
    <?php $resolved = PlayerImageHelper::resolvePlayerDisplay((int)$player->playerID, $player->name); ?>
    <td class="ibl-player-cell"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $player->playerID ?>"><?= $resolved['thumbnail'] ?><?= htmlspecialchars($resolved['name']) ?></a></td>
    <td><?= $player->age ?></td>
    <?php if ($player->teamColor1 !== null && $player->teamID > 0): ?>
    <td class="ibl-team-cell--colored" style="background-color: #<?= \Utilities\HtmlSanitizer::safeHtmlOutput($player->teamColor1) ?>;">
        <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $player->teamID ?>" class="ibl-team-cell__name" style="color: #<?= \Utilities\HtmlSanitizer::safeHtmlOutput($player->teamColor2 ?? '') ?>;">
            <img src="images/logo/new<?= $player->teamID ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
            <span class="ibl-team-cell__text"><?= htmlspecialchars($player->teamName) ?></span>
        </a>
    </td>
    <?php else: ?>
    <td><?= htmlspecialchars($player->teamName ?? '') ?></td>
    <?php endif; ?>
    <td><?= $player->yearsOfExperience ?></td>
    <td><?= $player->birdYears ?></td>
    <td><?= $player->ratingFieldGoalAttempts ?></td>
    <td><?= $player->ratingFieldGoalPercentage ?></td>
    <td><?= $player->ratingFreeThrowAttempts ?></td>
    <td><?= $player->ratingFreeThrowPercentage ?></td>
    <td><?= $player->ratingThreePointAttempts ?></td>
    <td><?= $player->ratingThreePointPercentage ?></td>
    <td><?= $player->ratingOffensiveRebounds ?></td>
    <td><?= $player->ratingDefensiveRebounds ?></td>
    <td><?= $player->ratingAssists ?></td>
    <td><?= $player->ratingSteals ?></td>
    <td><?= $player->ratingTurnovers ?></td>
    <td><?= $player->ratingBlocks ?></td>
    <td><?= $player->ratingFouls ?></td>
    <td><?= $player->ratingOutsideOffense ?></td>
    <td><?= $player->ratingOutsideDefense ?></td>
    <td><?= $player->ratingDriveOffense ?></td>
    <td><?= $player->ratingDriveDefense ?></td>
    <td><?= $player->ratingPostOffense ?></td>
    <td><?= $player->ratingPostDefense ?></td>
    <td><?= $player->ratingTransitionOffense ?></td>
    <td><?= $player->ratingTransitionDefense ?></td>
    <td><?= $player->ratingTalent ?></td>
    <td><?= $player->ratingSkill ?></td>
    <td><?= $player->ratingIntangibles ?></td>
    <td><?= $player->ratingClutch ?></td>
    <td><?= $player->ratingConsistency ?></td>
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
