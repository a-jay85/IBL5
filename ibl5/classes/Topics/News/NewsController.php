<?php

declare(strict_types=1);

namespace Topics\News;

use Topics\News\Contracts\NewsControllerInterface;

// English UI strings defined in this file.
if (!defined('_READMORE')) {
    define('_READMORE', 'Read More...');
}
if (!defined('_BYTESMORE')) {
    define('_BYTESMORE', 'bytes more');
}
if (!defined('_COMMENTSQ')) {
    define('_COMMENTSQ', 'comments?');
}
if (!defined('_COMMENT')) {
    define('_COMMENT', 'comment');
}
if (!defined('_NOINFO4TOPIC')) {
    define('_NOINFO4TOPIC', "Sorry, there isn't information for the selected topic.");
}
if (!defined('_GOTONEWSINDEX')) {
    define('_GOTONEWSINDEX', 'Go to News Index');
}
if (!defined('_SELECTNEWTOPIC')) {
    define('_SELECTNEWTOPIC', 'Select a New Topic');
}
if (!defined('_SEARCHONTOPIC')) {
    define('_SEARCHONTOPIC', 'Search on This Topic');
}
if (!defined('_GOTOHOME')) {
    define('_GOTOHOME', 'Go to Home');
}

class NewsController implements NewsControllerInterface
{
    public function __construct(
        private readonly NewsPageConfig $config,
        private readonly \Auth\Contracts\AuthServiceInterface $authService,
        private readonly \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepository,
        private readonly \LastSimRecap\Contracts\LastSimRecapServiceInterface $lastSimRecapService,
        private readonly \LastSimRecap\Contracts\LastSimRecapViewInterface $lastSimRecapView,
        private readonly \Topics\News\Contracts\NewsServiceInterface $newsService,
        private readonly \Topics\News\Contracts\NewsViewInterface $newsView,
    ) {}

