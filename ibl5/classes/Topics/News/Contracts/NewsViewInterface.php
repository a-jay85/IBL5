<?php

declare(strict_types=1);

namespace Topics\News\Contracts;

interface NewsViewInterface
{
    /**
     * @param array<int, array{aid: string, informant: string, time: int|string, title: string, counter: int, topic: int, hometext: ?string, notes: string, morelink: string, topicname: string, topicimage: string, topictext: string}> $stories
     */
    public function renderStories(array $stories): void;

    /**
     * @param array{aid: string, informant: string, time: int|string, title: string, bodytext: ?string, topic: int, topicname: string, topicimage: string, topictext: string} $vm
     */
    public function renderArticle(array $vm): void;

    /**
     * Page <h1>: the site tagline on the home file, "News" elsewhere.
     */
    public function renderPageTitle(bool $isHomeFile): string;

    /**
     * Topic banner for ?new_topic=N: legacy table chrome, title or no-info message, search form, links.
     *
     * @param string|null $safeTopicTitle already-escaped topic text; null when the topic is unknown
     */
    public function renderTopicBanner(string $siteName, ?string $safeTopicTitle, int $topicId, string $tableBgcolor1, string $tableBgcolor2): string;
}
