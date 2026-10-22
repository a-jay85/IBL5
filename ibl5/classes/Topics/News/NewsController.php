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
        private readonly \Api\Contracts\HtmlResponderInterface $htmlResponder = new \Api\Response\HtmlResponder(),
        private readonly ?\TradeSuggestions\Contracts\TradeSuggestionsServiceInterface $tradeSuggestionsService = null,
        private readonly ?\TradeSuggestions\Contracts\TradeSuggestionsViewInterface $tradeSuggestionsView = null,
    ) {}

    public function main(mixed $new_topic): void
    {
        $storyhome = $this->config->storyHome;
        $sitename = $this->config->siteName;
        $user_news = $this->config->userNews;
        $userinfo = $this->authService->isAuthenticated() ? $this->authService->getUserInfo() : null;
        $new_topic = is_numeric($new_topic) ? (int) $new_topic : 0;
        $this->renderHeader();
        $this->renderSimRecap($userinfo);
        $this->renderTradeSuggestions($userinfo);

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
            $safeTopicTitle = null;
            if ($topicText !== null) {
                $safeTopicTitle = \Security\HtmlSanitizer::safeHtmlOutput($topicText);
                $newsService->bumpAllTopics();
            }
            $this->htmlResponder->html($this->newsView->renderTopicBanner(
                $sitename,
                $safeTopicTitle,
                $new_topic,
                $this->config->tableBgcolor1,
                $this->config->tableBgcolor2,
            ));
        }

        $stories = $this->queryStories($new_topic, $storynum);
        $this->renderArticles($stories, $userinfo);
        \PageLayout\PageLayout::footer();
    }

    private function renderHeader(): void
    {
        \PageLayout\PageLayout::header();

        $this->htmlResponder->html($this->newsView->renderPageTitle(defined('HOME_FILE')));
    }

    /**
     * @param array<string, mixed>|null $userinfo
     */
    private function renderSimRecap(?array $userinfo): void
    {
        $tid = $this->resolveGmTid($userinfo);
        if ($tid !== null) {
            $recapService = $this->lastSimRecapService;
            $slate = $recapService->buildSlateForTeam($tid);
            if ($slate !== null) {
                $this->htmlResponder->html($this->lastSimRecapView->render($slate));
            }
        }
    }

    /**
     * @param array<string, mixed>|null $userinfo
     */
    private function renderTradeSuggestions(?array $userinfo): void
    {
        $service = $this->tradeSuggestionsService;
        $view = $this->tradeSuggestionsView;
        if ($service === null || $view === null) {
            return;
        }
        $tid = $this->resolveGmTid($userinfo);
        if ($tid === null) {
            return;
        }
        // The panel is optional; a failure in it must not take down the homepage.
        try {
            $panel = $service->buildForTeam($tid);
        } catch (\Throwable $e) {
            \Logging\LoggerFactory::getChannel('app')->error('Trade suggestions panel failed', ['tid' => $tid, 'error' => $e->getMessage()]);
            return;
        }
        if ($panel !== null) {
            $this->htmlResponder->html($view->render($panel, isset($_GET['trade_mode_error'])));
        }
    }

    /**
     * The logged-in GM's real-franchise tid, or null for anonymous visitors,
     * the Free Agents GM, unknown teams, and non-franchise teams.
     *
     * @param array<string, mixed>|null $userinfo
     */
    private function resolveGmTid(?array $userinfo): ?int
    {
        if ($this->authService->isAuthenticated()) {
            $teamRepo = $this->teamIdentityRepository;
            $username = $userinfo['username'] ?? null;
            $teamName = $teamRepo->getTeamnameFromUsername(is_string($username) ? $username : null);
            if ($teamName !== null && $teamName !== \League\League::FREE_AGENTS_TEAM_NAME) {
                $tid = $teamRepo->getTidFromTeamname($teamName);
                if ($tid !== null && \League\League::isRealFranchise($tid)) {
                    return $tid;
                }
            }
        }

        return null;
    }

    /**
     * Pure SQL-fragment builder. Byte-identical to the historical interpolation for every value the
     * language-file loader can produce (SecurityBootstrap::includeSafe charset, at most varchar(30)
     * nuke_stories.alanguage); any other byte sequence fails closed to the empty-language match.
     */
    private static function buildLanguageClause(int $multilingual, string $currentlang): string
    {
        if ($multilingual !== 1) {
            return "";
        }
        if (preg_match('/^[A-Za-z0-9_-]{0,30}\z/', $currentlang) !== 1) {
            return "AND (alanguage='')";
        }

        return "AND (alanguage='$currentlang' OR alanguage='')";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queryStories(int $new_topic, int $storynum): array
    {
        $querylang = self::buildLanguageClause($this->config->multilingual, $this->config->currentLang);

        return ($new_topic === 0)
            ? $this->newsService->getHomePageStories($storynum, $querylang)
            : $this->newsService->getTopicPageStories($new_topic, $storynum, $querylang);
    }

    /**
     * @param array<int, array<string, mixed>> $stories
     * @param array<string, mixed>|null $userinfo
     */
    private function renderArticles(array $stories, ?array $userinfo): void
    {
        $articlecomm = $this->config->articleComm;
        $newsService = $this->newsService;
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
    }
}
