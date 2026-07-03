<?php

declare(strict_types=1);

namespace Topics\News;

use Topics\News\Contracts\NewsRepositoryInterface;

class NewsRepository extends \BaseMysqliRepository implements NewsRepositoryInterface
{
    public function createNewsStory(
        int $categoryID,
        int $topicID,
        string $title,
        string $hometext,
        string $aid = 'Associated Press'
    ): int {
        $timestamp = date('Y-m-d H:i:s', time());

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

    /** @return array<int, array<string, mixed>> */
    public function getStoriesByCategory(int $catId, int $limit, string $langClause = ''): array
    {
        // @phpstan-ignore ibl.sqlStringInterpolation ($langClause is a legacy multilingual SQL fragment, not a bindable value)
        $sql = "SELECT sid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm
             FROM `nuke_stories`
             WHERE catid = ? $langClause
             ORDER BY sid DESC LIMIT ?";
        return $this->fetchAll($sql, 'ii', $catId, $limit);
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

    public function incrementStoryCounter(int $sid): int
    {
        return $this->execute(
            "UPDATE `nuke_stories` SET counter = counter + 1 WHERE sid = ?",
            'i',
            $sid
        );
    }
}
