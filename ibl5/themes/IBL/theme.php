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

function OpenTable()
{
    global $bgcolor1, $bgcolor2;
    echo "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"$bgcolor2\"><tr><td>\n";
    echo "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"8\" bgcolor=\"$bgcolor1\"><tr><td>\n";
}

function OpenTable2()
{
    global $bgcolor1, $bgcolor2;
    echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"$bgcolor2\" align=\"center\"><tr><td>\n";
    echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"8\" bgcolor=\"$bgcolor1\"><tr><td>\n";
}

function CloseTable()
{
    echo "</td></tr></table></td></tr></table>\n";
}

function CloseTable2()
{
    echo "</td></tr></table></td></tr></table>\n";
}

function FormatStory($thetext, $notes, $aid, $informant)
{
    global $anonymous;
    if (!empty($notes)) {
        $notes = "<b>" . _NOTE . "</b> <i>$notes</i>\n";
    } else {
        $notes = "";
    }
    if ("$aid" == "$informant") {
        echo "<font size=\"2\">$thetext<br>$notes</font>\n";
    } else {
        if (!empty($informant)) {
            $boxstuff = "<a href=\"modules.php?name=Your_Account&amp;op=userinfo&amp;username=$informant\">$informant</a> ";
        } else {
            $boxstuff = "$anonymous ";
        }
        $boxstuff .= "" . _WRITES . " <i>\"$thetext\"</i> $notes\n";
        echo "<font size=\"2\">$boxstuff</font>\n";
    }
}

/**
 * Modern story formatting for the redesigned news blocks
 */
function FormatStoryModern($thetext, $notes, $aid, $informant)
{
    global $anonymous;

    // Output the main text
    echo $thetext;

    // Add notes if present
    if (!empty($notes)) {
        echo '<div style="margin-top: 1rem; padding: 0.75rem 1rem; background: var(--gray-50, #f9fafb); border-left: 3px solid var(--accent-500, #f97316); border-radius: 0 0.5rem 0.5rem 0; font-size: 0.875rem; color: var(--gray-600, #4b5563);">
            <strong>' . _NOTE . ':</strong> <em>' . $notes . '</em>
        </div>';
    }

    // If different informant, show attribution
    if ("$aid" != "$informant" && !empty($informant)) {
        echo '<p style="margin-top: 0.75rem; font-size: 0.8125rem; color: var(--gray-500, #6b7280);">
            ' . _WRITES . ': <a href="modules.php?name=Your_Account&amp;op=userinfo&amp;username=' . \Utilities\HtmlSanitizer::safeHtmlOutput($informant) . '" style="color: var(--accent-500, #f97316);">' . \Utilities\HtmlSanitizer::safeHtmlOutput($informant) . '</a>
        </p>';
    }
}

function themeheader()
{
    global $user, $cookie, $bgcolor1, $bgcolor2, $user, $leagueContext, $mysqli_db;

    // Determine login state
    $isLoggedIn = is_user($user);
    $username = null;
    $teamId = null;

    if ($isLoggedIn) {
        cookiedecode($user);
        $username = $cookie[1];

        // Fetch user's team name and then lookup team ID
        if ($mysqli_db && $username) {
            // First get the team name from nuke_users
            $stmt = $mysqli_db->prepare("SELECT user_ibl_team FROM nuke_users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $teamName = trim($row['user_ibl_team']);

                    // If team name exists, lookup the team ID from ibl_team_info
                    if ($teamName !== '' && $teamName !== '0') {
                        $stmt2 = $mysqli_db->prepare("SELECT teamid FROM ibl_team_info WHERE team_name = ?");
                        if ($stmt2) {
                            $stmt2->bind_param('s', $teamName);
                            $stmt2->execute();
                            $result2 = $stmt2->get_result();
                            if ($row2 = $result2->fetch_assoc()) {
                                $teamId = (int)$row2['teamid'];
                            }
                            $stmt2->close();
                        }
                    }
                }
                $stmt->close();
            }
        }
    }

    // Get current league for switcher
    $currentLeague = $leagueContext->getCurrentLeague();

    // Render the floating navigation bar
    $navView = new \Navigation\NavigationView($isLoggedIn, $username, $currentLeague, $teamId);
    echo $navView->render();

    // Body tag and main content wrapper
    echo "<body bgcolor=\"$bgcolor1\">";
    echo "<table border=\"0\" cellpadding=\"4\" cellspacing=\"0\" width=\"100%\" align=\"center\">\n"
        . "<tr><td valign=\"top\" width=\"100%\" colspan=\"3\">\n";
}

