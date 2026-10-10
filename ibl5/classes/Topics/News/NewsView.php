<?php

declare(strict_types=1);

namespace Topics\News;

use Topics\News\Contracts\NewsViewInterface;

class NewsView implements NewsViewInterface
{
    /**
     * @see NewsViewInterface::renderStories()
     *
     * @param array<int, array{aid: string, informant: string, time: int|string, title: string, counter: int, topic: int, hometext: ?string, notes: string, morelink: string, topicname: string, topicimage: string, topictext: string}> $stories
     */
    public function renderStories(array $stories): void
    {
        foreach ($stories as $vm) {
            themeindex(
                $vm['aid'],
                $vm['informant'],
                $vm['time'],
                $vm['title'],
                $vm['counter'],
                $vm['topic'],
                $vm['hometext'],
                $vm['notes'],
                $vm['morelink'],
                $vm['topicname'],
                $vm['topicimage'],
                $vm['topictext'],
            );
        }
    }

    /**
     * @see NewsViewInterface::renderArticle()
     *
     * @param array{aid: string, informant: string, time: int|string, title: string, bodytext: ?string, topic: int, topicname: string, topicimage: string, topictext: string} $vm
     */
    public function renderArticle(array $vm): void
    {
        themearticle(
            $vm['aid'],
            $vm['informant'],
            $vm['time'],
            $vm['title'],
            $vm['bodytext'],
            $vm['topic'],
            $vm['topicname'],
            $vm['topicimage'],
            $vm['topictext'],
        );
    }
}
