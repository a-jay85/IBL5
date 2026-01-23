<?php

if (!defined('BLOCK_FILE')) {
    Header("Location: ./index.php");
    die();
}

use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

global $mysqli_db, $leagueContext;

$leagueConfig = $leagueContext->getConfig();
$imagesPath = $leagueConfig['images_path'];

// Query ibl_sim_dates
$queryLastSimDates = $mysqli_db->query("SELECT * FROM ibl_sim_dates ORDER BY Sim DESC LIMIT 1");
$rowLastSimDates = $queryLastSimDates->fetch_assoc();
$lastSimStartDate = $rowLastSimDates['Start Date'];
$lastSimEndDate = $rowLastSimDates['End Date'];
$simNumber = (int)$rowLastSimDates['Sim'];

$querySimStatLeaders = "SELECT *
FROM (
    SELECT
        players.name,
        boxes.pid,
        players.teamname,
        players.tid,
        CAST(FORMAT((2 * SUM(boxes.game2GM) + SUM(boxes.gameFTM) + 3 * SUM(boxes.game3GM)) / COUNT(players.name), 1) AS DECIMAL(3,1)) AS stat_value,
        'Points' AS stat_type,
        ROW_NUMBER() OVER (ORDER BY (2 * SUM(boxes.game2GM) + SUM(boxes.gameFTM) + 3 * SUM(boxes.game3GM)) / COUNT(players.name) DESC) AS rn
    FROM ibl_box_scores boxes
    INNER JOIN ibl_plr players USING(pid)
    WHERE boxes.Date BETWEEN '$lastSimStartDate' AND '$lastSimEndDate'
    GROUP BY players.name, boxes.pid, players.teamname, players.tid

    UNION ALL

    SELECT
        players.name,
        boxes.pid,
        players.teamname,
        players.tid,
        CAST(FORMAT((SUM(boxes.gameORB) + SUM(boxes.gameDRB)) / COUNT(players.name), 1) AS DECIMAL(3,1)) AS stat_value,
        'Rebounds' AS stat_type,
        ROW_NUMBER() OVER (ORDER BY (SUM(boxes.gameORB) + SUM(boxes.gameDRB)) / COUNT(players.name) DESC) AS rn
    FROM ibl_box_scores boxes
    INNER JOIN ibl_plr players USING(pid)
    WHERE boxes.Date BETWEEN '$lastSimStartDate' AND '$lastSimEndDate'
    GROUP BY players.name, boxes.pid, players.teamname, players.tid

    UNION ALL

    SELECT
        players.name,
        boxes.pid,
        players.teamname,
        players.tid,
        CAST(FORMAT(SUM(boxes.gameAST) / COUNT(players.name), 1) AS DECIMAL(3,1)) AS stat_value,
        'Assists' AS stat_type,
        ROW_NUMBER() OVER (ORDER BY SUM(boxes.gameAST) / COUNT(players.name) DESC) AS rn
    FROM ibl_box_scores boxes
    INNER JOIN ibl_plr players USING(pid)
    WHERE boxes.Date BETWEEN '$lastSimStartDate' AND '$lastSimEndDate'
    GROUP BY players.name, boxes.pid, players.teamname, players.tid

    UNION ALL

    SELECT
        players.name,
        boxes.pid,
        players.teamname,
        players.tid,
        CAST(FORMAT(SUM(boxes.gameSTL) / COUNT(players.name), 1) AS DECIMAL(3,1)) AS stat_value,
        'Steals' AS stat_type,
        ROW_NUMBER() OVER (ORDER BY SUM(boxes.gameSTL) / COUNT(players.name) DESC) AS rn
    FROM ibl_box_scores boxes
    INNER JOIN ibl_plr players USING(pid)
    WHERE boxes.Date BETWEEN '$lastSimStartDate' AND '$lastSimEndDate'
    GROUP BY players.name, boxes.pid, players.teamname, players.tid

    UNION ALL

    SELECT
        players.name,
        boxes.pid,
        players.teamname,
        players.tid,
        CAST(FORMAT(SUM(boxes.gameBLK) / COUNT(players.name), 1) AS DECIMAL(3,1)) AS stat_value,
        'Blocks' AS stat_type,
        ROW_NUMBER() OVER (ORDER BY SUM(boxes.gameBLK) / COUNT(players.name) DESC) AS rn
    FROM ibl_box_scores boxes
    INNER JOIN ibl_plr players USING(pid)
    WHERE boxes.Date BETWEEN '$lastSimStartDate' AND '$lastSimEndDate'
    GROUP BY players.name, boxes.pid, players.teamname, players.tid
) t
WHERE rn <= 5
ORDER BY FIELD(stat_type, 'Points', 'Rebounds', 'Assists', 'Steals', 'Blocks'), rn;";
$resultSimStatLeaders = $mysqli_db->query($querySimStatLeaders);

