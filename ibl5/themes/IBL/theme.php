<?php

$lnkcolor = "#336699";
if ($_SERVER['SERVER_NAME'] != "localhost") {
    $bgcolor1 = "#EEEEEE";
} else {
    $bgcolor1 = "#BBBBBB";
}
$bgcolor2 = "#CCCCCC";
$bgcolor3 = "#AAAAAA";
$textcolor1 = "#000000";
$textcolor2 = "#000000";
$theme_home = "Web_Links";
$hr = 1; # 1 to have horizonal rule in comments instead of table bgcolor

/**
 * @deprecated Use CSS classes directly: <div class="ibl-card">
 * Legacy PHP-Nuke table wrapper. Prefer modern CSS components.
 * Remaining callers: ~60 legacy PHP-Nuke module files (News, Your_Account, Trading, etc.)
 */
function OpenTable()
{
    global $bgcolor1, $bgcolor2;
    echo "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"$bgcolor2\"><tr><td>\n";
    echo "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"8\" bgcolor=\"$bgcolor1\"><tr><td>\n";
}

/**
 * @deprecated Use CSS classes directly: <div class="ibl-card">
 * Legacy PHP-Nuke centered table wrapper. Prefer modern CSS components.
 */
function OpenTable2()
{
    global $bgcolor1, $bgcolor2;
    echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"$bgcolor2\" align=\"center\"><tr><td>\n";
    echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"8\" bgcolor=\"$bgcolor1\"><tr><td>\n";
}

/**
 * @deprecated Use CSS classes directly: </div> (closing .ibl-card)
 * Legacy PHP-Nuke table closer. Prefer modern CSS components.
 * Remaining callers: ~60 legacy PHP-Nuke module files (News, Your_Account, Trading, etc.)
 */
function CloseTable()
{
    echo "</td></tr></table></td></tr></table>\n";
}

/**
 * Modern story formatting for the redesigned news blocks
 */
function FormatStoryModern($thetext, $notes, $aid, $informant)
{
    global $anonymous;

    echo $thetext;

    if (!empty($notes)) {
        echo '<div class="news-article__note">
            <strong>' . _NOTE . ':</strong> <em>' . $notes . '</em>
        </div>';
    }

    if ("$aid" != "$informant" && !empty($informant)) {
        echo '<p class="news-article__attribution">
            ' . _WRITES . ': ' . \Utilities\HtmlSanitizer::safeHtmlOutput($informant) . '
        </p>';
    }
}

function themeheader()
{
    global $user, $cookie, $bgcolor1, $leagueContext, $mysqli_db;

    $isLoggedIn = is_user($user);
    $username = null;
    $teamId = null;

    if ($isLoggedIn) {
        cookiedecode($user);
        $username = $cookie[1];
        if ($mysqli_db && $username) {
            $teamId = \Navigation\NavigationView::resolveTeamId($mysqli_db, $username);
        }
    }

    $currentLeague = $leagueContext->getCurrentLeague();

    // Fetch teams organized by conference and division for navigation mega-menu
    $teamsData = null;
    if ($mysqli_db) {
        $teamsData = [];
        $stmt = $mysqli_db->prepare(
            "SELECT ti.teamid, ti.team_name, ti.team_city, s.division, s.conference
             FROM ibl_team_info ti
             JOIN ibl_standings s ON ti.team_name = s.team_name
             ORDER BY s.conference, s.division, ti.team_city"
        );
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $conf = $row['conference'];
                $div = $row['division'];
                $teamsData[$conf][$div][] = [
                    'teamid' => (int)$row['teamid'],
                    'team_name' => $row['team_name'],
                    'team_city' => $row['team_city'],
                ];
            }
            $stmt->close();
        }
        if (empty($teamsData)) {
            $teamsData = null;
        }
    }

    $seasonPhase = '';
    $allowWaivers = '';
    if ($mysqli_db) {
        $season = new \Season($mysqli_db);
        $seasonPhase = $season->phase;
        $allowWaivers = $season->allowWaivers;
    }

    $navView = new \Navigation\NavigationView($isLoggedIn, $username, $currentLeague, $teamId, $teamsData, $seasonPhase, $allowWaivers, $_SERVER['SERVER_NAME'] ?? null, $_SERVER['REQUEST_URI'] ?? null);
    echo $navView->render();

    echo "<body bgcolor=\"$bgcolor1\"" . ($teamId ? " data-user-team-id=\"$teamId\"" : '') . ">";
    echo "<div class=\"site-content\">\n";
}

