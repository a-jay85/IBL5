<?php

declare(strict_types=1);

namespace Topics\News;

use Topics\News\Contracts\NewsRepositoryInterface;

class NewsRepository extends \Database\BaseMysqliRepository implements NewsRepositoryInterface
{
    private \Clock\ClockInterface $clock;

    public function __construct(\mysqli $db, ?\League\LeagueContext $leagueContext = null, ?\Clock\ClockInterface $clock = null)
    {
        parent::__construct($db, $leagueContext);
        $this->clock = $clock ?? new \Clock\SystemClock();
    }

    public function createNewsStory(
        int $categoryID,
        int $topicID,
        string $title,
        string $hometext,
        string $aid = 'Associated Press'
    ): int {
        $timestamp = date('Y-m-d H:i:s', $this->clock->now());

        return $this->execute(
            "INSERT INTO `nuke_stories`
              (`catid`, `aid`, `title`, `time`, `hometext`, `topic`, `informant`, `counter`, `alanguage`)
              VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'english')",
            'issssis',
            $categoryID,
            $aid,
            $title,
            $timestamp,
            $hometext,
            $topicID,
            $aid
        );
    }

    public function getTopicIDByTeamName(string $teamName): ?int
    {
        $row = $this->fetchOne(
            "SELECT `topicid` FROM `nuke_topics` WHERE `topicname` = ?",
            's',
            $teamName
        );

        if ($row === null) {
            return null;
        }
        $topicId = $row['topicid'];
        return is_int($topicId) ? $topicId : null;
    }

    public function getCategoryIDByTitle(string $categoryTitle): ?int
    {
        $row = $this->fetchOne(
            "SELECT `catid` FROM `nuke_stories_cat` WHERE `title` = ?",
            's',
            $categoryTitle
        );

        if ($row === null) {
            return null;
        }
        $catId = $row['catid'];
        return is_int($catId) ? $catId : null;
    }

    public function incrementCategoryCounter(string $categoryTitle): int
    {
        return $this->execute(
            "UPDATE `nuke_stories_cat`
              SET `counter` = `counter` + 1
              WHERE `title` = ?",
            's',
            $categoryTitle
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function getHomePageStories(int $limit, string $langClause = ''): array
    {
        // @phpstan-ignore ibl.sqlStringInterpolation ($langClause is a legacy multilingual SQL fragment, not a bindable value)
        $sql = "SELECT sid, catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm
             FROM `nuke_stories`
             WHERE (ihome='0' OR catid='0') $langClause
             ORDER BY sid DESC LIMIT ?";
        return $this->fetchAll($sql, 'i', $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function getStoriesByTopic(int $topicId, int $limit, string $langClause = ''): array
    {
        // @phpstan-ignore ibl.sqlStringInterpolation ($langClause is a legacy multilingual SQL fragment, not a bindable value)
        $sql = "SELECT sid, catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm
             FROM `nuke_stories`
             WHERE topic = ? $langClause
             ORDER BY sid DESC LIMIT ?";
        return $this->fetchAll($sql, 'ii', $topicId, $limit);
    }

    /**
     * @param string|null $language Bound to nuke_stories.alanguage; null skips the language clause.
     * @return array<int, array<string, mixed>>
     */
    public function getStoriesByCategory(int $catId, int $limit, ?string $language = null): array
    {
        if ($language === null) {
            return $this->fetchAll(
                "SELECT sid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm
                 FROM `nuke_stories`
                 WHERE catid = ?
                 ORDER BY sid DESC LIMIT ?",
                'ii',
                $catId,
                $limit
            );
        }
        return $this->fetchAll(
            "SELECT sid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm
             FROM `nuke_stories`
             WHERE catid = ? AND (alanguage = ? OR alanguage = '')
             ORDER BY sid DESC LIMIT ?",
            'isi',
            $catId,
            $language,
            $limit
        );
    }

    /** @return array<string, mixed>|null */
    public function getStoryById(int $sid): ?array
    {
        return $this->fetchOne(
            "SELECT catid, aid, time, title, hometext, bodytext, topic, informant, notes, acomm, haspoll, poll_id
             FROM `nuke_stories`
             WHERE sid = ?",
            'i',
            $sid
        );
    }

    /** @return array<string, mixed>|null */
    public function getTopicForStory(int $sid): ?array
    {
        return $this->fetchOne(
            "SELECT t.topicid, t.topicname, t.topicimage, t.topictext
             FROM `nuke_stories` s
             LEFT JOIN `nuke_topics` t ON t.topicid = s.topic
             WHERE s.sid = ?",
            'i',
            $sid
        );
    }

    public function getTopicText(int $topicId): ?string
    {
        $row = $this->fetchOne(
            "SELECT topictext FROM `nuke_topics` WHERE topicid = ?",
            'i',
            $topicId
        );
        return is_array($row) && is_string($row['topictext'] ?? null) ? $row['topictext'] : null;
    }

    public function getCategoryTitle(int $catId): ?string
    {
        $row = $this->fetchOne(
            "SELECT title FROM `nuke_stories_cat` WHERE catid = ?",
            'i',
            $catId
        );
        return is_array($row) && is_string($row['title'] ?? null) ? $row['title'] : null;
    }

    public function incrementTopicCounterAll(): int
    {
        return $this->execute(
            "UPDATE `nuke_topics` SET counter = counter + 1",
            ''
        );
    }

    public function incrementCategoryCounterById(int $catId): int
    {
        return $this->execute(
            "UPDATE `nuke_stories_cat` SET counter = counter + 1 WHERE catid = ?",
            'i',
            $catId
        );
    }

    /**
     * Stories whose hometext or bodytext mentions $needle, newest first.
     * Returns null when the statement cannot be prepared or yields no result set.
     *
     * @return list<array{sid: int, title: string|null, time: string|null}>|null
     */
    public function searchStoriesMentioning(string $needle): ?array
    {
        // Bound via prepared statement; only LIKE metacharacters need escaping.
        $searchTerm = '%' . addcslashes($needle, '\\%_') . '%';
        // @phpstan-ignore ibl.orderByMissingTiebreaker (query moved verbatim from Player/articles.php; ties on time keep their old order, and a tiebreaker is a separate change to the pinned SQL)
        $stmt = $this->db->prepare('SELECT sid, title, time FROM nuke_stories WHERE hometext LIKE ? OR bodytext LIKE ? ORDER BY time DESC');
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('ss', $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return null;
        }

        $rows = [];
        while (is_array($row = $result->fetch_assoc())) {
            /** @var array{sid: int, title: string|null, time: string|null} $row nuke_stories: sid int(11) NOT NULL, title varchar(80) NULL, time datetime NULL (per ibl5/docs/schema/current-schema.sql) */
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    public function incrementStoryCounter(int $sid): int
    {
        return $this->execute(
            "UPDATE `nuke_stories` SET counter = counter + 1 WHERE sid = ?",
            'i',
            $sid
        );
    }
}
