<?php

declare(strict_types=1);

namespace Tests\SiteStatistics;

use PHPUnit\Framework\TestCase;
use SiteStatistics\StatisticsView;

/**
 * StatisticsViewTest - Tests for StatisticsView
 */
class StatisticsViewTest extends TestCase
{
    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $view = new StatisticsView('TestModule', 'TestTheme');

        $this->assertInstanceOf(StatisticsView::class, $view);
    }

    public function testConstructorAcceptsModuleName(): void
    {
        $view = new StatisticsView('Statistics', 'Default');

        $this->assertInstanceOf(StatisticsView::class, $view);
    }

    public function testConstructorAcceptsThemeName(): void
    {
        $view = new StatisticsView('Statistics', 'CustomTheme');

        $this->assertInstanceOf(StatisticsView::class, $view);
    }

    // ============================================
    // METHOD EXISTENCE TESTS
    // ============================================

    public function testHasRenderMainStatsMethod(): void
    {
        $view = new StatisticsView('TestModule', 'TestTheme');

        $this->assertTrue(method_exists($view, 'renderMainStats'));
    }

    public function testHasRenderBrowserStatsMethod(): void
    {
        $view = new StatisticsView('TestModule', 'TestTheme');

        $this->assertTrue(method_exists($view, 'renderBrowserStats'));
    }

    public function testHasRenderOsStatsMethod(): void
    {
        $view = new StatisticsView('TestModule', 'TestTheme');

        $this->assertTrue(method_exists($view, 'renderOsStats'));
    }
}
