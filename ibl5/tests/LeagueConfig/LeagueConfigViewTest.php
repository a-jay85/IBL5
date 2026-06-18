<?php

declare(strict_types=1);

namespace Tests\LeagueConfig;

use LeagueConfig\LeagueConfigView;
use PHPUnit\Framework\TestCase;

final class LeagueConfigViewTest extends TestCase
{
    private LeagueConfigView $view;

    protected function setUp(): void
    {
        $this->view = new LeagueConfigView();
    }

    public function testRenderLgeNeededNotificationShowsSeasonLabel(): void
    {
        $output = $this->view->renderLgeNeededNotification(2025);

        // Beginning year (2025 - 1) and the two-digit ending year (substr from offset 2).
        $this->assertStringContainsString('2024-25 season', $output);
        $this->assertStringNotContainsString('2024-2025', $output);
        $this->assertStringContainsString('ibl-alert--info', $output);
        $this->assertStringContainsString('Place IBL5.lge in the ibl5/ directory', $output);
    }

    public function testRenderParseResultSuccessShowsSeasonAndTeamCount(): void
    {
        $output = $this->view->renderParseResult([
            'success' => true,
            'season_ending_year' => 2025,
            'teams_stored' => 28,
            'messages' => [],
        ]);

        $this->assertStringContainsString('ibl-alert--success', $output);
        $this->assertStringContainsString('.lge imported: 2024-25 season, 28 teams stored.', $output);
    }

    public function testRenderParseResultFailureShowsError(): void
    {
        $output = $this->view->renderParseResult([
            'success' => false,
            'season_ending_year' => 0,
            'teams_stored' => 0,
            'messages' => [],
            'error' => 'File not found',
        ]);

        $this->assertStringContainsString('ibl-alert--error', $output);
        $this->assertStringContainsString('.lge import failed: File not found', $output);
        $this->assertStringNotContainsString('ibl-alert--success', $output);
    }

    public function testRenderParseResultFailureWithoutErrorKeyFallsBackToUnknown(): void
    {
        $output = $this->view->renderParseResult([
            'success' => false,
            'season_ending_year' => 0,
            'teams_stored' => 0,
            'messages' => [],
        ]);

        $this->assertStringContainsString('.lge import failed: Unknown error', $output);
    }

    public function testRenderParseResultFailureEscapesXssInError(): void
    {
        $output = $this->view->renderParseResult([
            'success' => false,
            'season_ending_year' => 0,
            'teams_stored' => 0,
            'messages' => [],
            'error' => '<script>alert(1)</script>',
        ]);

        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    public function testRenderCrossCheckResultsEmptyReturnsEmptyString(): void
    {
        $this->assertSame('', $this->view->renderCrossCheckResults([]));
    }

    public function testRenderCrossCheckResultsListsDiscrepancies(): void
    {
        $output = $this->view->renderCrossCheckResults(['Team count mismatch', 'Salary cap differs']);

        $this->assertStringContainsString('ibl-alert--warning', $output);
        $this->assertStringContainsString('<li>Team count mismatch</li>', $output);
        $this->assertStringContainsString('<li>Salary cap differs</li>', $output);
    }

    public function testRenderCrossCheckResultsEscapesXss(): void
    {
        $output = $this->view->renderCrossCheckResults(['<script>alert(1)</script>']);

        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $output);
    }
}