function themefooter()
{
    global $bgcolor1;
    echo "</div>"; // closes .site-content
    Nuke\Footer::renderPageGenerationTime();
}

function themeindex($aid, $informant, $time, $title, $counter, $topic, $thetext, $notes, $morelink, $topicname, $topicimage, $topictext)
{
    global $tipath;
    $ThemeSel = get_theme();
    if (file_exists("themes/$ThemeSel/images/topics/$topicimage")) {
        $t_image = "themes/$ThemeSel/images/topics/$topicimage";
    } else {
        $t_image = "$tipath$topicimage";
    }

    // Sanitize output
    // Note: $title may contain trusted HTML links from News module (already filtered there)
    // We strip the deprecated <font> tags but preserve the links
    $safeTitle = str_replace(['<font class="storycat">', '</font>'], ['<span class="ibl-badge">', '</span>'], $title);
    $safeTime = \Utilities\HtmlSanitizer::safeHtmlOutput($time);
    $safeTopictext = \Utilities\HtmlSanitizer::safeHtmlOutput($topictext);
    $safeCounter = (int)$counter;

    // Determine if this is a transaction/league news item (topic-based styling)
    $isTransaction = stripos($topictext, 'transaction') !== false ||
                     stripos($topictext, 'trade') !== false ||
                     stripos($topictext, 'waiver') !== false ||
                     stripos($topictext, 'sign') !== false;

    $articleClass = $isTransaction ? 'news-article news-article--transaction' : 'news-article';

    $topicIconHtml = '';
    if (!empty($t_image) && file_exists($t_image)) {
        $topicIconHtml = '<a href="modules.php?name=News&amp;new_topic=' . (int)$topic . '" class="news-article__topic-icon-link"><img src="' . \Utilities\HtmlSanitizer::safeHtmlOutput($t_image) . '" alt="' . $safeTopictext . '" class="news-article__topic-icon" loading="lazy"></a>';
    }

    echo '<article class="' . $articleClass . '">
        <header class="news-article__header">
            ' . $topicIconHtml . '
            <h2 class="news-article__title">' . $safeTitle . '</h2>
            <div class="news-article__meta">
                <span class="news-article__meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    ' . $safeTime . '
                </span>
                <span class="news-article__meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    ';
    formatAidHeader($aid);
    echo '</span>
                <span class="news-article__meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    ' . $safeCounter . ' ' . _READS . '
                </span>
            </div>
        </header>
        <div class="news-article__body">';

    FormatStoryModern($thetext, $notes, $aid, $informant);

    echo '</div>
        <footer class="news-article__footer">
            <div>' . $morelink . '</div>
            <a href="modules.php?name=News&amp;new_topic=' . (int)$topic . '" class="news-article__link">
                ' . $safeTopictext . '
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        </footer>
    </article>';
}

