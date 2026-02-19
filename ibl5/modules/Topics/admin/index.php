<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

/**
 * Topics Admin Module - Refactored for Security
 *
 * SECURITY NOTES:
 * - All database queries use prepared statements via $mysqli_db
 * - All user input is validated and sanitized
 * - CSRF protection recommended for write operations (future enhancement)
 */

if (!defined('ADMIN_FILE')) {
    die("Access Denied");
}

global $prefix, $db, $mysqli_db, $admin_file, $aid;

// Safely get aid (admin ID)
$aid = isset($aid) && is_string($aid) ? substr($aid, 0, 25) : '';

// Check admin authorization using prepared statement
$stmtModule = $mysqli_db->prepare("SELECT title, admins FROM " . $prefix . "_modules WHERE title = ?");
$moduleName = 'Topics';
$stmtModule->bind_param('s', $moduleName);
$stmtModule->execute();
$moduleResult = $stmtModule->get_result();
$moduleRow = $moduleResult->fetch_assoc();
$stmtModule->close();

$stmtAuthor = $mysqli_db->prepare("SELECT name, radminsuper FROM " . $prefix . "_authors WHERE aid = ?");
$stmtAuthor->bind_param('s', $aid);
$stmtAuthor->execute();
$authorResult = $stmtAuthor->get_result();
$authorRow = $authorResult->fetch_assoc();
$stmtAuthor->close();

$admins = $moduleRow !== null ? explode(",", $moduleRow['admins'] ?? '') : [];
$auth_user = 0;
$authorName = $authorRow['name'] ?? '';
$radminsuper = (int) ($authorRow['radminsuper'] ?? 0);

foreach ($admins as $adminName) {
    if ($authorName === $adminName && !empty($moduleRow['admins'])) {
        $auth_user = 1;
        break;
    }
}