function themefooter()
{
    global $bgcolor1;
    // // if (defined('INDEX_FILE')) {
    // echo "</td><td>&nbsp;&nbsp;</td><td valign=\"top\" bgcolor=$bgcolor1>";
    // blocks("right");
    // echo "</td>";
    // // }
    echo "</td></tr></table></td></tr></table>";
    echo "<center>";
    Nuke\Footer::footmsg();
    echo "</center>";
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
    $safeTitle = str_replace(['<font class="storycat">', '</font>'], ['<span class="news-category">', '</span>'], $title);
    $safeTime = \Utilities\HtmlSanitizer::safeHtmlOutput($time);
    $safeTopictext = \Utilities\HtmlSanitizer::safeHtmlOutput($topictext);
    $safeCounter = (int)$counter;

    // Determine if this is a transaction/league news item (topic-based styling)
    $isTransaction = stripos($topictext, 'transaction') !== false ||
                     stripos($topictext, 'trade') !== false ||
                     stripos($topictext, 'waiver') !== false ||
                     stripos($topictext, 'sign') !== false;

    $articleClass = $isTransaction ? 'news-article news-article--transaction' : 'news-article';

    echo '<article class="' . $articleClass . '">
        <header class="news-article__header">
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

    if (!empty($t_image) && file_exists($t_image)) {
        echo '<a href="modules.php?name=News&amp;new_topic=' . (int)$topic . '">
            <img src="' . \Utilities\HtmlSanitizer::safeHtmlOutput($t_image) . '" alt="' . $safeTopictext . '" class="news-article__topic-icon" loading="lazy">
        </a>';
    }

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
    $safeTitle = \Utilities\HtmlSanitizer::safeHtmlOutput($title);
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
                ' . _CONTRIBUTEDBY . ' <a href="modules.php?name=Your_Account&amp;op=userinfo&amp;username=' . $safeInformant . '" style="color: var(--accent-500);">' . $safeInformant . '</a>
            </span>';
        } else {
            $contributorHtml = '<span class="news-article__meta-item">' . _CONTRIBUTEDBY . ' ' . $anonymous . '</span>';
        }
    }

    echo '<article class="news-article" style="max-width: 900px;">
        <header class="news-article__header">
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

    if (!empty($t_image) && file_exists($t_image)) {
        echo '<a href="modules.php?name=News&amp;new_topic=' . (int)$topic . '">
            <img src="' . \Utilities\HtmlSanitizer::safeHtmlOutput($t_image) . '" alt="' . $safeTopictext . '" class="news-article__topic-icon" loading="lazy">
        </a>';
    }

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

function themesidebox($title, $content)
{
    // Use modern card-style sidebar box
    $safeTitle = \Utilities\HtmlSanitizer::safeHtmlOutput($title);

    echo '<aside class="sidebar-block" style="
        font-family: var(--font-sans, Inter, -apple-system, sans-serif);
        background: white;
        border-radius: var(--radius-xl, 0.75rem);
        overflow: hidden;
        box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1));
        border: 1px solid var(--gray-100, #f3f4f6);
        margin-bottom: var(--space-4, 1rem);
    ">
        <header style="
            background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
            padding: var(--space-3, 0.75rem) var(--space-4, 1rem);
        ">
            <h3 style="
                font-family: var(--font-display, Oswald, sans-serif);
                font-size: 0.875rem;
                font-weight: 600;
                color: white;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin: 0;
            ">' . $safeTitle . '</h3>
        </header>
        <div style="padding: var(--space-4, 1rem); font-size: 0.8125rem; color: var(--gray-700, #374151);">
            ' . $content . '
        </div>
    </aside>';
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
        // Add specific class for leaders blocks to enable side-by-side layout
        $isLeadersBlock = strpos($content, 'leaders-tabbed') !== false;
        $wrapperClass = $isLeadersBlock ? 'leaders-grid-item' : '';
        echo '<div class="' . $wrapperClass . '" style="margin-bottom: 1rem; max-width: 100%; overflow: hidden;">'
            . $content
            . '</div>';
    } else {
        // Legacy content needs the box wrapper
        $safeTitle = \Utilities\HtmlSanitizer::safeHtmlOutput($title);

        echo '<section class="center-block" style="
            font-family: var(--font-sans, Inter, -apple-system, sans-serif);
            background: white;
            border-radius: var(--radius-xl, 0.75rem);
            overflow: hidden;
            box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1));
            border: 1px solid var(--gray-100, #f3f4f6);
            margin-bottom: var(--space-6, 1.5rem);
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        ">
            <header style="
                background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
                padding: var(--space-4, 1rem) var(--space-6, 1.5rem);
                text-align: center;
            ">
                <h2 style="
                    font-family: var(--font-display, Oswald, sans-serif);
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: white;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin: 0;
                ">' . $safeTitle . '</h2>
            </header>
            <div style="padding: var(--space-6, 1.5rem);">
                ' . $content . '
            </div>
        </section>';
    }
}