function themearticle($aid, $informant, $datetime, $title, $thetext, $topic, $topicname, $topicimage, $topictext)
{
    global $tipath, $anonymous;
    $ThemeSel = get_theme();
    if (file_exists("themes/$ThemeSel/images/topics/$topicimage")) {
        $t_image = "themes/$ThemeSel/images/topics/$topicimage";
    } else {
        $t_image = "$tipath$topicimage";
    }

    // Sanitize output
    // Note: $title may contain trusted HTML links from News module (already filtered there)
    // We strip the deprecated <font> tags but preserve the links
    $safeTitle = str_replace(['<font class="storycat">', '</font>'], ['<span class="ibl-badge">', '</span>'], $title);
    $safeDatetime = \Utilities\HtmlSanitizer::safeHtmlOutput($datetime);
    $safeTopictext = \Utilities\HtmlSanitizer::safeHtmlOutput($topictext);
    $safeAid = \Utilities\HtmlSanitizer::safeHtmlOutput($aid);

    // Determine contributor info
    $contributorHtml = '';
    if ("$aid" != "$informant") {
        if (!empty($informant)) {
            $safeInformant = \Utilities\HtmlSanitizer::safeHtmlOutput($informant);
            $contributorHtml = '<span class="news-article__meta-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                ' . _CONTRIBUTEDBY . ' ' . $safeInformant . '
            </span>';
        } else {
            $contributorHtml = '<span class="news-article__meta-item">' . _CONTRIBUTEDBY . ' ' . $anonymous . '</span>';
        }
    }

    $topicIconHtml = '';
    if (!empty($t_image) && file_exists($t_image)) {
        $topicIconHtml = '<a href="modules.php?name=News&amp;new_topic=' . (int)$topic . '" class="news-article__topic-icon-link"><img src="' . \Utilities\HtmlSanitizer::safeHtmlOutput($t_image) . '" alt="' . $safeTopictext . '" class="news-article__topic-icon" loading="lazy"></a>';
    }

    echo '<article class="news-article" style="max-width: 900px;">
        <header class="news-article__header">
            ' . $topicIconHtml . '
            <h1 class="news-article__title" style="font-size: 1.5rem;">' . $safeTitle . '</h1>
            <div class="news-article__meta">
                <span class="news-article__meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    ' . _POSTEDON . ' ' . $safeDatetime . '
                </span>
                <span class="news-article__meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    ' . $safeAid . '
                </span>
                ' . $contributorHtml . '
            </div>
        </header>
        <div class="news-article__body">';

    echo $thetext;

    echo '</div>
        <footer class="news-article__footer">
            <a href="modules.php?name=News&amp;new_topic=' . (int)$topic . '" class="news-article__link">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                ' . _TOPIC . ': ' . $safeTopictext . '
            </a>
        </footer>
    </article>';
}

/**
 * Center block for homepage content (Leaders, News, etc.)
 * Modern responsive container styling
 */
function themecenterbox($title, $content)
{
    // Check if content already has modern styling
    $hasModernStyling = strpos($content, 'leaders-block') !== false ||
                        strpos($content, 'leaders-tabbed') !== false ||
                        strpos($content, 'injury-block') !== false ||
                        strpos($content, 'news-block') !== false;

    if ($hasModernStyling) {
        // Content has its own styling, output directly
        $isLeadersBlock = strpos($content, 'leaders-tabbed') !== false;
        $isInjuryBlock = strpos($content, 'injury-block') !== false;

        if ($isLeadersBlock) {
            echo '<div class="leaders-grid-item" style="overflow: hidden;">'
                . $content
                . '</div>';
        } elseif ($isInjuryBlock) {
            echo '<div style="max-width: 100%; overflow: hidden;">'
                . $content
                . '</div>';
        } else {
            echo '<div style="margin-bottom: 1rem; max-width: 100%; overflow: hidden;">'
                . $content
                . '</div>';
        }
    } else {
        // Legacy content needs the box wrapper
        $safeTitle = \Utilities\HtmlSanitizer::safeHtmlOutput($title);

        echo '<section class="ibl-centerbox">
            <header class="ibl-centerbox__header">
                <h2 class="ibl-centerbox__title">' . $safeTitle . '</h2>
            </header>
            <div class="ibl-centerbox__content">' . $content . '</div>
        </section>';
    }
}