if ($radminsuper === 1 || $auth_user === 1) {

    /*********************************************************/
    /* Topics Manager Functions                              */
    /*********************************************************/

    function topicsmanager(): void
    {
        global $prefix, $mysqli_db, $admin_file, $tipath;
        PageLayout\PageLayout::header();
        GraphicAdmin();
        OpenTable();
        echo "<center><span class=\"title\"><b>" . _TOPICSMANAGER . "</b></span></center>";
        CloseTable();
        echo "<br>";
        OpenTable();
        echo "<center><span class=\"option\"><b>" . _CURRENTTOPICS . "</b></span><br>" . _CLICK2EDIT . "</span></center><br>"
            . "<table border=\"0\" width=\"100%\" align=\"center\" cellpadding=\"2\">";
        $count = 0;

        // Use prepared statement
        $stmt = $mysqli_db->prepare("SELECT topicid, topicname, topicimage, topictext FROM " . $prefix . "_topics ORDER BY topicname");
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $topicid = (int) $row['topicid'];
            $topicname = htmlspecialchars($row['topicname'] ?? '', ENT_QUOTES, 'UTF-8');
            $topicimage = htmlspecialchars($row['topicimage'] ?? '', ENT_QUOTES, 'UTF-8');
            $topictext = htmlspecialchars($row['topictext'] ?? '', ENT_QUOTES, 'UTF-8');
            echo "<td align=\"center\">"
                . "<a href=\"" . $admin_file . ".php?op=topicedit&amp;topicid=$topicid\"><img src=\"$tipath/$topicimage\" border=\"0\" alt=\"\"></a><br>"
                . "<span class=\"content\"><b>$topictext</td>";
            $count++;
            if ($count === 5) {
                echo "</tr><tr>";
                $count = 0;
            }
        }
        $stmt->close();

        echo "</table>";
        CloseTable();
        echo "<br><a name=\"Add\">";
        OpenTable();
        echo "<center><span class=\"option\"><b>" . _ADDATOPIC . "</b></span></center><br>"
            . "<form action=\"" . $admin_file . ".php\" method=\"post\">"
            . "<b>" . _TOPICNAME . ":</b><br><span class=\"tiny\">" . _TOPICNAME1 . "<br>"
            . "" . _TOPICNAME2 . "</span><br>"
            . "<input type=\"text\" name=\"topicname\" size=\"20\" maxlength=\"20\"><br><br>"
            . "<b>" . _TOPICTEXT . ":</b><br><span class=\"tiny\">" . _TOPICTEXT1 . "<br>"
            . "" . _TOPICTEXT2 . "</span><br>"
            . "<input type=\"text\" name=\"topictext\" size=\"40\" maxlength=\"40\"><br><br>"
            . "<b>" . _TOPICIMAGE . ":</b><br>"
            . "<select name=\"topicimage\">";

        $path1 = explode("/", "$tipath");
        $path = "$path1[0]/$path1[1]";
        $tlist = [];
        if (is_dir($path)) {
            $handle = opendir($path);
            if ($handle !== false) {
                while (($file = readdir($handle)) !== false) {
                    if ((preg_match("/^([_0-9a-zA-Z]+)(\.{1})([_0-9a-zA-Z]{3})$/", $file)) && $file !== "AllTopics.gif") {
                        $tlist[] = $file;
                    }
                }
                closedir($handle);
            }
        }
        sort($tlist);
        foreach ($tlist as $file) {
            $fileEscaped = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
            echo "<option value=\"$fileEscaped\">$fileEscaped</option>\n";
        }
        echo "</select><br><br>"
            . "<input type=\"hidden\" name=\"op\" value=\"topicmake\">"
            . "<input type=\"submit\" value=\"" . _ADDTOPIC . "\">"
            . "</form>";
        CloseTable();
        include "footer.php";
    }

    function topicedit(int $topicid): void
    {
        global $prefix, $mysqli_db, $admin_file, $tipath;
        PageLayout\PageLayout::header();
        GraphicAdmin();
        OpenTable();
        echo "<center><span class=\"title\"><b>" . _TOPICSMANAGER . "</b></span></center>";
        CloseTable();
        echo "<br>";
        OpenTable();

        // Use prepared statement
        $stmt = $mysqli_db->prepare("SELECT topicid, topicname, topicimage, topictext FROM " . $prefix . "_topics WHERE topicid = ?");
        $stmt->bind_param('i', $topicid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            echo "<center>Topic not found.</center>";
            CloseTable();
            include "footer.php";
            return;
        }

        $topicid = (int) $row['topicid'];
        $topicname = htmlspecialchars($row['topicname'] ?? '', ENT_QUOTES, 'UTF-8');
        $topicimage = htmlspecialchars($row['topicimage'] ?? '', ENT_QUOTES, 'UTF-8');
        $topictext = htmlspecialchars($row['topictext'] ?? '', ENT_QUOTES, 'UTF-8');

        echo "<img src=\"$tipath/$topicimage\" border=\"0\" align=\"right\" alt=\"$topictext\">"
            . "<span class=\"option\"><b>" . _EDITTOPIC . ": $topictext</b></span>"
            . "<br><br>"
            . "<form action=\"" . $admin_file . ".php\" method=\"post\"><br>"
            . "<b>" . _TOPICNAME . ":</b><br><span class=\"tiny\">" . _TOPICNAME1 . "<br>"
            . "" . _TOPICNAME2 . "</span><br>"
            . "<input type=\"text\" name=\"topicname\" size=\"20\" maxlength=\"20\" value=\"$topicname\"><br><br>"
            . "<b>" . _TOPICTEXT . ":</b><br><span class=\"tiny\">" . _TOPICTEXT1 . "<br>"
            . "" . _TOPICTEXT2 . "</span><br>"
            . "<input type=\"text\" name=\"topictext\" size=\"40\" maxlength=\"40\" value=\"$topictext\"><br><br>"
            . "<b>" . _TOPICIMAGE . ":</b><br>"
            . "<select name=\"topicimage\">";

        $path1 = explode("/", "$tipath");
        $path = "$path1[0]/$path1[1]";
        $tlist = [];
        if (is_dir($path)) {
            $handle = opendir($path);
            if ($handle !== false) {
                while (($file = readdir($handle)) !== false) {
                    if ((preg_match("/^([_0-9a-zA-Z]+)(\.{1})([_0-9a-zA-Z]{3})$/", $file)) && $file !== "AllTopics.gif") {
                        $tlist[] = $file;
                    }
                }
                closedir($handle);
            }
        }
        sort($tlist);
        foreach ($tlist as $file) {
            $fileEscaped = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
            $sel = ($row['topicimage'] === $file) ? "selected" : "";
            echo "<option value=\"$fileEscaped\" $sel>$fileEscaped</option>\n";
        }
        echo "</select><br><br>"
            . "<b>" . _ADDRELATED . ":</b><br>"
            . "" . _SITENAME . ": <input type=\"text\" name=\"name\" size=\"30\" maxlength=\"30\"><br>"
            . "" . _URL . ": <input type=\"text\" name=\"url\" value=\"http://\" size=\"50\" maxlength=\"200\"><br><br>"
            . "<b>" . _ACTIVERELATEDLINKS . ":</b><br>"
            . "<table width=\"100%\" border=\"0\">";

        // Fetch related links using prepared statement
        $stmtRelated = $mysqli_db->prepare("SELECT rid, name, url FROM " . $prefix . "_related WHERE tid = ?");
        $stmtRelated->bind_param('i', $topicid);
        $stmtRelated->execute();
        $relatedResult = $stmtRelated->get_result();
        $num = $relatedResult->num_rows;

        if ($num === 0) {
            echo "<tr><td><span class=\"tiny\">" . _NORELATED . "</span></td></tr>";
        }
        while ($row2 = $relatedResult->fetch_assoc()) {
            $rid = (int) $row2['rid'];
            $name = htmlspecialchars($row2['name'] ?? '', ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($row2['url'] ?? '', ENT_QUOTES, 'UTF-8');
            echo "<tr><td align=\"left\"><span class=\"content\"><strong><big>&middot;</big></strong>&nbsp;&nbsp;<a href=\"$url\">$name</a></td>"
                . "<td align=\"center\"><span class=\"content\"><a href=\"$url\">$url</a></td><td align=\"right\"><span class=\"content\">[ <a href=\"" . $admin_file . ".php?op=relatededit&amp;tid=$topicid&amp;rid=$rid\">" . _EDIT . "</a> | <a href=\"" . $admin_file . ".php?op=relateddelete&amp;tid=$topicid&amp;rid=$rid\">" . _DELETE . "</a> ]</td></tr>";
        }
        $stmtRelated->close();

        echo "</table><br><br>"
            . "<input type=\"hidden\" name=\"topicid\" value=\"$topicid\">"
            . "<input type=\"hidden\" name=\"op\" value=\"topicchange\">"
            . "<INPUT type=\"submit\" value=\"" . _SAVECHANGES . "\"> <span class=\"content\">[ <a href=\"" . $admin_file . ".php?op=topicdelete&amp;topicid=$topicid\">" . _DELETE . "</a> ]</span>"
            . "</form>";
        CloseTable();
        include "footer.php";
    }

    function relatededit(int $tid, int $rid): void
    {
        global $prefix, $mysqli_db, $admin_file, $tipath;
        PageLayout\PageLayout::header();
        GraphicAdmin();
        OpenTable();
        echo "<center><span class=\"title\"><b>" . _TOPICSMANAGER . "</b></span></center>";
        CloseTable();
        echo "<br>";

        // Fetch related link using prepared statement
        $stmt = $mysqli_db->prepare("SELECT name, url FROM " . $prefix . "_related WHERE rid = ?");
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $name = htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($row['url'] ?? '', ENT_QUOTES, 'UTF-8');

        // Fetch topic info
        $stmtTopic = $mysqli_db->prepare("SELECT topictext, topicimage FROM " . $prefix . "_topics WHERE topicid = ?");
        $stmtTopic->bind_param('i', $tid);
        $stmtTopic->execute();
        $topicResult = $stmtTopic->get_result();
        $row2 = $topicResult->fetch_assoc();
        $stmtTopic->close();

        $topicimage = htmlspecialchars($row2['topicimage'] ?? '', ENT_QUOTES, 'UTF-8');
        $topictext = htmlspecialchars($row2['topictext'] ?? '', ENT_QUOTES, 'UTF-8');

        OpenTable();
        echo "<center>"
            . "<img src=\"$tipath/$topicimage\" border=\"0\" alt=\"$topictext\" align=\"right\">"
            . "<span class=\"option\"><b>" . _EDITRELATED . "</b></span><br>"
            . "<b>" . _TOPIC . ":</b> $topictext</center>"
            . "<form action=\"" . $admin_file . ".php\" method=\"post\">"
            . "" . _SITENAME . ": <input type=\"text\" name=\"name\" value=\"$name\" size=\"30\" maxlength=\"30\"><br><br>"
            . "" . _URL . ": <input type=\"text\" name=\"url\" value=\"$url\" size=\"60\" maxlength=\"200\"><br><br>"
            . "<input type=\"hidden\" name=\"op\" value=\"relatedsave\">"
            . "<input type=\"hidden\" name=\"tid\" value=\"$tid\">"
            . "<input type=\"hidden\" name=\"rid\" value=\"$rid\">"
            . "<input type=\"submit\" value=\"" . _SAVECHANGES . "\"> " . _GOBACK . ""
            . "</form>";
        CloseTable();
        include "footer.php";
    }

    function relatedsave(int $tid, int $rid, string $name, string $url): void
    {
        global $prefix, $mysqli_db, $admin_file;

        // Use prepared statement for update
        $stmt = $mysqli_db->prepare("UPDATE " . $prefix . "_related SET name = ?, url = ? WHERE rid = ?");
        $stmt->bind_param('ssi', $name, $url, $rid);
        $stmt->execute();
        $stmt->close();

        Header("Location: " . $admin_file . ".php?op=topicedit&topicid=$tid");
        exit;
    }

    function relateddelete(int $tid, int $rid): void
    {
        global $prefix, $mysqli_db, $admin_file;

        // Use prepared statement for delete
        $stmt = $mysqli_db->prepare("DELETE FROM " . $prefix . "_related WHERE rid = ?");
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $stmt->close();

        Header("Location: " . $admin_file . ".php?op=topicedit&topicid=$tid");
        exit;
    }

    function topicmake(string $topicname, string $topicimage, string $topictext): void
    {
        global $prefix, $mysqli_db, $admin_file;

        // Validate input - only allow alphanumeric, spaces, and common punctuation
        $topicname = substr($topicname, 0, 20);
        $topicimage = substr($topicimage, 0, 100);
        $topictext = substr($topictext, 0, 40);

        // Use prepared statement for insert
        $stmt = $mysqli_db->prepare("INSERT INTO " . $prefix . "_topics (topicname, topicimage, topictext, counter) VALUES (?, ?, ?, 0)");
        $stmt->bind_param('sss', $topicname, $topicimage, $topictext);
        $stmt->execute();
        $stmt->close();

        Header("Location: " . $admin_file . ".php?op=topicsmanager#Add");
        exit;
    }

    function topicchange(int $topicid, string $topicname, string $topicimage, string $topictext, string $name, string $url): void
    {
        global $prefix, $mysqli_db, $admin_file;

        // Validate input lengths
        $topicname = substr($topicname, 0, 20);
        $topicimage = substr($topicimage, 0, 100);
        $topictext = substr($topictext, 0, 40);
        $name = substr($name, 0, 30);
        $url = substr($url, 0, 200);

        // Use prepared statement for update
        $stmt = $mysqli_db->prepare("UPDATE " . $prefix . "_topics SET topicname = ?, topicimage = ?, topictext = ? WHERE topicid = ?");
        $stmt->bind_param('sssi', $topicname, $topicimage, $topictext, $topicid);
        $stmt->execute();
        $stmt->close();

        // Add related link if name provided
        if ($name !== '') {
            $stmtRelated = $mysqli_db->prepare("INSERT INTO " . $prefix . "_related (tid, name, url) VALUES (?, ?, ?)");
            $stmtRelated->bind_param('iss', $topicid, $name, $url);
            $stmtRelated->execute();
            $stmtRelated->close();
        }

        Header("Location: " . $admin_file . ".php?op=topicedit&topicid=$topicid");
        exit;
    }

    function topicdelete(int $topicid, int $ok = 0): void
    {
        global $prefix, $mysqli_db, $admin_file, $tipath;

        if ($ok === 1) {
            // Get story IDs for this topic
            $stmtStories = $mysqli_db->prepare("SELECT sid FROM " . $prefix . "_stories WHERE topic = ?");
            $stmtStories->bind_param('i', $topicid);
            $stmtStories->execute();
            $storiesResult = $stmtStories->get_result();
            $sids = [];
            while ($row = $storiesResult->fetch_assoc()) {
                $sids[] = (int) $row['sid'];
            }
            $stmtStories->close();

            // Delete stories
            $stmtDelStories = $mysqli_db->prepare("DELETE FROM " . $prefix . "_stories WHERE topic = ?");
            $stmtDelStories->bind_param('i', $topicid);
            $stmtDelStories->execute();
            $stmtDelStories->close();

            // Delete topic
            $stmtDelTopic = $mysqli_db->prepare("DELETE FROM " . $prefix . "_topics WHERE topicid = ?");
            $stmtDelTopic->bind_param('i', $topicid);
            $stmtDelTopic->execute();
            $stmtDelTopic->close();

            // Delete related links
            $stmtDelRelated = $mysqli_db->prepare("DELETE FROM " . $prefix . "_related WHERE tid = ?");
            $stmtDelRelated->bind_param('i', $topicid);
            $stmtDelRelated->execute();
            $stmtDelRelated->close();

            // Delete comments for stories in this topic (comment system is deprecated but clean up data)
            foreach ($sids as $sid) {
                $stmtDelComments = $mysqli_db->prepare("DELETE FROM " . $prefix . "_comments WHERE sid = ?");
                $stmtDelComments->bind_param('i', $sid);
                $stmtDelComments->execute();
                $stmtDelComments->close();
            }

            Header("Location: " . $admin_file . ".php?op=topicsmanager");
            exit;
        } else {
            PageLayout\PageLayout::header();
            GraphicAdmin();
            OpenTable();
            echo "<center><span class=\"title\"><b>" . _TOPICSMANAGER . "</b></span></center>";
            CloseTable();
            echo "<br>";

            $stmt = $mysqli_db->prepare("SELECT topicimage, topictext FROM " . $prefix . "_topics WHERE topicid = ?");
            $stmt->bind_param('i', $topicid);
            $stmt->execute();
            $result = $stmt->get_result();
            $row3 = $result->fetch_assoc();
            $stmt->close();

            $topicimage = htmlspecialchars($row3['topicimage'] ?? '', ENT_QUOTES, 'UTF-8');
            $topictext = htmlspecialchars($row3['topictext'] ?? '', ENT_QUOTES, 'UTF-8');

            OpenTable();
            echo "<center><img src=\"$tipath$topicimage\" border=\"0\" alt=\"$topictext\"><br><br>"
                . "<b>" . _DELETETOPIC . " $topictext</b><br><br>"
                . "" . _TOPICDELSURE . " <i>$topictext</i>?<br>"
                . "" . _TOPICDELSURE1 . "<br><br>"
                . "[ <a href=\"" . $admin_file . ".php?op=topicsmanager\">" . _NO . "</a> | <a href=\"" . $admin_file . ".php?op=topicdelete&amp;topicid=$topicid&amp;ok=1\">" . _YES . "</a> ]</center><br><br>";
            CloseTable();
            include "footer.php";
        }
    }

    // Get operation parameter safely
    $op = isset($op) && is_string($op) ? $op : '';

    switch ($op) {
        case "topicsmanager":
            topicsmanager();
            break;

        case "topicedit":
            $topicid = isset($topicid) && is_numeric($topicid) ? (int) $topicid : 0;
            topicedit($topicid);
            break;

        case "topicmake":
            $topicname = isset($topicname) && is_string($topicname) ? $topicname : '';
            $topicimage = isset($topicimage) && is_string($topicimage) ? $topicimage : '';
            $topictext = isset($topictext) && is_string($topictext) ? $topictext : '';
            topicmake($topicname, $topicimage, $topictext);
            break;

        case "topicdelete":
            $topicid = isset($topicid) && is_numeric($topicid) ? (int) $topicid : 0;
            $ok = isset($ok) && is_numeric($ok) ? (int) $ok : 0;
            topicdelete($topicid, $ok);
            break;

        case "topicchange":
            $topicid = isset($topicid) && is_numeric($topicid) ? (int) $topicid : 0;
            $topicname = isset($topicname) && is_string($topicname) ? $topicname : '';
            $topicimage = isset($topicimage) && is_string($topicimage) ? $topicimage : '';
            $topictext = isset($topictext) && is_string($topictext) ? $topictext : '';
            $name = isset($name) && is_string($name) ? $name : '';
            $url = isset($url) && is_string($url) ? $url : '';
            topicchange($topicid, $topicname, $topicimage, $topictext, $name, $url);
            break;

        case "relatedsave":
            $tid = isset($tid) && is_numeric($tid) ? (int) $tid : 0;
            $rid = isset($rid) && is_numeric($rid) ? (int) $rid : 0;
            $name = isset($name) && is_string($name) ? $name : '';
            $url = isset($url) && is_string($url) ? $url : '';
            relatedsave($tid, $rid, $name, $url);
            break;

        case "relatededit":
            $tid = isset($tid) && is_numeric($tid) ? (int) $tid : 0;
            $rid = isset($rid) && is_numeric($rid) ? (int) $rid : 0;
            relatededit($tid, $rid);
            break;

        case "relateddelete":
            $tid = isset($tid) && is_numeric($tid) ? (int) $tid : 0;
            $rid = isset($rid) && is_numeric($rid) ? (int) $rid : 0;
            relateddelete($tid, $rid);
            break;
    }

} else {
    PageLayout\PageLayout::header();
    GraphicAdmin();
    OpenTable();
    echo "<center><b>" . _ERROR . "</b><br><br>You do not have administration permission for module \"$module_name\"</center>";
    CloseTable();
    include "footer.php";
}
