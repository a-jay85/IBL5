<?php

declare(strict_types=1);

namespace ComparePlayers;

use ComparePlayers\Contracts\ComparePlayersViewInterface;

/**
 * @see ComparePlayersViewInterface
 */
class ComparePlayersView implements ComparePlayersViewInterface
{
    /**
     * @see ComparePlayersViewInterface::renderSearchForm()
     */
    public function renderSearchForm(array $playerNames): string
    {
        ob_start();
        ?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<link rel="stylesheet" href="/resources/demos/style.css">
<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script>
$(function() {
    var availableTags = [
<?php foreach ($playerNames as $name): ?>
        <?= json_encode(stripslashes($name), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
<?php endforeach; ?>
    ];
    $("#Player1").autocomplete({
        source: availableTags
    });
    $("#Player2").autocomplete({
        source: availableTags
    });
});
</script>
<form action="modules.php?name=Compare_Players" method="POST">
    <div class="ui-widget">
        <label for="Player1">Player 1: </label>
        <input id="Player1" type="text" name="Player1"><br>
        <label for="Player2">Player 2: </label>
        <input id="Player2" type="text" name="Player2"><br>
    </div>
    <input type="submit" value="Compare">
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * @see ComparePlayersViewInterface::renderComparisonResults()
     */
    public function renderComparisonResults(array $comparisonData): string
    {
        $player1 = $comparisonData['player1'];
        $player2 = $comparisonData['player2'];

        ob_start();
        ?>
<table border="1" cellspacing="0" align="center" class="sortable">
    <caption>
        <center><b>Current Ratings</b></center>
    </caption>
    <colgroup>
        <col span="3">
        <col span="6" style="background-color: #ddd">
        <col span="7">
        <col span="4" style="background-color: #ddd">
        <col span="4">
    </colgroup>
    <thead>
        <tr>
            <th>Pos</th>
            <th>Player</th>
            <th>Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th>3g%</th>
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
        </tr>
    </thead>
    <tbody>
        <tr>
            <th><?= htmlspecialchars($player1['pos']) ?></th>
            <th><?= htmlspecialchars($player1['name']) ?></th>
            <th><?= htmlspecialchars((string)$player1['age']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_fga']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_fgp']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_fta']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_ftp']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_tga']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_tgp']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_orb']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_drb']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_ast']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_stl']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_to']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_blk']) ?></th>
            <th><?= htmlspecialchars((string)$player1['r_foul']) ?></th>
            <th><?= htmlspecialchars((string)$player1['oo']) ?></th>
            <th><?= htmlspecialchars((string)$player1['do']) ?></th>
            <th><?= htmlspecialchars((string)$player1['po']) ?></th>
            <th><?= htmlspecialchars((string)$player1['to']) ?></th>
            <th><?= htmlspecialchars((string)$player1['od']) ?></th>
            <th><?= htmlspecialchars((string)$player1['dd']) ?></th>
            <th><?= htmlspecialchars((string)$player1['pd']) ?></th>
            <th><?= htmlspecialchars((string)$player1['td']) ?></th>
        </tr>
        <tr>
            <th><?= htmlspecialchars($player2['pos']) ?></th>
            <th><?= htmlspecialchars($player2['name']) ?></th>
            <th><?= htmlspecialchars((string)$player2['age']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_fga']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_fgp']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_fta']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_ftp']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_tga']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_tgp']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_orb']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_drb']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_ast']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_stl']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_to']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_blk']) ?></th>
            <th><?= htmlspecialchars((string)$player2['r_foul']) ?></th>
            <th><?= htmlspecialchars((string)$player2['oo']) ?></th>
            <th><?= htmlspecialchars((string)$player2['do']) ?></th>
            <th><?= htmlspecialchars((string)$player2['po']) ?></th>
            <th><?= htmlspecialchars((string)$player2['to']) ?></th>
            <th><?= htmlspecialchars((string)$player2['od']) ?></th>
            <th><?= htmlspecialchars((string)$player2['dd']) ?></th>
            <th><?= htmlspecialchars((string)$player2['pd']) ?></th>
            <th><?= htmlspecialchars((string)$player2['td']) ?></th>
        </tr>
    </tbody>
</table>

<p>

<table border="1" cellspacing="0" align="center" class="sortable">
    <caption>
        <center><b>Current Season Stats</b></center>
    </caption>
    <colgroup>
        <col span="5">
        <col span="6" style="background-color: #ddd">
        <col span="8">
    </colgroup>
    <thead>
        <tr>
            <th>Pos</th>
            <th>Player</th>
            <th>g</th>
            <th>gs</th>
            <th>min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>ftm</th>
            <th>fta</th>
            <th>3gm</th>
            <th>3ga</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th><?= htmlspecialchars($player1['pos']) ?></th>
            <th><?= htmlspecialchars($player1['name']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_gm']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_gs']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_min']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_fgm']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_fga']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_ftm']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_fta']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_3gm']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_3ga']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_orb']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_drb']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_ast']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_stl']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_to']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_blk']) ?></th>
            <th><?= htmlspecialchars((string)$player1['stats_pf']) ?></th>
            <th><?= (2 * $player1['stats_fgm'] + $player1['stats_ftm'] + $player1['stats_3gm']) ?></th>
        </tr>
        <tr>
            <th><?= htmlspecialchars($player2['pos']) ?></th>
            <th><?= htmlspecialchars($player2['name']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_gm']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_gs']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_min']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_fgm']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_fga']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_ftm']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_fta']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_3gm']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_3ga']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_orb']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_drb']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_ast']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_stl']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_to']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_blk']) ?></th>
            <th><?= htmlspecialchars((string)$player2['stats_pf']) ?></th>
            <th><?= (2 * $player2['stats_fgm'] + $player2['stats_ftm'] + $player2['stats_3gm']) ?></th>
        </tr>
    </tbody>
</table>

<p>

<table border="1" cellspacing="0" align="center" class="sortable">
    <caption>
        <center><b>Career Stats</b></center>
    </caption>
    <colgroup>
        <col span="4">
        <col span="6" style="background-color: #ddd">
        <col span="8">
    </colgroup>
    <thead>
        <tr>
            <th>Pos</th>
            <th>Player</th>
            <th>g</th>
            <th>min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>ftm</th>
            <th>fta</th>
            <th>3gm</th>
            <th>3ga</th>
            <th>orb</th>
            <th>drb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th><?= htmlspecialchars($player1['pos']) ?></th>
            <th><?= htmlspecialchars($player1['name']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_gm']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_min']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_fgm']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_fga']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_ftm']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_fta']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_tgm']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_tga']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_orb']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_drb']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_reb']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_ast']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_stl']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_to']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_blk']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_pf']) ?></th>
            <th><?= htmlspecialchars((string)$player1['car_pts']) ?></th>
        </tr>
        <tr>
            <th><?= htmlspecialchars($player2['pos']) ?></th>
            <th><?= htmlspecialchars($player2['name']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_gm']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_min']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_fgm']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_fga']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_ftm']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_fta']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_tgm']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_tga']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_orb']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_drb']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_reb']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_ast']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_stl']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_to']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_blk']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_pf']) ?></th>
            <th><?= htmlspecialchars((string)$player2['car_pts']) ?></th>
        </tr>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }
}
