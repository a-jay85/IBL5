<?php

declare(strict_types=1);

namespace Topics;

use BaseMysqliRepository;
use Topics\Contracts\TopicsServiceInterface;

/**
 * Service class for retrieving news topics with articles.
 *
 * Queries the nuke_topics and nuke_stories tables to build a complete
 * view of all active topics with their story counts and recent articles.
 *
 * @see TopicsServiceInterface
 */
class TopicsService extends BaseMysqliRepository implements TopicsServiceInterface
{
    /** @var string Database table prefix */
    private string $prefix;

    /**
     * @param object $db Active mysqli connection
     * @param string $prefix Database table prefix (default: 'nuke')
     */
    public function __construct(object $db, string $prefix = 'nuke')
    {
        parent::__construct($db);
        $this->prefix = $prefix;
    }

    /**
     * @see TopicsServiceInterface::getTopicsWithArticles()
     */
    public function getTopicsWithArticles(): array
    {
        $topicsData = $this->fetchAllTopicsWithCounts();
        $topics = [];

        foreach ($topicsData as $row) {
            $topicId = (int) $row['topicid'];
            $storyCount = (int) $row['stories'];

            $recentArticles = [];
            if ($storyCount > 0) {
                $recentArticles = $this->fetchRecentArticles($topicId);
            }

            $topics[] = [
                'topicId' => $topicId,
                'topicName' => (string) $row['topicname'],
                'topicImage' => (string) $row['topicimage'],
                'topicText' => (string) $row['topictext'],
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
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllTopicsWithCounts(): array
    {
        $sql = "SELECT t.topicid, t.topicname, t.topicimage, t.topictext,
                       COUNT(s.sid) AS stories,
                       COALESCE(SUM(s.counter), 0) AS total_reads
                FROM {$this->prefix}_topics t
                LEFT JOIN {$this->prefix}_stories s ON (s.topic = t.topicid)
                GROUP BY t.topicid, t.topicname, t.topicimage, t.topictext
                ORDER BY t.topictext";

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
                FROM {$this->prefix}_stories s
                LEFT JOIN {$this->prefix}_stories_cat c ON s.catid = c.catid
                WHERE s.topic = ?
                ORDER BY s.sid DESC
                LIMIT 10";

        $rows = $this->fetchAll($sql, 'i', $topicId);
        $articles = [];

        foreach ($rows as $row) {
            $articles[] = [
                'sid' => (int) $row['sid'],
                'title' => (string) $row['title'],
                'catId' => (int) $row['catid'],
                'catTitle' => (string) $row['cat_title'],
            ];
        }

        return $articles;
    }
}
