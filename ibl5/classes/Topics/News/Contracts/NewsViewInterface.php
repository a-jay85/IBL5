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
}
