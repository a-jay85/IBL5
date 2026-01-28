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
<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<form action="modules.php?name=Compare_Players" method="POST">
    <div class="ui-widget">
        <label for="Player1">Player 1: </label>
        <input id="Player1" type="text" name="Player1"><br>
        <label for="Player2">Player 2: </label>
        <input id="Player2" type="text" name="Player2"><br>
    </div>
    <input type="submit" value="Compare">
</form>
</div>
</div>
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
<h2 class="ibl-title">Current Ratings</h2>
<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<table class="sortable compare-players-table ibl-data-table responsive-table">
    <colgroup>
        <col span="3">
        <col span="6" class="compare-highlight-cols">
        <col span="7">
        <col span="4" class="compare-highlight-cols">
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
            <td><?= htmlspecialchars($player1['pos']) ?></td>
            <td><?= htmlspecialchars($player1['name']) ?></td>
            <td><?= htmlspecialchars((string)$player1['age']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_fga']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_fgp']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_fta']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_ftp']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_tga']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_tgp']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_orb']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_drb']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_ast']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_stl']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_to']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_blk']) ?></td>
            <td><?= htmlspecialchars((string)$player1['r_foul']) ?></td>
            <td><?= htmlspecialchars((string)$player1['oo']) ?></td>
            <td><?= htmlspecialchars((string)$player1['do']) ?></td>
            <td><?= htmlspecialchars((string)$player1['po']) ?></td>
            <td><?= htmlspecialchars((string)$player1['to']) ?></td>
            <td><?= htmlspecialchars((string)$player1['od']) ?></td>
            <td><?= htmlspecialchars((string)$player1['dd']) ?></td>
            <td><?= htmlspecialchars((string)$player1['pd']) ?></td>
            <td><?= htmlspecialchars((string)$player1['td']) ?></td>
        </tr>
        <tr>
            <td><?= htmlspecialchars($player2['pos']) ?></td>
            <td><?= htmlspecialchars($player2['name']) ?></td>
            <td><?= htmlspecialchars((string)$player2['age']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_fga']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_fgp']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_fta']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_ftp']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_tga']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_tgp']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_orb']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_drb']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_ast']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_stl']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_to']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_blk']) ?></td>
            <td><?= htmlspecialchars((string)$player2['r_foul']) ?></td>
            <td><?= htmlspecialchars((string)$player2['oo']) ?></td>
            <td><?= htmlspecialchars((string)$player2['do']) ?></td>
            <td><?= htmlspecialchars((string)$player2['po']) ?></td>
            <td><?= htmlspecialchars((string)$player2['to']) ?></td>
            <td><?= htmlspecialchars((string)$player2['od']) ?></td>
            <td><?= htmlspecialchars((string)$player2['dd']) ?></td>
            <td><?= htmlspecialchars((string)$player2['pd']) ?></td>
            <td><?= htmlspecialchars((string)$player2['td']) ?></td>
        </tr>
    </tbody>
</table>
</div>
</div>

<h2 class="ibl-title">Current Season Stats</h2>
<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<table class="sortable compare-players-table ibl-data-table responsive-table">
    <colgroup>
        <col span="5">
        <col span="6" class="compare-highlight-cols">
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
            <td><?= htmlspecialchars($player1['pos']) ?></td>
            <td><?= htmlspecialchars($player1['name']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_gm']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_gs']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_min']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_fgm']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_fga']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_ftm']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_fta']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_3gm']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_3ga']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_orb']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_drb']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_ast']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_stl']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_to']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_blk']) ?></td>
            <td><?= htmlspecialchars((string)$player1['stats_pf']) ?></td>
            <td><?= (2 * $player1['stats_fgm'] + $player1['stats_ftm'] + $player1['stats_3gm']) ?></td>
        </tr>
        <tr>
            <td><?= htmlspecialchars($player2['pos']) ?></td>
            <td><?= htmlspecialchars($player2['name']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_gm']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_gs']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_min']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_fgm']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_fga']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_ftm']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_fta']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_3gm']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_3ga']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_orb']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_drb']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_ast']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_stl']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_to']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_blk']) ?></td>
            <td><?= htmlspecialchars((string)$player2['stats_pf']) ?></td>
            <td><?= (2 * $player2['stats_fgm'] + $player2['stats_ftm'] + $player2['stats_3gm']) ?></td>
        </tr>
    </tbody>
</table>
</div>
</div>

<h2 class="ibl-title">Career Stats</h2>
<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<table class="sortable compare-players-table ibl-data-table responsive-table">
    <colgroup>
        <col span="4">
        <col span="6" class="compare-highlight-cols">
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
            <td><?= htmlspecialchars($player1['pos']) ?></td>
            <td><?= htmlspecialchars($player1['name']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_gm']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_min']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_fgm']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_fga']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_ftm']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_fta']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_tgm']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_tga']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_orb']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_drb']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_reb']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_ast']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_stl']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_to']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_blk']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_pf']) ?></td>
            <td><?= htmlspecialchars((string)$player1['car_pts']) ?></td>
        </tr>
        <tr>
            <td><?= htmlspecialchars($player2['pos']) ?></td>
            <td><?= htmlspecialchars($player2['name']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_gm']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_min']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_fgm']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_fga']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_ftm']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_fta']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_tgm']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_tga']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_orb']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_drb']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_reb']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_ast']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_stl']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_to']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_blk']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_pf']) ?></td>
            <td><?= htmlspecialchars((string)$player2['car_pts']) ?></td>
        </tr>
    </tbody>
</table>
</div>
</div>
        <?php
        return ob_get_clean();
    }
}
