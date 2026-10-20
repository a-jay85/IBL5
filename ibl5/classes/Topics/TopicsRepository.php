<?php

declare(strict_types=1);

namespace Topics;

use Database\BaseMysqliRepository;
use Topics\Contracts\TopicsRepositoryInterface;

/**
 * Repository class for retrieving news topics with articles.
 *
 * Queries the nuke_topics and nuke_stories tables to build a complete
 * view of all active topics with their story counts and recent articles.
 *
 * @phpstan-type TopicDbRow array{topicid: int|string, topicname: string, topicimage: string, topictext: string, stories: int|string, total_reads: int|string}
 * @phpstan-type ArticleDbRow array{sid: int|string, catid: int|string, title: string, cat_title: string}
 *
 * @see TopicsRepositoryInterface
 */
class TopicsRepository extends BaseMysqliRepository implements TopicsRepositoryInterface
{
    /**
     * Table prefixes this repository may splice into table names. The prefix is
     * an identifier (never bindable), so it is allowlisted here and the property
     * below is typed as a member of this list; PHPStan then proves every table
     * name built from it is a constant string (BanSqlStringConcatenationRule).
     *
     * @var list<string>
     */
    private const SUPPORTED_PREFIXES = ['nuke'];

    /** @var value-of<self::SUPPORTED_PREFIXES> Table prefix for nuke_topics / nuke_stories / nuke_stories_cat */
    private string $prefix;

    /**
     * @param \mysqli $db Active mysqli connection
     * @param string $prefix Database table prefix (default: 'nuke')
     */
    public function __construct(\mysqli $db, string $prefix = 'nuke')
    {
        parent::__construct($db);
        // The prefix is an operator-controlled config identifier spliced into table
        // names (never a bindable value). Only members of SUPPORTED_PREFIXES reach
        // query text; anything else falls back to 'nuke', the only prefix the
        // schema defines (see ibl5/docs/schema/current-schema.sql).
        $this->prefix = in_array($prefix, self::SUPPORTED_PREFIXES, true) ? $prefix : 'nuke';
    }

    /**
     * @see TopicsRepositoryInterface::getTopicsWithArticles()
     */
    public function getTopicsWithArticles(): array
    {
        $topicsData = $this->fetchAllTopicsWithCounts();
        $topics = [];

        foreach ($topicsData as $row) {
            /** @var TopicDbRow $row */
            $topicId = (int) $row['topicid'];
            $storyCount = (int) $row['stories'];

            $recentArticles = [];
            if ($storyCount > 0) {
                $recentArticles = $this->fetchRecentArticles($topicId);
            }

            $topics[] = [
                'topicId' => $topicId,
                'topicName' => $row['topicname'],
                'topicImage' => $row['topicimage'],
                'topicText' => $row['topictext'],
                'storyCount' => $storyCount,
                'totalReads' => (int) $row['total_reads'],
                'recentArticles' => $recentArticles,
            ];
        }

        return $topics;
    }

    /**
     * Fetch all topics with aggregate story counts.
     *
     * @return array<int, TopicDbRow>
     */
    private function fetchAllTopicsWithCounts(): array
    {
        $sql = "SELECT t.topicid, t.topicname, t.topicimage, t.topictext,
                       COUNT(s.sid) AS stories,
                       COALESCE(SUM(s.counter), 0) AS total_reads
                FROM " . $this->prefix . "_topics t
                LEFT JOIN " . $this->prefix . "_stories s ON (s.topic = t.topicid)
                GROUP BY t.topicid, t.topicname, t.topicimage, t.topictext
                ORDER BY t.topictext";

        /** @var array<int, TopicDbRow> */
        return $this->fetchAll($sql);
    }

    /**
     * Fetch the 10 most recent articles for a given topic.
     *
     * @param int $topicId Topic ID
     * @return array<int, array{sid: int, title: string, catId: int, catTitle: string}>
     */
    private function fetchRecentArticles(int $topicId): array
    {
        $sql = "SELECT s.sid, s.catid, s.title, COALESCE(c.title, '') AS cat_title
                FROM " . $this->prefix . "_stories s
                LEFT JOIN " . $this->prefix . "_stories_cat c ON s.catid = c.catid
                WHERE s.topic = ?
                ORDER BY s.sid DESC
                LIMIT 10";

        $rows = $this->fetchAll($sql, 'i', $topicId);
        $articles = [];

        foreach ($rows as $row) {
            /** @var ArticleDbRow $row */
            $articles[] = [
                'sid' => (int) $row['sid'],
                'title' => $row['title'],
                'catId' => (int) $row['catid'],
                'catTitle' => $row['cat_title'],
            ];
        }

        return $articles;
    }
}
