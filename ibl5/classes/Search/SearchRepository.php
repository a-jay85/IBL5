<?php

declare(strict_types=1);

namespace Search;

use BaseMysqliRepository;
use Search\Contracts\SearchRepositoryInterface;

/**
 * Repository class for searching stories, comments, and users.
 *
 * Uses prepared statements for all database queries. Handles filtering
 * by topic, category, author, and date range for story searches.
 *
 * @phpstan-import-type StorySearchResult from Contracts\SearchRepositoryInterface
 * @phpstan-import-type CommentSearchResult from Contracts\SearchRepositoryInterface
 * @phpstan-import-type UserSearchResult from Contracts\SearchRepositoryInterface
 * @phpstan-import-type TopicRow from Contracts\SearchRepositoryInterface
 * @phpstan-import-type CategoryRow from Contracts\SearchRepositoryInterface
 * @phpstan-import-type TopicInfoRow from Contracts\SearchRepositoryInterface
 *
 * @phpstan-type StoryDbRow array{sid: int, aid: string, informant: string, title: string, time: string, hometext: string, bodytext: string, comments: int, topic: int, topictext: string|null}
 * @phpstan-type CommentDbRow array{tid: int, sid: int, subject: string, date: string, name: string, article_title: string|null, reply_count: int}
 * @phpstan-type UserDbRow array{user_id: int, username: string, name: string}
 * @phpstan-type TopicDbRow array{topicid: int, topictext: string}
 * @phpstan-type CategoryDbRow array{catid: int, title: string}
 * @phpstan-type AuthorDbRow array{aid: string}
 * @phpstan-type TopicInfoDbRow array{topicimage: string, topictext: string}
 *
 * @see SearchRepositoryInterface
 */
class SearchRepository extends BaseMysqliRepository implements SearchRepositoryInterface
{
    /** @var string Database table prefix */
    private string $prefix;

    /** @var string User table prefix */
    private string $userPrefix;

    /**
     * @param \mysqli $db Active mysqli connection
     * @param string $prefix Database table prefix (default: 'nuke')
     * @param string $userPrefix User table prefix (default: 'nuke')
     */
    public function __construct(\mysqli $db, string $prefix = 'nuke', string $userPrefix = 'nuke')
    {
        parent::__construct($db);
        $this->prefix = $prefix;
        $this->userPrefix = $userPrefix;
    }

    /**
     * @see SearchRepositoryInterface::searchStories()
     * @return StorySearchResult
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

        /** @var list<StoryDbRow> $rows */
        $rows = $this->fetchAll($sql, $types, ...$params);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'sid' => $row['sid'],
                'aid' => $row['aid'],
                'informant' => $row['informant'],
                'title' => $row['title'],
                'time' => $row['time'],
                'comments' => $row['comments'],
                'topicId' => $row['topic'],
                'topicText' => $row['topictext'] ?? '',
            ];
        }

        return ['results' => $results, 'hasMore' => $hasMore];
    }

    /**
     * @see SearchRepositoryInterface::searchComments()
     * @return CommentSearchResult
     */
    public function searchComments(string $query, int $offset = 0, int $limit = 10): array
    {
        if (strlen($query) < 3) {
            return ['results' => [], 'hasMore' => false];
        }

        $likeQuery = '%' . $query . '%';
        $sql = "SELECT c.tid, c.sid, c.subject, c.date, c.name,
                       s.title AS article_title,
                       COALESCE(rc.reply_count, 0) AS reply_count
                FROM {$this->prefix}_comments c
                LEFT JOIN {$this->prefix}_stories s ON c.sid = s.sid
                LEFT JOIN (
                    SELECT pid, COUNT(*) AS reply_count
                    FROM {$this->prefix}_comments
                    GROUP BY pid
                ) rc ON rc.pid = c.tid
                WHERE (c.subject LIKE ? OR c.comment LIKE ?)
                ORDER BY c.date DESC
                LIMIT ?, ?";

        /** @var list<CommentDbRow> $rows */
        $rows = $this->fetchAll($sql, 'ssii', $likeQuery, $likeQuery, $offset, $limit + 1);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'tid' => $row['tid'],
                'sid' => $row['sid'],
                'subject' => $row['subject'],
                'date' => $row['date'],
                'name' => $row['name'],
                'articleTitle' => $row['article_title'] ?? '',
                'replyCount' => $row['reply_count'],
            ];
        }

        return ['results' => $results, 'hasMore' => $hasMore];
    }

    /**
     * @see SearchRepositoryInterface::searchUsers()
     * @return UserSearchResult
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

        /** @var list<UserDbRow> $rows */
        $rows = $this->fetchAll($sql, 'sssii', $likeQuery, $likeQuery, $likeQuery, $offset, $limit + 1);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'userId' => $row['user_id'],
                'username' => $row['username'],
                'name' => $row['name'],
            ];
        }

        return ['results' => $results, 'hasMore' => $hasMore];
    }

    /**
     * @see SearchRepositoryInterface::getTopics()
     * @return list<TopicRow>
     */
    public function getTopics(): array
    {
        /** @var list<TopicDbRow> $rows */
        $rows = $this->fetchAll(
            "SELECT topicid, topictext FROM {$this->prefix}_topics ORDER BY topictext"
        );

        $topics = [];
        foreach ($rows as $row) {
            $topics[] = [
                'topicId' => $row['topicid'],
                'topicText' => $row['topictext'],
            ];
        }

        return $topics;
    }

    /**
     * @see SearchRepositoryInterface::getCategories()
     * @return list<CategoryRow>
     */
    public function getCategories(): array
    {
        /** @var list<CategoryDbRow> $rows */
        $rows = $this->fetchAll(
            "SELECT catid, title FROM {$this->prefix}_stories_cat ORDER BY title"
        );

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = [
                'catId' => $row['catid'],
                'title' => $row['title'],
            ];
        }

        return $categories;
    }

    /**
     * @see SearchRepositoryInterface::getAuthors()
     */
    public function getAuthors(): array
    {
        /** @var list<AuthorDbRow> $rows */
        $rows = $this->fetchAll(
            "SELECT aid FROM {$this->prefix}_authors ORDER BY aid"
        );

        $authors = [];
        foreach ($rows as $row) {
            $authors[] = $row['aid'];
        }

        return $authors;
    }

    /**
     * @see SearchRepositoryInterface::getTopicInfo()
     * @return TopicInfoRow|null
     */
    public function getTopicInfo(int $topicId): ?array
    {
        /** @var TopicInfoDbRow|null $row */
        $row = $this->fetchOne(
            "SELECT topicimage, topictext FROM {$this->prefix}_topics WHERE topicid = ?",
            'i',
            $topicId
        );

        if ($row === null) {
            return null;
        }

        return [
            'topicImage' => $row['topicimage'],
            'topicText' => $row['topictext'],
        ];
    }

}
