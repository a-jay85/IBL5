<?php

declare(strict_types=1);

namespace Search;

use BaseMysqliRepository;
use Search\Contracts\SearchServiceInterface;

/**
 * Service class for searching stories, comments, and users.
 *
 * Uses prepared statements for all database queries. Handles filtering
 * by topic, category, author, and date range for story searches.
 *
 * @see SearchServiceInterface
 */
class SearchService extends BaseMysqliRepository implements SearchServiceInterface
{
    /** @var string Database table prefix */
    private string $prefix;

    /** @var string User table prefix */
    private string $userPrefix;

    /**
     * @param object $db Active mysqli connection
     * @param string $prefix Database table prefix (default: 'nuke')
     * @param string $userPrefix User table prefix (default: 'nuke')
     */
    public function __construct(object $db, string $prefix = 'nuke', string $userPrefix = 'nuke')
    {
        parent::__construct($db);
        $this->prefix = $prefix;
        $this->userPrefix = $userPrefix;
    }

    /**
     * @see SearchServiceInterface::searchStories()
     */
    public function searchStories(
        string $query,
        int $topic = 0,
        int $category = 0,
        string $author = '',
        int $days = 0,
        int $offset = 0,
        int $limit = 10
    ): array {
        if (strlen($query) < 3) {
            return ['results' => [], 'hasMore' => false];
        }

        $likeQuery = '%' . $query . '%';
        $sql = "SELECT s.sid, s.aid, s.informant, s.title, s.time,
                       s.hometext, s.bodytext, s.comments, s.topic,
                       t.topictext
                FROM {$this->prefix}_stories s
                LEFT JOIN {$this->prefix}_topics t ON s.topic = t.topicid
                WHERE (s.title LIKE ? OR s.hometext LIKE ? OR s.bodytext LIKE ? OR s.notes LIKE ?)";

        $types = 'ssss';
        $params = [$likeQuery, $likeQuery, $likeQuery, $likeQuery];

        if ($topic > 0) {
            $sql .= " AND s.topic = ?";
            $types .= 'i';
            $params[] = $topic;
        }

        if ($category > 0) {
            $sql .= " AND s.catid = ?";
            $types .= 'i';
            $params[] = $category;
        }

        if ($author !== '') {
            $sql .= " AND s.aid = ?";
            $types .= 's';
            $params[] = $author;
        }

        if ($days > 0) {
            $sql .= " AND TO_DAYS(NOW()) - TO_DAYS(s.time) <= ?";
            $types .= 'i';
            $params[] = $days;
        }

        // Fetch one extra to detect if there are more results
        $sql .= " ORDER BY s.time DESC LIMIT ?, ?";
        $types .= 'ii';
        $params[] = $offset;
        $params[] = $limit + 1;

        $rows = $this->fetchAll($sql, $types, ...$params);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'sid' => (int) $row['sid'],
                'aid' => (string) $row['aid'],
                'informant' => (string) $row['informant'],
                'title' => (string) $row['title'],
                'time' => (string) $row['time'],
                'comments' => (int) $row['comments'],
                'topicId' => (int) $row['topic'],
                'topicText' => (string) ($row['topictext'] ?? ''),
            ];
        }

        return ['results' => $results, 'hasMore' => $hasMore];
    }

    /**
     * @see SearchServiceInterface::searchComments()
     */
    public function searchComments(string $query, int $offset = 0, int $limit = 10): array
    {
        if (strlen($query) < 3) {
            return ['results' => [], 'hasMore' => false];
        }

        $likeQuery = '%' . $query . '%';
        $sql = "SELECT c.tid, c.sid, c.subject, c.date, c.name,
                       s.title AS article_title,
                       (SELECT COUNT(*) FROM {$this->prefix}_comments c2 WHERE c2.pid = c.tid) AS reply_count
                FROM {$this->prefix}_comments c
                LEFT JOIN {$this->prefix}_stories s ON c.sid = s.sid
                WHERE (c.subject LIKE ? OR c.comment LIKE ?)
                ORDER BY c.date DESC
                LIMIT ?, ?";

        $rows = $this->fetchAll($sql, 'ssii', $likeQuery, $likeQuery, $offset, $limit + 1);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'tid' => (int) $row['tid'],
                'sid' => (int) $row['sid'],
                'subject' => (string) $row['subject'],
                'date' => (string) $row['date'],
                'name' => (string) $row['name'],
                'articleTitle' => (string) ($row['article_title'] ?? ''),
                'replyCount' => (int) $row['reply_count'],
            ];
        }

        return ['results' => $results, 'hasMore' => $hasMore];
    }

    /**
     * @see SearchServiceInterface::searchUsers()
     */
    public function searchUsers(string $query, int $offset = 0, int $limit = 10): array
    {
        if (strlen($query) < 3) {
            return ['results' => [], 'hasMore' => false];
        }

        $likeQuery = '%' . $query . '%';
        $sql = "SELECT user_id, username, name
                FROM {$this->userPrefix}_users
                WHERE (username LIKE ? OR name LIKE ? OR bio LIKE ?)
                ORDER BY username ASC
                LIMIT ?, ?";

        $rows = $this->fetchAll($sql, 'sssii', $likeQuery, $likeQuery, $likeQuery, $offset, $limit + 1);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'userId' => (int) $row['user_id'],
                'username' => (string) $row['username'],
                'name' => (string) $row['name'],
            ];
        }

        return ['results' => $results, 'hasMore' => $hasMore];
    }

    /**
     * @see SearchServiceInterface::getTopics()
     */
    public function getTopics(): array
    {
        $rows = $this->fetchAll(
            "SELECT topicid, topictext FROM {$this->prefix}_topics ORDER BY topictext"
        );

        $topics = [];
        foreach ($rows as $row) {
            $topics[] = [
                'topicId' => (int) $row['topicid'],
                'topicText' => (string) $row['topictext'],
            ];
        }

        return $topics;
    }

    /**
     * @see SearchServiceInterface::getCategories()
     */
    public function getCategories(): array
    {
        $rows = $this->fetchAll(
            "SELECT catid, title FROM {$this->prefix}_stories_cat ORDER BY title"
        );

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = [
                'catId' => (int) $row['catid'],
                'title' => (string) $row['title'],
            ];
        }

        return $categories;
    }

    /**
     * @see SearchServiceInterface::getAuthors()
     */
    public function getAuthors(): array
    {
        $rows = $this->fetchAll(
            "SELECT aid FROM {$this->prefix}_authors ORDER BY aid"
        );

        $authors = [];
        foreach ($rows as $row) {
            $authors[] = (string) $row['aid'];
        }

        return $authors;
    }

    /**
     * @see SearchServiceInterface::getTopicInfo()
     */
    public function getTopicInfo(int $topicId): ?array
    {
        $row = $this->fetchOne(
            "SELECT topicimage, topictext FROM {$this->prefix}_topics WHERE topicid = ?",
            'i',
            $topicId
        );

        if ($row === null) {
            return null;
        }

        return [
            'topicImage' => (string) $row['topicimage'],
            'topicText' => (string) $row['topictext'],
        ];
    }

}