    public function main(mixed $new_topic): void
    {
        $storyhome = $this->config->storyHome;
        $multilingual = $this->config->multilingual;
        $currentlang = $this->config->currentLang;
        $articlecomm = $this->config->articleComm;
        $sitename = $this->config->siteName;
        $user_news = $this->config->userNews;
        $userinfo = $this->authService->isAuthenticated() ? $this->authService->getUserInfo() : null;
        $new_topic = is_numeric($new_topic) ? (int) $new_topic : 0;
        if ($multilingual === 1) {
            $querylang = "AND (alanguage='$currentlang' OR alanguage='')";
        } else {
            $querylang = "";
        }
        \PageLayout\PageLayout::header();

        if (defined('HOME_FILE')) {
            echo '<h1 class="ibl-title">IBL: Internet Basketball League</h1>';
        } else {
            echo '<h1 class="ibl-title">News</h1>';
        }

        if ($this->authService->isAuthenticated()) {
            $teamRepo = $this->teamIdentityRepository;
            $username = $userinfo['username'] ?? null;
            $teamName = $teamRepo->getTeamnameFromUsername(is_string($username) ? $username : null);
            if ($teamName !== null && $teamName !== \League\League::FREE_AGENTS_TEAM_NAME) {
                $tid = $teamRepo->getTidFromTeamname($teamName);
                if ($tid !== null && \League\League::isRealFranchise($tid)) {
                    $recapService = $this->lastSimRecapService;
                    $slate = $recapService->buildSlateForTeam($tid);
                    if ($slate !== null) {
                        echo $this->lastSimRecapView->render($slate);
                    }
                }
            }
        }

        if (defined('HOME_FILE')) {
            blocks("Center");
        }

        $userStoryNum = $userinfo['storynum'] ?? null;
        if (is_numeric($userStoryNum) && $user_news === 1) {
            $storynum = (int) $userStoryNum;
        } else {
            $storynum = $storyhome;
        }

        $newsService = $this->newsService;

        if ($new_topic !== 0) {
            $topicText = $newsService->getTopicText($new_topic);
            OpenTable();
            if ($topicText === null) {
                echo "<center><font class=\"title\">$sitename</font><br><br>" . _NOINFO4TOPIC . "<br><br>[ <a href=\"modules.php?name=News\">" . _GOTONEWSINDEX . "</a> | <a href=\"modules.php?name=Topics\">" . _SELECTNEWTOPIC . "</a> ]</center>";
            } else {
                $topic_title = \Security\HtmlSanitizer::safeHtmlOutput($topicText);
                $newsService->bumpAllTopics();
                echo "<center><font class=\"title\">$sitename: $topic_title</font><br><br>"
                    . "<form action=\"modules.php?name=Search\" method=\"post\">"
                    . "<input type=\"hidden\" name=\"topic\" value=\"$new_topic\">"
                    . "" . _SEARCHONTOPIC . ": <input type=\"name\" name=\"query\" size=\"30\">&nbsp;&nbsp;"
                    . "<input type=\"submit\" value=\"" . _SEARCH . "\">"
                    . "</form>"
                    . "[ <a href=\"index.php\">" . _GOTOHOME . "</a> | <a href=\"modules.php?name=Topics\">" . _SELECTNEWTOPIC . "</a> ]</center>";
            }
            CloseTable();
            echo "<br>";
        }

        $stories = ($new_topic === 0)
            ? $newsService->getHomePageStories($storynum, $querylang)
            : $newsService->getTopicPageStories($new_topic, $storynum, $querylang);
        $viewModels = [];
        /**
         * nuke_stories row (per ibl5/docs/schema/current-schema.sql): sid int(11) NOT NULL, catid int(11) NOT NULL,
         * aid varchar(30) NOT NULL, title varchar(80) NULL, time datetime NULL (normalizeStoryTime(int|string)
         * already requires non-null; unchanged), hometext/bodytext/notes mediumtext NULL, comments int(11) NULL,
         * counter mediumint NULL, topic int(11) NOT NULL, informant varchar(20) NOT NULL, acomm int(11) NOT NULL.
         * @var array{sid: int, catid: int, aid: string, title: string|null, time: string, hometext: string|null, bodytext: string|null, comments: int|null, counter: int|null, topic: int, informant: string, notes: string|null, acomm: int} $row
         */
        foreach ($stories as $row) {
            $s_sid = (int) $row['sid'];
            $catid = (int) $row['catid'];
            $aid = $row['aid'];
            $title = \Security\HtmlSanitizer::safeHtmlOutput($row['title']);
            $hometext = $row['hometext'];
            $bodytext = $row['bodytext'];
            $comments = (int) $row['comments'];
            $counter = (int) $row['counter'];
            $topic = (int) $row['topic'];
            $informant = $row['informant'];
            $notes = \Security\HtmlSanitizer::safeHtmlOutput($row['notes']);
            $acomm = (int) $row['acomm'];
            $topicRow = $newsService->getTopicForStory($s_sid);
            $topicname = \Security\HtmlSanitizer::e($topicRow['topicname'] ?? '');
            $topicimage = \Security\HtmlSanitizer::e($topicRow['topicimage'] ?? '');
            $topictext = \Security\HtmlSanitizer::e($topicRow['topictext'] ?? '');
            $time = $newsService->normalizeStoryTime($row['time']);
            $counts = $newsService->computeByteCounts((string) ($hometext ?? ''), (string) ($bodytext ?? ''));
            $fullcount = $counts['full'];
            $totalcount = $counts['total'];
            $c_count = $comments;
            $r_options = "";
            $umode = $userinfo['umode'] ?? null;
            $uorder = $userinfo['uorder'] ?? null;
            $thold = $userinfo['thold'] ?? null;
            if (is_string($umode)) {$r_options .= "&amp;mode=" . $umode;}
            if (is_int($uorder) || is_string($uorder)) {$r_options .= "&amp;order=" . $uorder;}
            if (is_int($thold) || is_string($thold)) {$r_options .= "&amp;thold=" . $thold;}
            $story_url = "modules.php?name=News&amp;file=article&amp;sid=$s_sid$r_options";
            $morelink_parts = [];
            if ($fullcount > 0 or $c_count > 0 or $articlecomm === 0 or $acomm === 1) {
                $morelink_parts[] = "<a class=\"news-article__link\" href=\"$story_url\">" . _READMORE . "</a>";
            }
            if ($fullcount > 0) {
                $morelink_parts[] = "<span class=\"news-article__meta-item\">$totalcount " . _BYTESMORE . "</span>";
            }
            if ($articlecomm === 1 and $acomm === 0) {
                if ($c_count === 0) {
                    $morelink_parts[] = "<a class=\"news-article__link\" href=\"$story_url\">" . _COMMENTSQ . "</a>";
                } elseif ($c_count === 1) {
                    $morelink_parts[] = "<a class=\"news-article__link\" href=\"$story_url\">$c_count " . _COMMENT . "</a>";
                } elseif ($c_count > 1) {
                    $morelink_parts[] = "<a class=\"news-article__link\" href=\"$story_url\">$c_count " . _COMMENTS . "</a>";
                }
            }
            if ($catid !== 0) {
                $catTitle = $newsService->getCategoryTitle($catid);
                $title1 = \Security\HtmlSanitizer::safeHtmlOutput($catTitle ?? '');
                $catLabel = $title1 !== '' ? $title1 : 'Category';
                $title = "<a class='readmore' href=\"modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=$catid\" aria-label=\"$catLabel\"><font class=\"storycat\">$title1</font></a>: $title";
            }
            $morelink = implode(' | ', $morelink_parts);
            $viewModels[] = [
                'aid' => $aid, 'informant' => $informant, 'time' => $time, 'title' => $title,
                'counter' => $counter, 'topic' => $topic, 'hometext' => $hometext,
                'notes' => $notes, 'morelink' => $morelink, 'topicname' => $topicname,
                'topicimage' => $topicimage, 'topictext' => $topictext,
            ];
        }
        $this->newsView->renderStories($viewModels);
        \PageLayout\PageLayout::footer();
    }
}