$rows = $resultSimStatLeaders->fetch_all(MYSQLI_ASSOC);

// Group rows by stat type
$statCategories = [];
foreach ($rows as $row) {
    $statCategories[$row['stat_type']][] = $row;
}

// Tab labels
$tabLabels = [
    'Points' => 'PTS',
    'Rebounds' => 'REB',
    'Assists' => 'AST',
    'Steals' => 'STL',
    'Blocks' => 'BLK',
];

$blockId = 'sim-leaders-' . uniqid();
$categories = array_keys($statCategories);
$firstCategory = $categories[0] ?? 'Points';

// Compact tabbed layout with orange variant
$content = '<div class="leaders-tabbed leaders-tabbed--chunk" id="' . $blockId . '">
    <div class="leaders-tabbed__tabs" role="tablist">';

// Generate tabs
foreach ($categories as $index => $category) {
    $tabId = $blockId . '-tab-' . $index;
    $panelId = $blockId . '-panel-' . $index;
    $isActive = ($category === $firstCategory) ? ' leaders-tabbed__tab--active' : '';
    $ariaSelected = ($category === $firstCategory) ? 'true' : 'false';
    $tabLabel = $tabLabels[$category] ?? HtmlSanitizer::safeHtmlOutput($category);

    $content .= '<button class="leaders-tabbed__tab' . $isActive . '" id="' . $tabId . '" role="tab" aria-selected="' . $ariaSelected . '" aria-controls="' . $panelId . '">' . $tabLabel . '</button>';
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
    $leaderPid = (int)$leader['pid'];
    $leaderTid = (int)$leader['tid'];
    $leaderName = HtmlSanitizer::safeHtmlOutput($leader['name']);
    $leaderTeam = HtmlSanitizer::safeHtmlOutput($leader['teamname']);
    $leaderValue = HtmlSanitizer::safeHtmlOutput($leader['stat_value']);
    $leaderImgUrl = PlayerImageHelper::getImageUrl($leaderPid);

    $content .= '<div class="leaders-tabbed__panel' . $isActive . '" id="' . $panelId . '" role="tabpanel" aria-labelledby="' . $tabId . '">
        <div class="leaders-tabbed__leader">
            <div class="leaders-tabbed__leader-images">
                <img src="' . HtmlSanitizer::safeHtmlOutput($leaderImgUrl) . '" alt="' . $leaderName . '" class="leaders-tabbed__leader-img" loading="lazy">';

    if ($leaderTid) {
        $content .= '<img src="./' . HtmlSanitizer::safeHtmlOutput($imagesPath) . 'logo/new' . $leaderTid . '.png" alt="' . $leaderTeam . '" class="leaders-tabbed__leader-team-img" loading="lazy">';
    }

    $content .= '</div>
            <div class="leaders-tabbed__leader-info">
                <a href="modules.php?name=Player&pa=showpage&pid=' . $leaderPid . '" class="leaders-tabbed__leader-name">' . $leaderName . '</a>
                <a href="modules.php?name=Team&op=team&teamID=' . $leaderTid . '" class="leaders-tabbed__leader-team">' . $leaderTeam . '</a>
            </div>
            <div class="leaders-tabbed__leader-value">' . $leaderValue . '</div>
        </div>
        <ul class="leaders-tabbed__runners">';

    // Runners-up (positions 2-5)
    for ($i = 1; $i < count($players); $i++) {
        $player = $players[$i];
        $pid = (int)$player['pid'];
        $name = HtmlSanitizer::safeHtmlOutput($player['name']);
        $value = HtmlSanitizer::safeHtmlOutput($player['stat_value']);
        $rank = $i + 1;

        $content .= '<li class="leaders-tabbed__runner">
            <span class="leaders-tabbed__runner-rank">#' . $rank . '</span>
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
    var tabs = block.querySelectorAll(".leaders-tabbed__tab");
    var panels = block.querySelectorAll(".leaders-tabbed__panel");
    tabs.forEach(function(tab) {
        tab.addEventListener("click", function() {
            tabs.forEach(function(t) { t.classList.remove("leaders-tabbed__tab--active"); t.setAttribute("aria-selected", "false"); });
            panels.forEach(function(p) { p.classList.remove("leaders-tabbed__panel--active"); });
            tab.classList.add("leaders-tabbed__tab--active");
            tab.setAttribute("aria-selected", "true");
            var panel = document.getElementById(tab.getAttribute("aria-controls"));
            if (panel) panel.classList.add("leaders-tabbed__panel--active");
        });
    });
})();
</script>';

?>
