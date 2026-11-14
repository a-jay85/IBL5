<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the Leaderboards module entry point.
 */
final class LeaderboardsFactory implements \Module\Contracts\ModuleFactoryInterface
{
    /** The tab strip needs no shared services. */
    public function __construct(\Module\ModuleServices $services)
    {
    }

    public function tabs(): \UI\Components\PageTabs
    {
        return new \UI\Components\PageTabs(['season' => 'Season', 'career' => 'Career'], 'season');
    }
}
