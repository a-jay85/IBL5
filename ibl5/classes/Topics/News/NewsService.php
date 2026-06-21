<?php

declare(strict_types=1);

namespace Topics\News;

use Topics\News\Contracts\NewsRepositoryInterface;
use Topics\News\Contracts\NewsServiceInterface;

/**
 * Service for assembling News page data (pure-data; no HTML, no echo).
 *
 * @see NewsServiceInterface
 */
class NewsService implements NewsServiceInterface
{
    private NewsRepositoryInterface $repository;

    public function __construct(\mysqli $db, ?NewsRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new NewsRepository($db);
    }

    /**
     * @see NewsServiceInterface::getHomePageStories()
     */
    public function getHomePageStories(int $limit, string $langClause): array
    {
        return $this->repository->getHomePageStories($limit, $langClause);
    }

    /**
     * @see NewsServiceInterface::getTopicPageStories()
     */
    public function getTopicPageStories(int $topicId, int $limit, string $langClause): array
    {
        return $this->repository->getStoriesByTopic($topicId, $limit, $langClause);
    }

    /**
     * @see NewsServiceInterface::getCategoryPageStories()
     */
    public function getCategoryPageStories(int $catId, int $limit, string $langClause): array
    {
        return $this->repository->getStoriesByCategory($catId, $limit, $langClause);
    }

    /**
     * @see NewsServiceInterface::getStory()
     */
    public function getStory(int $sid): ?array
    {
        return $this->repository->getStoryById($sid);
    }

    /**
     * @see NewsServiceInterface::getTopicForStory()
     */
    public function getTopicForStory(int $sid): ?array
    {
        return $this->repository->getTopicForStory($sid);
    }

    /**
     * @see NewsServiceInterface::getTopicText()
     */
    public function getTopicText(int $topicId): ?string
    {
        return $this->repository->getTopicText($topicId);
    }

    /**
     * @see NewsServiceInterface::getCategoryTitle()
     */
    public function getCategoryTitle(int $catId): ?string
    {
        return $this->repository->getCategoryTitle($catId);
    }

    /**
     * @see NewsServiceInterface::bumpAllTopics()
     */
    public function bumpAllTopics(): int
    {
        return $this->repository->incrementTopicCounterAll();
    }

    /**
     * @see NewsServiceInterface::bumpCategory()
     */
    public function bumpCategory(int $catId): int
    {
        return $this->repository->incrementCategoryCounterById($catId);
    }

    /**
     * @see NewsServiceInterface::bumpStory()
     */
    public function bumpStory(int $sid): int
    {
        return $this->repository->incrementStoryCounter($sid);
    }

    /**
     * @see NewsServiceInterface::normalizeStoryTime()
     */
    public function normalizeStoryTime(int|string $time): int
    {
        if (!is_numeric($time)) {
            preg_match('/(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/', $time, $dtParts);
            $time = gmmktime((int) ($dtParts[4] ?? 0), (int) ($dtParts[5] ?? 0), (int) ($dtParts[6] ?? 0), (int) ($dtParts[2] ?? 0), (int) ($dtParts[3] ?? 0), (int) ($dtParts[1] ?? 0));
        }
        // @phpstan-ignore ibl.directTime (date('Z') is a timezone offset, not a now-read — faithful extraction of NukeCompat's identical logic)
        return (int) $time - (int) date('Z');
    }

    /**
     * @see NewsServiceInterface::computeByteCounts()
     */
    public function computeByteCounts(string $hometext, string $bodytext): array
    {
        $intro = strlen($hometext);
        $full = strlen($bodytext);
        return ['intro' => $intro, 'full' => $full, 'total' => $intro + $full];
    }
}
