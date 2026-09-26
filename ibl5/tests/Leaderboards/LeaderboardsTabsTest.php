<?php

declare(strict_types=1);

namespace Tests\Leaderboards;

use Leaderboards\LeaderboardsTabs;
use PHPUnit\Framework\TestCase;

class LeaderboardsTabsTest extends TestCase
{
    public function testResolveDefaultsToSeasonWhenMissing(): void
    {
        $this->assertSame('season', LeaderboardsTabs::resolve(null));
    }

    public function testResolveAcceptsCareer(): void
    {
        $this->assertSame('career', LeaderboardsTabs::resolve('career'));
        $this->assertSame('season', LeaderboardsTabs::resolve('season'));
    }

    public function testResolveRejectsUnknownCaseVariantAndNonStringInput(): void
    {
        $this->assertSame('season', LeaderboardsTabs::resolve('bogus'));
        $this->assertSame('season', LeaderboardsTabs::resolve('Career'));
        $this->assertSame('season', LeaderboardsTabs::resolve(' career'));
        $this->assertSame('season', LeaderboardsTabs::resolve(['career']));
        $this->assertSame('season', LeaderboardsTabs::resolve(1));
    }

    public function testRenderMarksOnlyTheActiveTab(): void
    {
        $html = LeaderboardsTabs::render('career');

        $this->assertSame(1, substr_count($html, 'ibl-tab--active'));
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertStringContainsString(
            '<a class="ibl-tab ibl-tab--active" href="modules.php?name=Leaderboards&amp;tab=career" aria-current="page">Career</a>',
            $html
        );
        $this->assertStringContainsString(
            '<a class="ibl-tab" href="modules.php?name=Leaderboards&amp;tab=season">Season</a>',
            $html
        );
    }

    public function testRenderLinksBothTabsToLeaderboardsModule(): void
    {
        $html = LeaderboardsTabs::render('season');

        $this->assertStringContainsString('href="modules.php?name=Leaderboards&amp;tab=season"', $html);
        $this->assertStringContainsString('href="modules.php?name=Leaderboards&amp;tab=career"', $html);
        $this->assertStringStartsWith('<nav class="ibl-tabs leaderboards-tabs"', $html);
        $this->assertStringEndsWith('</nav>', $html);
    }
}
