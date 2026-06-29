<?php

declare(strict_types=1);

namespace Topics\News\Contracts;

interface NewsViewInterface
{
    /** @param array<int, array<string, mixed>> $stories */
    public function renderStories(array $stories): void;

    /** @param array<string, mixed> $vm */
    public function renderArticle(array $vm): void;
}
