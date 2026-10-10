<?php

if (!defined('BLOCK_FILE')) {
    header("Location: ./index.php");
    die();
}

use Player\PlayerImageHelper;
use Security\HtmlSanitizer;

$mysqli_db = $GLOBALS['mysqli_db'] ?? null;
$leagueContext = $GLOBALS['leagueContext'] ?? null;
if (!$mysqli_db instanceof \mysqli || !$leagueContext instanceof \League\LeagueContext) {
    $content = '';
    return;
}

$leagueConfig = $leagueContext->getConfig();
$imagesPath = $leagueConfig['images_path'];

$queryTopFiveInSeasonStatAverages = "SELECT *
    FROM (
        SELECT
            p.pid,
            p.teamid,
            p.name,
            t.team_name AS teamname,
            ROUND((2 * p.stats_fgm + p.stats_ftm + p.stats_3gm) / p.stats_gm, 1) AS stat_value,
            'Points' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY (2 * p.stats_fgm + p.stats_ftm + p.stats_3gm) / p.stats_gm DESC) AS rn
        FROM ibl_plr p
        INNER JOIN ibl_team_info t ON p.teamid = t.teamid
        WHERE p.retired = 0 AND p.stats_gm > 0 AND p.name NOT LIKE '%Buyouts%'

        UNION ALL

        SELECT
            p.pid,
            p.teamid,
            p.name,
            t.team_name AS teamname,
            ROUND((p.stats_orb + p.stats_drb) / p.stats_gm, 1) AS stat_value,
            'Rebounds' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY (p.stats_orb + p.stats_drb) / p.stats_gm DESC) AS rn
        FROM ibl_plr p
        INNER JOIN ibl_team_info t ON p.teamid = t.teamid
        WHERE p.retired = 0 AND p.stats_gm > 0 AND p.name NOT LIKE '%Buyouts%'

        UNION ALL

        SELECT
            p.pid,
            p.teamid,
            p.name,
            t.team_name AS teamname,
            ROUND(p.stats_ast / p.stats_gm, 1) AS stat_value,
            'Assists' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY p.stats_ast / p.stats_gm DESC) AS rn
        FROM ibl_plr p
        INNER JOIN ibl_team_info t ON p.teamid = t.teamid
        WHERE p.retired = 0 AND p.stats_gm > 0 AND p.name NOT LIKE '%Buyouts%'

        UNION ALL

        SELECT
            p.pid,
            p.teamid,
            p.name,
            t.team_name AS teamname,
            ROUND(p.stats_stl / p.stats_gm, 1) AS stat_value,
            'Steals' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY p.stats_stl / p.stats_gm DESC) AS rn
        FROM ibl_plr p
        INNER JOIN ibl_team_info t ON p.teamid = t.teamid
        WHERE p.retired = 0 AND p.stats_gm > 0 AND p.name NOT LIKE '%Buyouts%'

        UNION ALL

        SELECT
            p.pid,
            p.teamid,
            p.name,
            t.team_name AS teamname,
            ROUND(p.stats_blk / p.stats_gm, 1) AS stat_value,
            'Blocks' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY p.stats_blk / p.stats_gm DESC) AS rn
        FROM ibl_plr p
        INNER JOIN ibl_team_info t ON p.teamid = t.teamid
        WHERE p.retired = 0 AND p.stats_gm > 0 AND p.name NOT LIKE '%Buyouts%'
    ) t
    WHERE rn <= 5
    ORDER BY FIELD(stat_type, 'Points', 'Rebounds', 'Assists', 'Steals', 'Blocks'), rn;";
$resultTopFiveInSeasonStatAverages = $mysqli_db->query($queryTopFiveInSeasonStatAverages);
if (!$resultTopFiveInSeasonStatAverages instanceof \mysqli_result) {
    $content = '';
    return;
}

$rows = $resultTopFiveInSeasonStatAverages->fetch_all(MYSQLI_ASSOC);

// Group rows by stat type
$statCategories = [];
foreach ($rows as $row) {
    $statType = $row['stat_type'] ?? null;
    if (!is_string($statType)) {
        continue;
    }
    $pidValue = $row['pid'] ?? null;
    $teamidValue = $row['teamid'] ?? null;
    $nameValue = $row['name'] ?? null;
    $teamnameValue = $row['teamname'] ?? null;
    $statValue = $row['stat_value'] ?? null;
    $statCategories[$statType][] = [
        'pid' => is_numeric($pidValue) ? (int) $pidValue : 0,
        'teamid' => is_numeric($teamidValue) ? (int) $teamidValue : 0,
        'name' => is_scalar($nameValue) ? (string) $nameValue : '',
        'teamname' => is_scalar($teamnameValue) ? (string) $teamnameValue : '',
        'stat_value' => is_scalar($statValue) ? (string) $statValue : '',
    ];
}

// Tab labels
$tabLabels = [
    'Points' => 'PTS',
    'Rebounds' => 'REB',
    'Assists' => 'AST',
    'Steals' => 'STL',
    'Blocks' => 'BLK',
];

$blockId = 'season-leaders-' . uniqid();
$categories = array_keys($statCategories);
$firstCategory = $categories[0] ?? 'Points';

