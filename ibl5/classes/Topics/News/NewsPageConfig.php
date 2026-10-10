<?php

declare(strict_types=1);

namespace Topics\News;

/**
 * Site configuration the News home/topic page reads. Built once by the
 * module entry point from the legacy config globals.
 */
final class NewsPageConfig
{
    public function __construct(
        public readonly int $storyHome,
        public readonly int $userNews,
        public readonly int $articleComm,
        public readonly string $siteName,
        public readonly int $multilingual,
        public readonly string $currentLang,
    ) {}
}
