<?php

declare(strict_types=1);

namespace Services;

class NewsService extends \BaseMysqliRepository
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
}