// Compact tabbed layout with header
$content = '<div class="leaders-tabbed" id="' . $blockId . '">
    <div class="leaders-tabbed__header">
        <h2 class="leaders-tabbed__title">League Leaders</h2>
    </div>
    <div class="ibl-tabs" role="tablist">';

// Generate tabs
foreach ($categories as $index => $category) {
    $tabId = $blockId . '-tab-' . $index;
    $panelId = $blockId . '-panel-' . $index;
    $isActive = ($category === $firstCategory) ? ' ibl-tab--active' : '';
    $ariaSelected = ($category === $firstCategory) ? 'true' : 'false';
    $tabLabel = $tabLabels[$category] ?? HtmlSanitizer::safeHtmlOutput($category);

    $content .= '<button class="ibl-tab' . $isActive . '" id="' . $tabId . '" role="tab" aria-selected="' . $ariaSelected . '" aria-controls="' . $panelId . '">' . $tabLabel . '</button>';
}

$content .= '</div>
    <div class="leaders-tabbed__panels">';

// Generate panels
foreach ($categories as $index => $category) {
    $players = $statCategories[$category];
    $tabId = $blockId . '-tab-' . $index;
    $panelId = $blockId . '-panel-' . $index;
    $isActive = ($category === $firstCategory) ? ' leaders-tabbed__panel--active' : '';

    // Leader (first player)
    $leader = $players[0];
    $leaderPid = $leader['pid'];
    $leaderTid = $leader['teamid'];
    $leaderName = HtmlSanitizer::safeHtmlOutput($leader['name']);
    $leaderTeam = HtmlSanitizer::safeHtmlOutput($leader['teamname']);
    $leaderValue = HtmlSanitizer::safeHtmlOutput($leader['stat_value']);
    $leaderImgUrl = PlayerImageHelper::getImageUrl($leaderPid);

    $content .= '<div class="leaders-tabbed__panel' . $isActive . '" id="' . $panelId . '" role="tabpanel" aria-labelledby="' . $tabId . '">
        <div class="leaders-tabbed__leader">
            <div class="leaders-tabbed__leader-images">
                <img src="' . HtmlSanitizer::safeHtmlOutput($leaderImgUrl) . '" alt="' . $leaderName . '" class="leaders-tabbed__leader-img" loading="lazy">';

    if ($leaderTid !== 0) {
        $content .= '<img src="./' . HtmlSanitizer::safeHtmlOutput($imagesPath) . 'logo/new' . $leaderTid . '.png" alt="' . $leaderTeam . '" class="leaders-tabbed__leader-team-img" loading="lazy">';
    }

    $content .= '</div>
            <div class="leaders-tabbed__leader-info">
                <a href="modules.php?name=Player&pa=showpage&pid=' . $leaderPid . '" class="leaders-tabbed__leader-name">' . $leaderName . '</a>
                <a href="modules.php?name=Team&op=team&teamid=' . $leaderTid . '" class="leaders-tabbed__leader-team">' . $leaderTeam . '</a>
            </div>
            <div class="leaders-tabbed__leader-value">' . $leaderValue . '</div>
        </div>
        <ul class="leaders-tabbed__runners">';

    // Runners-up (positions 2-5)
    foreach (array_slice($players, 1) as $runnerOffset => $player) {
        $pid = $player['pid'];
        $teamid = $player['teamid'];
        $name = HtmlSanitizer::safeHtmlOutput($player['name']);
        $team = HtmlSanitizer::safeHtmlOutput($player['teamname']);
        $value = HtmlSanitizer::safeHtmlOutput($player['stat_value']);
        $rank = $runnerOffset + 2;

        $teamLogo = $teamid !== 0 ? '<img src="./' . HtmlSanitizer::safeHtmlOutput($imagesPath) . 'logo/new' . $teamid . '.png" alt="' . $team . '" class="leaders-tabbed__runner-logo" loading="lazy">' : '';

        $content .= '<li class="leaders-tabbed__runner">
            <span class="leaders-tabbed__runner-rank">#' . $rank . '</span>
            ' . $teamLogo . '
            <a href="modules.php?name=Player&pa=showpage&pid=' . $pid . '" class="leaders-tabbed__runner-name">' . $name . '</a>
            <span class="leaders-tabbed__runner-value">' . $value . '</span>
        </li>';
    }

    $content .= '</ul>
    </div>';
}

$content .= '</div>
</div>
<script>
(function() {
    var block = document.getElementById("' . $blockId . '");
    if (!block) return;
    var tabs = block.querySelectorAll(".ibl-tab");
    var panels = block.querySelectorAll(".leaders-tabbed__panel");
    tabs.forEach(function(tab) {
        tab.addEventListener("click", function() {
            tabs.forEach(function(t) { t.classList.remove("ibl-tab--active"); t.setAttribute("aria-selected", "false"); });
            panels.forEach(function(p) { p.classList.remove("leaders-tabbed__panel--active"); });
            tab.classList.add("ibl-tab--active");
            tab.setAttribute("aria-selected", "true");
            var panel = document.getElementById(tab.getAttribute("aria-controls"));
            if (panel) panel.classList.add("leaders-tabbed__panel--active");
            if (window.IBL_refreshNameAbbreviations) window.IBL_refreshNameAbbreviations();
        });
    });
})();
</script>';

?>
