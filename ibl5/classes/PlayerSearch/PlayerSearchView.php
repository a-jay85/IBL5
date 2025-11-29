<?php

declare(strict_types=1);

namespace PlayerSearch;

/**
 * PlayerSearchView - HTML rendering for player search
 * 
 * Handles all presentation logic using output buffering pattern.
 * All output is properly escaped to prevent XSS attacks.
 */
class PlayerSearchView
{
    private PlayerSearchService $service;

    /**
     * Constructor
     * 
     * @param PlayerSearchService $service Service instance
     */
    public function __construct(PlayerSearchService $service)
    {
        $this->service = $service;
    }

    /**
     * Render the search form
     * 
     * @param array<string, mixed> $params Current filter values for repopulating form
     * @return string HTML for the search form
     */
    public function renderSearchForm(array $params): string
    {
        $positions = $this->service->getValidPositions();
        
        // Extract values with defaults
        $pos = $params['pos'] ?? '';
        $age = $params['age'] ?? '';
        $sta = $params['sta'] ?? '';
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
<p>Please enter your search parameters (Age is less than or equal to the age entered; all other fields are greater than or equal to the amount entered).</p>
<p>Partial matches on a name or college are okay and are <strong>not</strong> case sensitive (e.g., entering "Dard" will match with "Darden" and "Bedard").</p>
<p><em>Warning: Searches that may return a lot of players may take a long time to load!</em></p>

<form name="Search" method="post" action="modules.php?name=Player_Search">
    <table border="1">
        <tr>
            <td>Position: <select name="pos">
                <option value="">-</option>
                <?php foreach ($positions as $position): ?>
                    <option value="<?= htmlspecialchars($position) ?>"<?= ($pos === $position) ? ' selected' : '' ?>><?= htmlspecialchars($position) ?></option>
                <?php endforeach; ?>
            </select></td>
            <td>Age: <input type="text" name="age" size="2" value="<?= htmlspecialchars((string)$age) ?>"></td>
            <td>Stamina: <input type="text" name="sta" size="2" value="<?= htmlspecialchars((string)$sta) ?>"></td>
            <td>Talent: <input type="text" name="talent" size="1" value="<?= htmlspecialchars((string)$talent) ?>"></td>
            <td>Skill: <input type="text" name="skill" size="1" value="<?= htmlspecialchars((string)$skill) ?>"></td>
            <td>Intangibles: <input type="text" name="intangibles" size="1" value="<?= htmlspecialchars((string)$intangibles) ?>"></td>
            <td>Clutch: <input type="text" name="Clutch" size="1" value="<?= htmlspecialchars((string)$Clutch) ?>"></td>
            <td>Consistency: <input type="text" name="Consistency" size="1" value="<?= htmlspecialchars((string)$Consistency) ?>"></td>
            <td>College: <input type="text" name="college" size="16" value="<?= htmlspecialchars((string)$college) ?>"></td>
        </tr>
        <tr>
            <td colspan="9">Include Retired Players in search? <select name="active">
                <option value="1"<?= ($active === 1) ? ' selected' : '' ?>>Yes</option>
                <option value="0"<?= ($active === 0 || $active === null) ? ' selected' : '' ?>>No</option>
            </select></td>
        </tr>
        <tr>
            <td colspan="2">Minimum Years In League: <input type="text" name="exp" size="2" value="<?= htmlspecialchars((string)$exp) ?>"></td>
            <td colspan="2">Maximum Years In League: <input type="text" name="exp_max" size="2" value="<?= htmlspecialchars((string)$exp_max) ?>"></td>
            <td colspan="2">Minimum Bird Years: <input type="text" name="bird" size="2" value="<?= htmlspecialchars((string)$bird) ?>"></td>
            <td colspan="3">Maximum Bird Years: <input type="text" name="bird_max" size="2" value="<?= htmlspecialchars((string)$bird_max) ?>"></td>
        </tr>
    </table>
    <table border="1">
        <tr>
            <td>2ga: <input type="text" name="r_fga" size="2" value="<?= htmlspecialchars((string)$r_fga) ?>"></td>
            <td>2gp: <input type="text" name="r_fgp" size="2" value="<?= htmlspecialchars((string)$r_fgp) ?>"></td>
            <td>fta: <input type="text" name="r_fta" size="2" value="<?= htmlspecialchars((string)$r_fta) ?>"></td>
            <td>ftp: <input type="text" name="r_ftp" size="2" value="<?= htmlspecialchars((string)$r_ftp) ?>"></td>
            <td>3ga: <input type="text" name="r_tga" size="2" value="<?= htmlspecialchars((string)$r_tga) ?>"></td>
            <td>3gp: <input type="text" name="r_tgp" size="2" value="<?= htmlspecialchars((string)$r_tgp) ?>"></td>
            <td>orb: <input type="text" name="r_orb" size="2" value="<?= htmlspecialchars((string)$r_orb) ?>"></td>
            <td>drb: <input type="text" name="r_drb" size="2" value="<?= htmlspecialchars((string)$r_drb) ?>"></td>
            <td>ast: <input type="text" name="r_ast" size="2" value="<?= htmlspecialchars((string)$r_ast) ?>"></td>
            <td>stl: <input type="text" name="r_stl" size="2" value="<?= htmlspecialchars((string)$r_stl) ?>"></td>
            <td>blk: <input type="text" name="r_blk" size="2" value="<?= htmlspecialchars((string)$r_blk) ?>"></td>
            <td>tvr: <input type="text" name="r_to" size="2" value="<?= htmlspecialchars((string)$r_to) ?>"></td>
            <td>foul: <input type="text" name="r_foul" size="2" value="<?= htmlspecialchars((string)$r_foul) ?>"></td>
        </tr>
    </table>
    <table border="1">
        <tr>
            <td>NAME: <input type="text" name="search_name" size="32" value="<?= htmlspecialchars((string)$search_name) ?>"></td>
            <td>oo: <input type="text" name="oo" size="1" value="<?= htmlspecialchars((string)$oo) ?>"></td>
            <td>do: <input type="text" name="do" size="1" value="<?= htmlspecialchars((string)$do) ?>"></td>
            <td>po: <input type="text" name="po" size="1" value="<?= htmlspecialchars((string)$po) ?>"></td>
            <td>to: <input type="text" name="to" size="1" value="<?= htmlspecialchars((string)$to) ?>"></td>
            <td>od: <input type="text" name="od" size="1" value="<?= htmlspecialchars((string)$od) ?>"></td>
            <td>dd: <input type="text" name="dd" size="1" value="<?= htmlspecialchars((string)$dd) ?>"></td>
            <td>pd: <input type="text" name="pd" size="1" value="<?= htmlspecialchars((string)$pd) ?>"></td>
            <td>td: <input type="text" name="td" size="1" value="<?= htmlspecialchars((string)$td) ?>"></td>
        </tr>
    </table>
    <input type="hidden" name="submitted" value="1">
    <input type="submit" value="Search for Player!">
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the results table header
     * 
     * @return string HTML table header
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
        <th>Stamina</th>
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
     * Render a single player row
     * 
     * @param array<string, mixed> $player Processed player data
     * @param int $rowIndex Row index for alternating colors
     * @return string HTML table row
     */
    public function renderPlayerRow(array $player, int $rowIndex): string
    {
        $bgColor = ($rowIndex % 2) ? '#ffffff' : '#e6e7e2';
        $pid = (int)$player['pid'];
        $name = htmlspecialchars($player['name']);
        $pos = htmlspecialchars($player['pos']);
        $tid = (int)$player['tid'];
        $teamname = htmlspecialchars($player['teamname']);
        $college = htmlspecialchars($player['college']);
        $retired = (int)$player['retired'];

        ob_start();
        
        if ($retired === 1) {
            ?>
<tr style="background-color: <?= $bgColor ?>;">
    <td style="text-align: center;"><?= $pos ?></td>
    <td style="text-align: center;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $pid ?>"><?= $name ?></a></td>
    <td colspan="31" style="text-align: center;"> --- Retired --- </td>
    <td><?= $college ?></td>
</tr>
            <?php
        } else {
            ?>
<tr style="background-color: <?= $bgColor ?>;">
    <td style="text-align: center;"><?= $pos ?></td>
    <td style="text-align: center;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $pid ?>"><?= $name ?></a></td>
    <td style="text-align: center;"><?= (int)$player['age'] ?></td>
    <td style="text-align: center;"><?= (int)$player['sta'] ?></td>
    <td style="text-align: center;"><a href="team.php?tid=<?= $tid ?>"><?= $teamname ?></a></td>
    <td style="text-align: center;"><?= (int)$player['exp'] ?></td>
    <td style="text-align: center;"><?= (int)$player['bird'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_fga'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_fgp'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_fta'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_ftp'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_tga'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_tgp'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_orb'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_drb'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_ast'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_stl'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_tvr'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_blk'] ?></td>
    <td style="text-align: center;"><?= (int)$player['r_foul'] ?></td>
    <td style="text-align: center;"><?= (int)$player['oo'] ?></td>
    <td style="text-align: center;"><?= (int)$player['do'] ?></td>
    <td style="text-align: center;"><?= (int)$player['po'] ?></td>
    <td style="text-align: center;"><?= (int)$player['to'] ?></td>
    <td style="text-align: center;"><?= (int)$player['od'] ?></td>
    <td style="text-align: center;"><?= (int)$player['dd'] ?></td>
    <td style="text-align: center;"><?= (int)$player['pd'] ?></td>
    <td style="text-align: center;"><?= (int)$player['td'] ?></td>
    <td style="text-align: center;"><?= (int)$player['talent'] ?></td>
    <td style="text-align: center;"><?= (int)$player['skill'] ?></td>
    <td style="text-align: center;"><?= (int)$player['intangibles'] ?></td>
    <td style="text-align: center;"><?= (int)$player['Clutch'] ?></td>
    <td style="text-align: center;"><?= (int)$player['Consistency'] ?></td>
    <td><?= $college ?></td>
</tr>
            <?php
        }
        
        return ob_get_clean();
    }

    /**
     * Render table footer
     * 
     * @return string HTML table closing tag
     */
    public function renderTableFooter(): string
    {
        return '</table>';
    }
}
