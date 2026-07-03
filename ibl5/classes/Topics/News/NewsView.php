<?php

declare(strict_types=1);

namespace Topics\News;

use Topics\News\Contracts\NewsViewInterface;

class NewsView implements NewsViewInterface
{
    /** @param array<int, array<string, mixed>> $stories */
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

    /** @param array<string, mixed> $vm */
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
